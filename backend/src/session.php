<?php

// 本檔管理 PHP Session：伺服器替目前瀏覽器保存的登入與操作資料。
// 瀏覽器的 Cookie 記住 Session 編號，真正的會員編號、報價等保存在後端。
// 這和前端 sessionStorage 是兩套不同機制，不能把前端填的會員編號當登入證明。
// 由會員、CSRF 與購買工具共用；不在這裡查詢 MySQL。
// 本檔的 : array 表示回傳資料清單，: void 表示不交回結果；失敗仍能發出例外。

declare(strict_types=1);

// 用途：取得 app.php 的網站設定，例如登入期限。
// static 讓 $config 在同一個 PHP 請求內保留讀取結果，第二次呼叫不用重讀檔案。
// === null 表示還沒載入；require 執行設定檔，取得其中 return 的陣列。
function appConfig(): array
{
    // static 讓這個函式同一請求內共用一份值，不會每次呼叫都重設 null；不代表跨使用者共用登入資料。
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }

    // 交回 app.php 的設定陣列，外面可用 appConfig()['session_idle_timeout'] 取得指定設定。
    return $config;
}

// 用途：開啟目前瀏覽器的後端暫存，並檢查登入是否過期。
// 步驟：已開啟就直接 return；否則設定 Cookie、開啟 Session，再檢查最後活動時間。
// $_SESSION 是 PHP 提供的資料容器，設定值會跟著目前 Session 保存。
function startAppSession(): void
{
    // 檢查這次 PHP 是否已開 Session；已開就 return，不重複設定 Cookie 或 session_start。
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // 取得共用設定；後面的名稱、期限與 Cookie secure 全來自這份清單，避免各 API 設定不同。
    $config = appConfig();

    // Session ID 只透過 Cookie 傳遞。
    // ini_set() 改變這次 PHP 執行的設定；字串 1／0 代表開／關。
    // 只接受 Cookie 中的 Session 編號，不把編號放網址，也拒絕任意未建立的編號。
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');

    // 實際登入期限仍由下面的時間檢查決定。
    ini_set(
        // 這是後端清理舊 Session 資料的參考秒數，不直接證明身份有效；真正登入期限仍由下方 last_activity 檢查。
        'session.gc_maxlifetime',
        (string) $config['session_idle_timeout']
    );

    // 設定專案專用 Cookie 名稱 lifecharge_session，避免同一電腦其他 PHP 專案混用 PHPSESSID。
    session_name($config['session_name']);

    // 設定瀏覽器保存編號的 Cookie：path / 讓整站使用；httponly 禁止網頁 JS 讀取。
    // secure 決定是否只經 HTTPS 傳送，本機 HTTP 使用 false；samesite Lax 限制部分跨站傳送。
    // lifetime 0 表示工作階段 Cookie；實際登入是否過期仍由下方 last_activity 檢查。
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $config['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // 開啟後才能讀寫 $_SESSION；失敗要 throw，不能回傳一份未保存卻假裝可用的驗證碼。
    if (!session_start()) {
        throw new RuntimeException('Session could not be started.');
    }

    // 尚未登入，不需要檢查會員閒置期限。
    // 還沒有會員身份的匿名 Session 可用來取得 CSRF，不需要檢查會員閒置；isset 表示欄位存在且非 null。
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    // ?? null 表示欄位沒有值時改用 null；time() 是目前時間的整數秒數。
    // 最後活動時間不合法、在未來或超過閒置期限，都要清除身份。
    $lastActivity = $_SESSION['last_activity'] ?? null;
    $now = time();

    if (
        // 最後時間必須為整數、不能在未來，且現在減最後時間不能超出期限；任一條件壞掉就清身份。
        !is_int($lastActivity)
        || $lastActivity > $now
        || $now - $lastActivity >= $config['session_idle_timeout']
    ) {
        // 清除身份、CSRF token 與其他過期的 session 資料。
        // 清掉過期身份、token 與報價，[] 是空陣列；這不會刪 MySQL 的會員或訂單。
        $_SESSION = [];

        // 更新瀏覽器與後端對應編號，true 要求刪除舊編號資料；失敗不能當作成功更新。
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session could not be renewed.');
        }
    }
}

// 用途：把已登入會員的最後活動時間更新成現在。
// 由成功報價、成功購買呼叫；只是查詢結果不呼叫，避免背景輪詢讓登入永不到期。
function touchAppSession(): void
{
    // touchAppSession 必須在 Session 已開後呼叫；否則 throw 交上層，不偷偷建立另一份狀態。
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Session has not been started.');
    }

    if (isset($_SESSION['user_id'])) {
        // 有登入身份才把最後活動設為伺服器現在時間；不使用瀏覽器自己回報的時間。
        $_SESSION['last_activity'] = time();
    }
}

// 用途：登入／註冊成功後，準備一份新的會員暫存資料。
// 傳入會員編號，回傳包含身份、活動時間與驗證碼的陣列。
// 此處更新 Session 編號，但不直接覆寫 $_SESSION；註冊程式要等資料庫確認成功才套用。
function prepareMemberSession(int $userId): array
{
    // 準備登入資料前也擋非法編號；InvalidArgumentException 是 PHP 內建的「傳入參數不合理」錯誤。
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid member ID.');
    }

    startAppSession();

    // random_bytes(32) 產生 32 個位元組的安全隨機資料，bin2hex() 轉成 64 個可傳輸字元。
    // 這是操作驗證碼，不是會員密碼；重建 Session ID 則避免沿用登入前的編號。
    $token = bin2hex(random_bytes(32));

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session could not be renewed.');
    }

    // 只準備新身份資料，由呼叫端決定何時套用。
    // 交回新身份、現在時間與新 token。這只準備資料；register.php 等資料庫 commit 後才真正覆寫 $_SESSION。
    return [
        'user_id' => $userId,
        'last_activity' => time(),
        'csrf_token' => $token,
    ];
}

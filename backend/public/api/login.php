<?php

// 本檔用途：接收帳號／Email 與密碼，驗證後建立後端登入身份；不把密碼回傳前端。
// 前端透過 vite.config.js 的 /api 轉送來這裡。此處是 HTTP 入口，共用規則放在 backend/src。
// try 是嘗試執行的區段，catch 接住失敗；sendSuccess／sendError 送出統一 JSON 後會結束請求。

// 這是 PHP 的型別檢查設定：有宣告數字或文字的參數時，不隨便互轉。
// 它不會自動檢查 JSON 欄位、會員身份或資料庫內容，後面仍須逐步驗證。
declare(strict_types=1);

// 先關閉直接輸出的 PHP 錯誤文字，避免破壞 JSON 回覆格式；這不會自動修補 PHP 漏洞。
ini_set('display_errors', '0');

// 載入檢查請求的共用工具；dirname(..., 2) 從 public/api 往上兩層找到 backend。
require_once dirname(__DIR__, 2) . '/src/request.php';

// 確認使用者用對 GET／POST 方法。不符合就回覆 405 並結束，不進入下方業務流程。
requireRequestMethod('POST');

// 進入這次操作的主要流程。require_once 只載入共用函式定義，不表示每個工具已開始查詢。
// 下方任一步 throw 會跳到對應 catch；sendSuccess／sendError 則直接結束這次 HTTP 請求。
try {
    require_once dirname(__DIR__, 2) . '/src/request.php';
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/rateLimit.php';

    // 確認寫入請求來自 app.php 允許的前端；只檢查來源，不代替登入與 CSRF 檢查。
    requireAllowedOrigin();
    // 先限制同一來源的請求次數；超過回覆 429，因此格式或 CSRF 錯誤的嘗試也會計數。
    requireRateLimit('login');
    // 核對瀏覽器附上的操作驗證碼與 Session 一致；失效就拒絕，不自動接受原操作。
    requireCsrfToken();

    // 把 HTTP 的 JSON 表單讀成 PHP 陣列，再檢查 identifier 與 password 是合法文字。
    $input = readJsonBody();

    // ?? null 在沒送欄位時先給空值，接著檢查兩欄都是文字，避免把陣列當帳號或密碼。
    $identifier = $input['identifier'] ?? null;
    $password = $input['password'] ?? null;

    if (!is_string($identifier) || !is_string($password)) {
        sendError(
            'INVALID_INPUT',
            '請輸入帳號或 Email 與密碼。',
            400
        );
    }

    // 只清帳號／Email 的頭尾空白；密碼保留原輸入，不能因 trim 把另一個密碼當相同。
    $identifier = trim($identifier);

    // 密碼不 trim，避免改變使用者真正輸入的內容。
    // 現有 PHP bcrypt 雜湊採最多 72 bytes。
    if (
        // 逐項檢查空白、長度與密碼不可含特殊空白／空字元；|| 任一條件不合理就拒絕，不查資料庫。
        $identifier === ''
        || mb_strlen($identifier, 'UTF-8') > 254
        || $password === ''
        || strlen($password) > 72
        || preg_match('/[\s\p{Z}\x{FEFF}]/u', $password) !== 0
        || str_contains($password, "\0")
    ) {
        sendError(
            'INVALID_INPUT',
            '帳號或 Email、密碼格式不正確；密碼不可包含空白。',
            400
        );
    }

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();
    // 用帳號或 Email 找 id 與雜湊；找不到回 null，仍要做一次 dummy 密碼計算減少可觀察時間差。
    $loginUser = findLoginUser($pdo, $identifier);

    // 查不到帳號時仍執行一次密碼比對，減少明顯的時間差。
    // 此為公開的測試雜湊，不是會員密碼。
    // 這是公開的測試雜湊，不是專案真實會員密碼；用在帳號不存在時維持相近的驗證步驟。
    $dummyHash =
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    // password_verify() 用輸入密碼與儲存的雜湊比對，不是把雜湊解密回密碼。
    $passwordMatches = password_verify(
        $password,
        $loginUser['passwordHash'] ?? $dummyHash
    );

    // 帳號不存在或密碼不合，都回相同 INVALID_CREDENTIALS，避免錯誤訊息直接透露哪個帳號存在。
    if ($loginUser === null || !$passwordMatches) {
        sendError(
            'INVALID_CREDENTIALS',
            '帳號、Email 或密碼錯誤。',
            401
        );
    }

    // 比對完成後另外取公開會員資料，不能把含 passwordHash 的登入查詢結果直接回前端。
    $user = findPublicUserById($pdo, $loginUser['id']);

    if ($user === null) {
        sendError(
            'INVALID_CREDENTIALS',
            '帳號、Email 或密碼錯誤。',
            401
        );
    }

    // 全部驗證成功才更換 Session 編號、驗證碼與會員資料；回傳的 $user 不包含密碼雜湊。
    $_SESSION = prepareMemberSession($user['id']);

    // Session 已更新才回會員成功結果，前端 Store 接到資料後才更新目前身份。
    sendSuccess($user);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'LOGIN_UNAVAILABLE',
        '目前無法登入，請稍後再試。',
        503
    );
}

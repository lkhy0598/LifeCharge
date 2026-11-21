<?php

// 本檔用途：處理登出，清除後端 Session 與瀏覽器 Cookie；不刪會員、訂單或購物車。
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

    // 確認寫入請求來自 app.php 允許的前端；只檢查來源，不代替登入與 CSRF 檢查。
    requireAllowedOrigin();
    // 核對瀏覽器附上的操作驗證碼與 Session 一致；失效就拒絕，不自動接受原操作。
    requireCsrfToken();

    // 先清空後端身份與報價，再銷毀 Session；任一步失敗都不能假裝登出成功。
    $_SESSION = [];

    // 銷毀後端 Session 檔案／紀錄；失敗就發錯，不能只清前端讓使用者以為已確定登出。
    if (!session_destroy()) {
        throw new RuntimeException('Session could not be destroyed.');
    }

    // 刪除瀏覽器的 Cookie，屬性與建立時保持一致。
    // 刪 Cookie 是以相同名稱、路徑等屬性覆寫一份已過期 Cookie；不會清除所有網站的 Cookie。
    $params = session_get_cookie_params();

    // 以過去的到期時間覆寫同名 Cookie，其他 path／domain 等屬性要相同才能刪到原本那份。
    if (!setcookie(session_name(), '', [
        // time() - 3600 是一小時以前，瀏覽器收到就視為過期；不是真的再等一小時才登出。
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ])) {
        throw new RuntimeException('Session cookie could not be cleared.');
    }

    // 後端清 Session 與刪 Cookie 都成功後才回 loggedOut true；前端才能清目前會員。
    sendSuccess([
        'loggedOut' => true,
    ]);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'LOGOUT_UNAVAILABLE',
        '目前無法確認登出結果，請稍後再試。',
        503
    );
}
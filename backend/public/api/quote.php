<?php

// 本檔用途：接收遊戲編號，回傳後端報價供確認。報價存在 Session，還沒有建立訂單。
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
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/checkout.php';

    // 確認寫入請求來自 app.php 允許的前端；只檢查來源，不代替登入與 CSRF 檢查。
    requireAllowedOrigin();
    // 核對瀏覽器附上的操作驗證碼與 Session 一致；失效就拒絕，不自動接受原操作。
    requireCsrfToken();
    // 先檢查 Session 是否已登入，再連 MySQL；沒有身份就直接回覆 401。
    requireMemberId();

    // 前端只提供 gameIds；會員從 Session 取得，價格由後端查詢，不信任瀏覽器自行填總價。
    $input = readJsonBody();

    // 先驗 gameIds 外層是清單，細項整數／重複／長度由 checkout.php 繼續驗，不能只檢查這一層。
    if (!is_array($input['gameIds'] ?? null)) {
        sendError(
            'INVALID_GAME_IDS',
            '請提供遊戲編號清單。',
            400
        );
    }

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();
    // 會員從已登入的 Session 查回，不採用前端自行填 userId；價格也只讀資料庫。
    $member = requireAuthenticatedMember($pdo);

    // 共用 checkout.php 驗證清單與擁有權、保存報價並回傳期限；這不是成交。
    $quote = createCheckoutQuote(
        $pdo,
        $member['id'],
        $input['gameIds']
    );

    // 報價成功僅代表可供確認，不表示已取得遊戲；Session 內保存 quoteId，成交要另走 purchases。
    sendSuccess($quote);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'QUOTE_UNAVAILABLE',
        '目前無法取得報價，請稍後再試。',
        503
    );
}
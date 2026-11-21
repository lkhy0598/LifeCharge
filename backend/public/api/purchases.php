<?php

// 本檔用途：同一入口支援 POST 建立購買、GET 查詢目前會員的購買結果；不處理真實扣款。
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
requireRequestMethod(['GET', 'POST']);

// 保存目前 HTTP 方法，後面依此決定提交或查詢；只有 POST 需要操作來源與 CSRF 檢查。
$method = $_SERVER['REQUEST_METHOD'];

// 進入這次操作的主要流程。require_once 只載入共用函式定義，不表示每個工具已開始查詢。
// 下方任一步 throw 會跳到對應 catch；sendSuccess／sendError 則直接結束這次 HTTP 請求。
try {
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/checkout.php';

    // 寫入才驗 Origin 與 CSRF；GET 不改資料，但後面一樣要已登入，不能公開查所有人的訂單。
    if ($method === 'POST') {
        // 確認寫入請求來自 app.php 允許的前端；只檢查來源，不代替登入與 CSRF 檢查。
        requireAllowedOrigin();
        // 核對瀏覽器附上的操作驗證碼與 Session 一致；失效就拒絕，不自動接受原操作。
        requireCsrfToken();
    }

    // 先檢查 Session 是否已登入，再連 MySQL；沒有身份就直接回覆 401。
    requireMemberId();

    if ($method === 'POST') {
        // POST 只讀 requestKey 與 quoteId，沒有接受卡號、金額或要替哪個會員買。
        $input = readJsonBody();

        $requestKey = validatePurchaseRequestKey(
            $input['requestKey'] ?? null
        );

        $quoteId = $input['quoteId'] ?? null;

        // HTTP 欄位可能不是文字，先擋下；報價長度、內容、期限會在共用工具內核對。
        if (!is_string($quoteId)) {
            sendError(
                'INVALID_QUOTE_ID',
                '請提供報價識別碼。',
                400
            );
        }
    } else {
        $requestKey = validatePurchaseRequestKey(
            // GET 的識別碼在網址查詢參數；仍需 validatePurchaseRequestKey 檢查，並配目前會員查詢。
            $_GET['requestKey'] ?? null
        );
    }

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();
    $member = requireAuthenticatedMember($pdo);

    if ($method === 'POST') {
        // 進入共用成交流程，內部負責查重、鎖定、最終驗價與三張表的交易。
        $order = completeCheckoutPurchase(
            $pdo,
            $member['id'],
            $quoteId,
            $requestKey
        );

        // 成交後重新讀取最新擁有權。
        // 成交後再讀最新擁有權；就算這裡失敗，先前交易可能已 commit，因此回 503 不能當作沒成交。
        $member = findPublicUserById($pdo, $member['id']);

        if ($member === null) {
            throw new RuntimeException('Member could not be loaded.');
        }
    } else {
        // GET 只查同一會員此 requestKey 的訂單；找不到的明確 404，與連線失敗的 503 意義不同。
        $order = findPurchaseByRequestKey(
            $pdo,
            $member['id'],
            $requestKey
        );

        // 這是資料庫查詢已成功且明確沒有原單，才回 404；連線故障走 catch 503，兩者不能混用。
        if ($order === null) {
            sendError(
                'PURCHASE_NOT_FOUND',
                '尚未查到這次購買的成功訂單。',
                404
            );
        }
    }

    // 回同一會員的身份編號、訂單與最新擁有權，前端再核對原 key 才能套用完成畫面。
    sendSuccess([
        'userId' => $member['id'],
        'order' => $order,
        'purchasedGames' => $member['purchasedGames'],
    ]);
// 先處理價格改變、報價過期等可預期錯誤，保留其錯誤碼與狀態供畫面重新確認。
// 先接可預期業務錯誤，保留 code／message／status；後面的 Throwable 才處理其他未知失敗。
} catch (CheckoutException $error) {
    sendError(
        $error->errorCode,
        $error->getMessage(),
        $error->httpStatus
    );
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        // POST 失敗可能結果不明，用 PURCHASE_UNAVAILABLE；GET 失敗是查詢暫不可用，用另一錯誤碼。
        $method === 'POST'
            ? 'PURCHASE_UNAVAILABLE'
            : 'PURCHASE_QUERY_UNAVAILABLE',
        $method === 'POST'
            ? '目前無法確認購買結果，系統將自動確認，請稍候。'
            : '目前無法查詢購買結果，請稍後再試。',
        503
    );
}

<?php

// 本檔用途：依後端 Session 回傳目前會員與擁有權，供重新整理恢復身份，不接受任意會員編號查詢。
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
requireRequestMethod('GET');

// 進入這次操作的主要流程。require_once 只載入共用函式定義，不表示每個工具已開始查詢。
// 下方任一步 throw 會跳到對應 catch；sendSuccess／sendError 則直接結束這次 HTTP 請求。
try {
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';

    // 保留原本順序：先確認身份，再連資料庫。
    // 先檢查 Session 是否已登入，再連 MySQL；沒有身份就直接回覆 401。
    requireMemberId();

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();
    // 先前只確認 Session 編號，現在連線後再驗證會員存在並讀公開欄位與擁有權。
    $user = requireAuthenticatedMember($pdo);

    // 成功交回目前會員；不回 password_hash。未登入由共用工具直接回 401，服務異常才到下方 503。
    sendSuccess($user);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'MEMBER_UNAVAILABLE',
        '會員資料暫時無法載入，請稍後再試。',
        503
    );
}
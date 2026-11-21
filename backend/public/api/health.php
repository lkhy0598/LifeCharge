<?php

// 本檔用途：供 systemStore 啟動時詢問後端是否就緒。只查連線、結構與初始遊戲筆數，不寫資料。
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

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();

    // 確認連線到專案資料庫。
    // 查目前選定的資料庫名稱；連線成功不代表選到正確庫，所以再比對 lifecharge。
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($database !== 'lifecharge') {
        throw new RuntimeException('Unexpected database.');
    }

    // 檢查五張表與目前流程需要的欄位。
    // LIMIT 0 只驗證結構，不讀取會員資料或密碼雜湊。
    // 列出流程需要的資料表欄位。LIMIT 0 不讀取資料列，但缺表／缺欄位會讓查詢失敗。
    $structureChecks = [
        'SELECT id, title, title_zh, price, rating, release_date,
            developer, publisher, description, detail_description,
            genre, tags, status, cover, banner, screenshots
     FROM games LIMIT 0',

        'SELECT id, username, email, password_hash, display_name,
            avatar, created_at, updated_at
     FROM users LIMIT 0',

        'SELECT id, user_id, request_key, total_amount,
            currency, status, created_at
     FROM orders LIMIT 0',

        'SELECT id, order_id, game_id, title, title_zh, unit_price
     FROM order_items LIMIT 0',

        'SELECT user_id, game_id, source, order_item_id, created_at
     FROM user_games LIMIT 0',
    ];

    // 逐條執行固定結構查詢；closeCursor 釋放結果，缺欄位會直接發錯，不能宣稱 ready。
    foreach ($structureChecks as $sql) {
        $pdo->query($sql)->closeCursor();
    }

    // 本次 V2 的初始遊戲 ID 為 1～30。
    // 明確轉整數後檢查初始 1～30 編號是否共 30 筆；這不計算會員數，也不驗證圖片檔案。
    $initialGameCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM games WHERE id BETWEEN 1 AND 30'
    )->fetchColumn();

    // 只有初始編號 1～30 的遊戲都存在才 ready；此檢查不代表每個功能或每張圖片都已驗證。
    $ready = $initialGameCount === 30;

    // 四個欄位交給 healthService：service／apiVersion 確認服務，databaseConnected 表示連線成功，ready 表示初始資料就緒。
    sendSuccess([
        'service' => 'lifecharge-api',
        'apiVersion' => 1,
        'databaseConnected' => true,
        'ready' => $ready,
    ]);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'BACKEND_UNAVAILABLE',
        '後端連線尚未就緒，請檢查本機設定與資料庫服務。',
        503
    );
}

<?php

// 本檔用途：讀取 MySQL 的遊戲清單，整理成前端欄位名稱與資料類型，再回傳 JSON。
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

    // SELECT 選取欄位，AS 把資料庫的底線名稱改成前端習慣的名稱；ORDER BY id 固定順序。
    // fetchAll() 一次取出全部結果；此資料集很小，不是分頁查詢。
    $rows = $pdo->query(
        'SELECT
            id,
            title,
            title_zh AS titleZh,
            price,
            rating,
            release_date AS releaseDate,
            developer,
            publisher,
            description,
            detail_description AS detailDescription,
            genre,
            tags,
            status,
            cover,
            banner,
            screenshots
         FROM games
         ORDER BY id'
    )->fetchAll();

    // 先準備空結果，接著每一列完成數字／陣列轉換才加入，最後一次回整份清單。
    $games = [];

    // foreach 表示逐筆處理；每款遊戲將數字轉型、將 JSON 文字解回陣列，再放進 $games。
    foreach ($rows as $row) {
        // PDO 讀出的數字欄位可能是字串，明確轉成前端需要的型別。
        // 編號轉 int、價格與評分轉 float，讓前端收到數字而不是 PDO 可能給的數字文字。
        // 這只是遊戲展示價格；Database 成交另有整數分／字串金額處理。
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['rating'] = (float) $row['rating'];

        // 資料庫中的 JSON 字串，轉成真正的陣列。
        foreach (['genre', 'tags', 'status', 'screenshots'] as $field) {
            // true 讓 JSON 清單轉成 PHP 陣列，512 是最大巢狀深度；JSON_THROW_ON_ERROR 讓壞資料直接報錯。
            $values = json_decode(
                $row[$field],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // JSON 解碼也可能得到一般物件或數字，必須是陣列且索引連續的清單，才符合前端每項標籤的使用方式。
            if (!is_array($values) || !array_is_list($values)) {
                throw new RuntimeException('Invalid game array.');
            }

            // 再逐項確認都是非空文字，例如 [RPG, Action]；只有外層是清單仍不夠。
            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new RuntimeException('Invalid game array item.');
                }
            }

            // 把原本資料庫的 JSON 文字換成真正陣列，讓最後 json_encode 回前端時不是雙重編碼的文字。
            $row[$field] = $values;
        }

        if (count($row['screenshots']) !== 5) {
            throw new RuntimeException('Invalid screenshot count.');
        }

        // [] 表示加到結果尾端；只有整款檢查通過才加入，任一款錯誤會整個進 catch。
        $games[] = $row;
    }

    // 回傳全部標準化商品。API 不決定卡片怎麼排，也不做會員專屬購買判斷。
    sendSuccess($games);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    sendError(
        'GAMES_UNAVAILABLE',
        '遊戲資料暫時無法載入。',
        503
    );
}

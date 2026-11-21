<?php

// 本檔統一 API 回覆格式，前端才不用為每支 PHP 寫不同讀取方式。
// 成功回覆有 ok、data；失敗回覆有 ok、error。JSON 是前後端交換資料用的文字格式。
// 沒有資料庫操作；send 開頭的函式最後都會結束請求，不再執行後面的程式。

declare(strict_types=1);

// 用途：將 PHP 資料轉成 JSON、設定 HTTP 狀態，送出後結束。
// $payload 是整份資料，$status = 200 表示沒有另外指定時當成功處理。
// : never 的意思是這個函式不會正常回到呼叫位置，因為最後會 exit。
function sendJson(array $payload, int $status = 200): never
{
    // 先編碼，避免編碼失敗時已送出成功狀態。
    // json_encode() 把資料轉成 JSON 文字。UNESCAPED_UNICODE 讓中文保持可讀，
    // THROW_ON_ERROR 讓轉換失敗時發出錯誤；| 表示同時啟用兩個選項。
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    // HTTP 狀態碼讓瀏覽器知道成功或失敗，例如 200 成功、401 未登入、503 暫時不可用。
    // header() 設定回覆資訊：內容是 UTF-8 JSON，no-store 要求不要快取會員等即時資料。
    // 狀態碼與 JSON 的 ok 是兩層資訊，都要一致；200／201 成功，400 等表示請求錯誤，503 暫時不可用。
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // echo 真正送出 JSON 文字；exit 結束整個 PHP 請求，所以下方呼叫處不會再接著執行。
    echo $json;
    exit;
}

// 用途：替成功結果包上固定外層，例如 {ok:true,data:遊戲清單}。
// mixed 表示 $data 可是陣列、文字、布林等類型；此處不查資料，直接交給 sendJson。
function sendSuccess(mixed $data, int $status = 200): never
{
    // 組合固定外層：ok true 與 data；$data 可為清單或單一物件，後端在此不更動業務內容。
    sendJson([
        'ok' => true,
        'data' => $data,
    ], $status);
}

// 用途：回覆錯誤碼、給使用者看的訊息與 HTTP 狀態。
// $fields 是可選的個別欄位錯誤，例如密碼太短；預設 [] 表示沒有。
// 錯誤碼供程式判斷，message 供畫面顯示，兩者用途不同。
function sendError(
    string $code,
    string $message,
    int $status,
    array $fields = []
): never {
    // 先放程式判斷用 code 與人閱讀的 message；$fields 有內容才額外附上，不強迫每種錯誤都有表單欄位。
    $error = [
        'code' => $code,
        'message' => $message,
    ];

    // !== 嚴格比較不是空清單才加入 fields；避免每個一般錯誤都夾空的欄位錯誤。
    if ($fields !== []) {
        $error['fields'] = $fields;
    }

    // 失敗統一 ok false、error 物件，搭配呼叫者傳的狀態碼，讓 apiClient 能保留正確錯誤類型。
    sendJson([
        'ok' => false,
        'error' => $error,
    ], $status);
}
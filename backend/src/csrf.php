<?php

// 本檔產生與檢查操作驗證碼，稱為 CSRF token。
// 用途是讓後端核對：這次操作有沒有帶上屬於目前 Session 的驗證碼。
// 它不取代登入檢查；有 token 不代表已登入，也不代表可以操作其他會員的資料。
// 驗證碼放 PHP Session；前端從 csrf.php 取得後，在 POST 的 X-CSRF-Token 標頭帶回。

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

// 用途：產生新的安全隨機驗證碼，存入目前 Session 並回傳。
// random_bytes() 產生隨機資料；bin2hex() 將 32 bytes 變成 64 字元文字，方便傳送。
function rotateCsrfToken(): string
{
    // 先確保目前請求已連到瀏覽器的 Session，才能保存新 token，不從任意前端 userId 選身份。
    startAppSession();

    $token = bin2hex(random_bytes(32));
    // 產生後保存在後端，再 return 交給前端；後續核對的是同一份文字，不是臨時猜出來的值。
    $_SESSION['csrf_token'] = $token;

    return $token;
}

// 用途：取得目前驗證碼；沒有或格式不完整才產生新的。
// ?? null 是沒有值就用 null；: string 要求成功時回傳文字。
function getCsrfToken(): string
{
    startAppSession();

    // 取得現有驗證碼，沒有就用 null；下面只在缺少或不是 64 字元時才換新。
    $token = $_SESSION['csrf_token'] ?? null;

    if (!is_string($token) || strlen($token) !== 64) {
        // 呼叫另一個函式產生並保存新 token，再將其結果交回；不是每次 GET 都固定換碼。
        return rotateCsrfToken();
    }

    return $token;
}

// 用途：比較後端保存的 $expected，與瀏覽器傳來的 $provided。
// 先確認兩邊都是 64 字元文字，再用 hash_equals() 比較，減少比較時間洩漏資訊。
// 任一條件不符合就回覆 403；|| 表示其中一項成立就算檢查失敗。
function requireCsrfToken(): void
{
    startAppSession();

    // expected 是後端記住的正確碼；provided 由下面的 X-CSRF-Token 標頭取得，必須兩邊一致。
    $expected = $_SESSION['csrf_token'] ?? null;
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (
        // 先確認類型與長度再 hash_equals 比對；|| 遇到前項已失敗就不用繼續，避免對錯類型做比較。
        !is_string($expected)
        || strlen($expected) !== 64
        || !is_string($provided)
        || strlen($provided) !== 64
        || !hash_equals($expected, $provided)
    ) {
        // 回 403 CSRF_TOKEN_INVALID 後請求結束，不會繼續執行登入／購買。身份是否登入仍是另一層檢查。
        sendError(
            'CSRF_TOKEN_INVALID',
            '操作驗證已失效，請重新驗證後再試。',
            403
        );
    }
}
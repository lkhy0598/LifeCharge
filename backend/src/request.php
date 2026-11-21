<?php

// 本檔檢查「瀏覽器送來的請求」是否符合 API 規則。
// API 可以理解成前端請後端辦事的入口；GET 通常查資料，POST 提交操作。
// require_once 載入其他工具且避免同一請求重複載入。__DIR__ 是目前檔案所在資料夾。
// 下面的 : void 表示函式通過時不用回傳值；不通過則 sendError 回覆並結束。

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

// 用途：檢查寫入請求是從哪個前端網址發出。
// $_SERVER 由 PHP 提供，保存 HTTP 請求資訊；Origin 是協定、主機與連接埠，不含頁面路徑。
// 只接受 app.php 的清單，不接受沒有 Origin 的請求；這不是登入驗證。
function requireAllowedOrigin(): void
{
    // 從 HTTP 請求取 Origin，沒有就用 null；這是瀏覽器送來的來源資訊，不能當會員編號或密碼。
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    // 讀 app.php 允許清單，in_array 第三參數 true 表示連型別也要一致，不能用寬鬆比較誤判。
    $allowedOrigins = appConfig()['allowed_origins'];

    if (
        // 缺來源、非文字或清單沒有這個來源就拒絕；|| 表示任一項成立，後面 sendError 會直接結束。
        !is_string($origin)
        || !in_array($origin, $allowedOrigins, true)
    ) {
        sendError(
            'ORIGIN_NOT_ALLOWED',
            '不允許此來源的操作請求。',
            403
        );
    }
}

// 用途：讀取前端 POST 的 JSON，回傳 PHP 可用的欄位清單。
// 先驗證格式與大小，再解析內容；不接受把一個 JSON 陣列當作表單物件。
// 例如 {"gameIds":[1,2]} 會得到可用 $input['gameIds'] 讀取的資料。
function readJsonBody(): array
{
    // Content-Type 告訴後端提交內容的格式；分號後可能有 charset 等附加資訊。
    // explode() 切開文字、trim() 去掉前後空白、strtolower() 轉小寫後再比較。
    $contentType = strtolower(trim(
        explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]
    ));

    // 只接受 application/json；前面已去分號附加資訊與空白，不能拿表單編碼當 JSON 硬讀。
    if ($contentType !== 'application/json') {
        sendError(
            'UNSUPPORTED_MEDIA_TYPE',
            '請使用 application/json 格式。',
            415
        );
    }

    // 會員表單限制為 16 KiB，讀取時多取 1 byte 判斷是否超限。
    // 16 * 1024 = 16384 bytes，是這個 API 表單內容的讀取限制，不是 PHP 整個程序的記憶體上限。
    $maxBytes = 16 * 1024;
    // php://input 是原始請求內容，不是磁碟檔案。最多讀上限再多 1 byte，
    // 才能知道是否超過限制，而不先把任意大的內容全部放進記憶體。
    $raw = file_get_contents(
        'php://input',
        false,
        null,
        0,
        $maxBytes + 1
    );

    // file_get_contents 失敗會回 false，和合法空字串不同；先處理讀取失敗再解析內容。
    if ($raw === false) {
        throw new RuntimeException('Request body could not be read.');
    }

    // 多讀的一個 byte 用來識別超限；超過就回覆 413，避免繼續解析任意大的輸入。
    if (strlen($raw) > $maxBytes) {
        sendError(
            'PAYLOAD_TOO_LARGE',
            '送出的資料過大。',
            413
        );
    }

    try {
        // json_decode() 把 JSON 文字變成 PHP 資料；false 保留物件形式，32 限制巢狀深度。
        // JSON_THROW_ON_ERROR 表示格式不合法就發出 JsonException，讓 catch 回覆 400。
        $data = json_decode(
            $raw,
            false,
            32,
            JSON_THROW_ON_ERROR
        );
    // JSON 解析錯誤是使用者請求格式有問題，回 400；JsonException 是 PHP 為 JSON 失敗提供的錯誤類型。
    } catch (JsonException $error) {
        sendError(
            'INVALID_JSON',
            '送出的 JSON 格式不正確。',
            400
        );
    }

    // 最外層必須像 {"gameIds":[1,2]} 這樣有欄位的物件，不能是 [1,2] 或單一字串。
    if (!$data instanceof stdClass) {
        sendError(
            'INVALID_REQUEST',
            '請求內容必須是 JSON 物件。',
            400
        );
    }

    // stdClass 是 PHP 的一般物件；上方確認內容是物件後，這裡才轉成以欄位名取值的陣列。
    return get_object_vars($data);
}

// 用途：限制 API 接受 GET、POST 或兩者。
// string|array 表示可傳單一文字或清單；三元寫法 條件 ? A : B 用來統一成清單。
// 不符合時設定 Allow 告知可用方法，再回覆 405 並結束。
function requireRequestMethod(string|array $methods): void
{
    // 如果傳單一文字 GET，就包成 [GET]；已是清單則保留，後面統一用 in_array 比對。
    $allowed = is_array($methods) ? $methods : [$methods];

    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', $allowed, true)) {
        return;
    }

    // implode 用逗號串出允許方法清單，放進 HTTP Allow 標頭；再回覆 405 告知使用方法錯誤。
    header('Allow: ' . implode(', ', $allowed));

    sendError(
        'METHOD_NOT_ALLOWED',
        '此端點只接受 ' . implode(' 或 ', $allowed) . ' 請求。',
        405
    );
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function requireAllowedOrigin(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $allowedOrigins = appConfig()['allowed_origins'];

    if (
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

function readJsonBody(): array
{
    $contentType = strtolower(trim(
        explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]
    ));

    if ($contentType !== 'application/json') {
        sendError(
            'UNSUPPORTED_MEDIA_TYPE',
            '請使用 application/json 格式。',
            415
        );
    }

    // 會員表單限制為 16 KiB，讀取時多取 1 byte 判斷是否超限。
    $maxBytes = 16 * 1024;
    $raw = file_get_contents(
        'php://input',
        false,
        null,
        0,
        $maxBytes + 1
    );

    if ($raw === false) {
        throw new RuntimeException('Request body could not be read.');
    }

    if (strlen($raw) > $maxBytes) {
        sendError(
            'PAYLOAD_TOO_LARGE',
            '送出的資料過大。',
            413
        );
    }

    try {
        $data = json_decode(
            $raw,
            false,
            32,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $error) {
        sendError(
            'INVALID_JSON',
            '送出的 JSON 格式不正確。',
            400
        );
    }

    if (!$data instanceof stdClass) {
        sendError(
            'INVALID_REQUEST',
            '請求內容必須是 JSON 物件。',
            400
        );
    }

    return get_object_vars($data);
}

function requireRequestMethod(string|array $methods): void
{
    $allowed = is_array($methods) ? $methods : [$methods];

    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', $allowed, true)) {
        return;
    }

    header('Allow: ' . implode(', ', $allowed));

    sendError(
        'METHOD_NOT_ALLOWED',
        '此端點只接受 ' . implode(' 或 ', $allowed) . ' 請求。',
        405
    );
}
<?php

declare(strict_types=1);

function sendJson(array $payload, int $status = 200): never
{
    // 先編碼，避免編碼失敗時已送出成功狀態。
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo $json;
    exit;
}

function sendSuccess(mixed $data, int $status = 200): never
{
    sendJson([
        'ok' => true,
        'data' => $data,
    ], $status);
}

function sendError(
    string $code,
    string $message,
    int $status,
    array $fields = []
): never {
    $error = [
        'code' => $code,
        'message' => $message,
    ];

    if ($fields !== []) {
        $error['fields'] = $fields;
    }

    sendJson([
        'ok' => false,
        'error' => $error,
    ], $status);
}
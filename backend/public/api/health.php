<?php

declare(strict_types=1);

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');

    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => '此端點只接受 GET 請求。',
        ],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    require_once dirname(__DIR__, 2) . '/src/database.php';

    $pdo = connectDatabase();
    $pdo->query('SELECT 1')->fetchColumn();

    echo json_encode([
        'ok' => true,
        'data' => [
            'service' => 'lifecharge-api',
            'apiVersion' => 1,
            'databaseConnected' => true,
            'ready' => false,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(503);

    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => 'BACKEND_UNAVAILABLE',
            'message' => '後端連線尚未就緒，請檢查本機設定與資料庫服務。',
        ],
    ], JSON_UNESCAPED_UNICODE);
}
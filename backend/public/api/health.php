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

    // 確認連線到專案資料庫。
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($database !== 'lifecharge') {
        throw new RuntimeException('Unexpected database.');
    }

    // 檢查五張表與目前流程需要的欄位。
    // LIMIT 0 只驗證結構，不讀取會員資料或密碼雜湊。
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

    foreach ($structureChecks as $sql) {
        $pdo->query($sql)->closeCursor();
    }

    // 本次 V2 的初始遊戲 ID 為 1～30。
    $initialGameCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM games WHERE id BETWEEN 1 AND 30'
    )->fetchColumn();

    $ready = $initialGameCount === 30;

    echo json_encode([
        'ok' => true,
        'data' => [
            'service' => 'lifecharge-api',
            'apiVersion' => 1,
            'databaseConnected' => true,
            'ready' => $ready,
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

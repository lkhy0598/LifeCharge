<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('GET');

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

    sendSuccess([
        'service' => 'lifecharge-api',
        'apiVersion' => 1,
        'databaseConnected' => true,
        'ready' => $ready,
    ]);
} catch (Throwable $error) {
    sendError(
        'BACKEND_UNAVAILABLE',
        '後端連線尚未就緒，請檢查本機設定與資料庫服務。',
        503
    );
}

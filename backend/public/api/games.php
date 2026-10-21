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

    $games = [];

    foreach ($rows as $row) {
        // PDO 讀出的數字欄位可能是字串，明確轉成前端需要的型別。
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['rating'] = (float) $row['rating'];

        // 資料庫中的 JSON 字串，轉成真正的陣列。
        foreach (['genre', 'tags', 'status', 'screenshots'] as $field) {
            $values = json_decode(
                $row[$field],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($values) || !array_is_list($values)) {
                throw new RuntimeException('Invalid game array.');
            }

            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new RuntimeException('Invalid game array item.');
                }
            }

            $row[$field] = $values;
        }

        if (count($row['screenshots']) !== 5) {
            throw new RuntimeException('Invalid screenshot count.');
        }

        $games[] = $row;
    }

    echo json_encode([
        'ok' => true,
        'data' => $games,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);

    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => 'GAMES_UNAVAILABLE',
            'message' => '遊戲資料暫時無法載入。',
        ],
    ], JSON_UNESCAPED_UNICODE);
}

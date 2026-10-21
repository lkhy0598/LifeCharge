<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('POST');

try {
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/checkout.php';

    requireAllowedOrigin();
    requireCsrfToken();
    requireMemberId();

    $input = readJsonBody();

    if (!is_array($input['gameIds'] ?? null)) {
        sendError(
            'INVALID_GAME_IDS',
            '請提供遊戲編號清單。',
            400
        );
    }

    $pdo = connectDatabase();
    $member = requireAuthenticatedMember($pdo);

    $quote = createCheckoutQuote(
        $pdo,
        $member['id'],
        $input['gameIds']
    );

    sendSuccess($quote);
} catch (Throwable $error) {
    sendError(
        'QUOTE_UNAVAILABLE',
        '目前無法取得報價，請稍後再試。',
        503
    );
}
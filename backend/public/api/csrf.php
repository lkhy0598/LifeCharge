<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    sendError(
        'METHOD_NOT_ALLOWED',
        '此端點只接受 GET 請求。',
        405
    );
}

try {
    require_once dirname(__DIR__, 2) . '/src/csrf.php';

    sendSuccess([
        'csrfToken' => getCsrfToken(),
    ]);
} catch (Throwable $error) {
    sendError(
        'SESSION_UNAVAILABLE',
        '目前無法建立操作驗證，請稍後再試。',
        503
    );
}
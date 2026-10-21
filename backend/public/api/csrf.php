<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('GET');

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
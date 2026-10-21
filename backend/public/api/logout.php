<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('POST');

try {
    require_once dirname(__DIR__, 2) . '/src/request.php';
    require_once dirname(__DIR__, 2) . '/src/csrf.php';

    requireAllowedOrigin();
    requireCsrfToken();

    $_SESSION = [];

    if (!session_destroy()) {
        throw new RuntimeException('Session could not be destroyed.');
    }

    // 刪除瀏覽器的 Cookie，屬性與建立時保持一致。
    $params = session_get_cookie_params();

    if (!setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ])) {
        throw new RuntimeException('Session cookie could not be cleared.');
    }

    sendSuccess([
        'loggedOut' => true,
    ]);
} catch (Throwable $error) {
    sendError(
        'LOGOUT_UNAVAILABLE',
        '目前無法確認登出結果，請稍後再試。',
        503
    );
}
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
    require_once dirname(__DIR__, 2) . '/src/session.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';

    startAppSession();

    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) || $userId <= 0) {
        sendError(
            'UNAUTHENTICATED',
            '請先登入。',
            401
        );
    }

    $pdo = connectDatabase();
    $user = findPublicUserById($pdo, $userId);

    if ($user === null) {
        // 原會員已不存在，清除失效身份及相關資料。
        $_SESSION = [];

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session could not be renewed.');
        }

        sendError(
            'UNAUTHENTICATED',
            '請重新登入。',
            401
        );
    }

    sendSuccess($user);
} catch (Throwable $error) {
    sendError(
        'MEMBER_UNAVAILABLE',
        '會員資料暫時無法載入，請稍後再試。',
        503
    );
}
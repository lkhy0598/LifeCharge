<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('GET');

try {
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';

    // 保留原本順序：先確認身份，再連資料庫。
    requireMemberId();

    $pdo = connectDatabase();
    $user = requireAuthenticatedMember($pdo);

    sendSuccess($user);
} catch (Throwable $error) {
    sendError(
        'MEMBER_UNAVAILABLE',
        '會員資料暫時無法載入，請稍後再試。',
        503
    );
}
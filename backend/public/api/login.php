<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('POST');

try {
    require_once dirname(__DIR__, 2) . '/src/request.php';
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/rateLimit.php';

    requireAllowedOrigin();
    requireRateLimit('login');
    requireCsrfToken();

    $input = readJsonBody();

    $identifier = $input['identifier'] ?? null;
    $password = $input['password'] ?? null;

    if (!is_string($identifier) || !is_string($password)) {
        sendError(
            'INVALID_INPUT',
            '請輸入帳號或 Email 與密碼。',
            400
        );
    }

    $identifier = trim($identifier);

    // 密碼不 trim，避免改變使用者真正輸入的內容。
    // 現有 PHP bcrypt 雜湊採最多 72 bytes。
    if (
        $identifier === ''
        || mb_strlen($identifier, 'UTF-8') > 254
        || $password === ''
        || strlen($password) > 72
        || preg_match('/[\s\p{Z}\x{FEFF}]/u', $password) !== 0
        || str_contains($password, "\0")
    ) {
        sendError(
            'INVALID_INPUT',
            '帳號或 Email、密碼格式不正確；密碼不可包含空白。',
            400
        );
    }

    $pdo = connectDatabase();
    $loginUser = findLoginUser($pdo, $identifier);

    // 查不到帳號時仍執行一次密碼比對，減少明顯的時間差。
    // 此為公開的測試雜湊，不是會員密碼。
    $dummyHash =
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    $passwordMatches = password_verify(
        $password,
        $loginUser['passwordHash'] ?? $dummyHash
    );

    if ($loginUser === null || !$passwordMatches) {
        sendError(
            'INVALID_CREDENTIALS',
            '帳號、Email 或密碼錯誤。',
            401
        );
    }

    $user = findPublicUserById($pdo, $loginUser['id']);

    if ($user === null) {
        sendError(
            'INVALID_CREDENTIALS',
            '帳號、Email 或密碼錯誤。',
            401
        );
    }

    $_SESSION = prepareMemberSession($user['id']);

    sendSuccess($user);
} catch (Throwable $error) {
    sendError(
        'LOGIN_UNAVAILABLE',
        '目前無法登入，請稍後再試。',
        503
    );
}

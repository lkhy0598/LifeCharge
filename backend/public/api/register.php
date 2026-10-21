<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod('POST');

$pdo = null;

try {
    require_once dirname(__DIR__, 2) . '/src/request.php';
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/rateLimit.php';

    requireAllowedOrigin();
    requireRateLimit('register');
    requireCsrfToken();

    $input = validateRegistration(readJsonBody());

    $passwordHash = password_hash(
        $input['password'],
        PASSWORD_BCRYPT
    );

    $now = gmdate('Y-m-d H:i:s');

    $pdo = connectDatabase();
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'INSERT INTO users (
            username,
            email,
            password_hash,
            display_name,
            avatar,
            created_at,
            updated_at
         ) VALUES (
            :username,
            :email,
            :password_hash,
            :display_name,
            :avatar,
            :created_at,
            :updated_at
         )'
    );

    $statement->execute([
        'username' => $input['username'],
        'email' => $input['email'],
        'password_hash' => $passwordHash,
        'display_name' => $input['displayName'],
        'avatar' => 'usersDataWebp/default.webp',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $userId = (int) $pdo->lastInsertId();
    $user = findPublicUserById($pdo, $userId);

    if ($user === null) {
        throw new RuntimeException('Created user could not be loaded.');
    }

    $newSession = prepareMemberSession($userId);

    $pdo->commit();

    // 資料庫交易成功後，才套用新會員身份。
    $_SESSION = $newSession;

    sendSuccess($user, 201);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 由資料庫唯一約束處理重複及同時送出的註冊。
    if (
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        sendError(
            'ACCOUNT_EXISTS',
            '帳號或 Email 已被使用，請更換後再試。',
            409
        );
    }

    sendError(
        'REGISTER_UNAVAILABLE',
        '目前無法確認註冊結果，請稍後再試。',
        503
    );
}

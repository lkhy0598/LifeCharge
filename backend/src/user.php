<?php

declare(strict_types=1);
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function findPublicUserById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            username,
            email,
            display_name AS displayName,
            avatar
         FROM users
         WHERE id = :id'
    );

    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if ($user === false) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT game_id
         FROM user_games
         WHERE user_id = :user_id
         ORDER BY game_id'
    );

    $statement->execute(['user_id' => $userId]);

    $user['id'] = (int) $user['id'];
    $user['purchasedGames'] = array_map(
        static fn($id): int => (int) $id,
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );

    return $user;
}

function findLoginUser(PDO $pdo, string $identifier): ?array
{
    // 帳號禁止包含 @，因此可以明確區分 Email 與帳號。
    $column = str_contains($identifier, '@')
        ? 'email'
        : 'username';

    // 欄位名稱只來自上面的固定選項；
    // 使用者輸入仍透過參數綁定。
    $statement = $pdo->prepare(
        "SELECT id, password_hash
         FROM users
         WHERE {$column} = :identifier
         LIMIT 1"
    );

    $statement->execute([
        'identifier' => $identifier,
    ]);

    $user = $statement->fetch();

    if ($user === false) {
        return null;
    }

    return [
        'id' => (int) $user['id'],
        'passwordHash' => $user['password_hash'],
    ];
}

function requireMemberId(): int
{
    startAppSession();

    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) || $userId <= 0) {
        sendError(
            'UNAUTHENTICATED',
            '請先登入。',
            401
        );
    }

    return $userId;
}

function requireAuthenticatedMember(PDO $pdo): array
{
    $userId = requireMemberId();
    $user = findPublicUserById($pdo, $userId);

    if ($user === null) {
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

    return $user;
}

function validateRegistration(array $input): array
{
    $fields = [];

    $username = is_string($input['username'] ?? null)
        ? trim($input['username'])
        : '';

    $email = is_string($input['email'] ?? null)
        ? trim($input['email'])
        : '';

    $displayName = is_string($input['displayName'] ?? null)
        ? trim($input['displayName'])
        : '';

    // 密碼保持原樣，不移除前後空白。
    $password = is_string($input['password'] ?? null)
        ? $input['password']
        : '';

    if (!preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $username)) {
        $fields['username'] =
            '帳號須為 3～50 字元，只能使用英文字母、數字、底線與連字號。';
    }

    if (
        strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $fields['email'] = '請輸入有效的 Email，最多 254 bytes。';
    }

    $displayNameLength = mb_strlen($displayName, 'UTF-8');

    if ($displayNameLength < 1 || $displayNameLength > 100) {
        $fields['displayName'] = '顯示名稱須為 1～100 字元。';
    }

    if (
        mb_strlen($password, 'UTF-8') < 8
        || strlen($password) > 72
        || preg_match('/[\s\p{Z}\x{FEFF}]/u', $password) !== 0
        || str_contains($password, "\0")
    ) {
        $fields['password'] =
            '密碼至少 8 字元、最多 72 bytes，且不可包含空白或空字元。';
    }

    if ($fields !== []) {
        sendError(
            'VALIDATION_FAILED',
            '請檢查註冊資料。',
            400,
            $fields
        );
    }

    return [
        'username' => $username,
        'email' => $email,
        'displayName' => $displayName,
        'password' => $password,
    ];
}

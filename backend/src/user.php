<?php

declare(strict_types=1);

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

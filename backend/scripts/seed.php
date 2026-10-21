<?php

declare(strict_types=1);

// 只允許命令列執行。
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/database.php';

try {
    $pdo = connectDatabase();

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($database !== 'lifecharge') {
        fwrite(STDERR, "請確認本機設定的 database 是 lifecharge。\n");
        exit(1);
    }

    echo "目前資料庫：lifecharge\n";
    echo "開始唯讀預檢，不會寫入資料。\n\n";

    $tables = [
        'games',
        'users',
        'orders',
        'order_items',
        'user_games',
    ];

    foreach ($tables as $table) {
        // 表名只來自上面的固定清單。
        $count = $pdo
            ->query("SELECT COUNT(*) FROM `$table`")
            ->fetchColumn();

        echo $table . '：' . $count . " 筆\n";
    }

    $projectRoot = dirname(__DIR__, 2);

    $games = json_decode(
        file_get_contents($projectRoot . '/src/data/games.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $users = json_decode(
        file_get_contents($projectRoot . '/src/data/users.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($games) || !is_array($users)) {
        throw new RuntimeException('JSON 格式錯誤');
    }

    $gameIds = [];
    $ownershipCount = 0;

    foreach ($games as $game) {
        $id = $game['id'] ?? null;

        if (!is_int($id) || $id <= 0 || isset($gameIds[$id])) {
            throw new RuntimeException('遊戲 ID 無效或重複');
        }

        $gameIds[$id] = true;

        $textFields = [
            'title' => 200,
            'titleZh' => 200,
            'developer' => 200,
            'publisher' => 200,
        ];

        foreach ($textFields as $field => $maxLength) {
            $value = $game[$field] ?? null;

            if (
                !is_string($value)
                || trim($value) === ''
                || mb_strlen($value, 'UTF-8') > $maxLength
            ) {
                throw new RuntimeException("遊戲 $id 的 $field 格式錯誤");
            }
        }

        foreach (['description', 'detailDescription'] as $field) {
            $value = $game[$field] ?? null;

            if (
                !is_string($value)
                || trim($value) === ''
                || strlen($value) > 65535
            ) {
                throw new RuntimeException("遊戲 $id 的 $field 格式錯誤");
            }
        }

        $price = $game['price'] ?? null;
        $rating = $game['rating'] ?? null;

        if (
            (!is_int($price) && !is_float($price))
            || $price < 0
            || $price > 99999999.99
            || abs($price - round($price, 2)) > 0.000001
        ) {
            throw new RuntimeException("遊戲 $id 的價格格式錯誤");
        }

        if (
            (!is_int($rating) && !is_float($rating))
            || $rating < 0
            || $rating > 5
            || abs($rating - round($rating, 1)) > 0.000001
        ) {
            throw new RuntimeException("遊戲 $id 的評分格式錯誤");
        }

        $releaseDate = $game['releaseDate'] ?? null;

        $date = is_string($releaseDate)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $releaseDate)
            : false;

        if (!$date || $date->format('Y-m-d') !== $releaseDate) {
            throw new RuntimeException("遊戲 $id 的發售日期格式錯誤");
        }

        foreach (['genre', 'tags', 'status'] as $field) {
            $values = $game[$field] ?? null;

            if (!is_array($values) || !array_is_list($values)) {
                throw new RuntimeException("遊戲 $id 的 $field 必須是陣列");
            }

            foreach ($values as $value) {
                if (!is_string($value) || trim($value) === '') {
                    throw new RuntimeException("遊戲 $id 的 $field 內容錯誤");
                }
            }
        }

        if (array_diff($game['status'], ['new', 'bestseller', 'featured'])) {
            throw new RuntimeException("遊戲 $id 包含未知的推薦狀態");
        }

        $folder = sprintf('game%02d', $id);

        $suffixes = ['cover', 'banner'];

        for ($number = 1; $number <= 5; $number++) {
            $suffixes[] = sprintf('screenshots%02d', $number);
        }

        foreach ($suffixes as $suffix) {
            $path = $projectRoot
                . "/backend/public/media/gameDataWebp/$folder/"
                . "{$folder}_{$suffix}.webp";

            if (!is_file($path)) {
                throw new RuntimeException('遊戲圖片缺少');
            }
        }
    }

    $userIds = [];
    $usernames = [];
    $emails = [];

    $findExistingUser = $pdo->prepare(
        'SELECT id, username, email
     FROM users
     WHERE id = :id
        OR username = :username
        OR email = :email'
    );

    foreach ($users as $user) {
        $id = $user['id'] ?? null;

        if (!is_int($id) || $id <= 0) {
            throw new RuntimeException('會員 ID 無效');
        }

        $username = $user['username'] ?? null;
        $email = $user['email'] ?? null;
        $displayName = $user['displayName'] ?? null;
        $password = $user['password'] ?? null;

        if (
            !is_string($username)
            || !preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $username)
        ) {
            throw new RuntimeException('會員帳號格式錯誤');
        }

        if (
            !is_string($email)
            || strlen($email) > 254
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('會員 Email 格式錯誤');
        }

        if (
            !is_string($displayName)
            || mb_strlen(trim($displayName), 'UTF-8') < 1
            || mb_strlen($displayName, 'UTF-8') > 100
        ) {
            throw new RuntimeException('會員顯示名稱格式錯誤');
        }

        // 舊會員不強制套用新密碼長度，但不可為空或包含空白。
        if (
            !is_string($password)
            || $password === ''
            || preg_match('/[\s\p{Z}]/u', $password) !== 0
        ) {
            throw new RuntimeException('舊會員密碼格式錯誤');
        }

        $usernameKey = strtolower($username);
        $emailKey = strtolower($email);

        if (
            isset($userIds[$id])
            || isset($usernames[$usernameKey])
            || isset($emails[$emailKey])
        ) {
            throw new RuntimeException('JSON 會員身份資料重複');
        }

        $userIds[$id] = true;
        $usernames[$usernameKey] = true;
        $emails[$emailKey] = true;

        $findExistingUser->execute([
            'id' => $id,
            'username' => $username,
            'email' => $email,
        ]);

        foreach ($findExistingUser->fetchAll() as $existing) {
            if (
                (int) $existing['id'] !== $id
                || strtolower($existing['username']) !== $usernameKey
                || strtolower($existing['email']) !== $emailKey
            ) {
                throw new RuntimeException('資料庫會員身份與 JSON 衝突');
            }
        }

        $avatar = $projectRoot
            . "/backend/public/media/usersDataWebp/$id/user$id.webp";

        if (!is_file($avatar)) {
            throw new RuntimeException('會員頭像缺少');
        }

        if (!is_array($user['purchasedGames'] ?? null)) {
            throw new RuntimeException('擁有遊戲格式錯誤');
        }

        foreach (array_unique($user['purchasedGames']) as $gameId) {
            if (!is_int($gameId) || !isset($gameIds[$gameId])) {
                throw new RuntimeException('擁有紀錄包含無效遊戲');
            }

            $ownershipCount++;
        }
    }

    if (!is_file(
        $projectRoot . '/backend/public/media/usersDataWebp/default.webp'
    )) {
        throw new RuntimeException('缺少預設頭像');
    }

    echo "\nJSON 遊戲：" . count($games) . " 款\n";
    echo "JSON 會員：" . count($users) . " 位\n";
    echo "初始擁有權：$ownershipCount 筆\n";
    echo "圖片檔案檢查通過。\n";
    echo "會員欄位與身份衝突檢查通過。\n";
    echo "遊戲欄位檢查通過。\n";

    echo "\n連線與資料表預檢通過。\n";

    if (!in_array('--apply', $argv, true)) {
        echo "尚未寫入。正式匯入請加上 --apply。\n";
        exit(0);
    }

    $pdo->beginTransaction();

    $createdAt = gmdate('Y-m-d H:i:s');
    $insertedGames = 0;
    $insertedUsers = 0;
    $insertedOwnerships = 0;

    // ---------- 遊戲 ----------

    $findGame = $pdo->prepare(
        'SELECT title, title_zh FROM games WHERE id = ? FOR UPDATE'
    );

    $insertGame = $pdo->prepare(
        'INSERT INTO games (
        id, title, title_zh, price, rating, release_date,
        developer, publisher, description, detail_description,
        genre, tags, status, cover, banner, screenshots
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($games as $game) {
        $findGame->execute([$game['id']]);
        $existing = $findGame->fetch();

        if ($existing) {
            if (
                $existing['title'] !== $game['title']
                || $existing['title_zh'] !== $game['titleZh']
            ) {
                throw new RuntimeException('既有遊戲 ID 與名稱不一致');
            }

            // 已存在就保留資料庫內容，不覆寫售價等欄位。
            continue;
        }

        $folder = sprintf('game%02d', $game['id']);
        $basePath = "gameDataWebp/$folder/{$folder}_";
        $screenshots = [];

        for ($number = 1; $number <= 5; $number++) {
            $screenshots[] = $basePath
                . sprintf('screenshots%02d.webp', $number);
        }

        $insertGame->execute([
            $game['id'],
            $game['title'],
            $game['titleZh'],
            number_format($game['price'], 2, '.', ''),
            number_format($game['rating'], 1, '.', ''),
            $game['releaseDate'],
            $game['developer'],
            $game['publisher'],
            $game['description'],
            $game['detailDescription'],
            json_encode($game['genre'], JSON_THROW_ON_ERROR),
            json_encode($game['tags'], JSON_THROW_ON_ERROR),
            json_encode($game['status'], JSON_THROW_ON_ERROR),
            $basePath . 'cover.webp',
            $basePath . 'banner.webp',
            json_encode($screenshots, JSON_THROW_ON_ERROR),
        ]);

        $insertedGames++;
    }

    // ---------- 會員 ----------

    $findUser = $pdo->prepare(
        'SELECT id, username, email
     FROM users
     WHERE id = ? OR username = ? OR email = ?
     FOR UPDATE'
    );

    $insertUser = $pdo->prepare(
        'INSERT INTO users (
        id, username, email, password_hash,
        display_name, avatar, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($users as $user) {
        // 交易內再次確認身份，避免預檢後資料已改變。
        $findUser->execute([
            $user['id'],
            $user['username'],
            $user['email'],
        ]);

        $existingUsers = $findUser->fetchAll();

        if ($existingUsers) {
            foreach ($existingUsers as $existing) {
                if (
                    (int) $existing['id'] !== $user['id']
                    || strtolower($existing['username'])
                    !== strtolower($user['username'])
                    || strtolower($existing['email'])
                    !== strtolower($user['email'])
                ) {
                    throw new RuntimeException('既有會員身份衝突');
                }
            }

            // 不重設既有會員的密碼、名稱、頭像。
            continue;
        }

        // PHP 8.2 的 PASSWORD_DEFAULT 使用 bcrypt，
        // 不接受超過其有效輸入長度的種子密碼，避免靜默截斷。
        if (strlen($user['password']) > 72) {
            throw new RuntimeException('種子密碼超過目前雜湊方案的長度限制');
        }

        $passwordHash = password_hash(
            $user['password'],
            PASSWORD_DEFAULT
        );

        $insertUser->execute([
            $user['id'],
            $user['username'],
            $user['email'],
            $passwordHash,
            $user['displayName'],
            "usersDataWebp/{$user['id']}/user{$user['id']}.webp",
            $createdAt,
            $createdAt,
        ]);

        $insertedUsers++;
    }

    // ---------- 初始擁有權 ----------

    $findOwnership = $pdo->prepare(
        'SELECT source
     FROM user_games
     WHERE user_id = ? AND game_id = ?
     FOR UPDATE'
    );

    $insertOwnership = $pdo->prepare(
        'INSERT INTO user_games (
        user_id, game_id, source, order_item_id, created_at
    ) VALUES (?, ?, ?, NULL, ?)'
    );

    foreach ($users as $user) {
        foreach (array_unique($user['purchasedGames']) as $gameId) {
            $findOwnership->execute([$user['id'], $gameId]);

            if ($findOwnership->fetch()) {
                // 包含後來購買的紀錄，一律保留，不改成 import。
                continue;
            }

            $insertOwnership->execute([
                $user['id'],
                $gameId,
                'import',
                $createdAt,
            ]);

            $insertedOwnerships++;
        }
    }

    $pdo->commit();

    echo "\n匯入成功：\n";
    echo "新增遊戲：$insertedGames 款\n";
    echo "新增會員：$insertedUsers 位\n";
    echo "新增擁有權：$insertedOwnerships 筆\n";
    echo "未建立任何歷史訂單。\n";

} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    fwrite(
        STDERR,
        "預檢失敗，請確認連線、資料表、JSON 與圖片檔案。\n"
    );

    exit(1);
}

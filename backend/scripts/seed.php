<?php

// 本檔是初始資料匯入工具，只在命令列手動執行，不是每次開站都會執行。
// 預設只有檢查：php backend/scripts/seed.php；明確加 --apply 才開始寫入。
// 來源是 src/data 的 games.json、users.json，圖片須先放到 backend/public/media。
// 流程：確認資料庫 → 檢查資料／圖片／身份衝突 → 決定是否套用 → 交易匯入。
// 已存在的相同會員、遊戲與擁有權會保留，不會靠重跑 seed 清除測試資料或重設密碼。
// 註解中的交易表示「一起成功才確認」，rollback 則撤銷此次尚未提交的資料。

declare(strict_types=1);

// 只允許命令列執行。
// PHP_SAPI 表示 PHP 從哪種環境執行；cli 是終端機。
// 若有人經瀏覽器開啟就回覆 404 並 exit 結束，不能把匯入工具當公開 API。
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// 只載入資料庫連線函式，不會因 require 就開始匯入；下面 try 才實際建立連線。
require_once dirname(__DIR__) . '/src/database.php';

try {
    $pdo = connectDatabase();

    // SELECT DATABASE() 問目前連到哪個資料庫，fetchColumn() 只取第一欄的值。
    // 必須正好是 lifecharge 才繼續，避免把初始資料寫到別的資料庫。
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($database !== 'lifecharge') {
        fwrite(STDERR, "請確認本機設定的 database 是 lifecharge。\n");
        // 以失敗狀態結束命令列工作，和正常預檢不套用時 exit(0) 的成功狀態不同。
        exit(1);
    }

    echo "目前資料庫：lifecharge\n";
    echo "開始唯讀預檢，不會寫入資料。\n\n";

    // 逐張查 COUNT(*) 計算資料列數量。表名只用固定清單，不接受外面輸入。
    // 目前仍是唯讀；echo 只是把結果印到終端機。
    $tables = [
        'games',
        'users',
        'orders',
        'order_items',
        'user_games',
    ];

    foreach ($tables as $table) {
        // 表名只來自上面的固定清單。
        // query 執行固定 COUNT(*)，fetchColumn 取計數一欄；這些筆數供預檢確認，不改任何表。
        $count = $pdo
            ->query("SELECT COUNT(*) FROM `$table`")
            ->fetchColumn();

        echo $table . '：' . $count . " 筆\n";
    }

    // 從 backend/scripts 往上兩層找到專案根目錄；因此在不同目錄啟動仍可找到 JSON。
    $projectRoot = dirname(__DIR__, 2);

    // file_get_contents() 讀文字檔；json_decode() 把 JSON 文字轉成 PHP 資料。
    // true 表示使用陣列，512 是最大巢狀深度，JSON_THROW_ON_ERROR 讓壞 JSON 直接報錯。
    $games = json_decode(
        file_get_contents($projectRoot . '/src/data/games.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // 會員 JSON 和遊戲用同樣方式讀成陣列；原始 JSON 仍是 Demo 資料，匯入時另外產生資料庫密碼雜湊。
    $users = json_decode(
        file_get_contents($projectRoot . '/src/data/users.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // 檔案是合法 JSON 仍可能不包含清單；先確認兩份都是陣列，再逐筆驗證內容。
    if (!is_array($games) || !is_array($users)) {
        throw new RuntimeException('JSON 格式錯誤');
    }

    // 準備遊戲編號對照與擁有權計數；用編號當索引可快速判斷重複或不存在。
    $gameIds = [];
    $ownershipCount = 0;

    // 逐款檢查遊戲，foreach 是「清單中的每一項都執行一次」。
    // ?? null 表示欄位沒提供時先用 null；不合法就 throw，直接跳到最下方錯誤處理。
    foreach ($games as $game) {
        // 取每款編號，沒欄位用 null；非整數、小於等於 0 或 $gameIds 已有的都直接拒絕。
        $id = $game['id'] ?? null;

        if (!is_int($id) || $id <= 0 || isset($gameIds[$id])) {
            throw new RuntimeException('遊戲 ID 無效或重複');
        }

        // 驗證通過就用編號記 true；之後檢查會員擁有遊戲時能用 isset 快速查存在。
        $gameIds[$id] = true;

        // 用欄位名與最大字數建立檢查表，避免標題等超過資料庫可存長度。
        // mb_strlen 算字元，strlen 算 bytes；TEXT 的儲存上限要用 bytes 檢查。
        $textFields = [
            'title' => 200,
            'titleZh' => 200,
            'developer' => 200,
            'publisher' => 200,
        ];

        foreach ($textFields as $field => $maxLength) {
            // 欄位名稱由上面固定檢查表提供，逐個取值，不是動態執行使用者程式。
            $value = $game[$field] ?? null;

            if (
                // 必須文字、trim 後非空、長度不超限；|| 任一條件失敗就 throw，終止整份匯入。
                !is_string($value)
                || trim($value) === ''
                || mb_strlen($value, 'UTF-8') > $maxLength
            ) {
                throw new RuntimeException("遊戲 $id 的 $field 格式錯誤");
            }
        }

        // 描述是 MySQL TEXT，以 bytes 上限檢查；名稱則用 UTF-8 字元長度，避免中文長度算錯。
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

        // 金額與評分允許數字，另外檢查上下限與小數位數，避免 MySQL 自動截斷。
        // abs 是取絕對值，round 是四捨五入，用很小的容許誤差處理浮點表示。
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

        // 用指定的年月日格式讀日期，再輸出比對原文字，避免不存在日期被自動調整後放行。
        $releaseDate = $game['releaseDate'] ?? null;

        // 只對文字解析日期；條件 ? A : B 在非文字時直接用 false，不把數字硬轉日期。
        $date = is_string($releaseDate)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $releaseDate)
            : false;

        // 格式化後必須跟原文字完全一致；例如不合法的 2 月 30 日若被自動調整也不能放行。
        if (!$date || $date->format('Y-m-d') !== $releaseDate) {
            throw new RuntimeException("遊戲 $id 的發售日期格式錯誤");
        }

        // 每個標籤欄位都要是連續索引清單，每項非空文字；JSON 可存很多形狀，不能只看欄位型別叫 JSON。
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

        // 遊戲編號補兩位決定資料夾，例如 4 → game04；媒體路徑與前端命名規則保持一致。
        $folder = sprintf('game%02d', $id);

        // 先檢查封面、橫幅與五張截圖真的存在；檔名 %02d 表示將數字補成 01、02。
        $suffixes = ['cover', 'banner'];

        for ($number = 1; $number <= 5; $number++) {
            $suffixes[] = sprintf('screenshots%02d', $number);
        }

        foreach ($suffixes as $suffix) {
            // 組合完整磁碟路徑，is_file 確認檔案存在，不是只確認資料庫有路徑文字就算成功。
            $path = $projectRoot
                . "/backend/public/media/gameDataWebp/$folder/"
                . "{$folder}_{$suffix}.webp";

            if (!is_file($path)) {
                throw new RuntimeException('遊戲圖片缺少');
            }
        }
    }

    // 接著檢查會員。三份對照表分別避免編號、帳號、Email 重複，大小寫轉小寫後比較。
    $userIds = [];
    $usernames = [];
    $emails = [];

    // 預先準備會員查詢：:id 等是稍後填入資料的位置，不直接把輸入接到 SQL。
    // 檢查撞編號、帳號或 Email 的既有資料，不能把另一位會員誤認為同一人。
    $findExistingUser = $pdo->prepare(
        'SELECT id, username, email
     FROM users
     WHERE id = :id
        OR username = :username
        OR email = :email'
    );

    foreach ($users as $user) {
        // 每位初始會員固定沿用 JSON 編號，不能隨便讓自動編號改掉，否則擁有權與頭像對應會變。
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

        // 帳號與 Email 用小寫作衝突比較；不把密碼也轉小寫，密碼應保持原內容。
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

        // 在預檢階段查可能撞編號／帳號／Email 的資料；同編號不同身份就拒絕，不覆蓋別人的帳戶。
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

        // 確認這位初始會員的實際頭像檔存在；預設頭像也會在後面另外檢查。
        $avatar = $projectRoot
            . "/backend/public/media/usersDataWebp/$id/user$id.webp";

        if (!is_file($avatar)) {
            throw new RuntimeException('會員頭像缺少');
        }

        if (!is_array($user['purchasedGames'] ?? null)) {
            throw new RuntimeException('擁有遊戲格式錯誤');
        }

        // 同會員重複寫到 JSON 的遊戲先去重；每一組會員與遊戲算一筆初始擁有權。
        foreach (array_unique($user['purchasedGames']) as $gameId) {
            if (!is_int($gameId) || !isset($gameIds[$gameId])) {
                throw new RuntimeException('擁有紀錄包含無效遊戲');
            }

            // 每一組「會員擁有一款遊戲」算一筆；同款被不同會員擁有會分開計算，不是商品總數。
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

    // $argv 是命令列傳入的參數清單；找不到 --apply 就在此 exit(0) 正常結束。
    // 下面才是會寫資料的區段，不能因預檢通過就自動寫入。
    if (!in_array('--apply', $argv, true)) {
        echo "尚未寫入。正式匯入請加上 --apply。\n";
        exit(0);
    }

    // 開始資料庫交易，尚未 commit 前的寫入可在出錯時 rollback。
    // 後面的 FOR UPDATE 會鎖住查到的列，避免同時修改既有資料造成判斷不一致。
    $pdo->beginTransaction();

    // 統一保存本次 UTC 建立時間；三個 inserted 計數器從 0 起算，只計這次真的新增的資料。
    $createdAt = gmdate('Y-m-d H:i:s');
    $insertedGames = 0;
    $insertedUsers = 0;
    $insertedOwnerships = 0;

    // ---------- 遊戲 ----------

    // 先查同編號商品是否已存在，再準備新增指令；? 的順序要對應 execute 傳入的資料順序。
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

        // 編號存在時核對中英文名稱；確定同一款就 continue 跳下一款，保留資料庫後來改的價格等欄位。
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
        // 只存相對媒體路徑，例如 gameDataWebp/game04/game04_；前端 Service 會接上媒體根網址。
        $basePath = "gameDataWebp/$folder/{$folder}_";
        $screenshots = [];

        for ($number = 1; $number <= 5; $number++) {
            $screenshots[] = $basePath
                . sprintf('screenshots%02d.webp', $number);
        }

        // 真正寫入遊戲：價格轉固定小數位，類型／標籤等陣列編成 JSON 存入欄位。
        // 圖片只存媒體相對路徑，不把圖片二進位內容塞進資料庫。
        $insertGame->execute([
            $game['id'],
            $game['title'],
            $game['titleZh'],
            // 價格固定兩位、評分一位小數文字；標籤與截圖陣列另外 json_encode 才存進 JSON 欄位。
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

    // 交易內再次查會員是否衝突，預檢之後到現在可能已出現新資料。
    // 既有同一會員直接 continue 跳到下一位，不覆寫後來變更的名稱或密碼。
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

        // 交易內再次核對已存在會員，同人就略過不重設密碼；和預檢都要做，因中途可能有其他寫入。
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
        // 目前 bcrypt 最多有效 72 bytes，過長拒絕，不能讓密碼被默默截斷後照樣宣布匯入成功。
        if (strlen($user['password']) > 72) {
            throw new RuntimeException('種子密碼超過目前雜湊方案的長度限制');
        }

        // password_hash 產生單向密碼雜湊，無法用來還原原密碼；登入用 password_verify 比對。
        // PASSWORD_DEFAULT 使用該 PHP 版本的預設方案；本專案目前 PHP 8.2 為 bcrypt。
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

    // 最後匯入初始擁有權。這是從 JSON 搬入，不是假裝當年完成購買，
    // 所以 source 是 import，order_item_id 為 NULL，也不建立歷史訂單。
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

            // 已存在擁有權就保留，包括後來購買的 purchase 來源；不把真購買改標成 import。
            if ($findOwnership->fetch()) {
                // 包含後來購買的紀錄，一律保留，不改成 import。
                continue;
            }

            // 新增 import 擁有權，不填 order_item_id；這是資料搬移，不虛構一筆歷史訂單。
            $insertOwnership->execute([
                $user['id'],
                $gameId,
                'import',
                $createdAt,
            ]);

            // 只在 INSERT 成功後加一；重跑跳過既有資料，因此新增 0 不代表資料消失。
            $insertedOwnerships++;
        }
    }

    // 所有檢查與新增都完成才提交交易，讓這次寫入正式保存。
    // 下面印出的是本次新增筆數，重跑可能全部為 0，不表示資料庫原本是空的。
    $pdo->commit();

    echo "\n匯入成功：\n";
    echo "新增遊戲：$insertedGames 款\n";
    echo "新增會員：$insertedUsers 位\n";
    echo "新增擁有權：$insertedOwnerships 筆\n";
    echo "未建立任何歷史訂單。\n";

// 接住任何流程錯誤；若交易還在就撤銷這次寫入。
// fwrite(STDERR, ...) 將失敗訊息印到終端的錯誤輸出，exit(1) 表示執行失敗。
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

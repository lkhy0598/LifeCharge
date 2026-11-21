<?php

// 本檔提供「查詢會員、確認登入、檢查註冊欄位」的共用工具。
// 會員 API 和購買 API 都會用到，避免每支重寫相同身份檢查。
// PDO 是資料庫連線工具；$pdo 由外面傳入，本檔不自行決定要連哪個資料庫。
// 查詢使用 prepare() 先準備 SQL，再 execute() 分開傳入資料，避免把輸入當查詢指令。

declare(strict_types=1);
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

// 用途：依會員編號取得可回傳前端的會員資料，並附上已擁有遊戲編號。
// 輸入是連線與會員編號；回傳會員陣列，找不到則 null。?array 就是可回陣列或 null。
// SELECT 只列公開欄位，沒有取密碼；AS displayName 把資料庫欄位換成前端名稱。
function findPublicUserById(PDO $pdo, int $userId): ?array
{
    // 第一步先準備固定 SQL；:id 是之後傳值的位置，不把會員編號直接拼成查詢指令。
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

    // 第二步分開提供會員編號再執行；fetch 讀出一位會員，找不到是 false，而不是空會員物件。
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    // 沒找到就 return null，交由呼叫者決定回 401 或其他處理；此函式不直接假造身份。
    if ($user === false) {
        return null;
    }

    // 第三步查該會員擁有權，只選 game_id 並固定排序，避免把全部會員的遊戲都回傳。
    $statement = $pdo->prepare(
        'SELECT game_id
         FROM user_games
         WHERE user_id = :user_id
         ORDER BY game_id'
    );

    $statement->execute(['user_id' => $userId]);

    // PDO 部分數值可能以字串回傳，用 (int) 明確轉整數，讓前端身份比較不因文字／數字不同失敗。
    $user['id'] = (int) $user['id'];
    // fetchAll(FETCH_COLUMN) 只取查詢結果第一欄，得到遊戲編號清單。
    // array_map() 對每一項做相同轉換；fn 是簡短函式，(int) 明確將編號轉成整數。
    $user['purchasedGames'] = array_map(
        static fn($id): int => (int) $id,
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );

    return $user;
}

// 用途：登入時依帳號或 Email 找到編號與密碼雜湊，供 login.php 比對。
// 這份結果含 passwordHash，只在後端使用，不直接回傳網頁。
// 有 @ 視為 Email；SQL 欄位名稱只從固定選項挑選，使用者文字仍分開綁定。
function findLoginUser(PDO $pdo, string $identifier): ?array
{
    // 帳號禁止包含 @，因此可以明確區分 Email 與帳號。
    // 有 @ 選 email，否則 username；這個欄位名只來自兩個固定值，使用者輸入不會變成欄位或指令。
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

    // 將登入 identifier 分開綁定到已準備的查詢，LIMIT 1 只取一位；密碼雜湊只供後端比對。
    $statement->execute([
        'identifier' => $identifier,
    ]);

    $user = $statement->fetch();

    if ($user === false) {
        return null;
    }

    // 回傳登入內部需要的 id 與 passwordHash；不是公開會員回應，login.php 會另外查公開欄位。
    return [
        'id' => (int) $user['id'],
        'passwordHash' => $user['password_hash'],
    ];
}

// 用途：先從後端 Session 確認有合法登入編號，不需要先連資料庫。
// 通過就回傳正整數；不存在或到期時回覆 401。前端送來的會員編號不作為依據。
function requireMemberId(): int
{
    startAppSession();

    // 只從已開啟的後端 Session 取身份；?? null 在沒欄位時用 null，不接受前端自己指定會員。
    $userId = $_SESSION['user_id'] ?? null;

    // 編號不存在、不是整數或小於等於 0 都視為未登入，回 401 並結束，尚未碰資料庫。
    if (!is_int($userId) || $userId <= 0) {
        sendError(
            'UNAUTHENTICATED',
            '請先登入。',
            401
        );
    }

    return $userId;
}

// 用途：除了 Session 已登入，還確認資料庫中會員仍存在。
// 傳入 PDO，回傳完整公開會員；會員已刪除時清除舊身份並要求重新登入。
// 連線故障會發出錯誤，不當作會員自行登出。
function requireAuthenticatedMember(PDO $pdo): array
{
    $userId = requireMemberId();
    // 登入編號合法仍要查資料庫確認會員存在；Session 可能留下已刪除會員的舊身份。
    $user = findPublicUserById($pdo, $userId);

    if ($user === null) {
        // 如果會員已不存在，清 Session 並換編號，再回 401 要求登入；這不是查詢故障的處理方式。
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

// 用途：把註冊輸入整理並檢查格式，通過才回傳帳號、Email、名稱與原密碼。
// $fields 收集各欄位錯誤，一次回給畫面；此函式不新增會員，也不負責查重。
// 密碼不能 trim，否則會擅自改掉使用者輸入；新會員至少 8 字元，最多 72 bytes。
function validateRegistration(array $input): array
{
    // 準備收集每個錯誤欄位，而不是檢查到第一個就停，讓前端可一次指出多處問題。
    $fields = [];

    // 先確認欄位是文字才去頭尾空白；?? null 避免欄位未提供時直接存取出錯。
    // 條件 ? 結果A : 結果B，這裡不合法的輸入先整理為空字串，接著統一檢查。
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

    // preg_match() 用一組格式規則檢查帳號：從頭到尾只能是指定字元，長度 3～50。
    // 這種用來描述文字格式的寫法稱為正規表示式，不是 SQL。
    // 正規表示式中的 \A／\z 要求整段帳號符合，{3,50} 是長度，字元清單只允許英文數字 _ -。
    if (!preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $username)) {
        $fields['username'] =
            '帳號須為 3～50 字元，只能使用英文字母、數字、底線與連字號。';
    }

    if (
        // Email 要合法格式且不能超過資料庫欄位 bytes；filter_var 的驗證失敗明確等於 false。
        strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $fields['email'] = '請輸入有效的 Email，最多 254 bytes。';
    }

    // mb_strlen(..., UTF-8) 計算文字字元數；strlen() 計算 bytes（儲存大小）。
    // 中文字通常占多個 bytes，因此「8 個字」和「8 bytes」不是同一件事。
    $displayNameLength = mb_strlen($displayName, 'UTF-8');

    // 名稱以字元數檢查 1～100，和 Email／密碼的 bytes 上限不同，不把每個中文字當一個 byte。
    if ($displayNameLength < 1 || $displayNameLength > 100) {
        $fields['displayName'] = '顯示名稱須為 1～100 字元。';
    }

    if (
        // 新密碼至少 8 個字元、最多 72 bytes；另外拒絕空白、特殊空白與空字元，避免輸入與雜湊處理不一致。
        mb_strlen($password, 'UTF-8') < 8
        || strlen($password) > 72
        || preg_match('/[\s\p{Z}\x{FEFF}]/u', $password) !== 0
        || str_contains($password, "\0")
    ) {
        $fields['password'] =
            '密碼至少 8 字元、最多 72 bytes，且不可包含空白或空字元。';
    }

    // 有任何欄位錯誤就回 400 並附 fields；沒有錯誤才到最後 return 正規化的四個欄位。
    if ($fields !== []) {
        sendError(
            'VALIDATION_FAILED',
            '請檢查註冊資料。',
            400,
            $fields
        );
    }

    // 驗證完成才交回帳號、Email、名稱與原密碼；這裡不查唯一性，也不執行 INSERT，後續 register.php 才寫入。
    return [
        'username' => $username,
        'email' => $email,
        'displayName' => $displayName,
        'password' => $password,
    ];
}

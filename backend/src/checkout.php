<?php

// 本檔集中處理 Database 報價與成交，不處理畫面或真實刷卡。
// quote.php 呼叫報價函式；purchases.php 呼叫建立／查詢訂單函式。
// 報價暫存在 PHP Session，成功訂單與擁有權才寫 MySQL。
// quoteId 是哪份報價，requestKey 是哪次購買，orderId 是哪筆成交訂單，三者用途不同。
// 函式上的 int／string／array 是要求的數字／文字／資料清單；mixed 表示可能是各種輸入。

declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/session.php';

// 定義購買用的錯誤資料類別，沿用 PHP 的 RuntimeException。
// __construct 是建立錯誤時執行的函式；readonly 欄位建立後不能隨意重新賦值。
// errorCode 供前端程式判斷，message 給人閱讀，httpStatus 預設 409 表示操作條件衝突。
class CheckoutException extends RuntimeException
{
    // 建立購買錯誤時同時保存 errorCode、message、httpStatus；沒有另填狀態就用 409。
    // public readonly 表示外面可讀但不能事後改值，: int／string 指定資料類型。
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 409
    ) {
        // 先交給原本 RuntimeException 建立基本錯誤訊息，外層才能用 getMessage() 讀出。
        parent::__construct($message);
    }
}

// 用途：檢查 1～100 個遊戲編號，回傳排序後的清單。
// 不接受重複、非整數或超範圍編號；$seen 用編號當索引記住已看過的項目。
// 只檢查格式，商品是否真的存在要由後面的查詢確認。
function validateCheckoutGameIds(mixed $value): array
{
    if (
        // 商品編號必須是連續索引的清單，長度 1～100；不是任意物件，也不是空購物車。
        !is_array($value)
        || !array_is_list($value)
        || count($value) < 1
        || count($value) > 100
    ) {
        sendError(
            'INVALID_GAME_IDS',
            '請提供 1～100 款遊戲的編號清單。',
            400
        );
    }

    // 以編號為索引保存已看過的項目，例如 $seen[4] = true；只是檢查，不寫資料庫。
    $seen = [];

    // foreach 逐個取編號；每項要正整數、在 MySQL unsigned INT 範圍內，且沒重複。
    foreach ($value as $id) {
        if (
            !is_int($id)
            || $id <= 0
            || $id > 4294967295
            || isset($seen[$id])
        ) {
            sendError(
                'INVALID_GAME_IDS',
                '遊戲編號必須是有效且不重複的正整數。',
                400
            );
        }

        // 這個編號驗證通過就記住，後面 isset 能快速發現重複；最後 sort 固定由小到大。
        $seen[$id] = true;
    }

    // 固定排序，方便後續比較報價與依序鎖定資料。
    sort($value, SORT_NUMERIC);

    return $value;
}

// 用途：將金額文字轉成整數分，例如 12.30 變成 1230，方便精確比較。
// preg_match 檢查格式並將整數、小數放進 $matches；str_pad 補足兩位小數。
// 不直接用浮點數加錢，避免像 0.1 + 0.2 這類二進位表示誤差。
function moneyToCents(string $amount): int
{
    // 對應 DECIMAL(10,2)：最多八位整數、兩位小數。
    if (!preg_match(
        '/\A([0-9]{1,8})(?:\.([0-9]{1,2}))?\z/',
        $amount,
        $matches
    )) {
        throw new RuntimeException('Invalid monetary amount.');
    }

    // 小數不足兩位在右邊補 0，例如 .5 變 .50；整數部分乘 100 再加分，得到精確整數。
    $fraction = str_pad($matches[2] ?? '', 2, '0');

    return ((int) $matches[1] * 100) + (int) $fraction;
}

// 用途：將整數分轉回固定兩位小數的文字，例如 1230 變成 12.30。
// intdiv() 取整數商，% 取餘數，%02d 將分補成兩位；超過欄位範圍就發出錯誤。
function centsToMoney(int $cents): string
{
    // 總價也受 DECIMAL(10,2) 上限限制，不是每款合法就代表合計一定放得進欄位。
    if ($cents < 0 || $cents > 9999999999) {
        throw new RuntimeException('Monetary amount is out of range.');
    }

    return sprintf(
        '%d.%02d',
        intdiv($cents, 100),
        $cents % 100
    );
}

// 用途：讀商品現價、檢查會員尚未擁有，回傳商品清單、總價與幣別。
// 只查資料，不新增訂單；商品不存在或已擁有就整份拒絕，不偷偷少買其中幾款。
// PDO 是外面傳進來的連線工具，userId 是已驗證的會員編號。
function loadCheckoutQuoteData(
    PDO $pdo,
    int $userId,
    array $gameIds
): array {
    $gameIds = validateCheckoutGameIds($gameIds);

    // 查幾個商品就產生幾個 ?，例如 [1,2] 對應 IN (?,?)。
    // array_fill 建清單，implode 用逗號串成文字；編號另由 execute 傳入，不直接拼成 SQL。
    $placeholders = implode(
        ',',
        array_fill(0, count($gameIds), '?')
    );

    // 先查商品現價；WHERE id IN 的位置符號已固定數量，實際編號另外提供，ORDER BY 固定順序。
    $statement = $pdo->prepare(
        "SELECT id, title, title_zh, price
         FROM games
         WHERE id IN ({$placeholders})
         ORDER BY id"
    );

    // 將整份編號清單依序綁到 ?，fetchAll 讀出存在商品；回傳數目不足就整單拒絕。
    $statement->execute($gameIds);
    $games = $statement->fetchAll();

    if (count($games) !== count($gameIds)) {
        sendError(
            'GAMES_NOT_FOUND',
            '清單包含不存在的遊戲，請更新購物車後重新確認。',
            409
        );
    }

    $statement = $pdo->prepare(
        "SELECT game_id
         FROM user_games
         WHERE user_id = ?
           AND game_id IN ({$placeholders})"
    );

    // 擁有權查詢的第一個 ? 是會員，後面 ...$gameIds 把商品編號展開，順序要與 SQL 的 ? 對應。
    $statement->execute([
        $userId,
        ...$gameIds,
    ]);

    // 只要查到任一已擁有商品就拒絕整份報價；不悄悄少掉該商品後繼續下單。
    if ($statement->fetchColumn() !== false) {
        sendError(
            'GAMES_ALREADY_OWNED',
            '清單包含已擁有的遊戲，請更新購物車後重新確認。',
            409
        );
    }

    // 準備公開商品明細與整數分累計；不能直接加 PDO 讀出的 DECIMAL 字串或靠浮點數算錢。
    $items = [];
    $totalCents = 0;

    foreach ($games as $game) {
        // PDO 的 DECIMAL 保持字串，直接轉為整數分。
        $unitCents = moneyToCents((string) $game['price']);
        // += 是把這款單價分數加到原累計；最後轉固定兩位小數文字回傳。
        $totalCents += $unitCents;

        $items[] = [
            'gameId' => (int) $game['id'],
            'title' => $game['title'],
            'titleZh' => $game['title_zh'],
            'unitPrice' => centsToMoney($unitCents),
        ];
    }

    return [
        'items' => $items,
        'totalAmount' => centsToMoney($totalCents),
        'currency' => 'TWD',
    ];
}

// 用途：把查好的價格保存成有到期時間的報價，回傳讓使用者確認。
// 先核對 Session 身份、讀取有效秒數，清理過期報價，再限制最多保留 20 份有效報價。
// 成功時更新會員活動時間；報價尚未成交，不寫 orders 或 user_games。
function createCheckoutQuote(
    PDO $pdo,
    int $userId,
    array $gameIds
): array {
    startAppSession();

    // 防止內部呼叫時混用其他會員編號。
    // 即使內部呼叫傳入 userId，也要和目前後端 Session 一致，不能替另一位會員建立報價。
    if (($_SESSION['user_id'] ?? null) !== $userId) {
        throw new RuntimeException('Checkout member mismatch.');
    }

    // 讀報價有效秒數，只接受正整數；設定無效不能當永久報價。
    $lifetime = appConfig()['checkout']['quote_lifetime_seconds'] ?? null;

    if (!is_int($lifetime) || $lifetime <= 0) {
        throw new RuntimeException('Invalid quote lifetime.');
    }

    // 取這個 Session 原有報價，沒有則空清單；這些不是全網站共用訂單資料。
    $quotes = $_SESSION['checkout_quotes'] ?? [];

    if (!is_array($quotes)) {
        throw new RuntimeException('Invalid quote storage.');
    }

    $now = time();

    // 移除過期報價，不影響仍有效的報價。
    // 逐份驗證並移除過期報價；unset 只刪 Session 內這項，不刪任何 MySQL 訂單。
    foreach ($quotes as $id => $quote) {
        if (
            !is_array($quote)
            || !is_int($quote['expiresAt'] ?? null)
        ) {
            throw new RuntimeException('Invalid quote record.');
        }

        if ($quote['expiresAt'] <= $now) {
            unset($quotes[$id]);
        }
    }

    // 避免同一 session 無限制累積報價。
    // 有效報價已達 20 份就回 429，避免同一 Session 無限制累積資料；到期清理後才計算。
    if (count($quotes) >= 20) {
        sendError(
            'TOO_MANY_QUOTES',
            '有效報價過多，請稍後再試。',
            429
        );
    }

    // 現在才讀現價與擁有權；成功後保存這份後端資料，不採用前端回報單價。
    $data = loadCheckoutQuoteData($pdo, $userId, $gameIds);

    // time() 是伺服器目前秒數；加上有效秒數得到 expiresAt。
    // bin2hex(random_bytes(32)) 產生安全隨機的 64 字元報價編號，不使用流水號猜測。
    $createdAt = time();
    $expiresAt = $createdAt + $lifetime;
    $quoteId = bin2hex(random_bytes(32));

    // 後端保留完整報價，之後只接受 quoteId 來找回，不讓瀏覽器自行指定成交價格。
    // array_column(..., gameId) 取出每個商品的 gameId；gmdate 回傳 UTC 的可讀時間。
    $quotes[$quoteId] = [
        'userId' => $userId,
        'gameIds' => array_column($data['items'], 'gameId'),
        'items' => $data['items'],
        'totalAmount' => $data['totalAmount'],
        'currency' => $data['currency'],
        'createdAt' => $createdAt,
        'expiresAt' => $expiresAt,
    ];

    // 把新增報價後的整份清單存回 Session；touchAppSession 更新本次有效活動時間。
    $_SESSION['checkout_quotes'] = $quotes;

    // 成功建立報價算有效操作，更新會員活動時間。
    touchAppSession();

    return [
        'userId' => $userId,
        'quoteId' => $quoteId,
        // 外部以 UTC 的年月日時分秒表示到期，Z 表示 UTC；Session 內仍用整數秒數做比較。
        'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
        'items' => $data['items'],
        'totalAmount' => $data['totalAmount'],
        'currency' => $data['currency'],
    ];
}

// 用途：確認購買識別碼是 16～64 個允許的字元，再回傳原文字。
// 識別碼讓同一次購買可以重試／查結果，本身不是密碼，也不能取代會員驗證。
function validatePurchaseRequestKey(mixed $value): string
{
    if (
        !is_string($value)
        || !preg_match('/\A[A-Za-z0-9_-]{16,64}\z/', $value)
    ) {
        sendError(
            'INVALID_REQUEST_KEY',
            '購買識別碼格式不正確。',
            400
        );
    }

    return $value;
}

// 用途：用「目前會員 + 購買識別碼」查一筆成功訂單，找不到回 null。
// 再讀 order_items 組商品明細，使用成交時保存的名稱與價格，不使用遊戲現價。
// ?array 表示結果可能是訂單陣列，也可能是 null；此函式不建立訂單。
function findPurchaseByRequestKey(
    PDO $pdo,
    int $userId,
    string $requestKey
): ?array {
    $statement = $pdo->prepare(
        'SELECT
            id,
            request_key,
            total_amount,
            currency,
            status,
            created_at
         FROM orders
         WHERE user_id = :user_id
           AND request_key = :request_key'
    );

    $statement->execute([
        'user_id' => $userId,
        'request_key' => $requestKey,
    ]);

    // 以會員與 requestKey 限定讀原訂單，找不到回 null；不允許只靠 key 讀別人的訂單。
    $order = $statement->fetch();

    if ($order === false) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT game_id, title, title_zh, unit_price
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY game_id'
    );

    $statement->execute([
        'order_id' => $order['id'],
    ]);

    $items = [];

    // 讀成交明細並逐項整理；歷史單價與標題來自 order_items，不再 join games 的現在值。
    foreach ($statement->fetchAll() as $item) {
        $items[] = [
            'gameId' => (int) $item['game_id'],
            'title' => $item['title'],
            'titleZh' => $item['title_zh'],
            'unitPrice' => (string) $item['unit_price'],
        ];
    }

    return [
        // BIGINT 編號以字串回傳，避免 JavaScript 整數精度問題。
        // BIGINT 可能大於 JS 安全整數，因此訂單編號用字串；商品編號範圍較小仍用整數。
        'orderId' => (string) $order['id'],
        'requestKey' => $order['request_key'],
        'items' => $items,
        'totalAmount' => (string) $order['total_amount'],
        'currency' => $order['currency'],
        'status' => $order['status'],
        'createdAt' => str_replace(' ', 'T', $order['created_at']) . 'Z',
    ];
}

// 用途：從 Session 找出這位會員的報價，確認格式、身份與有效期限。
// 通過回傳報價資料；失敗發出 CheckoutException，讓外層回覆對應業務錯誤。
// 到期時間是後端建立的秒數，與目前伺服器時間比較，不依使用者電腦時鐘決定。
function requireCheckoutQuote(
    mixed $quoteId,
    int $userId
): array {
    if (
        !is_string($quoteId)
        || !preg_match('/\A[a-f0-9]{64}\z/', $quoteId)
    ) {
        throw new CheckoutException(
            'INVALID_QUOTE_ID',
            '報價識別碼格式不正確。',
            400
        );
    }

    startAppSession();

    if (($_SESSION['user_id'] ?? null) !== $userId) {
        throw new CheckoutException(
            'UNAUTHENTICATED',
            '登入身份已變更，請重新登入。',
            401
        );
    }

    // 透過 quoteId 找回後端保存報價；若曾登出或 Session 到期，前端還有編號也不等於報價仍有效。
    $quote = $_SESSION['checkout_quotes'][$quoteId] ?? null;

    if (!is_array($quote)) {
        throw new CheckoutException(
            'QUOTE_NOT_FOUND',
            '報價已失效，請重新取得報價並確認。'
        );
    }

    if (($quote['userId'] ?? null) !== $userId) {
        throw new CheckoutException(
            'QUOTE_MEMBER_MISMATCH',
            '這份報價不屬於目前會員。',
            403
        );
    }

    if (!is_int($quote['expiresAt'] ?? null)) {
        throw new RuntimeException('Invalid quote expiry.');
    }

    // 以後端現在時間決定期限，到期就拒絕；不能用瀏覽器改時間延長報價。
    if ($quote['expiresAt'] <= time()) {
        throw new CheckoutException(
            'QUOTE_EXPIRED',
            '報價已過期，請重新取得報價並確認。'
        );
    }

    if (
        !is_array($quote['gameIds'] ?? null)
        || !array_is_list($quote['gameIds'])
        || $quote['gameIds'] === []
        || !is_array($quote['items'] ?? null)
        || count($quote['items']) !== count($quote['gameIds'])
        || !is_string($quote['totalAmount'] ?? null)
        || ($quote['currency'] ?? null) !== 'TWD'
    ) {
        throw new RuntimeException('Invalid stored quote.');
    }

    return $quote;
}

// 用途：交易內鎖住會員資料列，讓遵循相同規則的購買依序處理。
// FOR UPDATE 是資料庫鎖定讀取，其他衝突交易需要等待這次 commit／rollback。
// 這不是鎖住整個網站；必須已在交易中，且會員仍存在。
function lockCheckoutMember(PDO $pdo, int $userId): void
{
    // FOR UPDATE 的鎖必須在交易裡才有正確生命週期；忘了先 beginTransaction 就發出錯誤。
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Checkout transaction is required.');
    }

    $statement = $pdo->prepare(
        'SELECT id FROM users WHERE id = :id FOR UPDATE'
    );

    // 鎖定目前會員那一列；另一個同樣先鎖這位會員的購買會等待，不是把所有會員都鎖住。
    $statement->execute(['id' => $userId]);

    if ($statement->fetchColumn() === false) {
        throw new CheckoutException(
            'UNAUTHENTICATED',
            '會員已不存在，請重新登入。',
            401
        );
    }
}

// 用途：正式寫入前再核對商品、擁有權、現價、總價與期限。
// 報價後其他請求可能已購買或改價，所以要在鎖定讀取後再次驗證。
// 成功回傳成交資料，失敗發出錯誤；此函式本身還不新增訂單。
function verifyLockedCheckoutQuote(
    PDO $pdo,
    int $userId,
    array $quote
): array {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Checkout transaction is required.');
    }

    // 以後端報價保存的商品清單再次查詢，不接受確認購買時重新送另一份前端商品清單。
    $gameIds = $quote['gameIds'];

    $placeholders = implode(
        ',',
        array_fill(0, count($gameIds), '?')
    );

    // 按固定順序鎖定遊戲，取得目前價格。
    $statement = $pdo->prepare(
        "SELECT id, title, title_zh, price
         FROM games
         WHERE id IN ({$placeholders})
         ORDER BY id
         FOR UPDATE"
    );

    $statement->execute($gameIds);
    $games = $statement->fetchAll();

    if (count($games) !== count($gameIds)) {
        throw new CheckoutException(
            'GAMES_NOT_FOUND',
            '清單包含不存在的遊戲，請更新購物車後重新確認。'
        );
    }

    // 使用鎖定讀取，確認最新擁有權。
    $statement = $pdo->prepare(
        "SELECT game_id
         FROM user_games
         WHERE user_id = ?
           AND game_id IN ({$placeholders})
         FOR UPDATE"
    );

    $statement->execute([$userId, ...$gameIds]);

    if ($statement->fetchColumn() !== false) {
        throw new CheckoutException(
            'GAMES_ALREADY_OWNED',
            '清單包含已擁有的遊戲，請更新購物車後重新確認。'
        );
    }

    // 以遊戲編號建立價格對照，後面逐款比較資料庫現價；漲價或降價都要求重新確認。
    $quotedPrices = [];

    foreach ($quote['items'] as $item) {
        // 把報價整理成「遊戲編號 → 整數分」對照，後面用編號精確比價，不依清單順序猜商品。
        $quotedPrices[$item['gameId']] = moneyToCents(
            $item['unitPrice']
        );
    }

    $items = [];
    $totalCents = 0;

    foreach ($games as $game) {
        $id = (int) $game['id'];
        $unitCents = moneyToCents((string) $game['price']);

        // 現價與報價不同就 PRICE_CHANGED，漲價、降價都需重新確認；不能擅自用新價成交。
        if (($quotedPrices[$id] ?? null) !== $unitCents) {
            throw new CheckoutException(
                'PRICE_CHANGED',
                '商品價格已更新，請重新取得報價並確認。'
            );
        }

        $totalCents += $unitCents;

        $items[] = [
            'gameId' => $id,
            'title' => $game['title'],
            'titleZh' => $game['title_zh'],
            'unitPrice' => centsToMoney($unitCents),
        ];
    }

    // 逐項比完仍核對總額，發現報價內部不一致就停止交易，不沿用可疑數字。
    if ($totalCents !== moneyToCents($quote['totalAmount'])) {
        throw new RuntimeException('Quote total mismatch.');
    }

    // 等待資料庫鎖期間，也可能剛好超過期限。
    if ($quote['expiresAt'] <= time()) {
        throw new CheckoutException(
            'QUOTE_EXPIRED',
            '報價已過期，請重新取得報價並確認。'
        );
    }

    return [
        'items' => $items,
        'totalAmount' => centsToMoney($totalCents),
        'currency' => 'TWD',
    ];
}

// 用途：完成一次購買；傳入連線、會員、報價與購買識別碼，回傳訂單。
// 步驟是開始交易 → 鎖會員 → 查重 → 驗證報價 → 寫三張表 → 確認後提交。
// 交易表示這些寫入要一起成功；中途任何例外就 rollback，撤銷這次尚未提交的寫入。
// 相同會員相同 requestKey 已有訂單時回傳原訂單，不再新增。
function completeCheckoutPurchase(
    PDO $pdo,
    int $userId,
    string $quoteId,
    string $requestKey
): array {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $quoteId)) {
        throw new CheckoutException(
            'INVALID_QUOTE_ID',
            '報價識別碼格式不正確。',
            400
        );
    }

    try {
        // 一次購買交易從這裡開始；後面出錯會走 catch 撤銷本次未提交的所有寫入。
        $pdo->beginTransaction();

        // 同一會員的購買依序處理。
        lockCheckoutMember($pdo, $userId);

        // 先查既有結果，避免重複建立訂單。
        // 先查原訂單，才檢查報價是否過期：已經成交的購買不應因後續報價過期而消失。
        // 只有 Session 仍保有這份報價時，才可比較它是否挪作不同商品或單價；不是永久內容指紋。
        $existing = findPurchaseByRequestKey(
            $pdo,
            $userId,
            $requestKey
        );

        // 已成功的相同購買優先回原單；就算現在報價已過期，也不能把已成交訂單當不存在。
        if ($existing !== null) {
            // 取同 Session 仍保存的報價做內容比對；若已沒有這份報價，只能回原訂單，沒有永久 fingerprint 欄位可比。
            $candidate = $_SESSION['checkout_quotes'][$quoteId] ?? null;

            // 若這次提供另一份仍保存在 session 的報價，
            // 必須與原成交商品及單價一致。
            if (is_array($candidate)) {
                // 只取商品編號與單價分數來比，不因名稱顯示差異就創造另一張單；array_map 對每項執行同樣轉換。
                $requestedItems = array_map(
                    static fn(array $item): array => [
                        $item['gameId'],
                        moneyToCents($item['unitPrice']),
                    ],
                    $candidate['items']
                );

                $existingItems = array_map(
                    static fn(array $item): array => [
                        $item['gameId'],
                        moneyToCents($item['unitPrice']),
                    ],
                    $existing['items']
                );

                // 同 key 挪來買不同商品或單價要 409 拒絕，不能默默用舊 key 建另一單。
                if ($requestedItems !== $existingItems) {
                    throw new CheckoutException(
                        'REQUEST_KEY_REUSED',
                        '此購買識別碼已用於其他內容，請重新確認訂單。'
                    );
                }
            }

            $pdo->commit();

            touchAppSession();

            return $existing;
        }

        // 確定是新單才檢查有效報價，再做鎖定後商品與擁有權的最終驗證。
        $quote = requireCheckoutQuote($quoteId, $userId);

        $data = verifyLockedCheckoutQuote(
            $pdo,
            $userId,
            $quote
        );

        // 進到這裡代表這次是新購買，且報價已通過最終驗證。接著保存 UTC 成交時間並寫資料。
        $now = gmdate('Y-m-d H:i:s');

        $statement = $pdo->prepare(
            'INSERT INTO orders (
                user_id,
                request_key,
                total_amount,
                currency,
                status,
                created_at
             ) VALUES (
                :user_id,
                :request_key,
                :total_amount,
                :currency,
                :status,
                :created_at
             )'
        );

        // 正式新增 orders：會員來自已驗證身份，總價來自後端驗證結果，不採用瀏覽器付款金額。
        $statement->execute([
            'user_id' => $userId,
            'request_key' => $requestKey,
            'total_amount' => $data['totalAmount'],
            'currency' => 'TWD',
            'status' => 'completed',
            'created_at' => $now,
        ]);

        // 取出剛新增的 orders 編號，接著每個商品明細都用它連回同一筆訂單。
        // 資料表關聯靠這些編號維持，不靠會員名稱或遊戲標題猜測。
        $orderId = $pdo->lastInsertId();

        // 預先準備每款商品的明細 INSERT，迴圈中再填值執行；準備 SQL 本身還沒有新增明細。
        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (
                order_id, game_id, title, title_zh, unit_price
             ) VALUES (
                :order_id, :game_id, :title, :title_zh, :unit_price
             )'
        );

        // 準備新增會員擁有權，透過 order_item_id 連回成交明細；不能只有訂單卻沒有擁有權。
        $insertOwnership = $pdo->prepare(
            'INSERT INTO user_games (
                user_id, game_id, source, order_item_id, created_at
             ) VALUES (
                :user_id, :game_id, :source, :order_item_id, :created_at
             )'
        );

        // 逐款先寫明細、取出明細 id，再寫擁有權；任一款失敗都撤銷整筆，不保留半套購買。
        foreach ($data['items'] as $item) {
            $insertItem->execute([
                'order_id' => $orderId,
                'game_id' => $item['gameId'],
                'title' => $item['title'],
                'title_zh' => $item['titleZh'],
                'unit_price' => $item['unitPrice'],
            ]);

            // 每新增一筆訂單商品，就取得它的編號，讓 user_games 記住擁有權來自哪筆明細。
            $orderItemId = $pdo->lastInsertId();

            $insertOwnership->execute([
                'user_id' => $userId,
                'game_id' => $item['gameId'],
                'source' => 'purchase',
                'order_item_id' => $orderItemId,
                'created_at' => $now,
            ]);
        }

        $order = findPurchaseByRequestKey(
            $pdo,
            $userId,
            $requestKey
        );

        // 交易內重新讀不到自己剛建立的訂單視為異常，throw 讓 rollback 處理，不回一個假成功單。
        if ($order === null) {
            throw new RuntimeException('Created order could not be loaded.');
        }

        $pdo->commit();

        touchAppSession();

        return $order;
    // Throwable 能接住 PHP 的各種例外／錯誤。若交易尚未提交就撤銷，
    // 再 throw 交給 purchases.php 決定 HTTP 回覆；這裡不把錯誤吞掉當成功。
    } catch (Throwable $error) {
        // 先確認交易尚未結束才 rollback；撤銷這次未提交資料不代表回復流水號，編號跳號仍正常。
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

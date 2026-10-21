<?php

declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/session.php';

class CheckoutException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 409
    ) {
        parent::__construct($message);
    }
}

function validateCheckoutGameIds(mixed $value): array
{
    if (
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

    $seen = [];

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

        $seen[$id] = true;
    }

    // 固定排序，方便後續比較報價與依序鎖定資料。
    sort($value, SORT_NUMERIC);

    return $value;
}

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

    $fraction = str_pad($matches[2] ?? '', 2, '0');

    return ((int) $matches[1] * 100) + (int) $fraction;
}

function centsToMoney(int $cents): string
{
    if ($cents < 0 || $cents > 9999999999) {
        throw new RuntimeException('Monetary amount is out of range.');
    }

    return sprintf(
        '%d.%02d',
        intdiv($cents, 100),
        $cents % 100
    );
}

function loadCheckoutQuoteData(
    PDO $pdo,
    int $userId,
    array $gameIds
): array {
    $gameIds = validateCheckoutGameIds($gameIds);

    $placeholders = implode(
        ',',
        array_fill(0, count($gameIds), '?')
    );

    $statement = $pdo->prepare(
        "SELECT id, title, title_zh, price
         FROM games
         WHERE id IN ({$placeholders})
         ORDER BY id"
    );

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

    $statement->execute([
        $userId,
        ...$gameIds,
    ]);

    if ($statement->fetchColumn() !== false) {
        sendError(
            'GAMES_ALREADY_OWNED',
            '清單包含已擁有的遊戲，請更新購物車後重新確認。',
            409
        );
    }

    $items = [];
    $totalCents = 0;

    foreach ($games as $game) {
        // PDO 的 DECIMAL 保持字串，直接轉為整數分。
        $unitCents = moneyToCents((string) $game['price']);
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

function createCheckoutQuote(
    PDO $pdo,
    int $userId,
    array $gameIds
): array {
    startAppSession();

    // 防止內部呼叫時混用其他會員編號。
    if (($_SESSION['user_id'] ?? null) !== $userId) {
        throw new RuntimeException('Checkout member mismatch.');
    }

    $lifetime = appConfig()['checkout']['quote_lifetime_seconds'] ?? null;

    if (!is_int($lifetime) || $lifetime <= 0) {
        throw new RuntimeException('Invalid quote lifetime.');
    }

    $quotes = $_SESSION['checkout_quotes'] ?? [];

    if (!is_array($quotes)) {
        throw new RuntimeException('Invalid quote storage.');
    }

    $now = time();

    // 移除過期報價，不影響仍有效的報價。
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
    if (count($quotes) >= 20) {
        sendError(
            'TOO_MANY_QUOTES',
            '有效報價過多，請稍後再試。',
            429
        );
    }

    $data = loadCheckoutQuoteData($pdo, $userId, $gameIds);

    $createdAt = time();
    $expiresAt = $createdAt + $lifetime;
    $quoteId = bin2hex(random_bytes(32));

    $quotes[$quoteId] = [
        'userId' => $userId,
        'gameIds' => array_column($data['items'], 'gameId'),
        'items' => $data['items'],
        'totalAmount' => $data['totalAmount'],
        'currency' => $data['currency'],
        'createdAt' => $createdAt,
        'expiresAt' => $expiresAt,
    ];

    $_SESSION['checkout_quotes'] = $quotes;

    // 成功建立報價算有效操作，更新會員活動時間。
    touchAppSession();

    return [
        'userId' => $userId,
        'quoteId' => $quoteId,
        'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
        'items' => $data['items'],
        'totalAmount' => $data['totalAmount'],
        'currency' => $data['currency'],
    ];
}

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
        'orderId' => (string) $order['id'],
        'requestKey' => $order['request_key'],
        'items' => $items,
        'totalAmount' => (string) $order['total_amount'],
        'currency' => $order['currency'],
        'status' => $order['status'],
        'createdAt' => str_replace(' ', 'T', $order['created_at']) . 'Z',
    ];
}

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

function lockCheckoutMember(PDO $pdo, int $userId): void
{
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Checkout transaction is required.');
    }

    $statement = $pdo->prepare(
        'SELECT id FROM users WHERE id = :id FOR UPDATE'
    );

    $statement->execute(['id' => $userId]);

    if ($statement->fetchColumn() === false) {
        throw new CheckoutException(
            'UNAUTHENTICATED',
            '會員已不存在，請重新登入。',
            401
        );
    }
}

function verifyLockedCheckoutQuote(
    PDO $pdo,
    int $userId,
    array $quote
): array {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Checkout transaction is required.');
    }

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

    $quotedPrices = [];

    foreach ($quote['items'] as $item) {
        $quotedPrices[$item['gameId']] = moneyToCents(
            $item['unitPrice']
        );
    }

    $items = [];
    $totalCents = 0;

    foreach ($games as $game) {
        $id = (int) $game['id'];
        $unitCents = moneyToCents((string) $game['price']);

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
        $pdo->beginTransaction();

        // 同一會員的購買依序處理。
        lockCheckoutMember($pdo, $userId);

        // 先查既有結果，避免重複建立訂單。
        $existing = findPurchaseByRequestKey(
            $pdo,
            $userId,
            $requestKey
        );

        if ($existing !== null) {
            $candidate = $_SESSION['checkout_quotes'][$quoteId] ?? null;

            // 若這次提供另一份仍保存在 session 的報價，
            // 必須與原成交商品及單價一致。
            if (is_array($candidate)) {
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

        $quote = requireCheckoutQuote($quoteId, $userId);

        $data = verifyLockedCheckoutQuote(
            $pdo,
            $userId,
            $quote
        );

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

        $statement->execute([
            'user_id' => $userId,
            'request_key' => $requestKey,
            'total_amount' => $data['totalAmount'],
            'currency' => 'TWD',
            'status' => 'completed',
            'created_at' => $now,
        ]);

        $orderId = $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (
                order_id, game_id, title, title_zh, unit_price
             ) VALUES (
                :order_id, :game_id, :title, :title_zh, :unit_price
             )'
        );

        $insertOwnership = $pdo->prepare(
            'INSERT INTO user_games (
                user_id, game_id, source, order_item_id, created_at
             ) VALUES (
                :user_id, :game_id, :source, :order_item_id, :created_at
             )'
        );

        foreach ($data['items'] as $item) {
            $insertItem->execute([
                'order_id' => $orderId,
                'game_id' => $item['gameId'],
                'title' => $item['title'],
                'title_zh' => $item['titleZh'],
                'unit_price' => $item['unitPrice'],
            ]);

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

        if ($order === null) {
            throw new RuntimeException('Created order could not be loaded.');
        }

        $pdo->commit();

        touchAppSession();

        return $order;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

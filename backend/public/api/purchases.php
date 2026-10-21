<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/src/request.php';

requireRequestMethod(['GET', 'POST']);

$method = $_SERVER['REQUEST_METHOD'];

try {
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/checkout.php';

    if ($method === 'POST') {
        requireAllowedOrigin();
        requireCsrfToken();
    }

    requireMemberId();

    if ($method === 'POST') {
        $input = readJsonBody();

        $requestKey = validatePurchaseRequestKey(
            $input['requestKey'] ?? null
        );

        $quoteId = $input['quoteId'] ?? null;

        if (!is_string($quoteId)) {
            sendError(
                'INVALID_QUOTE_ID',
                '請提供報價識別碼。',
                400
            );
        }
    } else {
        $requestKey = validatePurchaseRequestKey(
            $_GET['requestKey'] ?? null
        );
    }

    $pdo = connectDatabase();
    $member = requireAuthenticatedMember($pdo);

    if ($method === 'POST') {
        $order = completeCheckoutPurchase(
            $pdo,
            $member['id'],
            $quoteId,
            $requestKey
        );

        // 成交後重新讀取最新擁有權。
        $member = findPublicUserById($pdo, $member['id']);

        if ($member === null) {
            throw new RuntimeException('Member could not be loaded.');
        }
    } else {
        $order = findPurchaseByRequestKey(
            $pdo,
            $member['id'],
            $requestKey
        );

        if ($order === null) {
            sendError(
                'PURCHASE_NOT_FOUND',
                '尚未查到這次購買的成功訂單。',
                404
            );
        }
    }

    sendSuccess([
        'userId' => $member['id'],
        'order' => $order,
        'purchasedGames' => $member['purchasedGames'],
    ]);
} catch (CheckoutException $error) {
    sendError(
        $error->errorCode,
        $error->getMessage(),
        $error->httpStatus
    );
} catch (Throwable $error) {
    sendError(
        $method === 'POST'
            ? 'PURCHASE_UNAVAILABLE'
            : 'PURCHASE_QUERY_UNAVAILABLE',
        $method === 'POST'
            ? '目前無法確認購買結果，系統將自動確認，請稍候。'
            : '目前無法查詢購買結果，請稍後再試。',
        503
    );
}

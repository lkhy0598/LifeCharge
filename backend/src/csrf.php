<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function rotateCsrfToken(): string
{
    startAppSession();

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;

    return $token;
}

function getCsrfToken(): string
{
    startAppSession();

    $token = $_SESSION['csrf_token'] ?? null;

    if (!is_string($token) || strlen($token) !== 64) {
        return rotateCsrfToken();
    }

    return $token;
}

function requireCsrfToken(): void
{
    startAppSession();

    $expected = $_SESSION['csrf_token'] ?? null;
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (
        !is_string($expected)
        || strlen($expected) !== 64
        || !is_string($provided)
        || strlen($provided) !== 64
        || !hash_equals($expected, $provided)
    ) {
        sendError(
            'CSRF_TOKEN_INVALID',
            '操作驗證已失效，請重新驗證後再試。',
            403
        );
    }
}
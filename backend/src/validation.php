<?php

declare(strict_types=1);

require_once __DIR__ . '/response.php';

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
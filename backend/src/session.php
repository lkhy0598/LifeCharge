<?php

declare(strict_types=1);

function appConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }

    return $config;
}

function startAppSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = appConfig();

    // Session ID 只透過 Cookie 傳遞。
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');

    // 實際登入期限仍由下面的時間檢查決定。
    ini_set(
        'session.gc_maxlifetime',
        (string) $config['session_idle_timeout']
    );

    session_name($config['session_name']);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $config['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Session could not be started.');
    }

    // 尚未登入，不需要檢查會員閒置期限。
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;
    $now = time();

    if (
        !is_int($lastActivity)
        || $lastActivity > $now
        || $now - $lastActivity >= $config['session_idle_timeout']
    ) {
        // 清除身份、CSRF token 與其他過期的 session 資料。
        $_SESSION = [];

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Session could not be renewed.');
        }
    }
}

function touchAppSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Session has not been started.');
    }

    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

function prepareMemberSession(int $userId): array
{
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid member ID.');
    }

    startAppSession();

    $token = bin2hex(random_bytes(32));

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session could not be renewed.');
    }

    // 只準備新身份資料，由呼叫端決定何時套用。
    return [
        'user_id' => $userId,
        'last_activity' => time(),
        'csrf_token' => $token,
    ];
}

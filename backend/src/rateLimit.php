<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function requireRateLimit(string $action): void
{
    $rule = appConfig()['rate_limits'][$action] ?? null;

    if (
        !is_array($rule)
        || !is_int($rule['max_attempts'] ?? null)
        || !is_int($rule['window_seconds'] ?? null)
        || $rule['max_attempts'] < 1
        || $rule['window_seconds'] < 1
    ) {
        throw new RuntimeException('Invalid rate limit configuration.');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('Client address is unavailable.');
    }

    $directory = dirname(__DIR__) . '/storage/rate-limits';

    if (
        !is_dir($directory)
        && !@mkdir($directory, 0700, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('Rate limit directory is unavailable.');
    }

    // 不將原始 IP 或帳號放進檔名。
    $key = hash('sha256', $action . '|' . $ip);
    $handle = @fopen($directory . '/' . $key . '.json', 'c+');

    if ($handle === false) {
        throw new RuntimeException('Rate limit storage is unavailable.');
    }

    $retryAfter = 0;

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Rate limit lock failed.');
        }

        $raw = stream_get_contents($handle);

        if ($raw === false) {
            throw new RuntimeException('Rate limit read failed.');
        }

        $now = time();

        $record = $raw === ''
            ? ['startedAt' => $now, 'count' => 0]
            : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);

        if (
            !is_array($record)
            || !is_int($record['startedAt'] ?? null)
            || !is_int($record['count'] ?? null)
            || $record['startedAt'] < 0
            || $record['count'] < 0
        ) {
            throw new RuntimeException('Invalid rate limit record.');
        }

        $window = $rule['window_seconds'];

        if ($record['startedAt'] > $now) {
            throw new RuntimeException('Invalid rate limit timestamp.');
        }

        if ($now - $record['startedAt'] >= $window) {
            $record = ['startedAt' => $now, 'count' => 0];
        }

        if ($record['count'] >= $rule['max_attempts']) {
            $retryAfter = max(
                1,
                $record['startedAt'] + $window - $now
            );
        } else {
            $record['count']++;

            $json = json_encode($record, JSON_THROW_ON_ERROR);

            if (!rewind($handle) || !ftruncate($handle, 0)) {
                throw new RuntimeException('Rate limit reset failed.');
            }

            if (
                fwrite($handle, $json) !== strlen($json)
                || !fflush($handle)
            ) {
                throw new RuntimeException('Rate limit write failed.');
            }
        }
    } finally {
        // 關閉檔案也會釋放鎖定。
        fclose($handle);
    }

    if ($retryAfter > 0) {
        header('Retry-After: ' . $retryAfter);

        sendError(
            'RATE_LIMITED',
            "操作過於頻繁，請於 {$retryAfter} 秒後再試。",
            429
        );
    }
}
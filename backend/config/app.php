<?php

declare(strict_types=1);

return [
    // 與其他本機 PHP 專案區分 session。
    'session_name' => 'lifecharge_session',

    // 正式會員閒置 30 分鐘後過期。
    'session_idle_timeout' => 1800,

    // 本機目前使用 HTTP；未來部署 HTTPS 時須改為 true。
    'cookie_secure' => false,

    // 允許發出寫入請求的前端來源。
    // 不包含 /LifeCharge/ 路徑。
    'allowed_origins' => [
        'http://localhost:5173',
    ],

        // 登入／註冊限流，兩種操作分開計算。
    'rate_limits' => [
        'login' => [
            'max_attempts' => 20,
            'window_seconds' => 300,
        ],
        'register' => [
            'max_attempts' => 5,
            'window_seconds' => 600,
        ],
    ],
];
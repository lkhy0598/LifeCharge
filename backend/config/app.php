<?php

// 這支 PHP 回傳網站設定清單，由 session.php 的 appConfig() 載入。
// return 是把整組資料交回讀取者；[] 是清單，=> 左邊為設定名稱、右邊為值。
// 此處沒有連線密碼，也不查資料庫；改值後會影響後續 PHP 請求。

declare(strict_types=1);

// 這支設定檔沒有定義函式，而是執行時直接交回陣列；appConfig() 的 require 接收這份結果。
return [
    // 與其他本機 PHP 專案區分 session。
    // 專案 Session Cookie 名稱，改名後原 Cookie 不會被新名稱當作相同 Session，可能需要重新登入。
    'session_name' => 'lifecharge_session',

    // 正式會員閒置 30 分鐘後過期。
    // 單位是秒，1800 就是 30 分鐘；startAppSession 會拿它與最後有效活動時間比較。
    'session_idle_timeout' => 1800,

    // 本機目前使用 HTTP；未來部署 HTTPS 時須改為 true。
    // false 配合目前本機 HTTP；未來部署 HTTPS 必須重新檢查，不能把本機設定直接當公開服務安全設定。
    'cookie_secure' => false,

    // 允許發出寫入請求的前端來源。
    // 不包含 /LifeCharge/ 路徑。
    // Origin 只包含協定、主機與連接埠；開發網址換 port 時，這份清單也要核對。
    // 這不是讓後端公開上網的設定，更不是所有網址都可以放行。
    'allowed_origins' => [
        'http://localhost:5173',
    ],

    // 登入／註冊限流，兩種操作分開計算。
    // 每種操作各有 max_attempts（上限次數）與 window_seconds（計算時間窗秒數）。
    'rate_limits' => [
        // 登入最多 20 次／300 秒，註冊 5 次／600 秒，各自計數；單位是伺服器秒數，不是前端倒數次數。
        'login' => [
            'max_attempts' => 20,
            'window_seconds' => 300,
        ],
        'register' => [
            'max_attempts' => 5,
            'window_seconds' => 600,
        ],
    ],

    // 結帳報價有效 5分鐘，不保證鎖價。
    // 報價存在後端 Session；300 秒後必須重新取得報價，不代表價格會被鎖住 5 分鐘。
    'checkout' => [
        'quote_lifetime_seconds' => 300,
    ],
];

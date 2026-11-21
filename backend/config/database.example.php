<?php

// 這是連線設定的公開範本，不是目前實際使用的帳號密碼。
// 新環境複製為 database.local.php 後填入本機資訊；local 檔案由 Git 忽略。
// return [...] 把這組資料交給 database.php；引號中的是文字，3306 是整數。

declare(strict_types=1);

return [
    // host 指 MySQL 主機；127.0.0.1 是本機，port 是 MySQL 使用的連接埠。
    'host' => '127.0.0.1',
    // 3306 是 MySQL 連線用的埠；網站的 5173／Apache 8080 是不同服務，不要互換。
    'port' => 3306,
    // 填 lifecharge 才會預先選定專案資料庫；這裡刻意保留空白，提醒使用者設定。
    'database' => '',
    // 使用網站專用帳號，別把管理員密碼寫入會上傳 GitHub 的範本。
    'username' => 'YOUR_DATABASE_USER',
    // 這只是佔位文字；真密碼應填到忽略 Git 的 database.local.php，不要改此範本成自己的密碼。
    'password' => 'YOUR_DATABASE_PASSWORD',
    // utf8mb4 是 MySQL 的文字編碼，支援中文與表情符號。
    'charset' => 'utf8mb4',
];
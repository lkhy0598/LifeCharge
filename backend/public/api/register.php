<?php

// 本檔用途：檢查註冊欄位、建立會員與登入 Session；成功回覆 201 表示新增完成。
// 前端透過 vite.config.js 的 /api 轉送來這裡。此處是 HTTP 入口，共用規則放在 backend/src。
// try 是嘗試執行的區段，catch 接住失敗；sendSuccess／sendError 送出統一 JSON 後會結束請求。

// 這是 PHP 的型別檢查設定：有宣告數字或文字的參數時，不隨便互轉。
// 它不會自動檢查 JSON 欄位、會員身份或資料庫內容，後面仍須逐步驗證。
declare(strict_types=1);

// 先關閉直接輸出的 PHP 錯誤文字，避免破壞 JSON 回覆格式；這不會自動修補 PHP 漏洞。
ini_set('display_errors', '0');

// 載入檢查請求的共用工具；dirname(..., 2) 從 public/api 往上兩層找到 backend。
require_once dirname(__DIR__, 2) . '/src/request.php';

// 確認使用者用對 GET／POST 方法。不符合就回覆 405 並結束，不進入下方業務流程。
requireRequestMethod('POST');

// 先準備連線變數，catch 才能判斷是否已有交易；連線前發錯不應對不存在的 PDO 呼叫 rollback。
$pdo = null;

// 進入這次操作的主要流程。require_once 只載入共用函式定義，不表示每個工具已開始查詢。
// 下方任一步 throw 會跳到對應 catch；sendSuccess／sendError 則直接結束這次 HTTP 請求。
try {
    require_once dirname(__DIR__, 2) . '/src/request.php';
    require_once dirname(__DIR__, 2) . '/src/csrf.php';
    require_once dirname(__DIR__, 2) . '/src/database.php';
    require_once dirname(__DIR__, 2) . '/src/user.php';
    require_once dirname(__DIR__, 2) . '/src/rateLimit.php';

    // 確認寫入請求來自 app.php 允許的前端；只檢查來源，不代替登入與 CSRF 檢查。
    requireAllowedOrigin();
    // 先限制同一來源的請求次數；超過回覆 429，因此格式或 CSRF 錯誤的嘗試也會計數。
    requireRateLimit('register');
    // 核對瀏覽器附上的操作驗證碼與 Session 一致；失效就拒絕，不自動接受原操作。
    requireCsrfToken();

    // 先讀 JSON 再驗欄位，收到的是已整理帳號／Email／名稱與原密碼，不是任意前端物件。
    $input = validateRegistration(readJsonBody());

    // password_hash() 將密碼轉成單向雜湊；資料庫保存這個結果，不保存原始密碼。
    // PASSWORD_BCRYPT 指定方案；之後登入用 password_verify() 檢查。
    $passwordHash = password_hash(
        $input['password'],
        PASSWORD_BCRYPT
    );

    // 用 UTC 保存建立與更新時間；資料庫內容不直接使用使用者電腦提供的時間。
    $now = gmdate('Y-m-d H:i:s');

    // 取得 MySQL 連線工具，後面的查詢才有辦法執行；連不上由 catch 回覆通用錯誤。
    $pdo = connectDatabase();
    // 開始交易：新增會員與後續檢查都完成才 commit；途中出錯就 rollback 撤銷未提交寫入。
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'INSERT INTO users (
            username,
            email,
            password_hash,
            display_name,
            avatar,
            created_at,
            updated_at
         ) VALUES (
            :username,
            :email,
            :password_hash,
            :display_name,
            :avatar,
            :created_at,
            :updated_at
         )'
    );

    // 到這一步才真正 INSERT；預備指令用 :欄位 當位置，這裡另外傳值，避免輸入被解讀成 SQL。
    $statement->execute([
        'username' => $input['username'],
        'email' => $input['email'],
        'password_hash' => $passwordHash,
        'display_name' => $input['displayName'],
        // 新會員只給固定預設頭像，不接受這個註冊入口自行指定伺服器檔案或上傳圖片。
        'avatar' => 'usersDataWebp/default.webp',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // lastInsertId() 取得剛新增會員的流水號，轉整數後查可公開的會員資料。
    $userId = (int) $pdo->lastInsertId();
    $user = findPublicUserById($pdo, $userId);

    if ($user === null) {
        throw new RuntimeException('Created user could not be loaded.');
    }

    // 先準備新登入身份，但等 commit 確認資料庫成功後才放入 $_SESSION，避免登入不存在的會員。
    $newSession = prepareMemberSession($userId);

    $pdo->commit();

    // 資料庫交易成功後，才套用新會員身份。
    // commit 成功後才換成新會員身份；若新增失敗，不會登入一個資料庫沒有的會員。
    $_SESSION = $newSession;

    sendSuccess($user, 201);
// 接住這次流程發生的錯誤，避免把內部 SQL、檔案位置或設定資訊直接回傳。
// 503 代表目前無法完成／確認，前端不能直接當成未登入或未成交。
} catch (Throwable $error) {
    // 只有確實有 PDO 連線且交易還在才撤銷；避免捕捉錯誤時又因未建立連線產生第二個錯誤。
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 資料庫錯誤 1062 表示帳號／Email 撞到唯一限制；轉成前端能理解的 409，而非暴露 SQL。
    // 由資料庫唯一約束處理重複及同時送出的註冊。
    if (
        // PDOException 是資料庫工具發出的錯誤；1062 是 MySQL 唯一欄位重複碼，轉成帳號／Email 已使用提示。
        $error instanceof PDOException
        && (int) ($error->errorInfo[1] ?? 0) === 1062
    ) {
        sendError(
            'ACCOUNT_EXISTS',
            '帳號或 Email 已被使用，請更換後再試。',
            409
        );
    }

    sendError(
        'REGISTER_UNAVAILABLE',
        '目前無法確認註冊結果，請稍後再試。',
        503
    );
}

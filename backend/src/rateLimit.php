<?php

// 本檔限制同一來源 IP 的登入／註冊請求次數，避免短時間大量嘗試。
// 計數存在 backend/storage/rate-limits 的檔案，不寫 MySQL；兩種操作分開計算。
// 來源 IP 是伺服器看到的 REMOTE_ADDR；經本機 proxy 可能是相同 IP，不等於一位會員。

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

// 用途：讀 app.php 的操作次數與時間窗，通過就繼續，超過回覆 429。
// 傳入 login 或 register；每次呼叫都算一次嘗試，不只計算帳密錯誤。
// 儲存或鎖定失敗會發出例外，不在無法計數時假裝檢查通過。
function requireRateLimit(string $action): void
{
    // 用 action 挑 login 或 register 的限流設定；?? null 在沒有這種操作設定時給空值，接著視為設定錯誤。
    $rule = appConfig()['rate_limits'][$action] ?? null;

    if (
        // 設定必須有正整數上限與時間窗，否則停止；不能用壞設定直接當作不限次數。
        !is_array($rule)
        || !is_int($rule['max_attempts'] ?? null)
        || !is_int($rule['window_seconds'] ?? null)
        || $rule['max_attempts'] < 1
        || $rule['window_seconds'] < 1
    ) {
        throw new RuntimeException('Invalid rate limit configuration.');
    }

    // 讀伺服器看到的來源 IP；不採用可被任意填寫的轉送標頭當可信身份。IP 驗證失敗就發出錯誤。
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('Client address is unavailable.');
    }

    // 準備存計數的資料夾；mkdir(..., true) 允許建立中間缺少的目錄。
    // @ 只隱藏該次檔案函式的警告文字；回傳值仍要檢查，失敗不能忽略。
    $directory = dirname(__DIR__) . '/storage/rate-limits';

    if (
        // 資料夾不存在才嘗試建立；建立失敗後再查一次，處理另一請求剛好已建立目錄的情況。
        !is_dir($directory)
        && !@mkdir($directory, 0700, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('Rate limit directory is unavailable.');
    }

    // 不將原始 IP 或帳號放進檔名。
    // 用操作與 IP 算出固定長度的代號當檔名，不直接把 IP 放檔名。
    // fopen 的 c+ 表示可讀寫、不存在則建立，存在時不立刻清掉舊計數。
    $key = hash('sha256', $action . '|' . $ip);
    $handle = @fopen($directory . '/' . $key . '.json', 'c+');

    // fopen 沒開到檔案就不往下讀寫；連計數都無法保存時，不能假裝限流已通過。
    if ($handle === false) {
        throw new RuntimeException('Rate limit storage is unavailable.');
    }

    // 先以 0 表示目前不用等待；超額才改成剩餘秒數，離開 finally 後再回覆 429。
    $retryAfter = 0;

    try {
        // LOCK_EX 表示排他鎖：同一份計數檔同時只讓一個請求修改，避免互相覆蓋。
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Rate limit lock failed.');
        }

        // 取得檔案目前內容，必須在鎖內讀才能和下面的加一／寫回一起受保護。
        $raw = stream_get_contents($handle);

        if ($raw === false) {
            throw new RuntimeException('Rate limit read failed.');
        }

        $now = time();

        // 第一次沒有紀錄就從 0 開始；否則 json_decode() 把 JSON 文字讀回資料。
        // 時間窗結束後下面會重設計數，不需要永久累積以前的嘗試。
        $record = $raw === ''
            ? ['startedAt' => $now, 'count' => 0]
            : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);

        if (
            // 舊紀錄也要檢查時間與次數類型，避免壞 JSON 或遭修改的計數直接被信任。
            !is_array($record)
            || !is_int($record['startedAt'] ?? null)
            || !is_int($record['count'] ?? null)
            || $record['startedAt'] < 0
            || $record['count'] < 0
        ) {
            throw new RuntimeException('Invalid rate limit record.');
        }

        $window = $rule['window_seconds'];

        // 開始時間不能在未來；若超過整個時間窗，下面才重新從 0 計算，而不是每次請求都歸零。
        if ($record['startedAt'] > $now) {
            throw new RuntimeException('Invalid rate limit timestamp.');
        }

        if ($now - $record['startedAt'] >= $window) {
            $record = ['startedAt' => $now, 'count' => 0];
        }

        // 已達上限時只算還要等幾秒；尚未達上限才把次數加一並寫回檔案。
        if ($record['count'] >= $rule['max_attempts']) {
            // 至少回覆 1 秒，或時間窗真正剩餘秒數，避免超額卻要求等待 0 秒。
            $retryAfter = max(
                1,
                $record['startedAt'] + $window - $now
            );
        } else {
            // ++ 將次數加一，接著編成 JSON 寫回同檔案，保持下次請求能看到這次嘗試。
            $record['count']++;

            $json = json_encode($record, JSON_THROW_ON_ERROR);

            // rewind() 把讀寫位置移回開頭，ftruncate(..., 0) 清掉舊內容再寫新紀錄。
            // fflush() 要求把緩衝中的寫入送出，避免只改到記憶體而沒有保存。
            if (!rewind($handle) || !ftruncate($handle, 0)) {
                throw new RuntimeException('Rate limit reset failed.');
            }

            if (
                // 檢查實際寫入長度與 flush 成功；不完整寫入也要失敗，避免錯誤計數造成限制失效。
                fwrite($handle, $json) !== strlen($json)
                || !fflush($handle)
            ) {
                throw new RuntimeException('Rate limit write failed.');
            }
        }
    // finally 是不論成功或發生錯誤都會執行的收尾；先關檔釋放鎖，才回覆限流錯誤。
    } finally {
        // 關閉檔案也會釋放鎖定。
        fclose($handle);
    }

    if ($retryAfter > 0) {
        // 讓前端讀到應等待秒數，配合 429 RATE_LIMITED 顯示提示，不在此用 sleep 卡住請求。
        header('Retry-After: ' . $retryAfter);

        sendError(
            'RATE_LIMITED',
            "操作過於頻繁，請於 {$retryAfter} 秒後再試。",
            429
        );
    }
}
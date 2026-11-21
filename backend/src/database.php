<?php

/**
 * 這支檔案做什麼？
 * 幫其他 PHP 程式連接 MySQL 資料庫。
 *
 * 例如：games.php 要查遊戲、login.php 要查會員、seed.php 要匯入資料，
 * 都需要先連上資料庫，因此共同使用這裡的 connectDatabase()。
 *
 * 其他程式會先用 require_once 載入這支檔案，讓 PHP 知道函式的內容。
 * 載入後還沒有連線；要執行 $pdo = connectDatabase(); 才會開始連線。
 * 成功後，$pdo 就是後續查詢、寫入資料時會用到的連線工具。
 * PDO 是 PHP 提供的資料庫操作工具，這個名稱不是我們自己定義的。
 *
 * 這支檔案只負責連線，遊戲查詢或會員驗證由其他檔案處理。
 * 真實帳號密碼放在 database.local.php，不寫進這支會上傳 GitHub 的程式。
 */

// 要求本檔在有宣告資料類型的地方，嚴格檢查數字、文字等類型。
// 例如：本檔呼叫要求整數的函式時，不直接把文字 '123' 當整數 123 使用。
// 這不會自動檢查資料庫密碼對不對，也不會替設定檔補上缺少的資料。
declare(strict_types=1);

/**
 * 函式名稱：connectDatabase，意思是「連接資料庫」。
 *
 * 怎麼使用？
 * $pdo = connectDatabase();
 * 等號右邊先執行連線，成功後將連線工具放進左邊的 $pdo 變數。
 * 變數可以理解成替資料取的名字，後面就能透過 $pdo 使用這個工具。
 *
 * 需要傳什麼進來？
 * 括號裡不用填資料；主機、帳號、密碼會從本機設定檔讀取。
 *
 * 成功時會得到什麼？
 * 一個已連線的 PDO 工具。下方宣告的「: PDO」就是要求成功時回傳這種工具。
 * 這是 PHP 會檢查的程式宣告，與這些只供閱讀的註解不同。
 *
 * 失敗時會怎樣？
 * 找不到設定檔，或連線、帳號密碼出問題時，會發出錯誤並跳出正常流程。
 * PHP 稱這種錯誤為「例外」；外面的 try／catch 可以接住它，決定要顯示什麼。
 * 例如：網頁 API 可以回傳「後端暫時無法使用」，命令列程式則可印出失敗訊息。
 * 本函式自己不顯示網頁，也不把密碼印出來。
 */
function connectDatabase(): PDO
{
    // 步驟 1：找出本機設定檔的完整位置。
    // __DIR__ 是這支檔案所在的 backend/src，不是終端機目前所在的資料夾。
    // dirname(__DIR__) 往上一層取得 backend，再接上 config/database.local.php。
    // 中間的 . 是 PHP 串接文字的符號；$configPath 用來記住組好的檔案位置。
    // 因此無論從 API 或命令列呼叫，都會找到同一份本機設定。
    $configPath = dirname(__DIR__) . '/config/database.local.php';

    // 步驟 2：確認這個位置真的有設定檔。
    // is_file() 檢查是不是檔案，前面的 ! 表示「不是」，if 表示「如果」。
    // 所以下面這段可讀成：「如果找不到這個檔案，就發出錯誤」。
    // RuntimeException 是 PHP 內建的錯誤類別，代表執行過程遇到問題。
    // new 建立一份錯誤資料，throw 把錯誤交出去；後面的連線步驟不再繼續。
    if (!is_file($configPath)) {
        throw new RuntimeException('Database configuration is missing.');
    }

    // 步驟 3：讀取連線設定，放進 $config。
    // require 會執行指定的 PHP 檔案；設定檔用 return 把一組設定交回來。
    // 這一組有名稱的資料在 PHP 裡叫做陣列，例如 $config['host'] 就是取出主機設定。
    // 其他設定還有 port（連接埠）、charset（文字編碼）、database（資料庫名稱）、
    // username（帳號）、password（密碼）。此處只讀取，不修改設定檔。
    $config = require $configPath;

    // 步驟 4：把「要連去哪裡」組成一段 PDO 看得懂的文字，名稱叫 DSN。
    // $dsn 是我們用來保存這段文字的變數。
    // mysql: 告訴 PDO 要連 MySQL；host 是主機位置，例如本機 127.0.0.1；
    // port 是該服務使用的連接埠，例如 3306；charset 決定傳輸文字使用的編碼。
    // utf8mb4 能表示中文與表情符號，避免傳輸文字時使用不適合的編碼。
    // sprintf() 將後面的值依序放入格式中的位置：%s 放字串，%d 放整數。
    // 例如：mysql:host=127.0.0.1;port=3306;charset=utf8mb4。
    // 此時只在組文字，還沒連線。帳號與密碼在步驟 6 才另外交給 PDO。
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $config['host'],
        $config['port'],
        $config['charset']
    );

    // 步驟 5：指定連上伺服器後，要使用其中哪個資料庫。
    // 同一個 MySQL 伺服器可以有多個資料庫，我們的名稱是 lifecharge。
    // !== '' 表示「不是空字串」，也就是設定有填名稱才執行下面的大括號內容。
    // .= 表示「把右邊字串接到原字串後面」，例如加上 ;dbname=lifecharge。
    // 未指定名稱時仍可連到 MySQL 伺服器，但沒有預先選定資料庫。
    if ($config['database'] !== '') {
        $dsn .= ';dbname=' . $config['database'];
    }

    // 步驟 6：真的開始連接 MySQL，成功後把連線工具交回呼叫這個函式的程式。
    // new PDO(...) 建立工具並嘗試連線；return 表示把成功建立的工具交回去。
    // 括號內依序放：連線位置、帳號、密碼，最後的 [...] 放連線工具的使用規則。
    // 這些傳進去的資料叫做「參數」，PDO 會依據它們連線。
    // 連線失敗時 PDO 會發出 PDOException，意思是資料庫工具遇到了錯誤。
    // 每次呼叫 connectDatabase() 都會建立新的工具，不會自動沿用上次的連線。
    return new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            // 規則 1：資料庫操作失敗時，發出可被 catch 接住的錯誤。
            // SQL 是查詢或修改資料庫的指令；錯誤不能被忽略，否則後面可能誤認成功。
            // PDO:: 表示使用 PDO 已定義好的設定名稱；=> 左邊是選項，右邊是選擇的值。
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // 規則 2：查到一筆資料時，預設用資料欄位的名稱存取它。
            // 例如查到 title 欄位，就用 $row['title'] 讀遊戲名稱。
            // FETCH_ASSOC 是 PDO 對這種結果格式取的名稱，不必用第 0 欄猜欄位內容。
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // 規則 3：關閉 PDO 模擬處理查詢參數，改由 MySQL 處理預先準備的查詢。
            // 例如查會員時，先寫「WHERE id = ?」，再另外提供會員編號；
            // 這種先留位置、再交入資料的方式叫做「預備敘述」，使用 prepare() 建立。
            // false 表示關閉模擬。其他程式仍須分開提供查詢指令與輸入資料，
            // 不可把使用者輸入直接接進 SQL，否則仍可能被插入額外指令。
            PDO::ATTR_EMULATE_PREPARES => false,
            // 規則 4：嘗試連接 MySQL 時，設定最多等待 2 秒，避免連不上時一直等。
            // 這個選項叫 ATTR_TIMEOUT；TIMEOUT 就是等待超時。
            // 它不是限制每個查詢只能執行 2 秒，也不是瀏覽器等待 API 回覆的時間。
            PDO::ATTR_TIMEOUT => 2,
        ]
    );
}

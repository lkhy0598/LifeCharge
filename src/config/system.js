// 集中保存前端系統設定，export const 讓其他檔案引用同一個值。
// const 表示這個名稱不能再賦值；這裡只定義設定，不自行倒數或連接後端。

// 啟動畫面最短顯示時間。
// 2000 毫秒 = 2 秒，只控制需要顯示的啟動提示最短時間，不限制整個網站只能載入 2 秒。
// 外面 main.js 引用這個常數才真正啟動計時；在此宣告不會自行讓網站暫停。
export const STARTUP_MIN_DURATION_MS = 2000

// 健康檢查最長等待時間。
// 3000 毫秒 = 3 秒，讓健康檢查／預設 GET 等待有上限，與提示停留時間是不同設定。
export const HEALTH_CHECK_TIMEOUT_MS = 3000

// Database 模式的圖片來源，集中管理。
// Database 圖片會把資料庫的相對路徑接到這個根網址；localhost 是瀏覽者目前電腦。
// 公開 Pages 固定 Demo，不讓一般訪客使用這個本機圖片來源。
// 這是本機後端圖片位置，不是 GitHub Pages 網址；移機或部署後端時要和 Apache 對應一起核對。
export const DATABASE_MEDIA_BASE_URL =
    'http://localhost:8080/lifecharge-backend/media/'
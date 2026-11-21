// 這是健康檢查測試頁的獨立建置設定，不是正式網站入口。
// mergeConfig 將正式 Vite 設定與這裡的覆寫合併，避免重複寫 base 等設定。
// fileURLToPath 把模組檔案網址轉成作業系統檔案路徑；import.meta.url 是本設定檔的位置。

import { fileURLToPath } from 'node:url'
import { mergeConfig } from 'vite'
import appConfig from '../../vite.config.js'

// 先採正式設定，再用這裡覆寫測試建置輸出與入口；不動正式 main.js，也不呼叫資料庫匯入。
export default mergeConfig(appConfig, {
    build: {
        // 測試產物放 dist/phase3-check；emptyOutDir true 會清理指定輸出目錄，請勿改成重要資料夾。
        outDir: 'dist/phase3-check',
        emptyOutDir: true,
        rolldownOptions: {
            // 只把本測試資料夾的 index.html 作為建置入口，供驗證正式 build 固定 Demo。
            input: fileURLToPath(
                // 相對路徑以本設定檔網址為基準，再轉成 Windows／Linux 都能使用的本機路徑。
                new URL('./index.html', import.meta.url)
            ),
        },
    },
})
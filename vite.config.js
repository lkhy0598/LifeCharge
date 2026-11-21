// 本檔設定 Vite 開發與建置方式，執行 npm run dev 或 build 時由 Vite 讀取。
// defineConfig 協助工具辨認設定；vue() 讓 Vite 能編譯 .vue 元件。
// server 區塊只用在 dev，GitHub Pages／preview 不會幫你轉送 PHP。

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  // 公開網站放在 /LifeCharge/ 子路徑，建置圖片與 JS 網址要以它為基準。
  base: '/LifeCharge/',
  // 執行 Vue 編譯外掛，讓 <template>／<script setup>／樣式可被建成瀏覽器使用的檔案。
  plugins: [vue()],
  // 這組只影響開發伺服器；build 後沒有 Vite proxy，因此公開 Demo 不會依此連你的本機 Apache。
  server: {
    watch: {
      // 不監聽後端圖片與 PHP 變動，避免 Windows 複製媒體時的檔案鎖定問題；不會停用 PHP。
      ignored: ['**/backend/**'],
    },
    // proxy 是開發轉送：前端請求同來源 /api，再由 Vite 轉到 Apache 8080，保留 Session 使用方式。
    proxy: {
      '/api': {
        // 所有 /api 請求轉給此 Apache 位址，不是轉 MySQL 3306；MySQL 由 PHP 在後端連。
        target: 'http://127.0.0.1:8080',
        // 轉送時調整目標 Host 標頭，讓 Apache 收到對應主機；它不等於允許所有前端 Origin。
        changeOrigin: true,
        // rewrite 把邏輯網址改成 PHP 實體檔名；正規表示式只匹配允許的端點。
        // 例如 /api/checkout/quote → /lifecharge-backend/api/quote.php，不需要建立 checkout 資料夾。
        rewrite: path => {
          const targetPath = path
            // 第一條先匹配特殊報價路徑；正規表示式的 (?=\?|$) 限定後面是查詢參數或結尾，避免誤改相似路徑。
            .replace(
              /^\/api\/checkout\/quote(?=\?|$)/,
              '/api/quote.php'
            )
            .replace(
              /^\/api\/(health|games|csrf|me|login|logout|register|purchases)(?=\?|$)/,
              '/api/$1.php'
            )

          // 在改好的 PHP 路徑前加 Apache Alias；原本 query 仍保留，供 GET requestKey 查詢。
          return `/lifecharge-backend${targetPath}`
        },
      },
    },
  },
})

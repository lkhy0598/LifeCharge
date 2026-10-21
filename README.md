# LifeCharge

Vue 遊戲商城作品。V1 已完成；V2 正在加入 PHP API 與 MySQL。

## 目前進度

Phase 4：遊戲 API、雙來源遊戲 Service／Store 與畫面圖片適配已完成本輪驗收。

- 已建立五張資料表。
- 已匯入 30 款遊戲、6 位測試會員及 26 筆初始擁有權。
- 初始會員密碼以雜湊保存，未建立虛構訂單。
- 已驗證重複匯入不重複新增。
- 正式網站入口仍使用 Demo JSON 與 sessionStorage；Database 遊戲資料已在獨立測試頁串接驗證。
- 自動模式判斷目前只在獨立測試頁運作；正式網站入口尚未使用。
- 所有付款均為模擬。

## 本機環境

- Node.js 與 npm
- XAMPP：Apache、PHP 8.2.12
- 獨立 MySQL Server 8.4.13
- Apache 使用 8080，MySQL 使用 3306

不使用 XAMPP 附帶的 MariaDB。

## 後端設定

複製 `backend/config/database.example.php`，命名為
`backend/config/database.local.php`，並填寫本機設定：

| 設定 | 值 |
|---|---|
| host | `127.0.0.1` |
| port | `3306` |
| database | `lifecharge` |
| username | `lifecharge_app` |
| password | 本機專用帳號密碼 |
| charset | `utf8mb4` |

`database.local.php` 已由 `.gitignore` 排除，不得提交 Git。
`database.example.php` 僅保存範例，不可填入真實密碼。

應用程式帳號使用 `'lifecharge_app'@'127.0.0.1'`，僅授予
`lifecharge.*` 的 `SELECT`、`INSERT`、`UPDATE` 權限。
建表與帳號管理使用另外的管理員連線，不使用網站帳號執行。

## Apache 對應

在 XAMPP 的 httpd.conf 加入以下設定。
路徑須依專案實際位置調整：

```apache
Alias "/lifecharge-backend/" "C:/Users/Z270/Desktop/LifeCharge/backend/public/"

<Directory "C:/Users/Z270/Desktop/LifeCharge/backend/public">
    Options -Indexes
    AllowOverride None
    Require local
</Directory>
```

Apache 僅公開 backend/public，不公開 config 或 src。
修改 Apache 設定後須重新啟動。

## 啟動順序

1. 在 Windows「服務」啟動 MySQL84。
2. 在 XAMPP 啟動 Apache。
3. 在專案根目錄執行：

```powershell
npm.cmd run dev
```

首頁：

http://localhost:5173/LifeCharge/

健康檢查（經 Vite proxy）：

http://localhost:5173/api/health

若 Vite 使用其他連接埠，請依終端機顯示調整。

直接經 Apache 測試：

http://localhost:8080/lifecharge-backend/api/health.php

## 健康檢查結果

- 連線成功：ok 為 true，databaseConnected 為 true。
- 驗證連線資料庫為 `lifecharge`，並查驗五張表的必要欄位。
- 初始遊戲 ID 1～30 均存在時，`ready` 為 `true`；缺少遊戲時為 `false`。
- 資料表或必要欄位不存在時，由 API 回傳受控的 HTTP 503 錯誤。
- MySQL 停止：PHP 回傳 HTTP 503 與 BACKEND_UNAVAILABLE。
- Apache 停止：proxy 無法取得後端回應，可能顯示 502。
- 服務重新啟動後應恢復正常。

health 僅執行唯讀查詢，不建立或修改資料，也不輸出會員資料。
它目前沒有逐筆驗證所有遊戲內容、索引／約束或重新掃描媒體檔案。
`ready: true` 表示本階段的資料檢查通過，不表示遊戲、會員及購買 API 已全部完成。

## 資料庫初始化

使用 MySQL Workbench 的管理員連線，在全新環境建立資料庫：

```sql
CREATE DATABASE lifecharge
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;
```

僅在空白的 `lifecharge` 資料庫執行 `database/schema.sql`。
該檔案依外鍵順序建立五張表：

1. `games`：遊戲資料與圖片相對路徑。
2. `users`：會員資料與密碼雜湊。
3. `orders`：成功的模擬訂單。
4. `order_items`：訂單商品與成交快照。
5. `user_games`：遊戲擁有權。

既有環境不要重複執行 `CREATE DATABASE` 或 `CREATE TABLE`，
也不要為了重跑而刪除資料庫或資料表。

管理員另外建立專案帳號並授予前述必要權限。
含真實密碼的帳號建立指令不要保存於專案 SQL 或提交 Git。

## 後端圖片

保留原本 `src/assets/images/` 的圖片供 Demo 使用，另外複製：

| 來源 | 後端位置 |
|---|---|
| `src/assets/images/gameDataWebp/` | `backend/public/media/gameDataWebp/` |
| `src/assets/images/usersDataWebp/` | `backend/public/media/usersDataWebp/` |

不要多包一層同名資料夾。每款遊戲包含封面、Banner 與五張截圖。
選取現有測試頭像另複製一份為
`backend/public/media/usersDataWebp/default.webp`，作為新會員預設頭像。

資料庫保存相對於媒體目錄的路徑，例如：

```text
gameDataWebp/game01/game01_cover.webp
usersDataWebp/1/user1.webp
usersDataWebp/default.webp
```

不保存磁碟路徑、localhost、連接埠或 Vite 建置雜湊網址。
目前可直接測試後端圖片：

```text
http://localhost:8080/lifecharge-backend/media/gameDataWebp/game01/game01_cover.webp
```

Vite 已排除 `backend` 的檔案監看，避免複製後端圖片時發生 Windows
`EBUSY` 檔案鎖定錯誤；這不影響 Apache 提供圖片或 API proxy。

## 初始資料匯入

匯入來源為 `src/data/games.json` 與 `src/data/users.json`，
僅限可公開的虛構測試資料，不可將真實會員或密碼加入來源 JSON。
種子工具位於 `backend/scripts/seed.php`，僅允許命令列執行，
不放在 Apache 公開目錄。

以下命令在專案根目錄的 PowerShell 執行。其他安裝位置請調整 PHP 路徑。

只檢查 PHP 語法，不執行程式：

```powershell
& "C:\xampp\php\php.exe" -l backend/scripts/seed.php
```

執行唯讀預檢，不寫入資料：

```powershell
& "C:\xampp\php\php.exe" backend/scripts/seed.php
```

預檢通過後，明確加上 `--apply` 才正式匯入：

```powershell
& "C:\xampp\php\php.exe" backend/scripts/seed.php --apply
```

工具檢查遊戲欄位、圖片、會員身份及擁有的遊戲 ID，
並在寫入時使用單一交易；任一步失敗則取消本次交易。
會員密碼透過 `password_hash()` 轉成雜湊，不以明文存入資料庫。

重跑會跳過已存在資料，不重設會員密碼、不覆蓋既有會員資料、
遊戲售價或後來的購買紀錄。會員身份對應衝突，或既有遊戲 ID
對應不同名稱時停止，不自行合併。此工具不是更新遊戲資料的工具。

初次匯入後的預期筆數：

| 資料表 | 筆數 |
|---|---:|
| games | 30 |
| users | 6 |
| user_games | 26 |
| orders | 0 |
| order_items | 0 |

初始擁有權標記為 `import`，不建立虛構訂單或歷史成交價格。
不匯入瀏覽器 sessionStorage 中後續產生的 Demo 購買紀錄。

## Phase 2 驗收紀錄

- 已確認初始筆數為 30 款遊戲、6 位會員、26 筆擁有權。
- 第二次執行 `--apply`，三種資料的新增數量均為 0。
- 遊戲圖片路徑以 `gameDataWebp/` 開頭，每款含五張截圖。
- 26 筆初始擁有權皆為 `import`，兩張訂單相關表保持空白。
- 已使用 `password_verify()` 確認六位會員的原測試密碼均可比對成功，未輸出密碼或雜湊內容。

可在 Workbench 執行以下唯讀查詢核對筆數：

```sql
SELECT 'games' AS table_name, COUNT(*) AS total FROM lifecharge.games
UNION ALL
SELECT 'users', COUNT(*) FROM lifecharge.users
UNION ALL
SELECT 'user_games', COUNT(*) FROM lifecharge.user_games
UNION ALL
SELECT 'orders', COUNT(*) FROM lifecharge.orders
UNION ALL
SELECT 'order_items', COUNT(*) FROM lifecharge.order_items;
```

Phase 2 完成時 health 僅驗證連線；Phase 3 已加入表結構及初始遊戲檢查，
由查詢結果決定 `ready`，不手動寫死為 `true`。

## Phase 3：模式判斷基礎模組

| 檔案 | 職責 |
|---|---|
| `src/config/system.js` | 啟動畫面最短時間與 health 逾時設定 |
| `src/services/apiClient.js` | GET 請求、JSON 回應驗證、逾時及錯誤分類 |
| `src/services/healthService.js` | 驗證服務識別、API 版本及就緒旗標 |
| `src/stores/systemStore.js` | 單次初始化的模式判斷與狀態管理 |

模式判斷目前僅在獨立測試頁使用，尚未接入 `main.js`。Phase 4 已另外加入 gameStore 的雙來源初始化；會員與購物車仍沿用 Demo 流程。
正常網站仍使用 V1 Demo；測試頁顯示 Database 不代表網站已串接 Database。

### 模式與錯誤規則

- 目前正式建置（包含 preview 與 GitHub Pages）固定 Demo，不探測本機後端。
- Vite 開發環境呼叫 `/api/health`；只有服務識別、版本、格式與資料就緒狀態均符合才判定 Database。
- 啟動時未就緒、後端連線失敗、逾時或無效 health 回應會判定 Demo。
- HTTP 400、401、403、409、429 會進入錯誤狀態，不當成後端離線而降級。
- 模式決定後不持續自動切換；同次初始化的後續呼叫共用內部工作，不重複發送 health 請求。
- `status: ready` 僅代表模式判斷完成，不代表遊戲、會員及購物車初始化完成。
- `status: error` 且 `mode: checking` 代表判斷被錯誤中止，並非仍在背景持續請求。

### 開發環境測試

啟動 Vite，開啟以下測試頁；連接埠依實際輸出調整：

| 網址 | 用途 |
|---|---|
| `http://localhost:5173/LifeCharge/test/phase3/index.html` | 使用實際後端測模式判斷 |
| `http://localhost:5173/LifeCharge/test/phase3/errors.html` | 模擬 401／403／409 |
| `http://localhost:5173/LifeCharge/test/phase3/timeout.html` | 模擬無回應，驗證 3 秒逾時 |

錯誤與逾時頁只在各自頁面暫時替換 fetch，測完恢復，不改動後端或資料庫。
目前已人工驗證：

| 情境 | 結果 |
|---|---|
| 後端與 DB 正常 | `database`，無 Demo 原因 |
| MySQL 停止 | `demo / BACKEND_UNAVAILABLE` |
| Apache 停止 | `demo / HTTP_ERROR` |
| 服務重啟並重新整理 | 恢復 `database` |
| 模擬無回應 | 約 3 秒後 `demo / TIMEOUT` |
| 模擬 401／403／409 | `error`，三個案例通過，不降級 |
| 同時呼叫兩次初始化 | Network 僅一筆 health fetch |
| 正式測試建置 | `demo / static-build` |

Pinia 會包裝 action 回傳的 Promise，因此不能用兩次呼叫結果的
`first === second` 作為是否重複請求的驗收標準；以實際請求數確認。

### 正式環境測試建置

一般網站建置不包含上述獨立測試頁。另用測試設定執行：

```powershell
npm.cmd exec -- vite build --config test/phase3/vite.config.js
npm.cmd exec -- vite preview --outDir dist/phase3-check --port 4174 --strictPort
```

開啟：

```text
http://localhost:4174/LifeCharge/test/phase3/index.html
```

應顯示 `demo / static-build`；在 Network 的 Fetch/XHR 篩選確認不發出 health 請求。
產物放在已忽略的 `dist/phase3-check`，不需要提交 Git。
一般 `npm.cmd run build` 會清理 `dist`，測試產物需要時重新產生。

### 已確認、尚未接入的啟動與暫存設計

- 啟動畫面最短時間由 `STARTUP_MIN_DURATION_MS` 設定，預設 3000ms，之後可調整。
- health 請求逾時由 `HEALTH_CHECK_TIMEOUT_MS` 設定，預設 3000ms，兩者獨立。
- 啟動畫面計時與背景初始化同時開始，不先後累加。
- 顯示「正在判斷運作模式…」及 Demo／Database 的說明；判定後顯示對應模式與載入狀態。
- 公開靜態版本直接顯示 Demo，不假裝探測 Database。
- 最短顯示時間已滿且必要資料就緒，才進入網站；資料失敗顯示錯誤與重試，不因時間已滿就放行。
- 不新增通用 `storage.js`。後續由 cartStore 根據固定模式選擇 key：Demo 為 `lifecharge-cart`，Database 為 `lifecharge-database-cart`。
- Demo 的既有登入與購買 keys 保留；Database 身份及擁有權由後端提供，不互相搬移或合併。
- Phase 4 已修改 gameStore；authStore、cartStore 與上述啟動畫面尚未接入模式判斷，不宣稱已完成實際購物車隔離。

Database 遊戲來源已完成獨立驗收；會員與購買服務仍待 Phase 5～6 完成。
未完成的 Database 服務不可用 Demo 身份或購買流程冒充。

## Phase 4：遊戲 API、Service 與 Store

| 檔案 | 本輪內容 |
|---|---|
| `backend/public/api/games.php` | GET 遊戲清單，轉換數字與 JSON 陣列，失敗回傳通用錯誤 |
| `vite.config.js` | 將開發環境 `/api/games` 轉送至 Apache 的 `games.php` |
| `src/config/system.js` | 集中設定 `DATABASE_MEDIA_BASE_URL` |
| `src/services/gameService.js` | 統一 Demo／Database 遊戲格式及圖片網址，驗證 API 資料 |
| `src/stores/gameStore.js` | 新增非同步 `initialize(mode)`、來源、載入狀態與錯誤；保留搜尋及分類介面 |
| `src/components/GameCard.vue`、`src/components/CartMenu.vue` | 封面直接使用遊戲的 `cover` |
| `src/views/Home.vue`、`src/views/GameDetail.vue` | 直接使用遊戲的 `banner` 與 `screenshots` |
| `src/views/SearchResults.vue`、`src/views/Checkout.vue` | 封面直接使用遊戲的 `cover` |

遊戲圖片解析集中在 Service，以上六個元件不再自行使用 `import.meta.glob` 尋找遊戲圖片。
會員頭像不在這次圖片適配範圍內。

### 資料流程與目前限制

- Demo：JSON → Service 解析前端素材網址 → gameStore → 畫面。
- Database：`GET /api/games` → PHP／MySQL → Service 驗證與組合後端媒體網址 → gameStore → 畫面。
- 資料庫保存媒體相對路徑，保留 `gameDataWebp/` 原資料夾名稱；本機媒體基底為 `http://localhost:8080/lifecharge-backend/media/`。
- gameStore 預設同步建立 Demo 資料，維持現有正式入口可用；明確初始化 Database 的測試頁才會讀取 API。
- 同一個 Store 的初始化共用內部工作，不接受中途改用另一模式。失敗時清空遊戲資料、將來源設為 null、狀態設為 error，並向呼叫端拋出錯誤，不自動回退 Demo。
- 目前初始化失敗後的 Promise 仍保留；再次呼叫不會重新請求。獨立測試透過重新整理重試，正式啟動流程的重試介面仍待設計。
- 正式網站尚未接入 systemStore、自動模式選擇或三秒啟動畫面；登入、購物車暫存及購買仍是 Demo，不代表完整 Database 網站已完成。

### 獨立測試與結果

MySQL84、Apache、Vite 啟動後，開啟以下頁面：

| 網址 | 驗收內容 |
|---|---|
| `http://localhost:5173/LifeCharge/test/phase4/index.html` | 比較兩種來源的遊戲內容、五張截圖結構，顯示各來源第一張封面 |
| `http://localhost:5173/LifeCharge/test/phase4/store.html` | 兩個獨立 Store 的載入、ID 查詢、搜尋及分類 |
| `http://localhost:5173/LifeCharge/test/phase4/components.html` | Database 資料渲染實際 GameCard 的 30 張封面，另以 VImg 顯示第一款橫幅及五張截圖 |

已驗收：

- Demo／Database 各 30 款；Store 狀態 ready、來源正確。
- 字串 ID、未知 ID、中英文搜尋、空搜尋與類型查詢通過；新品 8、暢銷 14、精選 18。
- MySQL 停止後，Store 測試顯示 `passed: false / GAMES_UNAVAILABLE`；此為預期的故障情境，不是自動切回 Demo。重新啟動並重新整理後恢復通過。
- Database 元件頁的 30 張卡片封面、第一款橫幅與五張截圖已人工確認；測試區使用 inert，停用卡片操作。
- 使用者已逐步確認正式 Demo 畫面圖片修改，`npm.cmd run build` 通過。

元件頁沒有掛載完整 Home、GameDetail、CartMenu 或 Checkout；不將它描述為完整 Database 頁面、認證或購買驗收。其餘 29 款的後端橫幅／截圖尚未在此頁逐張目視驗收。
以上測試頁供 Vite dev 使用，一般正式建置不包含它們；Phase 3 的測試建置設定也不包含 Phase 4 測試頁。

後續先完成本階段文件與 Git 檢查，再依使用者授權進入 Phase 5；本輪不新增會員／購買 API、不更動資料表、不提交或推送 Git。

## 建置與部署

```powershell
npm.cmd run build
npm.cmd run preview
```

Vite proxy 只適用於 dev，不適用於 preview 或 GitHub Pages。

GitHub Actions 僅在推送 main 時部署 dist。
feature/v2 的推送不更新公開網站。
PHP 與資料庫不部署到 GitHub Pages。

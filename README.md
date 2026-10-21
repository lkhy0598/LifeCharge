# LifeCharge

Vue 遊戲商城作品。V1 已完成；V2 正在加入 PHP API 與 MySQL。

## 目前進度

Phase 1～6 已完成前述本輪驗收；Phase 7 已整合正式入口並完成多項手動測試，Phase 8 正在整理文件。跨連線並行與公開部署後驗收仍待完成。

程式閱讀教學：[V2 程式導讀](docs/V2程式導讀.md)，包含檔案／函式用途、登入與購買追蹤、斷線恢復、暫存位置及修改指南。詳細的分階段測試紀錄在本文後半部。

- 已建立五張資料表。
- 初始匯入 30 款遊戲、6 位測試會員及 26 筆擁有權；註冊測試另有新增會員，現況不再固定為 6 位。
- 初始會員密碼以雜湊保存，未建立虛構訂單。
- 已驗證重複匯入不重複新增。
- 正式入口已串接模式判斷、遊戲、會員與購物車；dev 依健康檢查選擇 Demo／Database，正式 build／preview 固定 Demo。
- 首次進入或模式改變時顯示最短 2 秒提示，同模式重新整理略過提示；每次仍重新檢查與載入資料。
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
- 不新增通用 `storage.js`。Phase 5 已由 cartStore 根據固定模式選擇 key：Demo 為 `lifecharge-cart`，Database 為 `lifecharge-database-cart`。
- Demo 的既有登入與購買 keys 保留；Database 身份及擁有權由後端提供，不互相搬移或合併。
- 三個 Store 已提供模式初始化；Phase 5 獨立視窗頁依序初始化遊戲、會員及購物車，驗證暫存隔離。正式 main.js 尚未接上 systemStore 與上述啟動畫面。

Database 遊戲與會員已完成本輪獨立驗收；購買服務仍待 Phase 6 完成。
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

Phase 4 已由使用者提交與推送；後續 Phase 5 實作及驗收如下。各階段的 Git 提交與推送仍由使用者操作。

## PHP 檔案分工

`config/` 保存設定，`src/` 提供內部工具，`public/api/` 是瀏覽器能呼叫的端點，`scripts/` 是命令列工具。Apache 僅公開 `backend/public/`。

| 檔案（相對於 backend/） | 用途與呼叫關係 | 資料庫操作 |
|---|---|---|
| `config/app.php` | session 名稱、期限、允許來源及限流參數，由共用工具讀取；不含帳密 | 無 |
| `config/database.example.php` | 提供可提交 Git 的連線範本 | 無 |
| `config/database.local.php` | 本機私有連線設定，由 database.php 讀取；不提交 Git | 僅保存設定 |
| `src/database.php` | connectDatabase() 建立 PDO，供端點及 seed 使用 | 建立連線 |
| `src/session.php` | appConfig()、啟動 session、檢查過期、準備登入 session 資料及提供活動時間工具 | 無，操作 PHP session |
| `src/response.php` | sendSuccess()／sendError() 輸出統一 JSON 並結束請求 | 無 |
| `src/csrf.php` | 產生、取得與驗證 CSRF token，供 csrf 及 POST 端點使用 | 無，操作 PHP session |
| `src/request.php` | 統一 HTTP 方法檢查、驗證 Origin、讀取最多 16 KiB 的 JSON 物件 | 無 |
| `src/user.php` | 會員查詢、要求登入、確認會員存在及註冊欄位驗證；密碼雜湊僅供後端登入比對 | 查詢部分唯讀；欄位驗證不查 DB |
| `src/rateLimit.php` | 依操作及 REMOTE_ADDR 計數，以檔案鎖保護限流紀錄 | 無，寫入 storage/rate-limits |
| `public/api/health.php` | 檢查 DB、必要欄位及初始遊戲是否就緒 | 唯讀 |
| `public/api/games.php` | 取得遊戲清單並轉換回應型別 | 唯讀 |
| `public/api/csrf.php` | GET 取得目前 session 的 CSRF token | 無 |
| `public/api/me.php` | GET 查詢目前身份及已擁有遊戲 | 唯讀 |
| `public/api/login.php` | POST 驗證帳號或 Email 與密碼，建立登入 session | 唯讀 |
| `public/api/logout.php` | POST 清除 session 與 Cookie，購物車不清除 | 無 |
| `public/api/register.php` | POST 新增會員並自動登入，使用預設頭像 | 交易新增 users，另讀取會員資料 |
| `scripts/seed.php` | 命令列預檢、匯入初始會員／遊戲／擁有權 | 預設唯讀，--apply 才寫入 |

例如瀏覽器呼叫 `public/api/csrf.php`，該入口使用 `src/csrf.php` 與 `src/session.php` 取得 token，再由 `src/response.php` 輸出 JSON。相同名稱的入口與工具各有職責，不是兩個公開端點。

## Phase 5：會員與註冊

### API 與驗證順序

Vite dev 將 `/api/csrf`、`/api/me`、`/api/login`、`/api/logout`、`/api/register` 轉送到 Apache 對應 PHP。瀏覽器透過同源 proxy 使用 session Cookie，不另外開啟跨來源 CORS。

| 方法／路徑 | 成功內容 | 注意事項 |
|---|---|---|
| GET /api/csrf | `{ csrfToken }` | 不查詢 DB，不延長登入期限 |
| GET /api/me | 公開會員物件 | 未登入回 401／UNAUTHENTICATED；服務故障回 503，不當成登出 |
| POST /api/login | 公開會員物件 | `{ identifier, password }`；失敗 401／INVALID_CREDENTIALS |
| POST /api/logout | `{ loggedOut: true }` | 清除 session 及 Cookie |
| POST /api/register | 201，公開會員物件 | `{ username, email, displayName, password }`；重複回 409／ACCOUNT_EXISTS |

公開會員欄位為 id、username、email、displayName、avatar、purchasedGames，不含密碼或雜湊。API avatar 為相對路徑，由 authService 組成媒體網址。

登入／註冊依序檢查方法、Origin、限流、CSRF、JSON 與欄位，再進行資料庫操作；登出檢查方法、Origin 與 CSRF。允許來源目前為 `http://localhost:5173`，換 host 或 port 時需更新 app.php；來源不包含 `/LifeCharge/` 路徑。寫入請求缺少 Origin 也會被拒絕。

- 帳號：3～50 個 ASCII 英文字母、數字、底線或連字號，不含 @；Email 使用資料庫唯一約束，帳號與 Email 依現有 collation 比對。
- 顯示名稱：去除一般前後空白後 1～100 字元；Email 由後端驗證，最多 254 bytes。
- 新密碼：至少 8 個字元、最多 72 bytes，不允許空白、分隔空白或空字元，不 trim、不改大小寫；使用 PASSWORD_BCRYPT 雜湊。舊會員登入不要求至少 8 字元，仍受 72 bytes 與空白規則限制。
- 確認密碼只在前端檢查，不送入 API。後端欄位錯誤為 400／VALIDATION_FAILED，附 error.fields；錯誤媒體格式／過大內容另回 415／413。
- 新會員的 purchasedGames 為空，avatar 為 `usersDataWebp/default.webp`；不新增訂單或擁有權。註冊會切換目前的後端登入身份。
- 註冊回應遺失或 503 時不能斷定沒有新增，不自動重送；先查 /me 或確認資料庫。AUTO_INCREMENT 編號可能因失敗嘗試產生間隔，不要求連號。

### Session、CSRF 與限流

- Cookie 名稱 lifecharge_session；只用 Cookie 傳遞 ID、strict mode、HttpOnly、SameSite=Lax、path=/，不設定持久保存期限。本機 HTTP 使用 secure=false，HTTPS 部署須另行調整。
- 後端根據 last_activity 明確檢查 1800 秒期限，不能只依賴 PHP 垃圾回收。登入／註冊成功更新 session ID、活動時間與 CSRF token，清除先前 session 資料。
- /me、/csrf 不更新活動時間。touchAppSession() 已提供，但現有端點尚未呼叫；目前登入後若沒有未來新增的有效活動更新，會於 30 分鐘後過期。Phase 6 須明確決定哪些有效操作延長閒置期限。
- token 為 random_bytes(32) 的 64 字元十六進位字串，保存在後端 session，以 hash_equals() 比對 X-CSRF-Token；登入／註冊成功會更新。
- authService 每次 POST 前取得目前 token，不存入 localStorage／sessionStorage。取得與送出間若身份改變，仍可能遇到 CSRF 403。
- authStore 只對 403／CSRF_TOKEN_INVALID 重新查 /me 並取得有效 token，成功才同步會員並提示重新操作，不重送原 action。重新驗證故障時保留先前會員狀態並拋出錯誤。身份變更與恢復失敗的分支尚未各自模擬驗收。
- 固定時間窗：登入同一 IP 5 分鐘 20 次，註冊 10 分鐘 5 次，兩者分開；允許來源的成功／失敗嘗試皆計入，超額回 429／RATE_LIMITED 與 Retry-After 秒數。
- 限流紀錄自動建立於已忽略且不公開的 `backend/storage/rate-limits/`；使用 REMOTE_ADDR，不信任使用者提供的轉送 IP。Vite proxy 下本機請求通常共用同一 IP，其他公開部署需重新設計代理信任與限流儲存。
- 記錄讀寫或鎖定失敗會由 API 回報服務錯誤，不略過限流。目前不自動刪除過期限流檔案；期限到後於下次請求重設計數。

### 前端整合範圍

- apiClient 保留原 GET 介面並新增 POST JSON／CSRF 標頭；ApiError 保存 fields 及 retryAfter。GET 預設逾時仍使用既有設定，POST 預設 10 秒，不自動重送。
- authService 負責 Database API、回應驗證、頭像網址與 Demo 頭像轉換；authStore 負責身份、操作忙碌與初始化狀態。
- authStore 預設 Demo；initialize(mode) 先確認同模式遊戲 ready，再恢復身份。Database 只用 PHP session／user_games，不讀寫 Demo 登入及購買 keys；Database 模式拒絕原 Demo purchaseGames()。
- Demo 會員資料及 purchasedGames 先複製，避免共用並修改 JSON 匯入物件；Demo 支援帳號或 Email 不分大小寫登入。NavBar、Member 直接使用已解析 avatar。
- LoginDialog 保留同一個視窗，Database 才顯示會員註冊。切換清除密碼與錯誤、保留目的地；關閉重設。提交中鎖定操作，註冊成功自動登入並接續目的地，無目的地前往 /member；長表單可捲動。
- Member 登出等待 API 成功後才清除畫面並回首頁；失敗顯示錯誤。cartStore 初始化使用兩個獨立 key，恢復期間暫停寫回，避免空陣列覆蓋已存清單；登入排除已擁有遊戲，登出保留購物車。
- 正式 main.js 仍沿用 Demo 恢復流程；獨立測試才依序 initialize games → auth → cart。完整 Database 導覽、真正結帳頁及自動模式啟動畫面尚未整合。

### 測試入口及紀錄

以下網址前綴為 `http://localhost:5173/LifeCharge/test/phase5/`，供 Vite dev 使用，普通正式建置不包含測試頁。

| 頁面 | 用途／副作用 |
|---|---|
| `login.html` | 直接測登入、/me、登出、錯誤 CSRF；建立／清除真實後端 session |
| `register.html` | 直接測註冊、自動登入與預設頭像；成功會新增真實 DB 測試會員 |
| `rate-limit.html` | 缺少 CSRF 的請求消耗額度，驗證 429；不新增會員，測後可能需等 5／10 分鐘 |
| `service.html` | 透過 authService 測登入、恢復、登出與故障 |
| `store.html` | 先初始化 Database 遊戲再測 authStore，檢查 Demo 購買被拒絕 |
| `dialog.html` | 真正 LoginDialog、獨立 Pinia／記憶體路由，驗證 Database 視窗；註冊會新增會員 |
| `dialog.html?mode=demo` | Demo 視窗與註冊入口隱藏；使用 Demo 暫存 |
| `csrf-recovery.html` | 只模擬登入 403，其餘 /me、/csrf 使用真實 API；不執行真正登入或占登入額度 |

已由使用者逐步驗收：

- 原會員登入／恢復、註冊自動登入、Email 登入、預設頭像、登出後 /me 401。
- 帳號重複、Email 重複皆 409；密碼含空白回 400 及 password 欄位錯誤。註冊測試已建立新會員，不再用初始 users=6 作現況驗收。
- 錯誤 CSRF 回 403，原登入身份仍可查詢；不允許來源回 403／ORIGIN_NOT_ALLOWED。
- 登入第 21 次、註冊第 6 次回 429，Retry-After 分別 300／600 秒。到期後恢復額度尚未以獨立自動案例驗證。
- 暫設 10 秒驗收過期後 /me 未登入，已恢復 app.php 為 1800 並修正設定語法；/me 與 /csrf 不延長期限。
- 已登入時停止 MySQL，會員查詢回 503／MEMBER_UNAVAILABLE，不轉成 user:null；服務恢復後可查原會員。
- Service／Store、實際視窗、目的地與 Demo 回歸由使用者確認；Database 視窗檢查 otherCartUnchanged。完整雙模式購物車新增／移除／重載的自動測試矩陣尚未建立。
- CSRF 恢復的登入／未登入兩情境皆 passed:true；各只有 login=1、me=1、csrf=2，原登入請求未重送，isBusy 恢復 false。
- npm.cmd run build 通過；PHP 與 JavaScript 語法檢查在逐步開發時執行。這些不等於正式入口 Database 全流程驗收。

### 尚未完成

正式入口自動模式判斷、三秒啟動畫面與必要資料等待、初始化重試介面、完整 Database 頁面整合仍待處理；Database 報價、訂單、購買結果查詢與持久化屬 Phase 6，尚未實作。購買的 CSRF 恢復還需先查原 requestKey，不可直接套用會員操作重送。頭像上傳、驗證信、忘記密碼及管理後台不在本階段。

本輪不變更資料表、不重新執行 seed、不自行提交或推送 Git。Phase 6 已確認初步範圍，購買功能實作前先完成下列 PHP 去重整理。

## PHP 去重整理（Phase 5 後）

依使用者要求，先降低重複邏輯及過細的工具檔拆分，再繼續購買功能。目前 `backend/src/` 共七支工具：database、response、request、session、csrf、user、rateLimit。

- 會員驗證集中於 user.php：requireMemberId() 先檢查 session，requireAuthenticatedMember(PDO) 再確認資料庫中會員存在。me.php 先執行前者，再連 DB，保留未登入時優先回 401 的順序。
- validateRegistration() 從 validation.php 搬至 user.php，register.php 改用既有 user.php；validation.php 已移除。Phase 6 初步建立的 auth.php 已併入 user.php 並移除，沒有成為新的長期工具檔。
- session.php 的 prepareMemberSession() 產生 token、更新 session ID 並回傳新身份資料，但不自行指定 $_SESSION。login.php 直接套用；register.php 先準備、成功 commit 後才套用，保留交易失敗不切換身份的行為。
- request.php 新增 requireRequestMethod()，七個現有 API 全部共用方法檢查。health.php、games.php 也改用 response.php，不再自行組成功／失敗 JSON。
- 不合併瀏覽器 API 入口與內部工具；保留現有網址、回應格式、錯誤碼與資料表。後續報價／訂單邏輯先集中於一支 checkout.php，不預先拆出 orders.php；有實際需要再拆分。
- 已完成七支工具與七支 API 共 14 支 PHP 的語法檢查；使用者確認 me 查詢、登入／登出、錯誤 CSRF、405／Allow 標頭及整理後註冊成功分支正常。登入／註冊的 session 與 token 行為維持原契約。health／games 的 SQL 未修改；最後一次重構後的 health ready 與遊戲 Store 結果尚未另貼獨立驗收輸出。
- 整理過程未執行 schema／seed；使用者依測試步驟另新增註冊測試會員。AUTO_INCREMENT 因重複插入失敗或交易回滾可能跳號，ID 不要求連續，不重排既有編號。

以上為 PHP 去重整理當時的紀錄；後續 Phase 6 進度見下節。

### Phase 6：報價、購買與結果恢復（進行中）

購買流程：`Checkout.vue → purchaseService.js → quote.php / purchases.php → src/checkout.php → MySQL`。會員擁有權由 `authStore.js` 同步，購物車只移除本次成功購買的商品。

| 檔案 | 用途 |
| --- | --- |
| `backend/src/checkout.php` | 共用報價、金額換算、商品與擁有權驗證、訂單交易及重複請求處理。 |
| `backend/public/api/quote.php` | 接收商品編號，回傳後端價格與有效期限；不建立訂單。前端網址為 `/api/checkout/quote`，沒有新增 checkout 資料夾。 |
| `backend/public/api/purchases.php` | POST 建立購買；GET 以購買識別碼查詢目前會員的成功訂單。 |
| `src/services/purchaseService.js` | 呼叫 API、檢查回傳資料格式，保存尚待確認的購買資料；不保存付款卡號。 |
| `src/views/Checkout.vue` | 呈現結帳步驟、送出購買、背景查詢結果與顯示訊息。 |

- 報價有效期目前為 300 秒，由後端判斷。成交前再次檢查價格、擁有權與期限；價格變動或報價過期必須重新確認。
- 一筆成功購買在同一交易寫入 `orders`、`order_items`、`user_games`。相同會員的相同購買識別碼重送時回傳原訂單，避免重複建立。
- 建立報價與完成購買會更新 Session 活動時間；讀取會員、CSRF 或查詢訂單不延長閒置期限。
- 識別碼由程式處理，消費者不需手動操作。送單回覆遺失時保留紀錄，每 3 秒自動查詢；連線恢復事件也會喚醒查詢，不自動再次送單。
- 查到成功訂單後進入完成畫面；明確查不到時停止背景查詢，顯示紅色「尚未完成購買，請再次確認購買。」，由使用者按一般「確認購買」按鈕接續，內部沿用原識別碼。
- 正在確認時顯示藍色提示。訊息統一在步驟下方、結帳卡片上方，沒有訊息時不預留高度。

已驗證：報價、成功購買、重新登入後保留擁有權、相同請求回傳原訂單、識別碼內容衝突、報價過期、價格變動。自動恢復測試共 8 項通過，涵蓋斷線、回覆遺失、會員切換、登入過期、離頁清理與 Demo 隔離：

```powershell
node test/phase6/checkout-recovery.test.mjs
```

`test/phase6/checkout.html` 使用真正的 Checkout 元件，預設阻擋購買 POST；勾選「允許實際建立測試訂單」後才允許寫入資料庫。另有同時送出相同購買的測試按鈕。這些控制只出現在測試頁，不放進正式消費者畫面；重新整理後開關恢復關閉。自動恢復測試使用模擬回應，不寫入資料庫。

2026-10-21 使用者操作驗收：真實 Checkout 送單成功，核對訂單狀態 completed、商品與擁有權；登出再登入仍保留擁有權，已擁有商品不能再次加入。兩個相同請求均回傳訂單 3，SQL 確認只有一筆訂單。暫時在第一筆擁有權寫入後拋出例外，兩個請求均失敗，使用者確認三張表筆數不變，並已移除測試例外、恢復正常程式。最後 8 項恢復測試全部 PASS，Vite 正式建置成功（919 modules，539ms）。

驗收限制：同一 PHP Session 可能使兩個請求依序執行，因此上述測試證明重複請求不重複建單，尚未驗證不同 Session／資料庫連線真正同時寫入。此項留待整合測試。正式 `main.js` 仍使用 Demo，啟動模式判斷與完整 Database 導覽尚待整合；目前不能宣稱整站已完成 Database 模式。後續另整理各函式的用途、呼叫關係、輸入輸出與操作追蹤教學。

### Phase 7：正式入口整合與手動驗收

`src/main.js` 已依序串接模式判斷、遊戲、會員、購物車，身份準備完成後才安裝 Router 並等待初始路由。正式頁面在初始化完成後顯示；載入失敗時由 `src/components/StartupScreen.vue` 顯示錯誤與重新載入按鈕，不在操作途中退回 Demo。

- 本機 dev 每次重新整理都重新檢查後端；正式 build／preview 固定 Demo，不探測訪客的 localhost。
- 分頁暫存 `lifecharge-startup-mode` 只記錄上次成功載入的模式，不省略健康檢查、不快取登入身份。首次進入或模式改變才顯示提示；同模式重新整理不顯示提示、不固定等待。
- 提示最短顯示時間已改為 2000ms（`STARTUP_MIN_DURATION_MS`）；健康檢查逾時仍為 3000ms。首次進入的計時與健康檢查並行；模式改變時從判斷完成後重新計時。
- 同模式重新整理時，初始化期間可能短暫空白；後端失效時也可能先等待健康檢查，再顯示模式改變提示。延遲顯示載入文字僅討論過，尚未實作。
- Demo 的 cart、current-user-id、user-purchases 與 Database cart 分別保存；Database 身份在後端 Session，訂單與擁有權在 MySQL。模式提示紀錄不是安全或身份驗證依據。

2026-10-21 使用者手動確認：正式 Database 結帳成功，會員頁與重新整理保留擁有權；進入 Database 後停止 MySQL，取得報價失敗並留在原模式，恢復服務後可再次取得報價。Demo 與 Database 購買資料切換未混用；會員頁登出後受登入保護。build／preview 在 MySQL 啟動時仍為 Demo，圖片、詳細頁、登入正常；約 390px 手機寬度的首頁、詳細頁、登入／註冊、購物車、結帳及會員頁回報正常。

尚未驗證：跨 Session／資料庫連線真正並行購買；尚未更新公開 main 部署，因此本機 preview 通過不等於公開網站已驗收。本輪未再次回報獨立的 Demo 完整購買後重新整理測試結果，不與已確認的模式隔離測試混為同一項。函式用途與呼叫流程的教學文件留待後續文件整理。

以上為目前進度；前面 Phase 5／6 的「正式入口仍 Demo／尚待整合」描述是當時階段紀錄，以本節為準。

## 建置與部署

```powershell
npm.cmd run build
npm.cmd run preview
```

Vite proxy 只適用於 dev，不適用於 preview 或 GitHub Pages。

GitHub Actions 僅在推送 main 時部署 dist。
feature/v2 的推送不更新公開網站。
PHP 與資料庫不部署到 GitHub Pages。

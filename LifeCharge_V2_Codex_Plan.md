# LifeCharge V2 — Codex 專用改版規格與執行計畫

## 0. Codex 首要指令（請先閱讀）

你正在協助升級一個**已完成 V1、已發布 GitHub Pages** 的 Vue 專案 LifeCharge。

- GitHub Pages：https://lkhy0598.github.io/LifeCharge/#/
- Repository：https://github.com/lkhy0598/LifeCharge
- 本機工作目錄：以 Codex 目前開啟的專案為準，**不要自行猜測路徑**。
- 技術方向：Vue 3、Vite、Vue Router、Pinia、Vuetify；V2 增加 PHP API + MySQL/MariaDB。
- **第一輪只做唯讀盤點、風險分析與修訂計畫；不要直接改檔、安裝套件、建立資料庫、提交 Git 或推送 GitHub。** 等使用者確認後，才逐階段實作。
- 本文件是目標規格，不是對現有程式碼的事實描述；所有檔名、資料格式、路由、登入流程、部署流程都應以本機實際檔案為準。

## 1. 產品目標

在**同一份 Vue 前端程式碼**中支援兩種運作模式，不需要使用者手動切換：

### Database Mode（本機完整模式）
- 本機 PHP API 與 MySQL/MariaDB 可用時，自動啟用。
- 遊戲資料由 API/資料庫提供。
- 可註冊正式會員、登入、登出、讀取會員資料。
- 購物車可以繼續使用 Pinia + sessionStorage；完成購買時由後端驗證並保存訂單/購買紀錄。
- 重新登入後可以取得持久化購買紀錄。

### Demo Mode（公開展示模式）
- GitHub Pages 使用 Demo Mode，不需要 PHP/MySQL。
- 使用 V1 原本的 JSON、Pinia、sessionStorage 與模擬會員流程。
- 保留 V1 已完成的遊戲瀏覽、搜尋、詳細頁、購物車、模擬結帳、會員頁等功能（以實際 V1 為準）。
- 不顯示會員註冊入口，只提供既有 Demo 會員登入；不顯示停用註冊按鈕或額外的 Demo 註冊說明，不得假裝已成功建立正式會員。
- GitHub Pages 的靜態部署**不執行 PHP/MySQL**；PHP/SQL 程式碼仍可放在同一 Repository 供檢視及本機運行。

## 2. 不可破壞的限制

1. 保留目前專案根目錄、`src/`、`public/`、現有相對/絕對圖片路徑與已運作的 Vue Router 設定，除非有實證需要修改。
2. 不把現有 Vue 專案整體搬進 `frontend/`；後端以新增目錄方式整合。
3. 優先沿用 V1 的 Component、頁面、Store、JSON 欄位及購物流程；不做與本次需求無關的重構。
4. 不更改網站視覺設計、RWD 或 UI 文案，除非為新增註冊/狀態提示所必要。
5. 不把密碼、API 密鑰、資料庫憑證、真實會員資料提交 Git；提供 `.env.example` 或安全範例設定。
6. 不將 Demo 明文測試密碼當作正式會員認證方式。正式密碼在 PHP 後端使用 `password_hash()` / `password_verify()`，資料庫只存雜湊。
7. 正式會員的身份及購買權限須由後端驗證；不得只信任前端傳來的 userId、價格或已購買狀態。
8. 正式與 Demo 的 sessionStorage key / 登入狀態要隔離，避免模式切換造成錯誤會員或錯誤購買紀錄。
9. API 的 400/401/403/409 等業務錯誤**不代表資料庫離線**，不能因此切換 Demo 或繞過驗證。
10. 不可在正式購買提交途中因網路錯誤而自動轉為 Demo 結帳；須顯示錯誤並避免重複扣款/重複建立訂單。

## 3. 第一輪：請 Codex 唯讀盤點（尚不實作）

請先確認並報告：

- 實際目錄樹、package.json、Vite 與 Vue Router（特別是 Hash History、GitHub Pages base 設定）。
- 現有 `src/views/`、`src/components/`、`src/stores/`、`src/data/` 的實際檔案與職責。
- 遊戲 JSON 欄位、圖片路徑、遊戲 ID 型別與讀取流程；是否有 getter/action 依賴同步初始化。
- 模擬會員 JSON 欄位、登入登出流程、sessionStorage key、會員購買紀錄及結帳流程。
- GitHub Pages 的部署方法（Actions 或其他），`npm run build` 與路由是否已正常運作。
- 是否已存在 API、PHP、SQL、service 層、註冊畫面或相關測試；避免重複建立。
- 目前 Git 工作樹是否有未提交修改；不得覆蓋使用者工作。

**第一輪交付內容（不要改程式碼）：**

A. 「V1 現況報告」：引用實際檔案路徑及關鍵程式碼位置。
B. 「差異清單」：本規格與現有程式的衝突、需澄清事項。
C. 「最小修改方案」：新增、修改、不動的檔案列表與理由。
D. 「風險清單」：尤其是登入/購買、圖片路徑、GitHub Pages、CORS、API 失敗。
E. 「分階段執行順序」與每階段驗收方法。
F. 如有必須由使用者決定的架構選項，列出選項及建議，**停下等待確認**。

## 4. 建議架構（僅供盤點後修訂）

保留 V1 原有根目錄與前端檔案；依需要新增：

```text
LifeCharge/
├── public/                     # 保留
├── src/
│   ├── components/             # 保留，LoginDialog.vue 內切換登入／註冊表單
│   ├── views/                  # 保留，不新增獨立註冊頁
│   ├── stores/                 # 視現況調整 game/auth/cart，新增 systemStore
│   ├── router/                 # 保留既有路由，不新增 /register 頁面路由
│   ├── data/                   # 保留 V1 JSON
│   └── services/               # 視需要新增 API、game、auth service
├── backend/
│   ├── api/                    # health / games / register / login / me / purchases
│   └── config/                 # 安全的資料庫連線設定
├── database/
│   ├── schema.sql
│   └── seed.sql                # 僅測試資料，勿包含真實帳密
├── index.html
├── package.json
├── vite.config.js
├── .gitignore
└── README.md
```

注意：PHP 放在 `backend/` 不代表 Vite 能執行 PHP。後端須另由 PHP 開發伺服器或 Apache 提供；MySQL/MariaDB 也須啟動。跨來源 API 須處理 Vite proxy 或 CORS。

## 5. 自動模式判斷的設計要求

- **不提供手動切換 UI**。
- GitHub Pages 部署預設固定 Demo Mode；不要對網站訪客的 `localhost` 嘗試資料庫 API。
- 本機開發環境可以先檢查 PHP `health` API（API 應真的檢查 DB，而不只是回傳 PHP 正常）。
- 啟動時要有 `checking` / `database` / `demo` 等狀態，避免檢查未完成就誤判模式。
- 健康檢查應有合理 timeout，並區分連線失敗、服務端故障與業務驗證失敗。
- 模式決定後，避免在登入或購買中途悄悄切換身份來源。斷線時採取明確提示；重新初始化/重新整理後再按規則進入 Demo。
- 前端隱藏註冊入口不是安全控制；後端註冊 API 必須獨立驗證請求。
- 如未來需要正式線上 Database Mode，另以可設定 API Base URL 的部署方式擴充；不要將 `import.meta.env.DEV` 誤認為資料庫連線檢查。

## 6. 資料庫設計方向

請先檢查 V1 JSON 的真實欄位，再規劃 schema，不可硬套欄位。

最低需求：
- `games`：可支援 V1 遊戲資料、分類、狀態、圖片等必要欄位；對多值欄位（genre/tags/screenshots/status）說明使用 JSON 欄位或關聯表的取捨。
- `users`：id、唯一 username/email、password_hash、display_name、created_at 等。
- `orders` / `order_items` 或適當的 `purchases`：記錄會員購買遊戲，具備外鍵、唯一性/重複購買規則、交易一致性。
- 匯入 V1 既有遊戲及 Demo 測試資料；正式會員密碼不可直接匯入明文。
- API 對前端輸出與 V1 Store 相容的資料格式，必要時由 service 層轉換。

## 7. 分階段實作（每階段需獨立驗收）

### Phase 1：環境與後端連線
- 確認本機 PHP 與 MySQL/MariaDB 安裝方式。
- 新增後端最小骨架與健康檢查 API。
- 驗證 Vue 能呼叫 PHP API；說明啟動命令及 CORS/proxy 設定。
- 驗收：API/DB 開啟、關閉時均有可預期結果；V1 不受影響。

### Phase 2：資料庫 schema 與資料匯入
- 設計、建立、匯入遊戲及測試會員資料。
- 驗證資料數量、ID、價格、圖片 URL、關聯完整性。
- 驗收：可透過 SQL 查詢取得正確遊戲資料。

### Phase 3：自動模式與 Service 層
- 新增系統狀態管理、API 健康檢查與 Demo 選擇邏輯。
- GitHub Pages 固定 Demo；本機可用時 Database，不可用時 Demo。
- 驗收：無手動切換，且不混淆身份資料。

### Phase 4：遊戲 API 與 gameStore
- 建立讀取遊戲 API，並將資料取得責任移至 service 層。
- 保留現有 Store getter/action 與各頁面的使用介面，盡量不動 UI。
- 驗收：兩種模式的首頁、搜尋、分類、詳細頁及圖片一致。

### Phase 5：會員註冊/登入
- 沿用 LoginDialog.vue，在同一視窗切換登入／註冊表單與驗證；不新增 Register.vue 或 `/register` 頁面路由。後端 `POST /api/register` 仍保留。
- Database Mode 顯示「會員註冊」按鈕，提供註冊/登入/登出/取得當前會員；Demo Mode 使用 V1 模擬登入，不顯示註冊入口。
- 後端實作唯一帳號/email、輸入驗證、密碼雜湊、安全 session/cookie 或經確認的其他登入機制；處理 CSRF/CORS/cookie 設定。
- 驗收：同視窗切換與手機捲動正常；註冊成功自動登入並接續原目的地；重複帳號正確報錯；Demo 無註冊入口且不發出註冊請求。

### Phase 6：購買紀錄持久化
- 保留 V1 購物車 UI 與暫存流程。
- Database Mode 由後端驗證商品、價格、會員身份，使用交易寫入訂單及項目；處理重複提交。
- Demo Mode 維持 V1 模擬購買與 sessionStorage。
- 驗收：正式會員重新登入可查詢購買紀錄，Demo 流程不受影響。

### Phase 7：整合、故障與安全測試
- 測試正常、API 不通、DB 不通、API timeout、401/409 等業務錯誤。
- 驗證切換前後身份隔離、購買不重複、Router Guard 正確。
- 驗證所有現有頁面、RWD、圖片、build、GitHub Pages 路由。
- 驗收：V1 主要功能無回歸。

### Phase 8：GitHub 與文件
- 提供 `.gitignore`、安全範例設定、schema/seed、README 啟動教學。
- GitHub Repository 包含前端、後端及 SQL 原始碼；GitHub Pages 只部署靜態前端 Demo。
- README 說明兩種模式、所需環境、啟動順序、展示功能、限制與測試步驟。
- 不要自行 push；待使用者檢查後操作。

## 8. Codex 每階段工作規則

每次只執行一個經使用者批准的 Phase。開始前先列出：
1. 本階段要修改/新增的確切檔案。
2. 為什麼要改，以及是否會影響現有路徑/Store/API。
3. 可能的風險與回退方式。

完成後回報：
1. 實際修改檔案與摘要。
2. 執行過的測試、命令與結果；未測試部分明確標註。
3. Demo 與 Database Mode 的驗收狀態。
4. 仍待使用者操作或確認的事項。

除非使用者明確批准，不要進入下一個 Phase。

## 9. 第一輪盤點與目前執行狀態

第 3 節的 V1 唯讀盤點已完成，使用者接著確認了資料表與主要 API 規則，整理於第 10 節。

**目前進度：Phase 1～4 已完成先前驗收；Phase 5 會員 API、雙模式 authStore、登入／註冊視窗及購物車暫存隔離已完成本輪獨立驗收。** 三個 Store 已提供模式初始化，但正式 main.js 仍沿用 Demo，尚未接上自動模式判斷、啟動畫面或完整 Database 頁面／購買流程。此次文件更新不代表批准 Phase 6；後續仍須按使用者授權逐階段進行，不自行安裝、執行資料庫變更、提交或推送 Git。

## 10. 使用者已確認的整合設計（2026-10-21）

本節記錄第一輪盤點後逐項確認的決定；若與前面「建議」內容不同，以本節為準。未列入的細節不代表已獲實作授權。

### 10.1 範圍與模式

- 同一份 Vue 前端支援 Demo / Database，保留既有目錄、頁面、外觀、RWD、Hash Router 與 `/LifeCharge/` base。
- 維持模擬付款；Database 模式的訂單、成交金額及擁有權永久保存於資料庫。不接真實金流，不向後端傳送或保存卡號、有效期限、CVV。
- 不做遊戲管理後台、驗證信、忘記密碼、退款或取消擁有功能。
- 新會員使用預設頭像。「簡單頭像上傳」延後為獨立小階段，不包含在本次核心建置範圍。
- 購物車沿用 Pinia + sessionStorage，不跨裝置保存、不建立購物車資料表。兩種模式隔離；登入後排除已擁有遊戲，登出保留該模式的購物車。
- Demo 與 Database 的登入、購買紀錄、購物車不得混用；正式身份只能由後端 session 驗證。

### 10.2 啟動與服務層

1. GitHub Pages 固定 Demo，不探測訪客的 localhost。
2. 本機顯示初始化狀態，呼叫 `GET /api/health`，等待上限先設為 3 秒。
3. 服務識別、API 版本與回應格式正確，且 PHP、DB、必要資料表與初始遊戲資料就緒，才使用 Database。
4. 無法連線、逾時或尚未就緒時使用 Demo。Phase 1 可先檢查 DB 連線；Phase 2 後補完整就緒檢查。
5. 模式在本次執行期間固定，重新整理才重新判斷。登入或購買中的錯誤不觸發降級。
6. 遊戲載入、身份恢復完成後，才恢復購物車、放行路由與呈現相關畫面，避免誤刪購物車或誤判遊戲不存在。

Service 負責 JSON/API 的讀寫、格式與型別整理；Store 保存畫面狀態。保留現有 gameStore 的同步查詢介面，只讓資料載入非同步。登入、登出、購買呼叫端必須等待結果，不能把 Promise 當作成功值。

健康檢查成功格式：

```json
{
  "ok": true,
  "data": {
    "service": "lifecharge-api",
    "apiVersion": 1,
    "ready": true
  }
}
```

健康檢查不回傳 DB 憑證、SQL 或內部路徑。

### 10.3 遊戲與圖片

- `GET /api/games` 一次回傳完整 30 款，前端繼續搜尋、分類、排序及依 ID 查詳細資料。
- 回應使用既有 camelCase 欄位；ID、price、rating 為數字，多值欄位為陣列，releaseDate 為日期字串。
- Database 模式由後端提供圖片；Demo 保留 `src/assets` 與既有 ID/glob 讀取方式。
- 既有圖片複製到後端公開媒體目錄，不移除原資產。實際結構為 `backend/public/api/`、`backend/public/media/`；連線設定放在公開根目錄外。
- 使用者決定保留資料夾名稱 `gameDataWebp`、`usersDataWebp`；DB 儲存 `gameDataWebp/game01/game01_cover.webp`、`usersDataWebp/1/user1.webp` 等相對路徑，不存磁碟絕對路徑、localhost、連接埠或 Vite 雜湊檔名。新會員預設頭像為 `usersDataWebp/default.webp`。
- Service 依設定的媒體 Base URL 轉成可用網址，元件不自行判斷模式。新會員頭像同樣支援後端網址。
- 初始截圖使用實際五張 WebP；原 games.json 的三張示意 JPG 路徑不得直接當成後端媒體資料。
- Database 遊戲 API 失敗時顯示錯誤及重試，不混入 Demo 資料；圖片失敗保留區域並顯示失敗狀態，不偷偷換成 Demo 圖片。
- 遊戲 ID 不存在沿用既有「找不到這款遊戲」。圖片更新需同步維護兩份資產。

### 10.4 五張資料表草案

以下是已確認的邏輯設計，不是已執行的 SQL。實際 JSON、CHECK、collation 等語法待選定 MySQL/MariaDB 版本後核對。採 InnoDB、utf8mb4；時間以 UTC 儲存。ID 與外鍵型別一致；外鍵限制刪除，不以連鎖刪除清掉歷史資料。

#### games

所有欄位必填；ID 保留 1～30。金額不得為負，評分為 0～5。

| 欄位 | 型別／規則 |
|---|---|
| id | INT UNSIGNED，主鍵 |
| title / title_zh | 各 VARCHAR(200) |
| price | DECIMAL(10,2) |
| rating | DECIMAL(2,1) |
| release_date | DATE |
| developer / publisher | 各 VARCHAR(200) |
| description / detail_description | 各 TEXT |
| genre / tags / status | JSON 格式陣列；status 可為空陣列 |
| cover / banner | 各 VARCHAR(255)，媒體相對路徑 |
| screenshots | JSON 格式，五張截圖相對路徑陣列 |

本次不拆分類／標籤關聯表。API 將 title_zh、release_date、detail_description 轉成原前端欄位。

#### users

所有欄位必填。

| 欄位 | 型別／規則 |
|---|---|
| id | INT UNSIGNED，自動編號主鍵；舊會員保留 1～6 |
| username | VARCHAR(50)，唯一、不區分大小寫 |
| email | VARCHAR(254)，唯一、不區分大小寫 |
| password_hash | VARCHAR(255) |
| display_name | VARCHAR(100)，可重複 |
| avatar | VARCHAR(255)，相對路徑；新會員預設圖片 |
| created_at / updated_at | 各 DATETIME |

- 帳號 3～50 字元，只接受英文字母、數字、底線、連字號，禁止 `@`。
- 顯示名稱 1～100 字元。
- 新密碼至少 8 字元，禁止一般空格、全形空格、Tab、換行等空白字元；區分大小寫，不自動修剪。前後端都驗證。
- 舊會員沿用原帳號、Email、密碼與初始擁有遊戲；密碼以 PHP password_hash 產生雜湊，登入以 password_verify 驗證，不保存明文。
- 匯入前檢查舊密碼是否含空白；有衝突回報，不擅自修改。舊會員不強制套用新密碼長度重設。
- 舊會員 created_at 為匯入時間，不虛構註冊日期。
- purchasedGames 不存 users，從 user_games 整理回 API。

#### user_games

| 欄位 | 型別／規則 |
|---|---|
| user_id | INT UNSIGNED，必填，FK users.id |
| game_id | INT UNSIGNED，必填，FK games.id |
| source | VARCHAR(16)，必填，import / purchase |
| order_item_id | BIGINT UNSIGNED，可 NULL，FK order_items.id，非空值唯一 |
| created_at | DATETIME，必填 |

- 複合主鍵 `(user_id, game_id)`，禁止重複擁有。
- import 必須沒有 order_item_id；purchase 必須有。
- 後端驗證訂單會員、訂單項目遊戲與擁有權一致，單純外鍵不足以保證這個跨表規則。
- 收藏庫及已購買狀態以此表為準。

#### orders

所有欄位必填。

| 欄位 | 型別／規則 |
|---|---|
| id | BIGINT UNSIGNED，自動編號主鍵 |
| user_id | INT UNSIGNED，FK users.id |
| request_key | VARCHAR(64) |
| total_amount | DECIMAL(10,2)，後端計算、不得為負 |
| currency | CHAR(3)，固定 TWD |
| status | VARCHAR(16)，本次只保存 completed |
| created_at | DATETIME |

- `(user_id, request_key)` 唯一。
- 相同 key、相同商品內容回傳原訂單；相同 key、不同內容拒絕。
- 不建立等待付款、退款等本次不需要的訂單狀態。

#### order_items

所有欄位必填。

| 欄位 | 型別／規則 |
|---|---|
| id | BIGINT UNSIGNED，自動編號主鍵 |
| order_id | BIGINT UNSIGNED，FK orders.id |
| game_id | INT UNSIGNED，FK games.id |
| title / title_zh | 各 VARCHAR(200)，成交名稱快照 |
| unit_price | DECIMAL(10,2)，成交單價、不得為負 |

- `(order_id, game_id)` 唯一；每款只能一份，不設數量欄位。
- 後端維護 orders.total_amount 等於項目合計。
- 訂單、項目、擁有權在同一交易寫入，全部成功才提交。

### 10.5 匯入規則

- 匯入既有 games.json 與 users.json 初始資料。現有 Demo 資料必須確定為可公開的虛構測試資料，不得加入真實會員或憑證。
- Demo 明文測試密碼只作公開模擬登入及本機種子輸入，不得作正式認證實作或把真實密碼加入 JSON/SQL。
- 舊擁有權標記 import，不虛構歷史訂單或價格。
- 不搬移瀏覽器 sessionStorage 的 Demo 後續購買紀錄。
- 重複匯入跳過已存在資料並回報，不重設密碼、不覆蓋正式修改或新購買；身份對應衝突停止回報，不自動合併。
- 匯入不是每次啟動網站都執行的工作。

### 10.6 會員 API 與 session

| API | 用途 |
|---|---|
| POST /api/register | 註冊並自動登入，接續原本的站內目的地 |
| POST /api/login | 帳號或 Email + 密碼登入 |
| POST /api/logout | 銷毀後端 session，前端清除正式會員狀態 |
| GET /api/me | 確認 session，回傳會員及 purchasedGames |
| GET /api/csrf | 取得目前 session 的 CSRF token |

- 登入輸入使用 `{ identifier, password }`；註冊使用 `{ username, email, displayName, password }`。
- 註冊新會員 purchasedGames 為空，使用預設頭像。
- 登入及 me 統一回傳 `{ id, username, email, displayName, avatar, purchasedGames }`，不含密碼／雜湊。
- 採 PHP session + HttpOnly cookie，本機優先使用 Vite 同源 proxy；cookie、SameSite、來源驗證與 CSRF 須一併處理。
- Database 登入閒置 30 分鐘過期，由後端明確檢查；登入／註冊成功更新 session ID 與 CSRF token。
- CSRF token 隨 session 有效；前端記憶體保存並在寫入請求附上 `X-CSRF-Token`，使用者不需手動操作。
- `/me` 的 401 表示未登入；網路／服務錯誤不能假裝已確認登出，也不轉 Demo。
- 登出保留該模式購物車。Demo 也支援帳號或 Email 登入，不顯示註冊入口或停用說明。
- `403 + CSRF_TOKEN_INVALID` 時先呼叫 me：仍登入則取得有效 CSRF token；已過期則請重新登入。其他 403 不一律當作 CSRF 錯誤。
- 購買遇到 CSRF 問題不自動重送：恢復認證後以原 requestKey 查詢，成功顯示訂單；查不到則驗證報價並由使用者再次確認；查詢失敗保留狀態。

### 10.7 結帳 API 與一致性

| API | 用途 |
|---|---|
| POST /api/checkout/quote | 依 gameIds 驗證商品與擁有權，計算報價 |
| POST /api/purchases | 依 quoteId、requestKey 建立模擬訂單 |
| GET /api/purchases?requestKey=… | 查詢目前登入會員的該次購買結果 |

- 三支都需登入；POST 需 CSRF。身份取自 session，不信任前端 userId。
- 報價收到 `{ gameIds: [1, 16] }`，回傳商品明細、總額與 quoteId。報價暫存 PHP session，有效 10 分鐘，不新增報價表、不保證鎖價。
- 送單收到 `{ quoteId, requestKey }`。後端重新核對商品、擁有權、DB 價格，不接受前端價格作為成交依據。
- 商品不存在或已擁有時整筆拒絕，前端更新後重新確認，不默默刪掉部分商品成交。
- 報價過期或價格變更（包含降價）要求重新報價與確認。
- 成功回傳訂單 ID、實際成交項目、總額與最新擁有遊戲清單；前端成功後才清除本次商品與顯示完成。
- 逾時／斷線不能直接斷言失敗，也不能立即產生新 key。先用原 requestKey 查詢；查不到可用原 key 重試；仍斷線就保留識別碼及商品。
- 後端先驗證身份，再檢查是否已有該 requestKey 的成功訂單；已成功就回傳原結果，不因原 quote 過期而拒絕查回。
- 查不到不保證原請求不會稍後完成；併發安全必須由唯一約束、交易與一致的重試處理保證。
- 正式結帳金額以後端報價／成交結果為準；sessionStorage 購物車與前端 cartTotal 只作商品選擇及預覽。

### 10.8 統一回應與錯誤

成功：`{ "ok": true, "data": ... }`。

失敗：`{ "ok": false, "error": { "code": "PRICE_CHANGED", "message": "商品價格已更新，請重新確認訂單。" } }`。

欄位驗證可附 `error.fields`，例如 `{ "password": "密碼不可包含空白。" }`。前端依 code 分流，不比對中文訊息。

| HTTP 狀態 | 用途／處理 |
|---|---|
| 400 | 欄位不合法，顯示欄位錯誤 |
| 401 | 帳密錯誤、未登入或過期；登入失敗不透露帳號是否存在 |
| 403 | CSRF 或權限拒絕；依 code 區分 |
| 404 | 遊戲／訂單不存在 |
| 409 | 帳號重複、已擁有、價格變更、報價過期等衝突，依 code 處理 |
| 429 | 請求過頻，提示稍後重試 |
| 500 / 503 | 伺服器／DB 異常，提示及重試，不轉 Demo |
| 無可靠回應 | 網路／逾時；讀取可重試，購買先查結果 |

內部 SQL、堆疊、路徑及憑證不回傳前端。報錯不得繞過認證、購買驗證或切換模式。

### 10.9 實作順序與尚待確認事項

- 建置順序維持先完成環境、資料庫及模式／遊戲資料串接，再於 Phase 5 一起完成會員 API 與註冊畫面，不先以假模式改動既有畫面。

- 保留原八階段的主順序；Phase 1 就處理秘密設定排除、API 契約與本機來源策略，文件及 Demo 回歸每階段持續更新，不等到最後。
- Phase 3 只驗收模式與 Service/啟動骨架；games、auth 尚未完成時，不宣稱完整 Database 可用，不用 Demo 身份冒充正式會員。
- Phase 4 加入後端遊戲媒體提供與前端圖片解析適配；Phase 5 加入預設頭像與完整認證；Phase 6 加入報價、訂單、擁有權與冪等處理。
- 每階段開始前仍須列出確切檔案、風險、回退與驗收，取得該階段授權；不得自行進入下一階段。
- 已選定並驗證：XAMPP Apache/PHP 8.2.12 搭配獨立 MySQL 8.4.13；Apache 使用 8080，MySQL 使用 3306，不使用 XAMPP MariaDB。MySQL Windows 服務為 MySQL84，圖形管理工具為 Workbench。
- 本機私有設定為 `backend/config/database.local.php`，已忽略且不提交 Git；連線到 lifecharge 的專用帳號為 lifecharge_app，限本機與 SELECT/INSERT/UPDATE。設定範本不保存真實憑證。
- Apache `/lifecharge-backend/` 僅對應 `backend/public/`，限制本機存取；Vite `/api/health` 轉送至 Apache `/lifecharge-backend/api/health.php`。媒體 Base URL 為 `http://localhost:8080/lifecharge-backend/media/`，後續 Service 需集中管理，不寫死於資料庫。
- Phase 5 已選定新密碼至少 8 字元、最多 72 bytes，使用 PASSWORD_BCRYPT；/me 與 /csrf 不延長閒置期限。登入／註冊限流參數及已實作錯誤碼見第 10.14 節與 README。尚待細化：Phase 6 有效操作的活動時間更新、報價與 requestKey 的分頁恢復／保存及請求內容比對方式、購買錯誤碼，不當成已完成的功能。
- 頭像上傳需之後獨立批准；目前不增加上傳 API 或處理流程。

### 10.10 會員登入／註冊視窗（後續確認）

- 沿用 LoginDialog.vue 作共同會員視窗，以內部狀態切換登入與註冊，不新增 Register.vue 或 `/register` 頁面路由。
- Demo 模式只有登入，不顯示「會員註冊」、停用按鈕或 Demo 註冊說明。
- Database 模式的登入畫面增加「會員註冊」按鈕，點擊後在同一視窗顯示註冊表單；註冊畫面提供「返回登入」。
- 登入欄位為帳號或 Email、密碼。註冊欄位為顯示名稱、帳號、Email、密碼、確認密碼；密碼可顯示／隱藏。
- 確認密碼只作表單一致性驗證，不存入資料庫；註冊 API 仍使用第 10.6 節的四個欄位。
- 沿用既有圓角、白底輸入框及主色按鈕，欄位錯誤預留空間；提交中顯示處理狀態並避免重複送出。
- 切換登入／註冊時清除密碼、確認密碼與錯誤訊息，但保留原站內目的地。
- 關閉視窗時重設表單；下次開啟回到登入。關閉／取消流程沿用既有目的地清理語意，表單內部切換不可誤觸取消登入。
- 註冊成功後自動登入，清理購物車中已擁有的遊戲，接續原目的地；無目的地則前往會員中心。
- 手機維持單欄，視窗內容可上下捲動，確保關閉與送出操作可到達。
- 隱藏入口不取代後端註冊驗證。Phase 5 已完成獨立視窗驗收；正式入口 Database 整合仍待後續處理。

### 10.11 啟動畫面與簡化暫存方案（Phase 3 討論確認）

- 使用者選擇啟動畫面最少顯示約 3 秒，同時進行模式判斷與資料初始化，不等畫面結束才載入。
- `src/config/system.js` 的 `STARTUP_MIN_DURATION_MS` 與 `HEALTH_CHECK_TIMEOUT_MS` 分別預設 3000ms。顯示時間與請求逾時獨立設定，不累加等待。
- 啟動顯示「正在判斷運作模式…」，說明 Demo 展示模式／Database 資料庫模式；完成判斷後顯示對應模式與載入進度。公開靜態建置固定 Demo，不假裝檢查本機服務。
- 只有最短時間已滿、必要資料已就緒才顯示可操作畫面；資料較慢則繼續等待，失敗顯示錯誤與重試，不因滿 3 秒就直接放行。
- 目前只建立時間設定，尚未實作畫面或整合 main.js。整合時必須等待遊戲、會員、購物車初始化，不可顯示 Database 卻使用 Demo 會員或購買資料。
- 依使用者要求簡化：不新增通用 storage.js。Phase 5 已由 cartStore 依模式選擇 `lifecharge-cart`（Demo）或 `lifecharge-database-cart`（Database）。
- Demo 原登入與購買 keys 繼續保留；正式身份由 PHP session 驗證，正式擁有權從後端取得，不新增可冒充正式身份／擁有權的 sessionStorage 紀錄。
- 兩種模式不搬移、不合併資料。不能假設重新整理會清除 sessionStorage；同分頁切換模式仍需使用獨立 key。關閉分頁或瀏覽器恢復分頁的行為不作安全依據。
- Phase 5 已修改 authStore／cartStore，並在獨立 Database 視窗驗收另一模式購物車 key 未改動；正式入口仍未接入 systemStore，完整購物車測試矩陣尚未建立。

### 10.12 Phase 1～3 實際進度與驗收範圍

- Phase 1 已完成本機 PHP/Apache/MySQL、私有設定、PDO 連線、health 與 Vite proxy，包含停用／恢復服務的人工測試。
- Phase 2 已完成 `database/schema.sql` 五張表、命令列 seed、後端圖片與預設頭像。初始筆數為 games 30、users 6、user_games 26、orders 0、order_items 0；重跑新增均為 0；六位會員的密碼雜湊驗證通過。
- Phase 3 已新增 system 設定、apiClient、healthService、systemStore，僅由 `test/phase3/` 獨立測試頁使用，不影響正式網站入口。
- health 現在驗證 lifecharge 資料庫、五張表必要欄位及初始遊戲 ID 1～30 是否齊全；資料未齊全時 ready 為 false，缺表／欄位等例外回傳 503。這不是所有資料內容、圖片或完整 API 功能的驗收。
- 已人工測試正常 Database、MySQL 停止的 BACKEND_UNAVAILABLE、Apache 停止的 HTTP_ERROR、服務恢復、約 3 秒 TIMEOUT、401/403/409 不降級、兩次初始化只有一次實際 health fetch、正式測試建置為 static-build Demo。
- 400/429 的處理分支已存在，但尚未獨立執行案例；ready false、錯誤版本／格式等分支也尚未逐一測試，不將其描述為全部通過。
- Pinia 包裝 action Promise，外部 `first === second` 不適合作為重複請求測試；以 Network 實際 fetch 數量確認。
- 測試目錄實際名稱是 `test/phase3/`（非 tests）；`vite.config.js` 測試設定輸出至 `dist/phase3-check`，正式網站的一般建置不包含測試頁。
- 正式測試頁預期不發出 health；透過 Network Fetch/XHR 驗收。正式建置目前一律 Demo，未來其他正式 Database 部署另行設計，不把 DEV 當成資料庫可用性。
- Phase 4 遊戲資料已完成下節所列獨立驗收；完整前端 Database 啟動整合、認證與購買流程尚未完成。後續各階段仍須獨立批准，不因基礎模式測試完成而跳階段。

### 10.13 Phase 4 實際進度與驗收範圍

- 新增 `backend/public/api/games.php`，僅接受 GET；查詢 games、轉換數字與四個 JSON 陣列欄位，回傳統一回應。失敗回傳 503／GAMES_UNAVAILABLE，不暴露內部例外。
- Vite 新增 `/api/games` 至 Apache `games.php` 的轉送；`src/config/system.js` 集中設定 DATABASE_MEDIA_BASE_URL，資料庫仍保存原資料夾名稱的相對路徑。
- 新增 `src/services/gameService.js`：Demo 使用 JSON 與前端素材，Database 讀取 API、驗證資料並組合媒體網址；兩種來源均提供 cover、banner 及五張 screenshots。不在 API 失敗時回退 JSON。
- gameStore 新增 initialize(mode)、source、loadStatus、loadError，保留 ID 查詢、搜尋及分類介面。預設同步 Demo 供現有入口使用；Database 初始化目前由獨立測試頁明確呼叫。
- 同一 Store 不允許初始化途中更換模式；失敗清空 games、source 為 null、狀態 error 並拋出錯誤。初始化 Promise 仍保留，現階段以重新整理重試，正式啟動重試機制尚待整合。
- GameCard、CartMenu、Home、GameDetail、SearchResults、Checkout 已移除各自的遊戲圖片 glob，直接使用 Service／Store 提供的圖片欄位；不包含會員頭像適配。
- `test/phase4/index.html` 驗證雙來源資料與樣本封面；`store.html` 使用獨立 Pinia 驗證兩種 Store；`components.html` 使用 Database 資料渲染實際 GameCard，停用互動，另以 VImg 顯示第一款橫幅及截圖。
- 已人工確認兩種 Store 各 30 款、來源及狀態正確，字串／未知 ID、中英文與空搜尋、類型查詢通過；新品 8、暢銷 14、精選 18。
- MySQL 關閉時測試頁回報 passed false／GAMES_UNAVAILABLE，恢復服務並重新整理後通過；符合明確 Database 載入失敗不回退 Demo 的規則。此項輸出未單獨斷言失敗後所有 Store 欄位，該狀態處理由程式碼檢查確認。
- Database 的 30 張 GameCard 封面及第一款橫幅、五張截圖已人工驗收；其餘後端橫幅／截圖未在此頁逐張目視驗收。正式 Demo 畫面圖片已由使用者逐步確認，npm run build 通過。
- 獨立元件頁並未掛載完整 Home／GameDetail／CartMenu／Checkout，不代表完整 Database 頁面或交易流程驗收。一般正式建置與 Phase 3 專用測試建置均不包含這些 Phase 4 頁面。
- 本輪未修改 main.js、authStore、cartStore 或資料表；正式入口仍為 Demo。systemStore 的啟動整合、三秒提示、會員認證、購物車隔離及購買持久化尚未完成，不能以現有 Demo 登入或購買冒充 Database。

### 10.14 Phase 5 實際進度與驗收範圍

- 新增 backend/config/app.php、src/session.php、response.php、csrf.php、request.php、user.php、validation.php、rateLimit.php；逐支 PHP 的職責、呼叫關係及資料庫操作已整理在 README「PHP 檔案分工」。config/database.local.php 仍不提交 Git，不公開帳密。
- 新增 GET /api/csrf、GET /api/me、POST /api/login、POST /api/logout、POST /api/register，Vite proxy 已轉送對應 PHP。會員回應僅包含 id、username、email、displayName、avatar、purchasedGames，不含密碼雜湊。
- 註冊單一交易新增 users，使用 usersDataWebp/default.webp，自動登入且無初始遊戲；不建立訂單或擁有權。唯一約束拒絕重複帳號／Email，回 409／ACCOUNT_EXISTS。本輪未改 schema 或重新匯入 seed，註冊測試另新增會員，users=6 僅代表初始匯入狀態。
- 密碼使用 PASSWORD_BCRYPT，新密碼至少 8 字元、最多 72 bytes，不含空白及空字元；舊會員登入不套用 8 字元下限。確認密碼不送 API；後端驗證失敗回 400／VALIDATION_FAILED 與 fields。JSON 媒體型別／過大內容另回 415／413。
- Session 使用專用 Cookie、HttpOnly、SameSite=Lax、strict mode；本機 HTTP secure=false。後端 last_activity 檢查 1800 秒，登入／註冊更新 ID、token 與時間並清除先前資料。/me、/csrf 不延長期限；touchAppSession() 尚無端點呼叫，Phase 6 須明確接入有效活動時間更新，不能宣稱已有完整滑動閒置更新。
- 登入／註冊先檢查 Origin，再依操作及 REMOTE_ADDR 限流，之後驗證 CSRF 與輸入。固定窗為登入 20 次／300 秒、註冊 5 次／600 秒；超額 429／RATE_LIMITED 與 Retry-After。storage/rate-limits 使用檔案鎖，失敗不略過限制；目前為本機單機方案，無過期檔自動清理，不代表公開部署限流架構。
- apiClient 增加 POST、fields、retryAfter；authService 統一 Database 會員回應及兩種頭像網址。每次 POST 前取得 token，不長期保存或自動重送。/me 只有明確 401／UNAUTHENTICATED 才視為未登入，服務故障保持為錯誤。
- authStore 支援固定模式初始化、非同步登入／登出／註冊及 isBusy，先要求同模式遊戲 ready；失敗不轉 Demo。Database 身份不從瀏覽器暫存還原，且拒絕原 Demo purchaseGames。Demo 保留既有 keys，會員資料先複製並支援帳號或 Email 登入。
- 403／CSRF_TOKEN_INVALID 時，authStore 查 /me 再取得有效 token，成功才同步身份並提示再次操作；不自動重送，重新驗證失敗也不假裝登出。此為會員操作處理，購買仍須在 Phase 6 先查原 requestKey。
- LoginDialog 同窗切換登入／註冊，Demo 隱藏註冊；密碼可顯示，切換清除密碼與錯誤、保留目的地，關閉重設，提交鎖定，手機長表單可捲動。註冊自動登入後接續目的地，無目的地前往 /member。Member 登出等待成功後返回首頁；NavBar／Member 使用正規化頭像。
- cartStore.initialize(mode) 要求遊戲、會員同模式就緒，再選獨立 key；恢復期間停止寫回，驗證／去重／排除已擁有後保存。登入排除已擁有遊戲，登出保留購物車，不新增購物車資料表。
- test/phase5/ 有 login、register、rate-limit、service、store、dialog、csrf-recovery 七個 HTML 測試頁；dialog 使用獨立 Pinia 與記憶體路由，/member、/checkout 僅為目的地紀錄，不渲染真正頁面。註冊頁與視窗註冊會寫入 users；限流頁會用完本機額度，且不新增會員。一般正式建置不包含這些頁面。
- 已人工確認登入／恢復／登出、註冊自動登入與預設頭像、Email 登入、帳號／Email 各自重複拒絕、密碼空白欄位錯誤、錯誤 CSRF 不登出、不允許來源 403、登入第 21 次與註冊第 6 次 429。到期恢復額度未另以獨立自動案例驗收。
- 10 秒過期測試已通過並恢復 1800，修正設定缺逗號後恢復正常；已登入時停止 MySQL 得到 503／MEMBER_UNAVAILABLE，不誤判登出，服務恢復後查回原會員。
- 已確認 Service／Store、Database 視窗註冊／目的地、Demo 回歸及 otherCartUnchanged；CSRF 恢復的已登入／未登入兩案例均 passed true，login=1、me=1、csrf=2，isBusy 恢復 false。身份變更、恢復失敗及完整購物車新增／移除／重載的自動測試矩陣尚未各自建立。
- 正式建置通過；後續仍須保留語法檢查。main.js 未修改，正式入口仍 Demo，尚未驗收完整 Database 導覽／真結帳頁。自動模式、三秒啟動畫面與重試介面仍待整合；Phase 6 報價／訂單／冪等查詢與持久化尚未開始。

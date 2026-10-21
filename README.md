# LifeCharge

Vue 遊戲商城作品。V1 已完成；V2 正在加入 PHP API 與 MySQL。

## 目前進度

Phase 2：資料表與初始資料匯入已完成。

- 已建立五張資料表。
- 已匯入 30 款遊戲、6 位測試會員及 26 筆初始擁有權。
- 初始會員密碼以雜湊保存，未建立虛構訂單。
- 已驗證重複匯入不重複新增。
- 前端仍使用 JSON 與 sessionStorage，尚未串接資料庫。
- 尚未加入自動模式判斷。
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
- `ready` 目前固定為 `false`；資料已匯入，但 health 尚未實作資料表與初始遊戲的完整就緒檢查。
- MySQL 停止：PHP 回傳 HTTP 503 與 BACKEND_UNAVAILABLE。
- Apache 停止：proxy 無法取得後端回應，可能顯示 502。
- 服務重新啟動後應恢復正常。

測試只執行 SELECT 1，不建立或修改資料。

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

health 仍只執行連線檢查，不能僅因已匯入就將 `ready` 手動改成 `true`。
完整就緒檢查與前端自動模式判斷留待後續階段實作。

## 建置與部署

```powershell
npm.cmd run build
npm.cmd run preview
```

Vite proxy 只適用於 dev，不適用於 preview 或 GitHub Pages。

GitHub Actions 僅在推送 main 時部署 dist。
feature/v2 的推送不更新公開網站。
PHP 與資料庫不部署到 GitHub Pages。

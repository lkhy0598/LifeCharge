# LifeCharge

Vue 遊戲商城作品。V1 已完成；V2 正在加入 PHP API 與 MySQL。

## 目前進度

Phase 1：後端連線與健康檢查。

- 前端仍使用 JSON 與 sessionStorage。
- 尚未加入自動模式判斷。
- 尚未建立 LifeCharge 業務資料表。
- 所有付款均為模擬。

## 本機環境

- Node.js 與 npm
- XAMPP：Apache、PHP 8.2.12
- 獨立 MySQL Server 8.4.13
- Apache 使用 8080，MySQL 使用 3306

不使用 XAMPP 附帶的 MariaDB。

## 後端設定

複製：

backend/config/database.example.php

命名為：

backend/config/database.local.php

填寫本機 MySQL 帳號與密碼。
database 暫時留空，只驗證連線。

database.local.php 不得提交 Git。
root 目前僅供環境驗證，後續改用專案專用帳號。

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
- ready 目前固定 false，尚未驗證資料表與初始遊戲。
- MySQL 停止：PHP 回傳 HTTP 503 與 BACKEND_UNAVAILABLE。
- Apache 停止：proxy 無法取得後端回應，可能顯示 502。
- 服務重新啟動後應恢復正常。

測試只執行 SELECT 1，不建立或修改資料。

## 建置與部署

```powershell
npm.cmd run build
npm.cmd run preview
```

Vite proxy 只適用於 dev，不適用於 preview 或 GitHub Pages。

GitHub Actions 僅在推送 main 時部署 dist。
feature/v2 的推送不更新公開網站。
PHP 與資料庫不部署到 GitHub Pages。
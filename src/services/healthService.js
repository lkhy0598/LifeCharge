// 本檔由 systemStore 呼叫，詢問 /api/health 是否能使用 Database 模式。
// HTTP 200 不夠，還須確認服務名稱、API 版本與狀態欄位，避免接到其他網站卻誤認成功。

import { ApiError, getApiJson } from './apiClient'

// 用途：取得並驗證健康資料，回傳 { ready: true 或 false }。
// typeof 檢查資料類型；Array.isArray 排除清單，因為這裡預期是有欄位的物件。
// 資料庫未連線但宣稱 ready 是矛盾狀態，會發出錯誤，不直接相信。
export async function checkBackendHealth() {
    // 向後端取得 data；apiClient 已檢查 HTTP 與外層 ok，這裡繼續核對健康資料的內容。
    const data = await getApiJson('/api/health')

    if (
        !data
        || typeof data !== 'object'
        || Array.isArray(data)
        // 確認連到 lifecharge-api 且版本為 1，避免 proxy 指到其他服務仍被當成功。
        || data.service !== 'lifecharge-api'
        || data.apiVersion !== 1
        || typeof data.databaseConnected !== 'boolean'
        || typeof data.ready !== 'boolean'
    ) {
        throw new ApiError(
            '後端健康檢查格式或版本不符合預期。',
            'INVALID_HEALTH_RESPONSE'
        )
    }

    // 矛盾狀態不能接受：宣稱資料已就緒但連線 false，表示回應不可靠，直接報格式錯誤。
    if (data.ready && !data.databaseConnected) {
        throw new ApiError(
            '後端健康檢查狀態不一致。',
            'INVALID_HEALTH_RESPONSE'
        )
    }

    // 只有 databaseConnected 與 ready 同時 true 才回 ready true；沒有另外在這裡選 Demo。
    return {
        ready: data.databaseConnected && data.ready,
    }
}
import { ApiError, getApiJson } from './apiClient'

export async function checkBackendHealth() {
    const data = await getApiJson('/api/health')

    if (
        !data
        || typeof data !== 'object'
        || Array.isArray(data)
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

    if (data.ready && !data.databaseConnected) {
        throw new ApiError(
            '後端健康檢查狀態不一致。',
            'INVALID_HEALTH_RESPONSE'
        )
    }

    return {
        ready: data.databaseConnected && data.ready,
    }
}
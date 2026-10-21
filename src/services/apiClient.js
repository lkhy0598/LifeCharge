import { HEALTH_CHECK_TIMEOUT_MS } from '../config/system'

// 統一保存錯誤代碼與 HTTP 狀態。
export class ApiError extends Error {
    constructor(message, code, status = null) {
        super(message)
        this.name = 'ApiError'
        this.code = code
        this.status = status
    }
}

// 目前先提供 GET，之後會員與購買階段再擴充 POST。
export async function getApiJson(
    path,
    { timeoutMs = HEALTH_CHECK_TIMEOUT_MS } = {}
) {
    const controller = new AbortController()

    const timer = setTimeout(() => {
        controller.abort()
    }, timeoutMs)

    try {
        const response = await fetch(path, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
        })

        let payload

        try {
            payload = await response.json()
        } catch (error) {
            // 讀取回應途中逾時，交給外層處理。
            if (controller.signal.aborted) throw error

            throw new ApiError(
                '伺服器回應格式不正確。',
                response.ok ? 'INVALID_RESPONSE' : 'HTTP_ERROR',
                response.status
            )
        }

        if (!response.ok) {
            throw new ApiError(
                typeof payload?.error?.message === 'string'
                    ? payload.error.message
                    : 'API 請求失敗。',
                typeof payload?.error?.code === 'string'
                    ? payload.error.code
                    : 'HTTP_ERROR',
                response.status
            )
        }

        if (payload?.ok !== true || !Object.hasOwn(payload, 'data')) {
            throw new ApiError(
                'API 回應格式不正確。',
                'INVALID_RESPONSE',
                response.status
            )
        }

        return payload.data
    } catch (error) {
        if (error instanceof ApiError) throw error

        if (controller.signal.aborted) {
            throw new ApiError('連線逾時。', 'TIMEOUT')
        }

        throw new ApiError('無法連接後端服務。', 'NETWORK_ERROR')
    } finally {
        clearTimeout(timer)
    }
}
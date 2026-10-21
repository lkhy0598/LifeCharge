import { HEALTH_CHECK_TIMEOUT_MS } from '../config/system'

// 統一保存 API 錯誤資訊。
export class ApiError extends Error {
    constructor(
        message,
        code,
        status = null,
        { fields = null, retryAfter = null } = {}
    ) {
        super(message)
        this.name = 'ApiError'
        this.code = code
        this.status = status
        this.fields = fields
        this.retryAfter = retryAfter
    }
}

async function requestApiJson(
    path,
    {
        method = 'GET',
        body,
        csrfToken,
        timeoutMs = HEALTH_CHECK_TIMEOUT_MS,
    } = {}
) {
    const headers = {
        Accept: 'application/json',
    }

    let encodedBody

    if (method === 'POST') {
        headers['Content-Type'] = 'application/json'
        encodedBody = JSON.stringify(body)

        if (typeof csrfToken === 'string' && csrfToken !== '') {
            headers['X-CSRF-Token'] = csrfToken
        }
    }

    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeoutMs)

    try {
        const response = await fetch(path, {
            method,
            headers,
            body: encodedBody,
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
        })

        let payload

        try {
            payload = await response.json()
        } catch (error) {
            if (controller.signal.aborted) throw error

            throw new ApiError(
                '伺服器回應格式不正確。',
                response.ok ? 'INVALID_RESPONSE' : 'HTTP_ERROR',
                response.status
            )
        }

        if (!response.ok) {
            const fields = payload?.error?.fields
            const retryHeader = response.headers.get('Retry-After')

            const retryAfter =
                retryHeader !== null && /^\d+$/.test(retryHeader)
                    ? Number(retryHeader)
                    : null

            throw new ApiError(
                typeof payload?.error?.message === 'string'
                    ? payload.error.message
                    : 'API 請求失敗。',
                typeof payload?.error?.code === 'string'
                    ? payload.error.code
                    : 'HTTP_ERROR',
                response.status,
                {
                    fields:
                        fields
                        && typeof fields === 'object'
                        && !Array.isArray(fields)
                            ? fields
                            : null,
                    retryAfter,
                }
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

// 保留既有 GET 介面與預設逾時。
export function getApiJson(path, options = {}) {
    return requestApiJson(path, {
        ...options,
        method: 'GET',
    })
}

// POST 預設等待 10 秒，讓密碼雜湊等操作有較充足的時間。
export function postApiJson(
    path,
    body,
    { csrfToken, timeoutMs = 10000 } = {}
) {
    return requestApiJson(path, {
        method: 'POST',
        body,
        csrfToken,
        timeoutMs,
    })
}
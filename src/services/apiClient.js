// 本檔是前端呼叫後端的共同工具，登入、遊戲、購買都透過它傳送請求。
// API 是後端提供給前端辦事的網址；JSON 是交換資料的文字格式。
// fetch() 送出 HTTP 請求；async 表示函式會交回等待結果的 Promise，await 等待它完成。
// 失敗會 throw 錯誤，交由外面的 catch 處理；本檔不決定模式，也不自動重送購買。

import { HEALTH_CHECK_TIMEOUT_MS } from '../config/system'

// 統一保存 API 錯誤資訊。
// 建立專案共用的錯誤類型；extends Error 表示沿用 JS 內建錯誤，再加欄位。
// constructor 是 new ApiError() 時執行的函式；super(message) 先保存基本錯誤訊息。
// code 給程式判斷，status 是 HTTP 狀態，fields 是欄位錯誤，retryAfter 是需要等幾秒。
export class ApiError extends Error {
    constructor(
        message,
        code,
        status = null,
        // 解構最後的選項物件，沒傳整份用 {}，沒傳個別欄位用 null；避免呼叫時一定要填所有資料。
        { fields = null, retryAfter = null } = {}
    ) {
        // 先讓內建 Error 保存訊息與堆疊；this 指這份錯誤物件，後面附加的欄位可由 catch 讀取。
        super(message)
        this.name = 'ApiError'
        this.code = code
        this.status = status
        this.fields = fields
        this.retryAfter = retryAfter
    }
}

// 用途：依參數送 GET／POST，讀 JSON 回覆，成功只交回 payload.data。
// path 是 API 網址；第二個參數用解構取出選項，= 指定沒提供時的預設值。
// 接下來依序建立標頭、設定逾時計時器、送出請求、檢查回覆，最後清除計時器。
async function requestApiJson(
    path,
    {
        method = 'GET',
        body,
        csrfToken,
        timeoutMs = HEALTH_CHECK_TIMEOUT_MS,
    } = {}
) {
    // headers 是隨請求帶的說明。Accept 表示希望收到 JSON；POST 的 Content-Type 表示送出 JSON。
    const headers = {
        Accept: 'application/json',
    }

    // 先宣告可能要送的文字；GET 沒有 body，因此維持 undefined，POST 才轉 JSON。
    let encodedBody

    // 只有提交資料才加 JSON Content-Type 並編碼內容；不能把 JS 物件直接當 HTTP body 傳。
    if (method === 'POST') {
        headers['Content-Type'] = 'application/json'
        // JSON.stringify 把 JS 物件轉成可傳送的 JSON 文字，不是寫進瀏覽器暫存。
        encodedBody = JSON.stringify(body)

        // 有合法非空的 token 才放 X-CSRF-Token；缺少時不亂造一份，後端仍會拒絕需要驗證的操作。
        if (typeof csrfToken === 'string' && csrfToken !== '') {
            headers['X-CSRF-Token'] = csrfToken
        }
    }

    // AbortController 用來中止等待；setTimeout 時間到就 abort，稍後會轉成 TIMEOUT 錯誤。
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeoutMs)

    try {
        // same-origin 讓同來源請求帶 Cookie，後端才知道是哪個 Session。
        // no-store 避免快取即時資料；signal 連到上面的中止控制器。
        const response = await fetch(path, {
            method,
            headers,
            body: encodedBody,
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
        })

        // 準備保存解析後的回覆，這裡還不知道成功／失敗內容；內層 try 專門處理 JSON 解析。
        let payload

        try {
            // 先把回覆文字解析成 JS 資料；HTTP 失敗不保證回覆仍是合法 JSON，所以分開處理。
            payload = await response.json()
        } catch (error) {
            // 若解析到一半其實是我們的逾時中止，不誤報格式錯誤；讓外層 catch 統一轉 TIMEOUT。
            if (controller.signal.aborted) throw error

            throw new ApiError(
                '伺服器回應格式不正確。',
                response.ok ? 'INVALID_RESPONSE' : 'HTTP_ERROR',
                response.status
            )
        }

        // fetch 收到 401／503 等不會自己當成網路錯誤，所以必須查看 response.ok。
        // 把後端錯誤碼、欄位錯誤及 Retry-After 組成 ApiError，供畫面正確分流。
        if (!response.ok) {
            // ?. 在 error 或 payload 不存在時得到 undefined，不直接取值崩潰；後面再驗證欄位形狀。
            const fields = payload?.error?.fields
            // 從 HTTP 回覆讀取伺服器要求等待秒數；get 沒找到回 null，和數字 0 不同。
            const retryHeader = response.headers.get('Retry-After')

            const retryAfter =
                // 只接受純數字秒數文字才轉 Number；不支援的格式保留 null，不猜時間。
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

        // HTTP 成功仍不保證是本專案格式；ok 必須 true，且 data 欄位必須真的存在。
        // Object.hasOwn 允許 data 為 null，但不允許整個欄位缺少。
        if (payload?.ok !== true || !Object.hasOwn(payload, 'data')) {
            throw new ApiError(
                'API 回應格式不正確。',
                'INVALID_RESPONSE',
                response.status
            )
        }

        // 外層 ok 與 data 已檢查過，Service 只拿到 data，仍須再檢查會員或商品的個別欄位。
        return payload.data
    } catch (error) {
        // 已經分類好的 API 錯誤直接保留；不能把 401、409 等都改成網路失敗。
        if (error instanceof ApiError) throw error

        // 我們的逾時計時器主動中止時用 TIMEOUT，其餘 fetch 層失敗用 NETWORK_ERROR，方便外面分辨。
        if (controller.signal.aborted) {
            throw new ApiError('連線逾時。', 'TIMEOUT')
        }

        throw new ApiError('無法連接後端服務。', 'NETWORK_ERROR')
    // 不論成功、失敗都清除逾時計時器，避免請求結束後還殘留計時工作。
    } finally {
        // 取消已用完的倒數；即使成功回覆或提早發生錯誤，也必須執行這個收尾。
        clearTimeout(timer)
    }
}

// 保留既有 GET 介面與預設逾時。
// 用途：用 GET 查資料；...options 展開呼叫者的選項，但 method 固定為 GET。
// 回傳等待中的 Promise，呼叫處通常用 await 取得最後的資料。
export function getApiJson(path, options = {}) {
    return requestApiJson(path, {
        // 展開外面提供的選項，再把 method 固定 GET；後面的同名欄位會覆蓋前面的值。
        ...options,
        method: 'GET',
    })
}

// POST 預設等待 10 秒，讓密碼雜湊等操作有較充足的時間。
// 用途：用 POST 提交資料，預設等 10 秒，讓密碼驗證等操作有足夠時間。
// body 是提交內容，csrfToken 是目前 Session 的操作驗證碼；本函式不保存密碼或 token。
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

// 用途：向 /api/csrf 取得操作驗證碼，再確認是 64 字元的小寫十六進位文字。
// 格式錯誤就拋出 ApiError，不把未確認的資料交給登入或購買操作。
export async function getCsrfToken() {
    const data = await getApiJson('/api/csrf')

    if (
        typeof data?.csrfToken !== 'string'
        || !/^[a-f0-9]{64}$/.test(data.csrfToken)
    ) {
        throw new ApiError(
            '操作驗證資料格式不正確。',
            'INVALID_CSRF_RESPONSE'
        )
    }

    // 只有格式檢查通過才交回文字，呼叫者再放到下一次 POST，不在此存 localStorage。
    return data.csrfToken
}
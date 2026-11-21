// 本檔提供報價、送出購買、查詢結果與保存待確認紀錄，供 Checkout.vue 呼叫。
// 它不呈現按鈕、不判斷何時重試，也不處理真實付款；卡號不會送到這裡。
// 報價編號 quoteId、購買識別碼 requestKey、成交訂單編號 orderId 是不同資料。
// 後端可能寫入成功但回覆遺失，所以網路失敗不能直接當作沒有成交。

import {
    ApiError,
    getApiJson,
    postApiJson,
    getCsrfToken,
} from './apiClient'

// 用途：建立統一購買資料格式錯誤，避免把不完整訂單套用到畫面。
function invalidPurchase() {
    return new ApiError(
        '結帳資料格式不正確。',
        'INVALID_PURCHASE_RESPONSE'
    )
}

// 用途：檢查固定兩位小數的金額文字，再轉整數分，例如 12.30 轉成 1230。
// split 切開小數點，const [whole, fraction] 分別取得整數與小數，避免浮點金額加總誤差。
function moneyToCents(value) {
    if (
        typeof value !== 'string'
        || !/^\d{1,8}\.\d{2}$/.test(value)
    ) {
        throw invalidPurchase()
    }

    // split 依小數點拆兩段，解構將它們放入兩個名稱；Number 轉整數後以分加總，不用浮點小數計錢。
    const [whole, fraction] = value.split('.')
    return Number(whole) * 100 + Number(fraction)
}

// 用途：驗證商品編號、名稱、單價、幣別與總額，交回整理好的清單。
// map 逐項產生新商品，Set 記住編號排除重複，reduce 從 0 累加每項單價。
function normalizeSummary(data) {
    if (
        !data
        || data.currency !== 'TWD'
        || !Array.isArray(data.items)
        || data.items.length < 1
        || data.items.length > 100
    ) {
        throw invalidPurchase()
    }

    // 用 Set 記商品編號，避免同筆結果有兩個相同遊戲。下方 map 每通過一項才放入 seen。
    const seen = new Set()

    // 逐項檢查編號、標題、單價後建立只含允許欄位的新商品，不直接信任後端回應全部內容。
    const items = data.items.map(item => {
        if (
            !item
            || !Number.isInteger(item.gameId)
            || item.gameId <= 0
            || seen.has(item.gameId)
            || typeof item.title !== 'string'
            || item.title.trim() === ''
            || typeof item.titleZh !== 'string'
            || item.titleZh.trim() === ''
        ) {
            throw invalidPurchase()
        }

        seen.add(item.gameId)
        moneyToCents(item.unitPrice)

        return {
            gameId: item.gameId,
            title: item.title,
            titleZh: item.titleZh,
            unitPrice: item.unitPrice,
        }
    })

    const totalCents = moneyToCents(data.totalAmount)
    // reduce 從 0 開始，將每項單價轉分再累加；和 totalCents 比對可發現總價與明細不一致。
    const itemTotal = items.reduce(
        (sum, item) => sum + moneyToCents(item.unitPrice),
        0
    )

    // 即使 HTTP 成功，總額不符也不能讓畫面顯示成交；throw 交外面做恢復或提示。
    if (totalCents !== itemTotal) {
        throw invalidPurchase()
    }

    return {
        items,
        totalAmount: data.totalAmount,
        currency: 'TWD',
    }
}

// 用途：在商品摘要之外，確認訂單屬於這次 requestKey、會員合法且狀態 completed。
// ?. 表示前面的值不存在就得到 undefined，不直接存取失敗；仍須由後面條件驗證。
// 所有成交遊戲都應出現在回傳擁有權中，通過才交給 Store。
function normalizePurchase(data, expectedKey) {
    // 先取訂單欄位，?. 在 data 沒值時不直接報存取錯誤；下一行 normalizeSummary 再做嚴格驗證。
    const order = data?.order
    const summary = normalizeSummary(order)

    if (
        !Number.isInteger(data.userId)
        || data.userId <= 0
        ||
        // orderId 保持文字，因 MySQL BIGINT 可能超過 JS 安全整數；這裡不使用 Number 轉訂單編號。
        typeof order.orderId !== 'string'
        || !/^[1-9]\d*$/.test(order.orderId)
        || order.requestKey !== expectedKey
        || order.status !== 'completed'
        || typeof order.createdAt !== 'string'
        || !Number.isFinite(Date.parse(order.createdAt))
        || !Array.isArray(data.purchasedGames)
        || !data.purchasedGames.every(id =>
            Number.isInteger(id) && id > 0
        )
        || new Set(data.purchasedGames).size
        !== data.purchasedGames.length
    ) {
        throw invalidPurchase()
    }

    // every 要求本單每款商品都在最新擁有權內，避免顯示成功卻沒有遊戲。
    if (!summary.items.every(item =>
        data.purchasedGames.includes(item.gameId)
    )) {
        throw invalidPurchase()
    }

    return {
        userId: data.userId,
        order: {
            ...summary,
            orderId: order.orderId,
            requestKey: order.requestKey,
            status: order.status,
            createdAt: order.createdAt,
        },
        purchasedGames: [...data.purchasedGames],
    }
}

// 用途：產生一次購買用的隨機識別碼，重新查詢或重試必須沿用它。
// crypto.randomUUID 是瀏覽器提供的功能，不是訂單流水號，也不是給消費者輸入的欄位。
export function createPurchaseRequestKey() {
    // UUID 是隨機識別文字，不是連號；必須在同次購買追查與重試沿用，不能每次查結果重新產生。
    return crypto.randomUUID()
}

// 用途：傳遊戲編號取得報價，核對會員、期限、商品與金額後回傳。
// 先拿 CSRF token，再 POST；最後將要求與回傳的編號排序比較，避免拿到別份商品清單。
export async function createDatabaseQuote(gameIds) {
    // 操作前先取得目前 Session 驗證碼，失敗就不繼續提交；這不是送卡號或建立付款授權。
    const csrfToken = await getCsrfToken()

    const data = await postApiJson(
        '/api/checkout/quote',
        { gameIds },
        { csrfToken }
    )

    // 先驗明細與總額，再驗報價身份／編號／到期時間；都通過才讓 Checkout 進確認步驟。
    const summary = normalizeSummary(data)

    if (
        !Number.isInteger(data.userId)
        || data.userId <= 0
        ||
        typeof data.quoteId !== 'string'
        || !/^[a-f0-9]{64}$/.test(data.quoteId)
        || typeof data.expiresAt !== 'string'
        || !Number.isFinite(Date.parse(data.expiresAt))
    ) {
        throw invalidPurchase()
    }

    // 確認後端回傳的商品正是本次要求的清單。
    // 複製編號再排序，避免改呼叫者原陣列；對照回傳商品是否完全相同，而不要求傳送順序一樣。
    const requested = [...gameIds].sort((a, b) => a - b)
    const returned = summary.items
        .map(item => item.gameId)
        .sort((a, b) => a - b)

    // 轉成固定順序的 JSON 文字比較內容；陣列不能直接用 === 比內容，因為它比較的是同一個物件。
    if (JSON.stringify(requested) !== JSON.stringify(returned)) {
        throw invalidPurchase()
    }

    return {
        ...summary,
        userId: data.userId,
        quoteId: data.quoteId,
        expiresAt: data.expiresAt,
    }
}

// 用途：送 quoteId 與 requestKey 讓後端成交，回傳驗證過的訂單與擁有權。
// 本函式只送一次，不能因逾時自行產生新 key 重買；結果恢復由 Checkout 處理。
export async function submitDatabasePurchase(quoteId, requestKey) {
    const csrfToken = await getCsrfToken()

    const data = await postApiJson(
        '/api/purchases',
        { quoteId, requestKey },
        { csrfToken }
    )

    // 送單或查詢成功仍要核對 requestKey；不能拿其他訂單回應更新這次畫面。
    return normalizePurchase(data, requestKey)
}

// 用途：以原 requestKey 發 GET 查詢，取得訂單或明確查無的 null。
// encodeURIComponent 讓識別碼安全放入網址參數；只有指定 404 錯誤才回 null。
// 網路／資料庫失敗仍拋錯，不與確定沒有訂單混淆。
export async function findDatabasePurchase(requestKey) {
    try {
        const data = await getApiJson(
            `/api/purchases?requestKey=${encodeURIComponent(requestKey)}`
        )

        return normalizePurchase(data, requestKey)
    } catch (error) {
        if (
            error instanceof ApiError
            && error.status === 404
            && error.code === 'PURCHASE_NOT_FOUND'
        ) {
            // 只有 API 明確 404 PURCHASE_NOT_FOUND 才走這裡；逾時、503、格式壞掉都保留錯誤。
            return null
        }

        throw error
    }
}

// 用途：依正整數會員編號產生暫存名稱，讓各會員待確認購買分開。
// 這不是後端權限控制，真正查詢仍要 Session 身份。
function pendingPurchaseStorageKey(userId) {
    // 暫存也先要求合法會員，不能用 undefined 當 key 把不同人的紀錄放到同一格。
    if (!Number.isInteger(userId) || userId <= 0) {
        throw new Error('會員編號不正確。')
    }

    return `lifecharge-database-pending-purchase-${userId}`
}

// 用途：驗證暫存紀錄的會員、報價、購買 key 與金額，回傳允許保存的欄位。
// 不因 expiresAt 已過就自動刪除，因為購買可能已成交但還沒確認結果。
function normalizePendingPurchase(data, userId) {
    if (
        !data
        || data.userId !== userId
        || typeof data.quoteId !== 'string'
        || !/^[a-f0-9]{64}$/.test(data.quoteId)
        || typeof data.requestKey !== 'string'
        || !/^[A-Za-z0-9_-]{16,64}$/.test(data.requestKey)
        || typeof data.expiresAt !== 'string'
        || !Number.isFinite(Date.parse(data.expiresAt))
    ) {
        throw new ApiError(
            '待確認的購買紀錄格式異常，請先確認原訂單結果。',
            'INVALID_PENDING_PURCHASE'
        )
    }

    // 待確認暫存中的商品與金額也必須驗證，瀏覽器暫存可能被手動修改或已壞掉。
    const summary = normalizeSummary(data)

    return {
        ...summary,
        userId,
        quoteId: data.quoteId,
        requestKey: data.requestKey,
        expiresAt: data.expiresAt,
    }
}

// 用途：從此分頁 sessionStorage 讀取該會員尚待確認的購買。
// 沒紀錄回 null；JSON 壞掉則報錯，不直接丟棄可能已成交的追查資訊。
export function loadPendingDatabasePurchase(userId) {
    // 讀取該會員的待確認紀錄；沒有回 null，有則仍是文字，接著 JSON.parse 才能用。
    const raw = sessionStorage.getItem(
        pendingPurchaseStorageKey(userId)
    )

    if (raw === null) return null

    let data

    try {
        // 這裡專門處理壞 JSON；讀不懂不能直接刪除，因為可能有一筆已成交但還沒拿到回覆。
        data = JSON.parse(raw)
    } catch {
        throw new ApiError(
            '待確認的購買紀錄無法讀取，請先確認原訂單結果。',
            'INVALID_PENDING_PURCHASE'
        )
    }

    return normalizePendingPurchase(data, userId)
}

// 用途：在 POST 前先保存追查資訊，避免回覆遺失後不知道要查哪筆。
// 已有另一筆尚待確認時拒絕覆蓋；JSON.stringify 將物件變成暫存可存的文字。
export function savePendingDatabasePurchase(data) {
    const key = pendingPurchaseStorageKey(data.userId)
    const record = normalizePendingPurchase(data, data.userId)
    // 保存前先讀現有紀錄；若不是同 quoteId／requestKey，拒絕覆寫，避免失去原單的追查線索。
    const existing = loadPendingDatabasePurchase(data.userId)

    if (
        existing !== null
        && (
            existing.requestKey !== record.requestKey
            || existing.quoteId !== record.quoteId
        )
    ) {
        throw new ApiError(
            '尚有未確認的購買，請先查詢原訂單結果。',
            'PENDING_PURCHASE_EXISTS'
        )
    }

    // 全部檢查通過才寫入 JSON 文字，成功後回傳真正保存的白名單資料；不能存就拋錯阻止送單。
    sessionStorage.setItem(key, JSON.stringify(record))

    return record
}

// 用途：只清除符合會員與原 requestKey 的待確認紀錄。
// 完成或已明確處理後由 Checkout 呼叫；不會刪除 MySQL 的任何訂單。
export function clearPendingDatabasePurchase(userId, requestKey) {
    const existing = loadPendingDatabasePurchase(userId)

    if (existing === null) return

    // 清除時也比原 key，避免舊請求結束後錯刪同一會員另一筆新的待確認紀錄。
    if (existing.requestKey !== requestKey) {
        throw new Error('購買識別碼不一致，不能清除待確認紀錄。')
    }

    // 只清瀏覽器追查暫存，完全不執行 DELETE SQL；已成交訂單仍保存在 MySQL。
    sessionStorage.removeItem(pendingPurchaseStorageKey(userId))
}
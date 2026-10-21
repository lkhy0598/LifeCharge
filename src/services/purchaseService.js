import {
    ApiError,
    getApiJson,
    postApiJson,
    getCsrfToken,
} from './apiClient'

function invalidPurchase() {
    return new ApiError(
        '結帳資料格式不正確。',
        'INVALID_PURCHASE_RESPONSE'
    )
}

function moneyToCents(value) {
    if (
        typeof value !== 'string'
        || !/^\d{1,8}\.\d{2}$/.test(value)
    ) {
        throw invalidPurchase()
    }

    const [whole, fraction] = value.split('.')
    return Number(whole) * 100 + Number(fraction)
}

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

    const seen = new Set()

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
    const itemTotal = items.reduce(
        (sum, item) => sum + moneyToCents(item.unitPrice),
        0
    )

    if (totalCents !== itemTotal) {
        throw invalidPurchase()
    }

    return {
        items,
        totalAmount: data.totalAmount,
        currency: 'TWD',
    }
}

function normalizePurchase(data, expectedKey) {
    const order = data?.order
    const summary = normalizeSummary(order)

    if (
        !Number.isInteger(data.userId)
        || data.userId <= 0
        ||
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

export function createPurchaseRequestKey() {
    return crypto.randomUUID()
}

export async function createDatabaseQuote(gameIds) {
    const csrfToken = await getCsrfToken()

    const data = await postApiJson(
        '/api/checkout/quote',
        { gameIds },
        { csrfToken }
    )

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
    const requested = [...gameIds].sort((a, b) => a - b)
    const returned = summary.items
        .map(item => item.gameId)
        .sort((a, b) => a - b)

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

export async function submitDatabasePurchase(quoteId, requestKey) {
    const csrfToken = await getCsrfToken()

    const data = await postApiJson(
        '/api/purchases',
        { quoteId, requestKey },
        { csrfToken }
    )

    return normalizePurchase(data, requestKey)
}

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
            return null
        }

        throw error
    }
}

function pendingPurchaseStorageKey(userId) {
    if (!Number.isInteger(userId) || userId <= 0) {
        throw new Error('會員編號不正確。')
    }

    return `lifecharge-database-pending-purchase-${userId}`
}

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

    const summary = normalizeSummary(data)

    return {
        ...summary,
        userId,
        quoteId: data.quoteId,
        requestKey: data.requestKey,
        expiresAt: data.expiresAt,
    }
}

export function loadPendingDatabasePurchase(userId) {
    const raw = sessionStorage.getItem(
        pendingPurchaseStorageKey(userId)
    )

    if (raw === null) return null

    let data

    try {
        data = JSON.parse(raw)
    } catch {
        throw new ApiError(
            '待確認的購買紀錄無法讀取，請先確認原訂單結果。',
            'INVALID_PENDING_PURCHASE'
        )
    }

    return normalizePendingPurchase(data, userId)
}

export function savePendingDatabasePurchase(data) {
    const key = pendingPurchaseStorageKey(data.userId)
    const record = normalizePendingPurchase(data, data.userId)
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

    sessionStorage.setItem(key, JSON.stringify(record))

    return record
}

export function clearPendingDatabasePurchase(userId, requestKey) {
    const existing = loadPendingDatabasePurchase(userId)

    if (existing === null) return

    if (existing.requestKey !== requestKey) {
        throw new Error('購買識別碼不一致，不能清除待確認紀錄。')
    }

    sessionStorage.removeItem(pendingPurchaseStorageKey(userId))
}
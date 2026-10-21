import { DATABASE_MEDIA_BASE_URL } from '../config/system'
import {
    ApiError,
    getApiJson,
    postApiJson,
    getCsrfToken,
} from './apiClient'

function invalidMember() {
    return new ApiError(
        '會員資料格式不正確。',
        'INVALID_MEMBER_RESPONSE'
    )
}

function normalizeMember(data) {
    if (
        !data
        || typeof data !== 'object'
        || Array.isArray(data)
        || !Number.isInteger(data.id)
        || data.id <= 0
    ) {
        throw invalidMember()
    }

    for (const field of [
        'username', 'email', 'displayName', 'avatar',
    ]) {
        if (
            typeof data[field] !== 'string'
            || data[field].trim() === ''
        ) {
            throw invalidMember()
        }
    }

    if (
        !Array.isArray(data.purchasedGames)
        || !data.purchasedGames.every(id =>
            Number.isInteger(id) && id > 0
        )
        || new Set(data.purchasedGames).size
        !== data.purchasedGames.length
    ) {
        throw invalidMember()
    }

    const base = new URL(DATABASE_MEDIA_BASE_URL)
    const avatar = new URL(data.avatar, base)

    if (
        avatar.origin !== base.origin
        || !avatar.pathname.startsWith(base.pathname)
        || avatar.search
        || avatar.hash
    ) {
        throw invalidMember()
    }

    // 明確列出前端可以使用的欄位。
    return {
        id: data.id,
        username: data.username,
        email: data.email,
        displayName: data.displayName,
        avatar: avatar.href,
        purchasedGames: [...data.purchasedGames],
    }
}

async function postMemberAction(path, body) {
    // 每次操作前取得目前 session 的 token，
    // 不存入 sessionStorage 或 localStorage。
    const csrfToken = await getCsrfToken()

    return postApiJson(path, body, { csrfToken })
}

export async function getDatabaseMember() {
    try {
        const data = await getApiJson('/api/me')
        return normalizeMember(data)
    } catch (error) {
        // 只有明確未登入，才能回傳 null。
        if (
            error instanceof ApiError
            && error.status === 401
            && error.code === 'UNAUTHENTICATED'
        ) {
            return null
        }

        throw error
    }
}

export async function loginDatabaseMember(identifier, password) {
    const data = await postMemberAction('/api/login', {
        identifier,
        password,
    })

    return normalizeMember(data)
}

export async function registerDatabaseMember(input) {
    const data = await postMemberAction('/api/register', {
        username: input.username,
        email: input.email,
        displayName: input.displayName,
        password: input.password,
    })

    return normalizeMember(data)
}

export async function logoutDatabaseMember() {
    const data = await postMemberAction('/api/logout', {})

    if (data?.loggedOut !== true) {
        throw new ApiError(
            '登出回應格式不正確。',
            'INVALID_LOGOUT_RESPONSE'
        )
    }

    return true
}

const demoAvatars = import.meta.glob(
    '/src/assets/images/usersDataWebp/*/*.webp',
    {
        eager: true,
        import: 'default',
        query: '?url',
    }
)

export function normalizeDemoMember(user) {
    const avatar = demoAvatars[user.avatar]

    if (!avatar) {
        throw new ApiError(
            `找不到 Demo 會員頭像：${user.avatar}`,
            'MISSING_DEMO_AVATAR'
        )
    }

    return {
        id: user.id,
        username: user.username,
        email: user.email,
        displayName: user.displayName,
        avatar,
        purchasedGames: [...user.purchasedGames],
    }
}

export async function recoverDatabaseSession() {
    // 先確認目前身份，服務故障仍會拋出錯誤。
    const user = await getDatabaseMember()

    // 確認目前 session 能取得有效 token。
    // 下一次操作前仍會再次取得，不長期保存。
    await getCsrfToken()

    return user
}
// 本檔處理會員 API 與會員資料格式，不控制登入視窗是否開啟。
// authStore 呼叫這裡的登入、註冊、登出與恢復身份函式，再更新畫面共用狀態。
// Database 身份來自後端 Session，不把會員編號當證明存進前端暫存。
// async／await 用來等待網路結果；throw 發出錯誤，交給呼叫者的 catch 處理。

import { DATABASE_MEDIA_BASE_URL } from '../config/system'
import {
    ApiError,
    getApiJson,
    postApiJson,
    getCsrfToken,
} from './apiClient'

// 用途：建立固定的會員資料格式錯誤，讓各個檢查都用相同錯誤碼。
function invalidMember() {
    // 建立可辨識的錯誤物件；return 只是交回它，呼叫處 throw invalidMember() 才會中斷流程。
    return new ApiError(
        '會員資料格式不正確。',
        'INVALID_MEMBER_RESPONSE'
    )
}

// 用途：檢查後端會員格式，再回傳明確列出的公開欄位。
// 先檢查編號與文字，再檢查擁有權清單不重複，最後限制頭像網址來源。
// ...data.purchasedGames 複製一份清單，不與原回應共用同一個陣列。
function normalizeMember(data) {
    // 會員格式第一關：必須是非陣列物件，且 id 為正整數；合法 JSON 並不一定是合法會員。
    if (
        !data
        || typeof data !== 'object'
        || Array.isArray(data)
        || !Number.isInteger(data.id)
        || data.id <= 0
    ) {
        throw invalidMember()
    }

    // for...of 逐一取欄位名稱，再用 data[field] 動態讀值；每個公開文字欄位都不能是空白。
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
        // 擁有權必須是陣列，每個編號為正整數；Set 不留重複值，大小不同表示原清單有重複。
        !Array.isArray(data.purchasedGames)
        || !data.purchasedGames.every(id =>
            Number.isInteger(id) && id > 0
        )
        || new Set(data.purchasedGames).size
        !== data.purchasedGames.length
    ) {
        throw invalidMember()
    }

    // 先用設定建立允許的媒體根網址，再把 avatar 接上去；不讓回應任意指向別的主機。
    const base = new URL(DATABASE_MEDIA_BASE_URL)
    // 將相對 avatar 路徑接到設定的根網址；URL 會正規化路徑，所以還要檢查結果沒有越出目錄。
    const avatar = new URL(data.avatar, base)

    if (
        // 來源必須相同、路徑在 media 內，也不能有 ?query 或 #fragment；不讓會員資料指定任意外部圖片。
        avatar.origin !== base.origin
        || !avatar.pathname.startsWith(base.pathname)
        || avatar.search
        || avatar.hash
    ) {
        throw invalidMember()
    }

    // 明確列出前端可以使用的欄位。
    // 明確挑公開欄位交回 Store，不直接展開全部 data，避免多餘欄位跟著進入目前會員。
    return {
        id: data.id,
        username: data.username,
        email: data.email,
        displayName: data.displayName,
        avatar: avatar.href,
        purchasedGames: [...data.purchasedGames],
    }
}

// 用途：會員寫入操作的共用步驟，先取得目前 Session 的 token，再送 POST。
// 每次操作重新取得，不長期保存驗證碼；驗證碼不是輸入的密碼。
async function postMemberAction(path, body) {
    // 每次操作前取得目前 session 的 token，
    // 不存入 sessionStorage 或 localStorage。
    // 先等 token，拿不到就不發後面的 POST；此處沒有自動重送原操作。
    const csrfToken = await getCsrfToken()

    // 交回 POST 的等待結果，讓外層登入／註冊繼續檢查會員格式；Cookie 由 apiClient 帶上。
    return postApiJson(path, body, { csrfToken })
}

// 用途：問 /api/me 目前登入誰，回會員或 null。
// 只有 401 加 UNAUTHENTICATED 才代表沒登入；資料庫故障仍 throw，不能假裝登出。
export async function getDatabaseMember() {
    try {
        // 查目前 Session 的會員，而不是接受前端傳入任意 userId。
        const data = await getApiJson('/api/me')
        return normalizeMember(data)
    } catch (error) {
        // 只有明確未登入，才能回傳 null。
        if (
            // 只有確定是 API 的 401 UNAUTHENTICATED 才回 null；503 必須保留錯誤讓入口提示失敗。
            error instanceof ApiError
            && error.status === 401
            && error.code === 'UNAUTHENTICATED'
        ) {
            return null
        }

        throw error
    }
}

// 用途：提交帳號／Email 與密碼，將通過格式檢查的會員資料交回 Store。
// 本函式不自行設定 currentUser，也不在失敗後自動重送帳密。
export async function loginDatabaseMember(identifier, password) {
    // identifier 可是帳號或 Email；password 保持原輸入，不在這裡 trim 或轉小寫。
    const data = await postMemberAction('/api/login', {
        identifier,
        password,
    })

    return normalizeMember(data)
}

// 用途：明確只送允許的註冊欄位，不把整個畫面物件直接傳到後端。
// 成功後回傳已登入的新會員，重複帳號等錯誤交回畫面處理。
export async function registerDatabaseMember(input) {
    // 逐欄列出要送的資料，不送確認密碼、欄位錯誤或視窗開關等 UI 狀態。
    const data = await postMemberAction('/api/register', {
        username: input.username,
        email: input.email,
        displayName: input.displayName,
        password: input.password,
    })

    return normalizeMember(data)
}

// 用途：請後端登出並確認 loggedOut 為 true，成功才交回 true。
// 連線失敗或回覆格式錯誤不當作登出成功。
export async function logoutDatabaseMember() {
    const data = await postMemberAction('/api/logout', {})

    // HTTP 成功但 loggedOut 沒有明確 true 仍是格式錯誤，不能清前端身份假裝確認成功。
    if (data?.loggedOut !== true) {
        throw new ApiError(
            '登出回應格式不正確。',
            'INVALID_LOGOUT_RESPONSE'
        )
    }

    return true
}

// Vite 依路徑規則收集本機頭像；eager true 先建立對照，query ?url 表示要圖片網址。
const demoAvatars = import.meta.glob(
    '/src/assets/images/usersDataWebp/*/*.webp',
    {
        eager: true,
        import: 'default',
        query: '?url',
    }
)

// 用途：將 JSON 會員頭像轉成 Vite 圖片網址，複製已擁有清單後交給 Store。
// 只回公開會員欄位，不把 Demo 比對用密碼放進 currentUser。
export function normalizeDemoMember(user) {
    // 以 JSON 的完整頭像路徑查 Vite 對照表；缺圖先報錯，避免會員頁出現無效網址。
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
        // 另複製擁有權清單，避免 currentUser 被修改時直接改到原始 Demo 會員的同一個陣列。
        purchasedGames: [...user.purchasedGames],
    }
}

// 用途：操作驗證過期時，重新查目前會員並取得可用 token，再回傳身份。
// 這是恢復驗證資訊，不是重做剛才的登入／註冊／購買操作。
export async function recoverDatabaseSession() {
    // 先確認目前身份，服務故障仍會拋出錯誤。
    const user = await getDatabaseMember()

    // 確認目前 session 能取得有效 token。
    // 下一次操作前仍會再次取得，不長期保存。
    // 即使已重查 me，也要確認此 Session 可取得有效 token；下一次寫入仍再取，不長期快取。
    await getCsrfToken()

    return user
}
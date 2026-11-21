// 本檔把 Demo JSON 與 Database API 整理成畫面都能使用的遊戲格式。
// gameStore 只需傳模式給 loadGames()，不用在每個畫面分別尋找圖片。
// import 引用其他檔案；export 讓其他檔案可以使用指定函式。此處只載入資料，不建立訂單。

import gamesData from '../data/games.json'
import { DATABASE_MEDIA_BASE_URL } from '../config/system'
import { ApiError, getApiJson } from './apiClient'

// 用途：統一建立遊戲格式錯誤，呼叫處用 throw 把它交出去；return 本身不會拋錯。
function invalidGames() {
    return new ApiError(
        '遊戲資料格式不正確。',
        'INVALID_GAMES_RESPONSE'
    )
}

// 用途：把後端相對圖片路徑接到設定的媒體網址，回傳完整 URL。
// URL 是瀏覽器的網址工具；origin 是協定＋主機＋連接埠，pathname 是網址路徑。
// 僅接受指定媒體目錄，不接受額外查詢或 # 片段。
function resolveDatabaseImage(path) {
    // 圖片路徑先要求非空文字，否則連 URL 都不能安全組合，直接發格式錯誤。
    if (typeof path !== 'string' || path.trim() === '') {
        throw invalidGames()
    }

    const base = new URL(DATABASE_MEDIA_BASE_URL)
    // base 是設定的媒體根網址，path 可是相對路徑；組合後的結果仍要檢查有沒有跑到其他主機或目錄。
    const image = new URL(path, base)

    // 只接受設定的媒體目錄內的圖片網址。
    if (
        image.origin !== base.origin
        || !image.pathname.startsWith(base.pathname)
        || image.search
        || image.hash
    ) {
        throw invalidGames()
    }

    // href 是整理好的完整網址字串，元件直接給圖片 src 使用，不在每張卡片重新找圖。
    return image.href
}

// 用途：向 /api/games 讀清單，逐款驗證編號、價格、文字與陣列，再補圖片網址。
// Set 用來記住已看過的編號避免重複；map 對每款遊戲轉換後產生新清單。
// 壞資料發出錯誤，不在此改用 Demo 假裝成功。
export async function loadDatabaseGames() {
    const data = await getApiJson('/api/games')

    // 遊戲列表必須是非空清單；不是看到 HTTP 200 就直接把任何資料當遊戲。
    if (!Array.isArray(data) || data.length === 0) {
        throw invalidGames()
    }

    // Set 記錄已接收的編號；has 檢查重複，add 記住這筆，避免卡片 key 或商品對應混亂。
    const seenIds = new Set()

    // 逐款驗證並產生新遊戲物件；任一款出錯會中斷整個載入，避免半套清單被當成功。
    return data.map(game => {
        if (!game || typeof game !== 'object' || Array.isArray(game)) {
            throw invalidGames()
        }

        if (
            // 編號必須正整數；價格／評分必須有限數字，排除 NaN、Infinity，並檢查合法範圍。
            !Number.isInteger(game.id)
            || game.id <= 0
            || seenIds.has(game.id)
            || !Number.isFinite(game.price)
            || game.price < 0
            || !Number.isFinite(game.rating)
            || game.rating < 0
            || game.rating > 5
        ) {
            throw invalidGames()
        }

        seenIds.add(game.id)

        const textFields = [
            'title', 'titleZh', 'releaseDate',
            'developer', 'publisher',
            'description', 'detailDescription',
        ]

        // for...of 逐一取出欄位名；trim() 去掉頭尾空白後仍需有文字，避免畫面收到空標題。
        for (const field of textFields) {
            if (
                typeof game[field] !== 'string'
                || game[field].trim() === ''
            ) {
                throw invalidGames()
            }
        }

        // 逐個陣列欄位驗證：Array.isArray 確認清單，every 要每一項都是非空文字才通過。
        for (const field of ['genre', 'tags', 'status', 'screenshots']) {
            if (
                !Array.isArray(game[field])
                || !game[field].every(value =>
                    typeof value === 'string' && value.trim() !== ''
                )
            ) {
                throw invalidGames()
            }
        }

        // 目前畫面與資料規則預期五張截圖，少或多都當格式不一致，不靜默補假網址。
        if (game.screenshots.length !== 5) {
            throw invalidGames()
        }

        // 複製原遊戲欄位，但用驗證後的完整媒體網址覆蓋 cover、banner、screenshots。
        return {
            ...game,
            cover: resolveDatabaseImage(game.cover),
            banner: resolveDatabaseImage(game.banner),
            screenshots: game.screenshots.map(resolveDatabaseImage),
        }
    })
}

// Vite 將 Demo 圖片轉成可供瀏覽器使用的網址。
// import.meta.glob 是 Vite 的功能，按照路徑規則收集圖片。
// eager true 表示預先載入對照，?url 要的是圖片網址而不是圖片內容本身。
const demoImages = import.meta.glob(
    '../assets/images/gameDataWebp/*/*.webp',
    {
        eager: true,
        import: 'default',
        query: '?url',
    }
)

// 用途：依 game01 等資料夾與 cover 等檔名尾段，從 Vite 圖片對照表找網址。
// `...${變數}...` 是把變數放進文字；圖片缺少就報錯，不回空白網址。
function resolveDemoImage(folder, suffix) {
    const path =
        `../assets/images/gameDataWebp/${folder}/${folder}_${suffix}.webp`

    // 這是從 Vite 預先建立的檔案對照取值，不是執行動態 SQL 或每次讀磁碟。
    const image = demoImages[path]

    if (!image) {
        throw new ApiError(
            `找不到 Demo 圖片：${folder}_${suffix}.webp`,
            'MISSING_DEMO_IMAGE'
        )
    }

    return image
}

// 用途：複製 JSON 遊戲並補齊封面、橫幅、五張截圖的網址。
// ...game 展開複製欄位，陣列另用 [...] 複製，避免修改 Store 時直接改到原 JSON。
// padStart(2, 0) 將編號補足兩位；Array.from 建立五個項目再依索引組檔名。
export function loadDemoGames() {
    return gamesData.map(game => {
        // String 將編號轉文字，padStart 補到兩位，所以 1 對應 game01，避免 game1 找不到既有檔名。
        const folder = `game${String(game.id).padStart(2, '0')}`

        return {
            ...game,
            genre: [...game.genre],
            tags: [...game.tags],
            status: [...game.status],
            cover: resolveDemoImage(folder, 'cover'),
            banner: resolveDemoImage(folder, 'banner'),
            // 建立五個項目，index 為 0～4；組檔名時加 1，得到 screenshots01 到 screenshots05。
            screenshots: Array.from(
                { length: 5 },
                (_, index) => resolveDemoImage(
                    folder,
                    `screenshots${String(index + 1).padStart(2, '0')}`
                )
            ),
        }
    })
}

// 對 Store 提供統一入口。
// 用途：按 mode 分派到 Demo 或 Database，回傳遊戲資料。
// 只接受 demo／database；這裡不自動檢查後端，模式由 main.js 啟動流程決定。
export async function loadGames(mode) {
    // 模式分派不做 fallback：Database 分支失敗會交錯誤給 Store，不能偷偷改用 JSON。
    if (mode === 'demo') {
        return loadDemoGames()
    }

    if (mode === 'database') {
        return loadDatabaseGames()
    }

    throw new ApiError(
        '尚未決定有效的遊戲資料模式。',
        'INVALID_MODE'
    )
}
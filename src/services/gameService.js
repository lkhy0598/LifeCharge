import gamesData from '../data/games.json'
import { DATABASE_MEDIA_BASE_URL } from '../config/system'
import { ApiError, getApiJson } from './apiClient'

function invalidGames() {
    return new ApiError(
        '遊戲資料格式不正確。',
        'INVALID_GAMES_RESPONSE'
    )
}

function resolveDatabaseImage(path) {
    if (typeof path !== 'string' || path.trim() === '') {
        throw invalidGames()
    }

    const base = new URL(DATABASE_MEDIA_BASE_URL)
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

    return image.href
}

export async function loadDatabaseGames() {
    const data = await getApiJson('/api/games')

    if (!Array.isArray(data) || data.length === 0) {
        throw invalidGames()
    }

    const seenIds = new Set()

    return data.map(game => {
        if (!game || typeof game !== 'object' || Array.isArray(game)) {
            throw invalidGames()
        }

        if (
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

        for (const field of textFields) {
            if (
                typeof game[field] !== 'string'
                || game[field].trim() === ''
            ) {
                throw invalidGames()
            }
        }

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

        if (game.screenshots.length !== 5) {
            throw invalidGames()
        }

        return {
            ...game,
            cover: resolveDatabaseImage(game.cover),
            banner: resolveDatabaseImage(game.banner),
            screenshots: game.screenshots.map(resolveDatabaseImage),
        }
    })
}

// Vite 將 Demo 圖片轉成可供瀏覽器使用的網址。
const demoImages = import.meta.glob(
    '../assets/images/gameDataWebp/*/*.webp',
    {
        eager: true,
        import: 'default',
        query: '?url',
    }
)

function resolveDemoImage(folder, suffix) {
    const path =
        `../assets/images/gameDataWebp/${folder}/${folder}_${suffix}.webp`

    const image = demoImages[path]

    if (!image) {
        throw new ApiError(
            `找不到 Demo 圖片：${folder}_${suffix}.webp`,
            'MISSING_DEMO_IMAGE'
        )
    }

    return image
}

export function loadDemoGames() {
    return gamesData.map(game => {
        const folder = `game${String(game.id).padStart(2, '0')}`

        return {
            ...game,
            genre: [...game.genre],
            tags: [...game.tags],
            status: [...game.status],
            cover: resolveDemoImage(folder, 'cover'),
            banner: resolveDemoImage(folder, 'banner'),
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
export async function loadGames(mode) {
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
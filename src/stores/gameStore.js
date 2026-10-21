
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { loadDemoGames, loadGames } from '../services/gameService'

export const useGameStore = defineStore('game', () => {
    // 保留 V1 同步初始化，正式入口目前仍使用 Demo。
    const games = ref(loadDemoGames())
    const source = ref('demo')
    const loadStatus = ref('ready')
    const loadError = ref(null)

    let initializationPromise = null
    let initializationMode = null

    function getGameById(id) {
        return games.value.find(game => game.id === Number(id))
    }

    function initialize(mode) {
        if (mode !== 'demo' && mode !== 'database') {
            return Promise.reject(new Error('遊戲資料模式不正確。'))
        }

        if (initializationPromise) {
            if (mode !== initializationMode) {
                return Promise.reject(
                    new Error('同一次初始化不能切換遊戲資料模式。')
                )
            }

            return initializationPromise
        }

        initializationMode = mode
        loadStatus.value = 'loading'
        loadError.value = null

        initializationPromise = Promise.resolve().then(async () => {
            try {
                const loadedGames = await loadGames(mode)

                games.value = loadedGames
                source.value = mode
                loadStatus.value = 'ready'

                return games.value
            } catch (error) {
                // 不用原本的 Demo 資料冒充載入成功。
                games.value = []
                source.value = null
                loadStatus.value = 'error'
                loadError.value = {
                    code: error.code ?? 'GAMES_LOAD_FAILED',
                    message: error.message ?? '遊戲資料載入失敗。',
                }

                throw error
            }
        })

        return initializationPromise
    }

    function searchGames(keyword) {
        const searchText = keyword.trim().toLowerCase()

        if (!searchText) {
            return games.value
        }

        return games.value.filter(game => {
            return (
                game.title.toLowerCase().includes(searchText) ||
                game.titleZh.toLowerCase().includes(searchText)
            )
        })
    }


    function getGamesByGenre(genre) {
        if (!genre) {
            return games.value
        }

        return games.value.filter(game => {
            return game.genre.includes(genre)
        })
    }

    function getNewGames() {
        return games.value.filter(game => {
            return game.status.includes('new')
        })
    }

    function getBestsellers() {
        return games.value.filter(game => {
            return game.status.includes('bestseller')
        })
    }

    function getFeaturedGames() {
        return games.value.filter(game => {
            return game.status.includes('featured')
        })
    }

    return {
        games,
        getGameById,
        searchGames,
        getGamesByGenre,
        getNewGames,
        getBestsellers,
        getFeaturedGames,
        source,
        loadStatus,
        loadError,
        initialize
    }
})

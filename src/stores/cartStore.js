import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useGameStore } from './gameStore'
import { useAuthStore } from './authStore'

export const useCartStore = defineStore('cart', () => {
    const cart = ref([])
    const source = ref('demo')

    let initializationMode = null
    let isRestoring = false

    function storageKey() {
        return source.value === 'database'
            ? 'lifecharge-database-cart'
            : 'lifecharge-cart'
    }

    watch(
        cart,
        value => {
            if (isRestoring) return

            sessionStorage.setItem(
                storageKey(),
                JSON.stringify(value)
            )
        },
        { deep: true, flush: 'sync' }
    )

    const cartCount = computed(() => {
        return cart.value.length
    })

    const gameStore = useGameStore()
    const authStore = useAuthStore()

    function isInCart(gameId) {
        const id = Number(gameId)

        return cart.value.some(item => item.gameId === id)
    }

    function addToCart(gameId) {
        const id = Number(gameId)

        if (!gameStore.getGameById(id)) return false
        if (isInCart(id)) return false
        if (authStore.purchasedGames.includes(id)) return false

        cart.value.push({ gameId: id })
        return true
    }

    const cartGames = computed(() => {
        return cart.value.map(item => {
            const game = gameStore.getGameById(item.gameId)

            if (!game) {
                throw new Error(`購物車找不到遊戲：${item.gameId}`)
            }

            return game
        })
    })

    function removeFromCart(gameId) {
        const id = Number(gameId)

        cart.value = cart.value.filter(item => {
            return item.gameId !== id
        })
    }

    const cartTotal = computed(() => {
        return cartGames.value.reduce((total, game) => {
            return total + game.price
        }, 0)
    })

    function removePurchasedGames() {
        cart.value = cart.value.filter(item => {
            return !authStore.purchasedGames.includes(item.gameId)
        })
    }

    function restoreCart() {
        const savedCart = sessionStorage.getItem(storageKey())
        if (savedCart === null) return

        try {
            const parsedCart = JSON.parse(savedCart)

            if (!Array.isArray(parsedCart)) {
                throw new Error('購物車資料格式錯誤')
            }

            const seenIds = new Set()

            cart.value = parsedCart.filter(item => {
                const id = item?.gameId

                if (!Number.isInteger(id)) return false
                if (!gameStore.getGameById(id)) return false
                if (seenIds.has(id)) return false

                seenIds.add(id)
                return true
            }).map(item => ({
                gameId: item.gameId
            }))

            removePurchasedGames()
        } catch {
            cart.value = []
            sessionStorage.removeItem(storageKey())
        }
    }

    function initialize(mode) {
        if (mode !== 'demo' && mode !== 'database') {
            throw new Error('購物車模式不正確。')
        }

        if (initializationMode !== null) {
            if (initializationMode !== mode) {
                throw new Error('同一次初始化不能切換購物車模式。')
            }

            return
        }

        if (
            gameStore.source !== mode
            || gameStore.loadStatus !== 'ready'
            || authStore.source !== mode
            || authStore.loadStatus !== 'ready'
            || authStore.isBusy
        ) {
            throw new Error('請先完成相同模式的遊戲與會員初始化。')
        }

        isRestoring = true

        try {
            source.value = mode
            cart.value = []
            restoreCart()
        } finally {
            isRestoring = false
        }

        // 保存驗證、去重與排除已擁有遊戲後的清單。
        sessionStorage.setItem(
            storageKey(),
            JSON.stringify(cart.value)
        )

        initializationMode = mode
    }

    return {
        cart,
        cartCount,
        isInCart,
        addToCart,
        cartGames,
        removeFromCart,
        cartTotal,
        removePurchasedGames,
        restoreCart,
        source,
        initialize
    }
})
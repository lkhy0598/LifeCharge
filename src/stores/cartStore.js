import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useGameStore } from './gameStore'
import { useAuthStore } from './authStore'

export const useCartStore = defineStore('cart', () => {
    const cart = ref([])
    const CART_STORAGE_KEY = 'lifecharge-cart'

    watch(
        cart,
        value => {
            sessionStorage.setItem(CART_STORAGE_KEY, JSON.stringify(value))
        },
        { deep: true }
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
        const savedCart = sessionStorage.getItem(CART_STORAGE_KEY)
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
            sessionStorage.removeItem(CART_STORAGE_KEY)
        }
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
        restoreCart
    }
})
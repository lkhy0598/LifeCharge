import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import usersData from '../data/users.json'
import { useGameStore } from './gameStore'

export const useAuthStore = defineStore('auth', () => {
    const users = ref(usersData)
    const currentUser = ref(null)
    const LOGIN_STORAGE_KEY = 'lifecharge-current-user-id'
    const PURCHASES_STORAGE_KEY = 'lifecharge-user-purchases'

    const isLoggedIn = computed(() => {
        return currentUser.value !== null
    })

    const purchasedGames = computed(() => {
        return currentUser.value?.purchasedGames ?? []
    })

    function login(username, password) {
        const user = users.value.find(user => {
            return user.username === username.trim() &&
                user.password === password
        })

        if (!user) {
            return false
        }

        currentUser.value = {
            id: user.id,
            username: user.username,
            email: user.email,
            displayName: user.displayName,
            avatar: user.avatar,
            purchasedGames: [...user.purchasedGames]
        }

        sessionStorage.setItem(LOGIN_STORAGE_KEY, String(user.id))

        return true
    }

    function logout() {
        currentUser.value = null
        sessionStorage.removeItem(LOGIN_STORAGE_KEY)
    }

    function restoreSession() {
        restorePurchases()
        const savedId = sessionStorage.getItem(LOGIN_STORAGE_KEY)

        if (savedId === null) return

        const user = users.value.find(user => {
            return String(user.id) === savedId
        })

        if (!user) {
            sessionStorage.removeItem(LOGIN_STORAGE_KEY)
            return
        }

        currentUser.value = {
            id: user.id,
            username: user.username,
            email: user.email,
            displayName: user.displayName,
            avatar: user.avatar,
            purchasedGames: [...user.purchasedGames]
        }
    }

    const isLoginOpen = ref(false)
    const loginDestination = ref(null)

    function requestLogin(destination = null) {
        loginDestination.value = destination
        isLoginOpen.value = true
    }

    function cancelLogin() {
        isLoginOpen.value = false
        loginDestination.value = null
    }

    function purchaseGames(gameIds) {
        if (!currentUser.value) {
            throw new Error('請先登入')
        }

        const gameStore = useGameStore()
        const ids = [...new Set(gameIds)]

        if (ids.some(id => !gameStore.getGameById(id))) {
            throw new Error('購買清單包含不存在的遊戲')
        }

        const newIds = ids.filter(id => {
            return !currentUser.value.purchasedGames.includes(id)
        })

        const purchasedIds = [
            ...currentUser.value.purchasedGames,
            ...newIds
        ]

        const user = users.value.find(user => {
            return user.id === currentUser.value.id
        })

        // 先保存；失敗會拋出錯誤，不執行後面的更新
        savePurchases(user.id, purchasedIds)

        // 保存成功後，才更新畫面使用的資料
        user.purchasedGames = [...purchasedIds]
        currentUser.value.purchasedGames = [...purchasedIds]

        return newIds
    }

    function savePurchases(userId, purchasedIds) {
        const records = {}

        users.value.forEach(user => {
            records[user.id] = user.id === userId
                ? [...purchasedIds]
                : [...user.purchasedGames]
        })

        sessionStorage.setItem(
            PURCHASES_STORAGE_KEY,
            JSON.stringify(records)
        )
    }

    function restorePurchases() {
        const saved = sessionStorage.getItem(PURCHASES_STORAGE_KEY)
        if (saved === null) return

        try {
            const records = JSON.parse(saved)

            if (!records || typeof records !== 'object' || Array.isArray(records)) {
                throw new Error('購買紀錄格式錯誤')
            }

            const gameStore = useGameStore()

            users.value.forEach(user => {
                const savedIds = records[user.id]

                if (!Array.isArray(savedIds)) return

                const validIds = savedIds.filter(id => {
                    return Number.isInteger(id) && gameStore.getGameById(id)
                })

                user.purchasedGames = [
                    ...new Set([...user.purchasedGames, ...validIds])
                ]
            })
        } catch {
            sessionStorage.removeItem(PURCHASES_STORAGE_KEY)
        }
    }

    return {
        users,
        currentUser,
        isLoggedIn,
        purchasedGames,
        login,
        logout,
        restoreSession,
        isLoginOpen,
        loginDestination,
        requestLogin,
        cancelLogin,
        purchaseGames
    }
})
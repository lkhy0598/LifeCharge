import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import usersData from '../data/users.json'
import { useGameStore } from './gameStore'
import {
    normalizeDemoMember,
    getDatabaseMember,
    loginDatabaseMember,
    registerDatabaseMember,
    logoutDatabaseMember,
    recoverDatabaseSession,
} from '../services/authService'

export const useAuthStore = defineStore('auth', () => {
    const users = ref(usersData.map(user => ({
        ...user,
        purchasedGames: [...user.purchasedGames],
    })))
    const currentUser = ref(null)
    const source = ref('demo')
    const isBusy = ref(false)
    const loadStatus = ref('ready')
    const loadError = ref(null)

    let initializationPromise = null
    let initializationMode = null
    const LOGIN_STORAGE_KEY = 'lifecharge-current-user-id'
    const PURCHASES_STORAGE_KEY = 'lifecharge-user-purchases'

    const isLoggedIn = computed(() => {
        return currentUser.value !== null
    })

    const purchasedGames = computed(() => {
        return currentUser.value?.purchasedGames ?? []
    })

    function loginDemo(username, password) {
        const identifier = username.trim().toLowerCase()

        const user = users.value.find(user => {
            const identityMatches =
                user.username.toLowerCase() === identifier
                || user.email.toLowerCase() === identifier

            return identityMatches && user.password === password
        })

        if (!user) {
            return false
        }

        currentUser.value = normalizeDemoMember(user)

        sessionStorage.setItem(LOGIN_STORAGE_KEY, String(user.id))

        return true
    }

    function logoutDemo() {
        currentUser.value = null
        sessionStorage.removeItem(LOGIN_STORAGE_KEY)
    }
    async function runMemberAction(action) {
        if (loadStatus.value !== 'ready') {
            throw new Error('會員資料尚未就緒，請重新載入後再試。')
        }

        if (isBusy.value) {
            throw new Error('會員操作正在處理中，請稍候。')
        }

        isBusy.value = true

        try {
            return await action()
        } catch (error) {
            if (
                source.value === 'database'
                && error.status === 403
                && error.code === 'CSRF_TOKEN_INVALID'
            ) {
                const previousId = currentUser.value?.id ?? null

                // 若重新驗證失敗，會直接拋出該錯誤，
                // 不清除原身份，也不假裝驗證成功。
                const user = await recoverDatabaseSession()

                currentUser.value = user

                if (user === null) {
                    error.message =
                        '目前未登入或登入已過期，請重新登入後再操作。'
                } else if (user.id !== previousId) {
                    error.message =
                        '登入身份已更新，請確認目前會員後重新操作。'
                } else {
                    error.message =
                        '操作驗證已更新，請重新確認後再操作。'
                }
            }

            // 不重新呼叫 action，原操作仍以錯誤結束。
            throw error
        } finally {
            isBusy.value = false
        }
    }

    async function login(identifier, password) {
        return runMemberAction(async () => {
            if (source.value === 'demo') {
                return loginDemo(identifier, password)
            }

            if (source.value !== 'database') {
                throw new Error('會員資料模式尚未就緒。')
            }

            const user = await loginDatabaseMember(identifier, password)
            currentUser.value = user

            return true
        })
    }

    async function logout() {
        return runMemberAction(async () => {
            if (source.value === 'demo') {
                logoutDemo()
                return
            }

            if (source.value !== 'database') {
                throw new Error('會員資料模式尚未就緒。')
            }

            await logoutDatabaseMember()

            // 後端確認登出成功，才清除前端身份。
            currentUser.value = null
        })
    }

    async function register(input) {
        return runMemberAction(async () => {
            if (source.value !== 'database') {
                throw new Error('目前模式不提供會員註冊。')
            }

            const user = await registerDatabaseMember(input)
            currentUser.value = user

            return true
        })
    }
    function restoreSession() {
        if (source.value !== 'demo') {
            throw new Error('Database 身份必須由後端 session 恢復。')
        }

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

        currentUser.value = normalizeDemoMember(user)
    }

    function initialize(mode) {
        if (mode !== 'demo' && mode !== 'database') {
            return Promise.reject(new Error('會員資料模式不正確。'))
        }

        if (initializationPromise) {
            if (mode !== initializationMode) {
                return Promise.reject(
                    new Error('同一次初始化不能切換會員資料模式。')
                )
            }

            return initializationPromise
        }

        if (isBusy.value) {
            return Promise.reject(
                new Error('會員操作尚未完成，不能開始初始化。')
            )
        }

        const gameStore = useGameStore()

        if (
            gameStore.source !== mode
            || gameStore.loadStatus !== 'ready'
        ) {
            return Promise.reject(
                new Error('請先完成相同模式的遊戲資料初始化。')
            )
        }

        initializationMode = mode
        source.value = mode
        currentUser.value = null
        loadStatus.value = 'loading'
        loadError.value = null
        isBusy.value = true

        initializationPromise = Promise.resolve().then(async () => {
            try {
                if (mode === 'demo') {
                    restoreSession()
                } else {
                    currentUser.value = await getDatabaseMember()
                }

                loadStatus.value = 'ready'
                return currentUser.value
            } catch (error) {
                currentUser.value = null
                loadStatus.value = 'error'
                loadError.value = {
                    code: error.code ?? 'MEMBER_LOAD_FAILED',
                    message: error.message ?? '會員資料載入失敗。',
                }

                throw error
            } finally {
                isBusy.value = false
            }
        })

        return initializationPromise
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
        if (source.value !== 'demo') {
            throw new Error('Database 購買功能尚未接入。')
        }

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

    function applyDatabasePurchaseResult(result, expectedUserId) {
        if (
            source.value !== 'database'
            || loadStatus.value !== 'ready'
            || !currentUser.value
            || currentUser.value.id !== expectedUserId
            || result?.userId !== expectedUserId
        ) {
            throw new Error('會員身份已變更，請重新確認登入與購買結果。')
        }

        const ids = result.purchasedGames

        if (
            !Array.isArray(ids)
            || !ids.every(id => Number.isInteger(id) && id > 0)
            || new Set(ids).size !== ids.length
        ) {
            throw new Error('購買結果的遊戲清單格式不正確。')
        }

        currentUser.value = {
            ...currentUser.value,
            purchasedGames: [...ids],
        }
    }

    async function revalidateDatabaseMember() {
        return runMemberAction(async () => {
            if (source.value !== 'database') {
                throw new Error('目前不是 Database 會員模式。')
            }

            const user = await recoverDatabaseSession()

            // 確認身份與 token 均可取得後，才同步狀態。
            currentUser.value = user

            return user
        })
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
        purchaseGames,
        source,
        isBusy,
        register,
        loadStatus,
        loadError,
        initialize,
        applyDatabasePurchaseResult,
        revalidateDatabaseMember,
    }
})
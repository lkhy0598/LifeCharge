import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError } from '../services/apiClient'
import { checkBackendHealth } from '../services/healthService'

export const useSystemStore = defineStore('system', () => {
    const mode = ref('checking')
    const status = ref('idle')
    const demoReason = ref(null)
    const initializationError = ref(null)

    // 同一次啟動共用同一個 Promise。
    let initializationPromise = null

    const isDemo = computed(() => mode.value === 'demo')
    const isDatabase = computed(() => mode.value === 'database')

    function initialize() {
        if (initializationPromise) {
            return initializationPromise
        }

        status.value = 'checking'

        initializationPromise = Promise.resolve().then(async () => {
            try {
                // 目前正式建置固定 Demo，不探測訪客的 localhost。
                if (!import.meta.env.DEV) {
                    mode.value = 'demo'
                    demoReason.value = 'static-build'
                } else {
                    const health = await checkBackendHealth()

                    mode.value = health.ready ? 'database' : 'demo'
                    demoReason.value = health.ready ? null : 'backend-not-ready'
                }

                status.value = 'ready'
                return mode.value
            } catch (error) {
                // 認證、權限或業務衝突不是「後端離線」。
                if (
                    error instanceof ApiError
                    && [400, 401, 403, 409, 429].includes(error.status)
                ) {
                    initializationError.value = {
                        code: error.code,
                        message: error.message,
                    }

                    status.value = 'error'
                    throw error
                }

                // 只在啟動判斷階段，因不可用或無效回應選擇 Demo。
                mode.value = 'demo'
                demoReason.value = error instanceof ApiError
                    ? error.code
                    : 'HEALTH_CHECK_FAILED'

                status.value = 'ready'
                return mode.value
            }
        })

        return initializationPromise
    }

    return {
        mode,
        status,
        demoReason,
        initializationError,
        isDemo,
        isDatabase,
        initialize,
    }
})
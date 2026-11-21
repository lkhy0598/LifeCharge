// 本檔決定本次啟動是 Demo 還是 Database；由 main.js 呼叫。
// Store 是 Pinia 管理的共用狀態，讓不同 Vue 畫面使用同一份資料。
// defineStore 定義它，use...Store() 取得目前網站的實例；不是每次呼叫都建立另一份會員。
// ref() 建立畫面會跟著更新的資料，JS 內用 .value 讀寫；computed() 依資料算出結果。
// return {...} 列出供其他檔案使用的資料與函式，沒有列出的只在這個 Store 內使用。
// Promise 是尚待完成的工作；async／await 等待網路結果，catch 接失敗，finally 負責收尾。

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { ApiError } from '../services/apiClient'
import { checkBackendHealth } from '../services/healthService'

export const useSystemStore = defineStore('system', () => {
    // checking 是還在判斷，status 描述工作進度；demoReason 記錄選 Demo 的原因。
    // 這裡 ready 只表示模式決定，整站仍須等 main.js 載入遊戲、會員與購物車。
    const mode = ref('checking')
    // idle 尚未開始、checking 檢查中、ready 模式決定、error 判斷失敗；不是所有商品已載完的標記。
    const status = ref('idle')
    // 保存選 Demo 的原因供測試與排查；initializationError 是需要顯示失敗的錯誤資訊，起初均為 null。
    const demoReason = ref(null)
    const initializationError = ref(null)

    // 同一次啟動共用同一個 Promise。
    let initializationPromise = null

    // computed 把 mode 文字轉成方便畫面使用的真假值；isDatabase 同理，不另外保存第二份模式。
    const isDemo = computed(() => mode.value === 'demo')
    const isDatabase = computed(() => mode.value === 'database')

    // 用途：啟動時決定一次模式，回傳等待中的工作；重複呼叫共用同一份 Promise。
    // 正式 build 固定 Demo，dev 才查健康狀態。400／401／403／409／429 是請求或業務錯誤，
    // 不能拿來當後端離線偷偷退回 Demo；其他不可用狀態只在啟動階段選 Demo。
    function initialize() {
        // 有工作就原樣回傳，無論它還在等、已成功或已失敗；避免多個地方各發一次健康檢查。
        if (initializationPromise) {
            return initializationPromise
        }

        // 正式開始檢查前先更新進度；改 ref 透過 .value，讀取這個狀態的畫面才會跟著更新。
        status.value = 'checking'

        // 把工作放進 Promise，讓多個呼叫者等待相同結果，不重複發健康檢查。
        initializationPromise = Promise.resolve().then(async () => {
            try {
                // 目前正式建置固定 Demo，不探測訪客的 localhost。
                // Vite 在 build 時提供 DEV；!DEV 表示正式建置／preview，直接固定 Demo。
                // 訪客不需本機資料庫，也不會對他的 localhost 探測 PHP。
                if (!import.meta.env.DEV) {
                    // 正式建置的 Demo 不算故障，下面將原因設為 static-build，便於測試識別。
                    mode.value = 'demo'
                    demoReason.value = 'static-build'
                } else {
                    // 只有 dev 才等待真正健康檢查；await 失敗進 catch，成功後 ready 決定 Database 或 Demo。
                    const health = await checkBackendHealth()

                    // 三元條件：ready true 選 database，false 選 demo；demoReason 同步記錄後端尚未就緒。
                    mode.value = health.ready ? 'database' : 'demo'
                    demoReason.value = health.ready ? null : 'backend-not-ready'
                }

                // 模式已確定才標 ready 並 return；main.js 等到它後才繼續載入遊戲與會員。
                status.value = 'ready'
                return mode.value
            } catch (error) {
                // 認證、權限或業務衝突不是「後端離線」。
                if (
                    // instanceof 檢查是不是專案 API 錯誤；includes 判斷 HTTP 狀態是否在業務／身份錯誤清單。
                    // 這些錯誤不能解讀成後端離線，必須通知入口失敗。
                    error instanceof ApiError
                    && [400, 401, 403, 409, 429].includes(error.status)
                ) {
                    // 保存錯誤碼與人可讀訊息，再設 error 並 throw；不能只改狀態卻讓等待者誤以為成功。
                    initializationError.value = {
                        code: error.code,
                        message: error.message,
                    }

                    status.value = 'error'
                    throw error
                }

                // 只在啟動判斷階段，因不可用或無效回應選擇 Demo。
                mode.value = 'demo'
                // 處理其他不可用錯誤時，能取得 ApiError.code 就保留，否則使用 HEALTH_CHECK_FAILED。
                // 這個 fallback 只在啟動階段，購買中途不會呼叫這裡切 Demo。
                demoReason.value = error instanceof ApiError
                    ? error.code
                    : 'HEALTH_CHECK_FAILED'

                status.value = 'ready'
                return mode.value
            }
        })

        return initializationPromise
    }

    // 只提供模式狀態與 initialize；這個 Store 不管理購物車、付款或登入視窗。
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
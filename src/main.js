// 本檔是正式網站的啟動入口，瀏覽器載入後從這裡建立 Vue 應用。
// 流程：顯示需要的啟動畫面 → 判斷模式 → 遊戲 → 會員 → 購物車 → 路由 → 正式 App。
// import 引用其他檔案；createApp 建立應用；Pinia 保存共用狀態；Vuetify 提供畫面元件。
// h 是 Vue 內建的渲染函式，h(App) 顯示 App.vue，h(StartupScreen, 資料) 顯示啟動畫面。
// ref 建立能觸發畫面更新的值，在這個 JS 檔透過 .value 讀寫，不直接操作網頁 HTML。

import { createApp, h, ref } from 'vue'
import App from './App.vue'
import StartupScreen from './components/StartupScreen.vue'

// Vue Router
import router from './router'

// Pinia
import { createPinia } from 'pinia'

// 全域 CSS
import './assets/styles/reset.css'
import './assets/styles/style.scss'

// Vuetify
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

import {
    VAlert, VApp, VAppBar, VAvatar, VBadge, VBreadcrumbs,
    VBtn, VCard, VCardText, VCardTitle,
    VCarousel, VCarouselItem, VChip, VChipGroup, VCol,
    VDialog, VDivider, VFooter, VForm, VIcon, VImg,
    VListItem, VListItemTitle, VMain, VNavigationDrawer,
    VRow, VSelect, VSheet, VSnackbar, VSpacer,
    VTab, VTabs, VTextField,
} from 'vuetify/components'

// Stores 與啟動設定
import { useAuthStore } from './stores/authStore'
import { useCartStore } from './stores/cartStore'
import { useGameStore } from './stores/gameStore'
import { useSystemStore } from './stores/systemStore'
import { STARTUP_MIN_DURATION_MS } from './config/system'

// 建立這個網站的共用狀態管理器；所有 use...Store 都屬於這份實例，不能每頁重建讓資料分裂。
const pinia = createPinia()

// 建立 UI 元件工具並明確註冊本專案會用的元件；只是設定畫面工具，還沒有顯示正式 App。
const vuetify = createVuetify({
    components: {
        VAlert, VApp, VAppBar, VAvatar, VBadge, VBreadcrumbs,
        VBtn, VCard, VCardText, VCardTitle,
        VCarousel, VCarouselItem, VChip, VChipGroup, VCol,
        VDialog, VDivider, VFooter, VForm, VIcon, VImg,
        VListItem, VListItemTitle, VMain, VNavigationDrawer,
        VRow, VSelect, VSheet, VSnackbar, VSpacer,
        VTab, VTabs, VTextField,
    },
    // 預設使用 light 主題，primary 是網站主要按鈕顏色，on-primary 是其上文字顏色。
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    primary: '#d94b39',
                    'on-primary': '#ffffff',
                },
            },
        },
    },
    icons: {
        defaultSet: 'mdi',
    },
    // 統一元件預設外觀，例如 VCard 不顯示預設陰影，避免每張卡片重複設定。
    defaults: {
        VCard: {
            elevation: 0,
        },
    },
})

// 決定目前顯示啟動畫面，還是正式網站。
// 記住此分頁上次成功進入的模式。
// 這個 key 只記上次成功的模式，讓同分頁重新整理不用一直看提示。
// 不保存登入證明，也不跳過健康檢查；sessionStorage 是目前分頁的瀏覽器暫存。
const startupModeKey = 'lifecharge-startup-mode'
// 先用 null 表示這個分頁沒有可用的先前模式；只有暫存值正好 demo 或 database 才接受。
let previousMode = null

try {
    // 讀取只供提示判斷的 key，不把這個值當後端目前可用的證據；每次仍重新做健康檢查。
    const savedMode = sessionStorage.getItem(startupModeKey)

    if (savedMode === 'demo' || savedMode === 'database') {
        previousMode = savedMode
    }
// 瀏覽器可能不允許 sessionStorage；讀取失敗保留 previousMode null，仍可用完整提示正常啟動。
} catch {
    // 無法使用暫存時，仍可正常啟動。
}

// 資料與初始路由都完成才 true；startupError 保存失敗文字，startupMessage 保存模式與進度。
const startupReady = ref(false)
const startupError = ref('')
const startupMessage = ref('正在判斷 DEMO 或 DATABASE 模式…')
// 第一次進入才立即顯示提示；有先前模式時先不顯示，等模式判斷後確認是否需要提醒改變。
const showStartupScreen = ref(previousMode === null)

// 這個最外層先選擇要渲染哪個元件。startupReady 之前不建立 App，避免資料未就緒就顯示。
// 同模式暫不顯示任何元件，所以較慢時可能空白；出錯仍會顯示 StartupScreen。
const app = createApp({
    setup() {
        // setup 回傳渲染函式，Vue 在讀到的 ref 改變時重新執行它，決定要顯示哪個元件。
        return () => {
            // 全部初始化成功顯示 App；這之前 NavBar／RouterView 等正式元件不會先出現半套資料。
            if (startupReady.value) {
                return h(App)
            }

            // 首次或模式改變顯示提示；就算原本略過提示，發生錯誤仍顯示錯誤畫面供重新載入。
            if (showStartupScreen.value || startupError.value) {
                return h(StartupScreen, {
                    message: startupMessage.value,
                    error: startupError.value,
                })
            }

            // 同模式重整時還在等資料就暫不渲染內容，因此可能短暫空白，不是取舊會員資料直接顯示。
            return null
        }
    },
})

// use 安裝工具，mount 將畫面放進 index.html 的 #app 容器。
// Router 延後到身份完成才安裝，避免受保護路由提早誤判登入狀態。
app.use(pinia)
app.use(vuetify)
app.mount('#app')

// 用途：交回一個計時中的 Promise，STARTUP_MIN_DURATION_MS 毫秒後完成。
// resolve 是通知「等完了」的函式；1 秒為 1000 毫秒，不會阻塞背景資料請求。
function waitForStartupScreen() {
    return new Promise(resolve => {
        window.setTimeout(resolve, STARTUP_MIN_DURATION_MS)
    })
}

// 用途：完成整站初始化，成功才切換顯示正式頁面。
// 第一次提示計時與資料載入並行；同模式重整不固定等，模式改變才重新顯示提示與計時。
// async 函式內用 await 等各步完成；任一步失敗會跳 catch 顯示錯誤，而非混用其他模式資料。
async function startApplication() {
    // 第一次進入時，顯示提示並同時開始計時。
    let minimumDisplay = showStartupScreen.value
        ? waitForStartupScreen()
        : Promise.resolve()

    try {
        const systemStore = useSystemStore(pinia)
        const gameStore = useGameStore(pinia)
        const authStore = useAuthStore(pinia)
        const cartStore = useCartStore(pinia)

        // 每次啟動都重新檢查，不直接沿用上次模式。
        // 先讓 systemStore 決定模式；即使有 previousMode，也不能直接假定後端還能用。
        const mode = await systemStore.initialize()
        // 必須曾成功載入且這次模式不同才算切換；首次 null 不當作 Database 切 Demo 的事件。
        const modeChanged =
            previousMode !== null && previousMode !== mode

        // 模式真的改變才開提示並重新開始最短顯示倒數；相同模式不強迫再等兩秒。
        if (modeChanged) {
            showStartupScreen.value = true
            minimumDisplay = waitForStartupScreen()
        }

        // 只是將內部小寫代碼換成顯示文字，不更動 Store 的模式值。
        const modeLabel = mode === 'database' ? 'DATABASE' : 'DEMO'

        startupMessage.value = modeChanged
            ? `目前切換為 ${modeLabel} 模式，正在載入資料…`
            : `目前使用 ${modeLabel} 模式，正在載入資料…`

        // 先載入商品，會員擁有權與購物車才能核對遊戲是否存在。
        // 每個 Store 使用同一模式，避免 JSON 會員搭配 Database 購物車。
        await gameStore.initialize(mode)
        await authStore.initialize(mode)
        cartStore.initialize(mode)

        // 身份確認後才啟動路由。
        // 到這一步會員初始化已完成才執行路由守衛，避免會員頁被未就緒的 currentUser 誤擋。
        app.use(router)
        // 等待初次網址解析、必要元件與守衛完成，再等最短提示時間；計時與前面載入是重疊進行。
        await router.isReady()
        await minimumDisplay

        // 只有成功載入，才記錄這次模式。
        try {
            // 只有所有資料與初始路由成功後才記模式；暫存無法使用也不阻止網站進入。
            sessionStorage.setItem(startupModeKey, mode)
        } catch {
            // 暫存失敗不影響網站使用。
        }

        // 全部完成後才切正式 App；成功才記模式，錯誤時不更新成一個從沒成功進入的模式。
        startupReady.value = true
    // 任何初始化失敗都走這裡，顯示重新載入按鈕；不在 Database 已選定後再偷偷換 Demo。
    } catch (error) {
        await minimumDisplay

        console.error('網站啟動失敗：', error)
        startupError.value =
            '網站資料暫時無法載入，請確認服務恢復後重新載入。'
    }
}

// 呼叫啟動函式。void 表示此處不使用它回傳的 Promise；函式內已用 catch 處理失敗。
void startApplication()
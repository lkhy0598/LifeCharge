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

const pinia = createPinia()

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
    defaults: {
        VCard: {
            elevation: 0,
        },
    },
})

// 決定目前顯示啟動畫面，還是正式網站。
// 記住此分頁上次成功進入的模式。
const startupModeKey = 'lifecharge-startup-mode'
let previousMode = null

try {
    const savedMode = sessionStorage.getItem(startupModeKey)

    if (savedMode === 'demo' || savedMode === 'database') {
        previousMode = savedMode
    }
} catch {
    // 無法使用暫存時，仍可正常啟動。
}

const startupReady = ref(false)
const startupError = ref('')
const startupMessage = ref('正在判斷 DEMO 或 DATABASE 模式…')
const showStartupScreen = ref(previousMode === null)

const app = createApp({
    setup() {
        return () => {
            if (startupReady.value) {
                return h(App)
            }

            if (showStartupScreen.value || startupError.value) {
                return h(StartupScreen, {
                    message: startupMessage.value,
                    error: startupError.value,
                })
            }

            return null
        }
    },
})

app.use(pinia)
app.use(vuetify)
app.mount('#app')

function waitForStartupScreen() {
    return new Promise(resolve => {
        window.setTimeout(resolve, STARTUP_MIN_DURATION_MS)
    })
}

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
        const mode = await systemStore.initialize()
        const modeChanged =
            previousMode !== null && previousMode !== mode

        if (modeChanged) {
            showStartupScreen.value = true
            minimumDisplay = waitForStartupScreen()
        }

        const modeLabel = mode === 'database' ? 'DATABASE' : 'DEMO'

        startupMessage.value = modeChanged
            ? `目前切換為 ${modeLabel} 模式，正在載入資料…`
            : `目前使用 ${modeLabel} 模式，正在載入資料…`

        await gameStore.initialize(mode)
        await authStore.initialize(mode)
        cartStore.initialize(mode)

        // 身份確認後才啟動路由。
        app.use(router)
        await router.isReady()
        await minimumDisplay

        // 只有成功載入，才記錄這次模式。
        try {
            sessionStorage.setItem(startupModeKey, mode)
        } catch {
            // 暫存失敗不影響網站使用。
        }

        startupReady.value = true
    } catch (error) {
        await minimumDisplay

        console.error('網站啟動失敗：', error)
        startupError.value =
            '網站資料暫時無法載入，請確認服務恢復後重新載入。'
    }
}

void startApplication()
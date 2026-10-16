
import { createApp } from 'vue'
import App from './App.vue'

// Vue Router
import router from './router'

// Pinia
import { createPinia } from 'pinia'

// 全域 CSS
import './assets/styles/reset.css'
import './assets/styles/style.scss'

import {
    VAlert, VApp, VAppBar, VAvatar, VBadge, VBreadcrumbs,
    VBtn, VCard, VCardText, VCardTitle,
    VCarousel, VCarouselItem, VChip, VChipGroup, VCol,
    VDialog, VDivider, VFooter, VForm, VIcon, VImg,
    VListItem, VListItemTitle, VMain, VNavigationDrawer,
    VRow, VSelect, VSheet, VSnackbar, VSpacer,
    VTab, VTabs, VTextField
} from 'vuetify/components'

// Vuetify
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

import { useAuthStore } from './stores/authStore'
import { useCartStore } from './stores/cartStore'

// 建立 Pinia
const pinia = createPinia()

// 建立 Vuetify
const vuetify = createVuetify({
    components: {
        VAlert, VApp, VAppBar, VAvatar, VBadge, VBreadcrumbs,
        VBtn, VCard, VCardText, VCardTitle,
        VCarousel, VCarouselItem, VChip, VChipGroup, VCol,
        VDialog, VDivider, VFooter, VForm, VIcon, VImg,
        VListItem, VListItemTitle, VMain, VNavigationDrawer,
        VRow, VSelect, VSheet, VSnackbar, VSpacer,
        VTab, VTabs, VTextField
    },
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    primary: '#d94b39',
                    'on-primary': '#ffffff'
                }
            }
        }
    },
    icons: {
        defaultSet: 'mdi'
    },
    defaults: {
        VCard: {
            elevation: 0
        }
    }
})

// 建立 Vue 應用程式
const app = createApp(App)

// 註冊套件

app.use(pinia)

const authStore = useAuthStore(pinia)
authStore.restoreSession()

const cartStore = useCartStore(pinia)
cartStore.restoreCart()

app.use(router)
app.use(vuetify)

// 掛載到 #app
app.mount('#app')




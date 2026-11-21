// 本檔設定「網址對應哪個 Vue 畫面」，以及會員頁／結帳頁的登入保護。
// Router 是頁面路由工具；Hash 路由用網址 # 後面的內容切頁，適合 GitHub Pages 靜態主機。
// const Home = () => import(...) 是需要時才載入元件的函式，讓首頁不用先下載所有頁面。
// 這裡只管前端導覽，真正資料權限仍必須由 PHP 檢查，不能只靠畫面隱藏。

import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
// 箭頭函式被路由需要時才執行 import，稱為延遲載入；不是現在立即把所有頁面都建立。
import { useSystemStore } from '../stores/systemStore.js'

const Home = () => import('../views/Home.vue')
const SearchResults = () => import('../views/SearchResults.vue')
const Member = () => import('../views/Member.vue')
const GameDetail = () => import('../views/GameDetail.vue')
const Checkout = () => import('../views/Checkout.vue')
const DemoAccountNews = () => import('../views/DemoAccountNews.vue')

// path 是網址路徑，component 是要顯示的元件，name 是程式導覽使用的名稱。
// /game/:id 的 :id 表示可變遊戲編號；meta.requiresAuth 是我們加的「必須登入」標記。
const routes = [
    {
        path: '/',
        name: 'Home',
        component: Home
    },
    {
        path: '/search',
        name: 'SearchResults',
        component: SearchResults
    },
    {
        path: '/member',
        name: 'Member',
        component: Member,
        // 這個標記由下方 beforeEach 讀取；只加標記不會自動登入，PHP 也不讀這份前端路由。
        meta: { requiresAuth: true }
    },
    {
        path: '/game/:id',
        name: 'GameDetail',
        component: GameDetail
    },
    {
        path: '/checkout',
        name: 'Checkout',
        component: Checkout,
        meta: { requiresAuth: true }
    },
    {
        path: '/news/demo-account',
        name: 'DemoAccountNews',
        component: DemoAccountNews,
        meta: { demoOnly: true }
    }
]

// 建立 Router，BASE_URL 由 Vite 的 base 設定提供，這裡是 /LifeCharge/。
// scrollBehavior 決定換頁捲動；瀏覽器返回時有 savedPosition 就恢復原位置。
const router = createRouter({
    // Hash 路由將頁面放 # 後，靜態主機只需提供 index.html；BASE_URL 對應 /LifeCharge/ 子路徑。
    history: createWebHashHistory(import.meta.env.BASE_URL),
    routes,
    scrollBehavior(to, from, savedPosition) {
        // 瀏覽器上一頁／下一頁有舊捲動位置時恢復它；一般換頁則回 top 0。
        if (savedPosition) {
            return savedPosition
        }

        return { top: 0, behavior: 'instant' }
    }
})

// 每次換頁前都檢查；to 是目的頁、from 是原頁。
// 未登入卻去受保護頁時先要求登入並記目的地；false 取消跳轉，直接開網址則先顯示首頁。
router.beforeEach((to, from) => {
    // 守衛真正執行時才取得已安裝的 Pinia 身份；main.js 安排先還原會員再安裝 Router。
    const authStore = useAuthStore()
    const systemStore = useSystemStore()

    if (to.meta.demoOnly && !systemStore.isDemo) {
        return { name: 'Home', replace: true }
    }

    // 目的頁需要登入且目前沒身份才擋；首頁、搜尋、詳細頁沒標記就不進這個分支。
    if (to.meta.requiresAuth && !authStore.isLoggedIn) {
        // 記住完整目的路徑含查詢參數，登入成功後能接續，不直接丟失原本要去的頁面。
        authStore.requestLogin(to.fullPath)

        // 已在網站內：取消這次跳轉，保留原頁
        // 有原本網站內頁就 return false 取消這次換頁，保留原畫面；直接進網址沒有原頁則改顯示首頁。
        if (from.matched.length > 0) {
            return false
        }

        // 直接開啟受保護網址：先顯示首頁
        return { path: '/', replace: true }
    }
})

// 將同一個 Router 交給 main.js 安裝，不在此自行 mount 網站。
export default router
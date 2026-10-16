import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
const Home = () => import('../views/Home.vue')
const SearchResults = () => import('../views/SearchResults.vue')
const Member = () => import('../views/Member.vue')
const GameDetail = () => import('../views/GameDetail.vue')
const Checkout = () => import('../views/Checkout.vue')

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
    }
]

const router = createRouter({
    history: createWebHashHistory(import.meta.env.BASE_URL),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition
        }

        return { top: 0, behavior: 'instant' }
    }
})

router.beforeEach((to, from) => {
    const authStore = useAuthStore()

    if (to.meta.requiresAuth && !authStore.isLoggedIn) {
        authStore.requestLogin(to.fullPath)

        // 已在網站內：取消這次跳轉，保留原頁
        if (from.matched.length > 0) {
            return false
        }

        // 直接開啟受保護網址：先顯示首頁
        return { path: '/', replace: true }
    }
})

export default router
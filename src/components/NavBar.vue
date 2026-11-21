<template>
    <v-app-bar tag="header" :height="65" color="var(--color-secondary)" elevation="0">
        <section class="page-container d-flex align-center">
            <RouterLink to="/">
                <img :src="logo" alt="Life Charge" width="180" class="navbar-logo">
            </RouterLink>
            <v-spacer />
            <nav aria-label="主要導覽" class="d-flex align-center ga-2">
                <v-badge class="cart-badge" :content="cartStore.cartCount" :model-value="cartStore.cartCount > 0"
                    color="var(--color-primary)" :offset-x="10" :offset-y="10">
                    <v-btn icon="mdi mdi-cart" aria-label="開啟購物車" @click="openCart" color="var(--color-text)"
                        :disabled="route.name === 'Checkout'" />
                </v-badge>
                <v-btn icon aria-label="會員中心或登入" @click="handleMemberClick" color="var(--color-text)">
                    <v-avatar v-if="authStore.isLoggedIn" :size="32">
                        <v-img :src="currentAvatar" :alt="`${authStore.currentUser.displayName}的頭像`" cover />
                    </v-avatar>

                    <v-icon v-else icon="mdi-account" />
                </v-btn>
            </nav>
        </section>
    </v-app-bar>

</template>

<script setup>
// 本元件負責網站上方導覽、購物車入口與會員入口。
// 它通知 App 開啟抽屜或登入視窗，不自己建立另一個登入系統。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { RouterLink, useRouter, useRoute } from 'vue-router'
import logo from '../assets/images/logoWebp/logoDw.webp'
import { useAuthStore } from '../stores/authStore'
import { computed } from 'vue'
import { useCartStore } from '../stores/cartStore'

// route 代表當前網址，template 可依頁面調整導覽外觀；router 則是稍後切換頁面的工具。
const route = useRoute()

// 共用 cartCount 等狀態直接顯示購物車數量，不在導覽自己數另一份清單。
const cartStore = useCartStore()

// defineEmits 列出這個導覽列可以通知 App 的兩件事：開購物車、開登入視窗。真正的開關由 App 管理。
// 這裡的事件就是畫面之間傳話，不是呼叫 PHP，也不會因按了購物車就新增資料庫訂單。
// 對照 App 的 template：@open-cart 接購物車通知，@open-login 接登入通知。
// 只有發出事件還不會開視窗，App 收到後修改開關，才會讓對應元件顯示。
const emit = defineEmits(['open-cart', 'open-login'])

const router = useRouter()
const authStore = useAuthStore()

// 用途：emit 發出 open-cart 事件，交給父元件 App 切換購物車開關。
// 這裡只發通知，不直接修改 App 內的變數。
function openCart() {
    // 向父元件 App 發通知，App 才修改 isCartOpen；這不是送後端請求，也不修改購物車商品。
    emit('open-cart')
}

// 用途：已登入就進會員頁，沒登入就通知父元件打開登入視窗。
// isLoggedIn 由 authStore 計算，不靠頭像有沒有顯示來猜身份。
function handleMemberClick() {
    // 只依目前身份決定進會員頁或要求登入；後端仍會在會員 API 再驗身份，不只靠這個按鈕。
    if (authStore.isLoggedIn) {
        router.push('/member')
    } else {
        emit('open-login')
    }
}

// 從已整理好的會員資料取頭像；?. 在沒有會員時避免存取失敗，?? null 表示沒有就用空值。
const currentAvatar = computed(() => {
    // 有會員取完整頭像網址；無會員回 null，template 顯示登入入口，不讀取空物件屬性。
    // 拆開讀：currentUser 是目前會員；?. 表示有會員才讀 avatar，沒有就得到 undefined。
    // ?? null 表示讀到 null 或 undefined 時改回 null；它沒有在這裡產生預設圖片網址。
    // computed 會追蹤會員變化，登入、登出或換會員後，導覽列的頭像會跟著更新。
    return authStore.currentUser?.avatar ?? null
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.cart-badge {
    :deep(.v-badge__badge) {
        min-width: 16px;
        height: 16px;
        padding-inline: 4px;
        font-size: 10px;
        line-height: 16px;
        color: #fff;
        pointer-events: none;
    }
}

.navbar-logo {
    display: block;

    @include bp.mobile {
        width: 140px;
    }
}
</style>
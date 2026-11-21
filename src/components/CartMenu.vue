<template>
    <v-navigation-drawer v-model="isOpen" location="right" temporary :width="drawerWidth">
        <v-list-item class="align-center mt-5 mb-4">
            <v-list-item-title class="text-headline-small">
                購物車
            </v-list-item-title>
            <template #append>
                <v-btn icon="mdi mdi-close" aria-label="關閉購物車" variant="text" @click="isOpen = false" />
            </template>
        </v-list-item>

        <template v-if="cartStore.cartCount > 0">
            <v-card class="pa-4" v-for="game in cartStore.cartGames" :key="game.id">
                <section class="d-flex align-center ga-5">
                    <v-img :src="game.cover" :alt="`${game.titleZh}封面`" width="80" max-width="80"
                        aspect-ratio="0.75" cover rounded="lg" />
                    <div class="flex-grow-1 d-flex flex-column align-self-stretch">
                        <h3 class="text-title-large">{{ game.titleZh }}</h3>
                        <p class="text-body-medium">{{ game.title }}</p>
                        <v-spacer />
                        <p class="text-body-large font-weight-bold">NT$ {{ game.price.toLocaleString('en-US') }}</p>
                    </div>

                    <div class="d-flex flex-column align-end ">
                        <v-btn icon="mdi mdi-trash-can-outline" :aria-label="`移除${game.titleZh}`" variant="text"
                            size="default" color="var(--color-primary)" @click="cartStore.removeFromCart(game.id)" />
                    </div>
                </section>
            </v-card>
            <v-divider class="mx-4" color="var(--color-muted)" opacity="1" />

            <v-sheet class="pa-4">
                <div class="d-flex mb-4">
                    <p>共 {{ cartStore.cartCount }} 款遊戲</p>
                    <v-spacer />
                    <p class="font-weight-bold" style="color: var(--color-primary)">NT$ {{
                        cartStore.cartTotal.toLocaleString('en-US') }}</p>
                </div>
                <v-btn block color="primary" class="py-4" height="auto" @click="handleCheckout">
                    前往結帳
                </v-btn>
            </v-sheet>
        </template>

        <div v-else class="text-center pa-6">
            <p class="mb-4">目前購物車尚無商品</p>

            <v-btn to="/search" color="primary" rounded="lg" @click="isOpen = false">
                探索遊戲
            </v-btn>
        </div>


    </v-navigation-drawer>
</template>

<script setup>
// 本元件是側邊購物車抽屜，顯示 cartStore 的商品並引導至結帳。
// 抽屜開關由 App 透過 v-model 傳入；不在這裡產生報價或送出購買。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { useCartStore } from '../stores/cartStore'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

// App 和這個抽屜共用 isOpen 開關。把它設 false 就會關抽屜，App 也會同步知道，而不是兩邊各記不同狀態。
// 對照畫面：v-navigation-drawer 的 v-model 讀這個開關。
// 使用者按右上角關閉或開始結帳時設 false，App 也同步知道抽屜關了。
const isOpen = defineModel({ type: Boolean, default: false })
const cartStore = useCartStore()

const router = useRouter()
const authStore = useAuthStore()

// 用途：購物車非空時關閉抽屜，已登入進結帳，否則要求登入並記住 /checkout 目的地。
// 登入完成後由 LoginDialog 接續導頁；空購物車直接 return，不繼續操作。
function handleCheckout() {
    // 空購物車不允許進結帳，return 直接結束；不開登入或取得報價。
    // cartCount 是商品數量；=== 0 表示一款都沒有。
    // 即使畫面上的結帳按鈕已隱藏，函式仍再檢查一次，避免空購物車進入結帳流程。
    if (cartStore.cartCount === 0) return

    // 先關抽屜，再切頁或開登入，避免兩個視窗重疊；defineModel 會把這個值同步給父元件。
    isOpen.value = false

    // 已登入就導向結帳，沒有則記 /checkout 給登入成功後接續；這時還沒有送訂單。
    if (authStore.isLoggedIn) {
        router.push('/checkout')
    } else {
        // 這一行先記住「登入後要去 /checkout」，再要求打開登入視窗。
        // 它不會立刻換到結帳；等 LoginDialog 確認登入成功，才取出這個目的地換頁。
        authStore.requestLogin('/checkout')
    }
}

// useDisplay 取得目前視窗大小，{ xs, width } 是一次拿出兩項資料：xs 告訴我們是否為小螢幕，width 是寬幾個像素。
const { xs, width } = useDisplay()

// 小螢幕抽屜占滿寬度；其他尺寸用視窗的 25%，但至少 440px。
// Math.max 在兩個數字中選較大值；使用者拉大或縮小視窗時，computed 會重新計算抽屜要多寬。
const drawerWidth = computed(() => {
    // 小螢幕直接回 100% 寬度；其他才計算視窗四分之一，Math.max 確保至少 440px。
    // xs 是 Vuetify 判斷目前是否落在最小螢幕範圍的結果，不是自己保存的手機型號。
    // 成立就直接回傳 100%，下面的像素計算不再執行，讓購物車佔滿螢幕。
    if (xs.value) return '100%'

    // width.value 是目前螢幕寬度；乘 0.25 就是取四分之一。
    // 例如 1920 × 0.25 = 480，抽屜寬 480px；1200 × 0.25 = 300，則取較大的 440px。
    // 因此桌面不會小於 440px；視窗大小變動時 computed 會依新寬度重算。
    return Math.max(440, width.value * 0.25)
})

</script>


<style scoped></style>

<template>
    <v-card @click="openGame">
        <v-img :src="props.game.cover" :alt="`${props.game.titleZh}封面`" rounded="lg" :aspect-ratio="3 / 4" cover>
            <div v-if="props.mode === 'store'" class="cover-status">
                <span v-if="isPurchased" class="status-badge is-owned">
                    已購買
                </span>

                <span v-else-if="cartStore.isInCart(props.game.id)" class="status-badge is-cart">
                    已加入購物車
                </span>
            </div>
        </v-img>
        <v-card-text class="game-card-text">
            <section class="d-flex align-center">
                <h3>{{ props.game.titleZh }}</h3>
                <v-spacer />
                <span v-if="props.mode === 'store'">
                    <v-icon icon="mdi-star" size="small" />
                    {{ props.game.rating }}
                </span>

            </section>
            <p>{{ props.game.title }}</p>
            <section v-if="props.mode === 'store'" class="game-price-row d-flex mt-4 align-center">
                <h3>NT$ {{ props.game.price.toLocaleString('en-US') }}</h3>
                <v-spacer />
                <v-btn v-if="!isPurchased && !cartStore.isInCart(props.game.id)" class="cart-button" icon variant="text" :size="32"
                    aria-label="加入購物車" @click.stop="handleAddToCart">
                    <v-icon icon="mdi-cart" :size="24" />
                </v-btn>
            </section>
        </v-card-text>
    </v-card>
</template>

<script setup>
// 本元件可重複顯示一款遊戲，首頁、搜尋與會員清單都能使用。
// 遊戲資料由父元件傳入，不在每張卡片各自呼叫 API 或自行尋找圖片。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'

const cartStore = useCartStore()

// 用途：把父元件傳來的遊戲編號交給 cartStore；實際的重複／已擁有檢查由 Store 處理。
// 這只加入購物車，不建立訂單或直接取得擁有權。
function handleAddToCart() {
    // 把 props 中這款遊戲的 id 傳給共用 Store；能否加入由 Store 驗證，這裡沒有直接 push 改內部資料。
    cartStore.addToCart(props.game.id)
}

// 這張卡片不自己找商品，使用它的頁面要把遊戲資料傳進來，這種傳入資料叫 props；defineProps 列出我們需要哪些資料。
// mode 預設 store 代表商城展示方式，不是 Demo／Database 模式；game 必須是完整遊戲物件。
const props = defineProps({
    // 這裡 mode 是卡片展示模式，不是 systemStore 的 Demo／Database；名稱相同但用途不同。
    mode: {
        type: String,
        default: 'store'
    },
    // 要求父元件提供遊戲物件，required true 讓 Vue 在缺資料時警告；不在卡片裡建立假商品。
    game: {
        type: Object,
        required: true
    }
})

const router = useRouter()

// 用途：導向這款遊戲的詳細頁。反引號中的 ${...} 將編號放入網址，例如 /game/4。
function openGame() {
    // 將遊戲 id 放入 /game/:id 路徑，詳細頁依路由再查同一個 gameStore。
    router.push(`/game/${props.game.id}`)
}

const authStore = useAuthStore()
// 依目前會員擁有權判斷卡片是否顯示「已擁有」；includes 檢查編號是否在清單。
// 換會員或買成功後，這個結果會自動重算，卡片就能從「加入購物車」改顯示「已擁有」。
const isPurchased = computed(() => {
    // includes 比較已擁有清單中的編號；身份／擁有權改變時 computed 重算，按鈕文字也跟著更新。
    // 例如會員已買編號 [1, 4]，這張卡的 id 是 4，includes 就回 true；id 是 8 則回 false。
    // 這個結果只控制卡片顯示；真正加入購物車時，cartStore 仍會再次檢查是否已擁有。
    return authStore.purchasedGames.includes(props.game.id)
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.game-price-row {
    min-height: 32px;
}

.cart-button {
    &:hover {
        color: var(--color-primary);
    }

    &:active {
        background-color: var(--color-secondary);
    }

    &:active :deep(.v-btn__overlay) {
        opacity: 0;
    }

}

.cover-status {
    position: absolute;
    top: 8px;
    right: 8px;
}

.status-badge {
    display: inline-block;
    padding: 6px 8px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;

    &.is-owned {
        color: #486C58;
        background-color: #E3EDE4;
    }

    &.is-cart {
        color: #42648B;
        background-color: #E5EAF1;
    }
}

.game-card-text{
    padding: 0px 6px;
    padding-top: 2px;
}

@include bp.mobile {

    .game-card-text{
    padding: 0px;
    padding-top: 2px;
}
}
</style>

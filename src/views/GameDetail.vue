<template>
    <div class="page-container" v-if="game">
        <section class="mt-8">
            <v-img :src="gamebanner" :alt="`${game.titleZh} Banner`" :aspect-ratio="1920 / 700" />
        </section>
        <v-row class="mt-8">
            <v-col cols="12" md="8">
                <section>
                    <div>
                        <h2>{{ game.titleZh }}</h2>
                        <p>{{ game.title }}</p>
                    </div>
                    <div class="mt-4  d-flex align-center ga-4">
                        <span>
                            <v-icon icon="mdi-star" size="small" />
                            {{ game.rating }}
                        </span>
                        <span class="font-weight-light">{{game.genre.map(genre => genreLabels[genre] ??
                            genre).join('／')}}</span>
                    </div>
                    <div class="mt-4">
                        <h3>遊戲介紹</h3>
                        <p class="mt-2 game-description">
                            {{ game.detailDescription }}
                        </p>
                    </div>
                    <div class="mt-4">
                        <h3>遊戲特色</h3>
                        <div class="d-flex flex-wrap ga-2 mt-2">
                            <v-chip class="feature-chip pa-3 px-8" base-color="var(--color-secondary)" variant="flat"
                                :ripple="false" v-for="tag in game.tags" :key="tag">
                                {{ tagLabels[tag] ?? tag }}
                            </v-chip>
                        </div>
                    </div>
                </section>
            </v-col>
            <v-col cols="12" md="4" class="d-flex">
                <aside aria-label="購買與發行資訊" class="d-flex flex-grow-1">
                    <v-card color="#fff" rounded="xl" class="flex-grow-1 purchase-card">
                        <v-card-text>
                            <h2>NT$ {{ game.price.toLocaleString('en-US') }}</h2>
                            <div class="my-5">
                                <!-- 已購買：優先判斷 -->
                                <v-btn v-if="isPurchased" block disabled height="auto"
                                    class="py-3 purchase-status is-owned" variant="flat">
                                    已購買
                                </v-btn>

                                <!-- 已在購物車 -->
                                <v-btn v-else-if="isInCart" block disabled height="auto"
                                    class="py-3 purchase-status is-cart" variant="flat">
                                    已加入購物車
                                </v-btn>

                                <!-- 可購買 -->
                                <v-btn v-else block height="auto" class="py-3" color="primary"
                                    @click="cartStore.addToCart(game.id)">
                                    加入購物車
                                </v-btn>
                            </div>
                            <dl class="game-metadata">
                                <div class="d-flex justify-space-between mt-4 pt-4">
                                    <dt>開發商</dt>
                                    <dd>{{ game.developer }}</dd>
                                </div>
                                <div class="d-flex justify-space-between mt-4 pt-4">
                                    <dt>發行商</dt>
                                    <dd>{{ game.publisher }}</dd>
                                </div>
                                <div class="d-flex justify-space-between mt-4 pt-4">
                                    <dt>發售日期</dt>
                                    <dd>{{ game.releaseDate }}</dd>
                                </div>
                            </dl>
                        </v-card-text>
                    </v-card>
                </aside>
            </v-col>
        </v-row>
        <section class="mt-10">
            <h3 class="mb-4">遊戲截圖</h3>
            <div class="screenshot-gallery d-flex ga-4">
                <div class="flex-grow-1 screenshot-main">
                    <v-carousel v-model="selectedShot" class="screenshot-carousel" height="auto" hide-delimiters
                        :show-arrows="isLightboxOpen ? false : 'hover'">
                        <v-carousel-item v-for="(shot, index) in screenshots" :key="shot" :src="shot"
                            :alt="`遊戲截圖 ${index + 1}`" role="button" tabindex="0" aria-label="放大遊戲截圖"
                            @click="isLightboxOpen = true" @keydown.enter.prevent="isLightboxOpen = true"
                            @keydown.space.prevent="isLightboxOpen = true" />
                    </v-carousel>
                </div>
                <div class="d-flex flex-column ga-2 screenshot-thumbs">
                    <button v-for="(shot, index) in screenshots" :key="shot" type="button" class="screenshot-thumb"
                        :class="{ active: selectedShot === index }" :aria-label="`查看第 ${index + 1} 張截圖`"
                        :aria-pressed="selectedShot === index" @click="selectedShot = index">
                        <v-img :src="shot" :alt="`截圖 ${index + 1} 縮圖`" :aspect-ratio="16 / 9" rounded="lg" />
                    </button>
                </div>
            </div>
        </section>
    </div>
    <div v-else class="page-container py-10 text-center">
        <h2>找不到這款遊戲</h2>
        <v-btn to="/search" class="mt-4">查看全部遊戲</v-btn>
    </div>
    <v-dialog v-model="isLightboxOpen" fullscreen aria-label="遊戲截圖放大檢視">
        <div class="screenshot-lightbox" @keydown.left.prevent="changeShot(-1)" @keydown.right.prevent="changeShot(1)">
            <v-btn class="lightbox-close" icon="mdi-close" variant="text" color="white" aria-label="關閉放大檢視"
                @click="isLightboxOpen = false" />
            <v-btn class="lightbox-arrow lightbox-prev" icon="mdi-chevron-left" variant="text" aria-label="上一張截圖"
                @click="changeShot(-1)" />
            <v-img class="lightbox-image" :src="screenshots[selectedShot]" :alt="`遊戲截圖 ${selectedShot + 1}`" />
            <v-btn class="lightbox-arrow lightbox-next" icon="mdi-chevron-right" variant="text" aria-label="下一張截圖"
                @click="changeShot(1)" />
        </div>
    </v-dialog>
</template>

<script setup>
// 這是遊戲詳細頁。例如網址最後是 /game/4，就找編號 4 的遊戲，顯示名稱、圖片及是否已購買。
// 遊戲、會員與購物車資料都向全站共用資料取得。切到另一款遊戲時，截圖要回到第一張並關閉放大畫面。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useGameStore } from '../stores/gameStore'
import { genreLabels, tagLabels } from '../utils/gameLabels'
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'

const cartStore = useCartStore()
const authStore = useAuthStore()

// 決定按鈕是否顯示「已擁有」：先看目前是哪款遊戲，再看會員的已購買清單有沒有它。會員或遊戲改變時會重新計算。
const isPurchased = computed(() => {
    // 先問 game.value 有沒有資料。有就執行 ? 後面的購買檢查，沒有就用 : 後面的 false，不能對不存在的遊戲讀 id。
    return game.value
        ? authStore.purchasedGames.includes(game.value.id)
        : false
})

// 決定按鈕是否顯示「已加入」：問購物車有沒有這款遊戲。放進購物車與買成功是兩件事，所以和上面的已擁有分開檢查。
const isInCart = computed(() => {
    // 一樣先確認找到遊戲，才把它的編號交給 isInCart() 查購物車；找不到則回 false，表示不顯示已加入。
    return game.value
        ? cartStore.isInCart(game.value.id)
        : false
})

// selectedShot 記錄「現在選第幾張截圖」。程式從 0 開始數，所以 0 是第一張、1 是第二張；這種位置編號叫索引。
const selectedShot = ref(0)
// 點截圖後會把這個開關改 true，顯示放大的圖片；false 表示放大視窗關閉。
const isLightboxOpen = ref(false)

// 按放大截圖的左／右箭頭時執行這個函式。direction 是方向：1 看下一張，-1 看上一張。
// 要讓圖片可以循環：最後一張按下一張回第一張，第一張按上一張回最後一張。下面的算式就是處理這件事。
function changeShot(direction) {
    // count 取得這款遊戲共有幾張截圖。若是 0，return 就停止這次切圖，不繼續計算不存在的圖片位置。
    const count = screenshots.value.length
    if (count === 0) return

    // % 是除法後取餘數。以五張圖為例：最後一張的位置 4，往後是 (4+1+5)%5 = 0，回第一張。
    // 第一張往前則是 (0-1+5)%5 = 4，回最後一張；先加 count 是為了讓往前的計算不變成負數。
    selectedShot.value =
        (selectedShot.value + direction + count) % count
}

// route 保存目前網址資訊；下面透過 route.params.id 取得 /game/ 後面的編號，再去遊戲清單找資料。
const route = useRoute()
const gameStore = useGameStore()

const game = computed(() => {
    // 例如 /game/4 的 id 是文字「4」。getGameById() 會先轉成數字再查，找到完整遊戲交給 game；沒找到就沒有結果。
    return gameStore.getGameById(route.params.id)
})

// 取目前遊戲的橫幅網址。?. 表示「有遊戲才讀 banner」；?? null 表示沒讀到就用空值，避免輸入不存在的編號時報錯。
const gamebanner = computed(() => {
    return game.value?.banner ?? null
})

// 取目前遊戲的截圖清單；找不到遊戲就用空清單 []，畫面就沒有截圖可顯示。
const screenshots = computed(() => {
    return game.value?.screenshots ?? []
})

// 使用者可能從一款遊戲切到另一款，但仍使用同一支詳細頁程式。watch 發現網址編號改變時，執行下面的重設動作。
watch(
    // () => route.params.id 告訴 watch「只注意遊戲編號」。例如購物車數量改變，就不會因此把正在看的截圖切回第一張。
    () => route.params.id,
    () => {
        // 換遊戲後 selectedShot 設為 0，顯示第一張；isLightboxOpen 設 false，關掉上一款留下的放大截圖視窗。
        selectedShot.value = 0
        isLightboxOpen.value = false
    }
)
</script>


<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.feature-chip {
    color: var(--color-text);
    cursor: default;

    &:hover :deep(.v-chip__overlay) {
        opacity: 0;
    }
}

.purchase-card {
    border: 1px solid var(--color-border);
}

.game-metadata {
    >div {
        border-top: 1px solid var(--color-border);
    }
}

.game-description {
    line-height: 1.85;
}

.screenshot-thumb {
    display: block;
    width: 100%;
    padding: 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    cursor: pointer;

    &.active {
        outline: 3px solid var(--color-primary);
        outline-offset: 2px;
    }

    &:focus-visible {
        outline: 3px solid var(--color-text);
        outline-offset: 2px;
    }
}

.screenshot-carousel {
    aspect-ratio: 16 / 9;

    :deep(.v-window__container),
    :deep(.v-carousel-item),
    :deep(.v-carousel-item > .v-img) {
        height: 100%;
    }

    :deep(.v-carousel-item) {
        cursor: zoom-in;
    }

    :deep(.v-window__controls .v-btn) {
        width: 40px;
        min-width: 40px;
        height: 80px;
        border-radius: 6px;
        background-color: #fff;
        color: var(--color-text);
        opacity: 0.5;
    }
}

.screenshot-thumbs {
    flex: 0 0 140px;
}

.screenshot-main {
    min-width: 0;
}

.screenshot-lightbox {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    height: 100%;
    padding: 64px 8px 24px;
    background: rgba(0, 0, 0, 0.94);

    .lightbox-image {
        flex: 1;
        min-width: 0;
        height: 100%;
    }

    .lightbox-close {
        position: absolute;
        top: 12px;
        right: 12px;
    }
}

.lightbox-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;

    width: 40px;
    min-width: 40px;
    height: 80px;
    flex-shrink: 0;
    border-radius: 6px;
    background-color: #fff;
    color: var(--color-text);
    opacity: 0.5;

    &:hover {
        opacity: 1;
    }

    &.lightbox-prev {
        left: 8px;
    }

    &.lightbox-next {
        right: 8px;
    }
}

.purchase-status {
    &.is-owned {
        color: #486C58;
        background-color: #E3EDE4;
    }

    &.is-cart {
        color: #42648B;
        background-color: #E5EAF1;
    }

    // 停用時仍保留指定顏色
    &.v-btn--disabled {
        opacity: 1;
    }

    :deep(.v-btn__overlay) {
        opacity: 0;
    }
}

@mixin stacked-screenshots {
    .screenshot-gallery {
        flex-direction: column;
    }

    .screenshot-thumbs {
        flex: 0 0 auto;
        flex-direction: row !important;
        min-width: 0;
    }

    .screenshot-thumb {
        flex: 1 1 0;
        min-width: 0;
        width: auto;
    }

}

@include bp.tablet {
    @include stacked-screenshots;
}

@include bp.mobile {
    @include stacked-screenshots;

    .lightbox-arrow {
        width: 22px;
        min-width: 22px;
        height: 48px;

        :deep(.v-icon) {
            font-size: 20px;
        }
    }
}

@media (min-width: 960px) and (hover: hover) and (pointer: fine) {
    .screenshot-lightbox {
        .lightbox-arrow {
            opacity: 0;
            pointer-events: none;
        }

        &:hover .lightbox-arrow,
        &:focus-within .lightbox-arrow {
            opacity: 0.5;
            pointer-events: auto;
        }
    }
}
</style>

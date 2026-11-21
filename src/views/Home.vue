<template>
    <div class="page-container">
        <section class="mt-8">
            <v-carousel v-model="bannerIndex" class="home-carousel" height="auto" hide-delimiters show-arrows="hover"
                @mouseenter="pauseBanner" @mouseleave="resumeBanner">
                <v-carousel-item v-for="banner in banners" :key="banner.id">
                    <RouterLink :to="`/game/${banner.id}`" class="banner-link" :aria-label="`查看${banner.title}遊戲詳細頁`">
                        <v-img :src="banner.image" :alt="banner.title" height="100%" cover />
                    </RouterLink>
                </v-carousel-item>
            </v-carousel>
            <div class="banner-dots mb-3" role="group" aria-label="輪播圖片切換">
                <button v-for="(banner, index) in banners" :key="banner.id" type="button" class="banner-dot"
                    :class="{ active: bannerIndex === index }" :aria-label="`切換到第 ${index + 1} 張`"
                    :aria-current="bannerIndex === index ? 'true' : 'false'" @click="selectBanner(index)" />
            </div>
            <v-divider color="var(--color-muted)" opacity="0.3" />
        </section>
        <section v-if="systemStore.isDemo" class="mt-10">
            <h2>最新消息</h2>
            <RouterLink to="/news/demo-account" class="news-link mt-3">
                <div>
                    <h3>生活充電站 Demo 版體驗帳號</h3>
                    <p>想體驗購買遊戲的流程嗎？使用 Demo 帳號登入，即可探索會員與購物功能。</p>
                </div>
                <span>查看詳細內容</span>
            </RouterLink>
        </section>
        <section class="mt-10">
            <header class="d-flex justify-space-between align-end">
                <h2>探索遊戲</h2>
                <RouterLink to="/search" class="view-all-link">
                    <span>查看全部</span>
                    <v-icon icon="mdi-arrow-right" />
                </RouterLink>
            </header>
            <v-tabs v-model="currentTab" class="mt-3 game-tabs" hide-slider :grow="xs">
                <v-tab value="new">新品上市</v-tab>
                <v-tab value="bestseller">暢銷遊戲</v-tab>
                <v-tab value="featured">精選遊戲</v-tab>
            </v-tabs>
            <v-row class="mt-6">
                <v-col cols="6" md="4" xl="3" v-for="game in displayedGames" :key="game.id">
                    <GameCard :game="game" />
                </v-col>
            </v-row>
        </section>
        <section class="mt-10">
            <h2>探索類型</h2>
            <v-chip-group class="genre-group mt-3" column>
                <v-chip class="genre-chip" base-color="var(--color-secondary)" variant="flat" v-for="genre in genres"
                    :key="genre" :to="{ path: '/search', query: { genre } }">
                    {{ genreLabels[genre] ?? genre }}
                </v-chip>
            </v-chip-group>
        </section>
    </div>

</template>

<script setup>
// 本頁顯示橫幅輪播、新品／暢銷／精選商品與類型入口。
// 從 gameStore 讀已載入的資料，不重新決定模式；圖片使用 Service 提供的網址。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import GameCard from '../components/GameCard.vue'
import { useGameStore } from '../stores/gameStore'
import { genreLabels } from '../utils/gameLabels'
import { useDisplay } from 'vuetify'
import { useSystemStore } from '../stores/systemStore.js'

// Vuetify 的 xs 表示目前是否為最小螢幕尺寸，template 據此切換手機版排列。
const { xs } = useDisplay()

// bannerIndex 記首頁目前顯示第幾張大圖，從 0 開始數；currentTab 記選了新品、暢銷或精選，不是網站資料模式。
const bannerIndex = ref(0)
const currentTab = ref('new')

const gameStore = useGameStore()
const systemStore = useSystemStore()

// filter 篩出 status 清單中包含目前分頁標籤的遊戲；切換 tab 只改顯示結果，不寫資料。
const displayedGames = computed(() => {
    // 逐款檢查 status 是否包含目前 tab 代碼，回新清單；不因切 tab 重新呼叫 API。
    return gameStore.games.filter(game => {
        return game.status.includes(currentTab.value)
    })
})

// 先收集所有遊戲的類型，再拿掉重複項目，作為首頁的類型按鈕。flatMap 負責收集，Set 負責讓同一類型只出現一次。
const genres = computed(() => {
    return [...new Set(
        // 例如兩款遊戲分別有 [RPG, Action]、[Action]，先合在一起，再拿掉重複的 Action，最後得到 [RPG, Action]。
        gameStore.games.flatMap(game => game.genre)
    )]
})

// 這三個編號指定首頁展示商品；map 依序找遊戲並整理橫幅需要的編號、圖片與標題。
const bannerGameIds = [4, 13, 24]

const banners = computed(() => {
    return bannerGameIds.map(id => {
        // 用固定 bannerGameIds 找完整商品，沒有就發錯；不是用清單第幾筆猜是哪個商品。
        const game = gameStore.getGameById(id)

        if (!game) {
            throw new Error(`找不到 Banner 遊戲：${id}`)
        }

        // 每張橫幅只帶 id、banner 網址與中文名，畫面不需要的價格等欄位不另複製。
        return {
            id: game.id,
            image: game.banner,
            title: game.titleZh,
        }
    })
})

// let 允許之後改值：保存計時器編號與滑鼠暫停狀態，不是需要直接顯示的畫面資料。
let bannerTimer
let isBannerHovered = false

// 用途：取消等待中的下一次輪播；clearTimeout 可清除計時器，避免重複切換。
function stopBannerTimer() {
    // 取消上一個待執行倒數；即使還沒有有效 timer，clearTimeout 也不會排出新工作。
    clearTimeout(bannerTimer)
}

// 用途：先取消舊計時，再決定是否等待 8 秒切換下一張。
// 滑鼠停在橫幅或不到兩張時不啟動；% 是餘數運算，到最後一張後回第 0 張。
function restartBannerTimer() {
    stopBannerTimer()
    // 滑鼠停住或只有一張圖就不倒數，不為沒有下一張的情況白跑計時。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜isBannerHovered
    // 滑鼠目前停在首頁大圖上，先暫停自動換圖，讓使用者能閱讀或點選。
    // 條件 2｜banners.value.length < 2
    // 大圖不到兩張，沒有另一張需要輪播，因此不必安排下一次切圖。
    if (isBannerHovered || banners.value.length < 2) return

    // 保存本次計時器編號，8 秒後才切圖；index 改變又觸發 watch，接續下一次倒數。
    bannerTimer = setTimeout(() => {
        // % banners.length 取餘數，讓最後一張加一後回索引 0；不是無上限一直加到不存在的圖片。
        bannerIndex.value = (bannerIndex.value + 1) % banners.value.length
    }, 8000)
}

// 用途：滑鼠進入橫幅時記住暫停狀態，並取消目前計時，讓使用者可以閱讀或點選。
function pauseBanner() {
    // 這個內部旗標只控制計時，暫停後取消等待；不改目前 bannerIndex，所以停在原張。
    isBannerHovered = true
    stopBannerTimer()
}

// 用途：滑鼠離開後解除暫停，重新從 8 秒開始等待，不補跳剛才停住的次數。
function resumeBanner() {
    isBannerHovered = false
    restartBannerTimer()
}

// 使用者點選某張大圖的切換按鈕時，記住要顯示的位置，再重新等八秒。位置 0 是第一張，1 是第二張。
function selectBanner(index) {
    // 先換成使用者選的圖片，再重新倒數，避免剛切過去就被先前快結束的倒數換走。
    bannerIndex.value = index
    restartBannerTimer()
}

// watch：圖片位置變了，重新倒數；onMounted：首頁顯示後開始倒數；onBeforeUnmount：離開首頁前取消倒數。
// Vue 會在畫面出現、離開等時機通知程式，這些時機稱為生命週期；我們用它來確保離開首頁後不再偷偷切圖。
watch(bannerIndex, restartBannerTimer)
onMounted(restartBannerTimer)
onBeforeUnmount(stopBannerTimer)
</script>


<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.news-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    padding: 24px;
    border: 1px solid var(--color-border);
    border-radius: 12px;
    color: var(--color-text);
    text-decoration: none;

    p {
        margin-top: 8px;
        color: var(--color-text);
    }

    span {
        flex-shrink: 0;
        color: var(--color-primary);
    }

    &:hover {
        border-color: var(--color-primary);
    }

    &:focus-visible {
        outline: 2px solid var(--color-primary);
        outline-offset: 3px
    }
}

.view-all-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--color-primary);
    text-decoration: none;

    &:hover span {
        text-decoration: underline;
        text-underline-offset: 4px;
    }
}

.home-carousel {
    aspect-ratio: 1920 / 700;

    :deep(.v-window__container) {
        height: 100%;
    }

    :deep(.v-carousel-item),
    :deep(.v-carousel-item > .v-img) {
        height: 100%;
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

.banner-link {
    display: block;
    height: 100%;

    &:focus-visible {
        outline: 3px solid var(--color-primary);
        outline-offset: -3px;
    }
}

.banner-dots {
    display: flex;
    justify-content: center;
    gap: 28px;
    padding-block: 20px;
}

.banner-dot {
    width: 14px;
    height: 14px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background-color: var(--color-border);
    cursor: pointer;

    &.active {
        background-color: var(--color-primary);
    }

    &:focus-visible {
        outline: 2px solid var(--color-text);
        outline-offset: 4px;
    }
}

.game-tabs {
    :deep(.v-tab) {
        margin-right: 8px;
        padding-inline: 58px;
        border: 1px solid var(--color-border);
        border-radius: 12px;
        color: var(--color-text);
        text-transform: none;
        transition:
            background-color 0.2s ease,
            border-color 0.2s ease,
            color 0.2s ease,
            box-shadow 0.2s ease;

        &:hover .v-btn__overlay {
            opacity: 0;
        }

        &:not(.v-tab--selected):hover {
            color: var(--color-primary);
            border-color: var(--color-primary);
            box-shadow: inset 0 0 0 1px var(--color-primary);
        }
    }

    :deep(.v-tab--selected) {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
    }
}

.genre-chip {
    color: var(--color-muted);
    padding: 12px 24px;
    height: auto;

    &:hover {
        color: var(--color-primary);
    }

    &:hover :deep(.v-chip__overlay) {
        opacity: 0;
    }

    :deep(.v-ripple__container) {
        color: var(--color-border);
    }
}

@include bp.mobile {
    .news-link {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .game-tabs {
        :deep(.v-tab) {
            flex: 1 1 0;
            min-width: 0;
            margin-right: 0;
            padding-inline: 2px;
            font-size: 13px;
        }

        :deep(.v-slide-group__content) {
            gap: 6px;
        }
    }

    .genre-group {
        :deep(.v-slide-group__content) {
            gap: 0px;
        }

        :deep(.v-chip) {
            margin: 2px;
        }
    }

    .genre-chip {
        padding: 4px 12px;
        font-size: 12px;
    }
}
</style>

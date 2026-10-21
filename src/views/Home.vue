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

import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import GameCard from '../components/GameCard.vue'
import { useGameStore } from '../stores/gameStore'
import { genreLabels } from '../utils/gameLabels'
import { useDisplay } from 'vuetify'

const { xs } = useDisplay()

const bannerIndex = ref(0)
const currentTab = ref('new')

const gameStore = useGameStore()

const displayedGames = computed(() => {
    return gameStore.games.filter(game => {
        return game.status.includes(currentTab.value)
    })
})

const genres = computed(() => {
    return [...new Set(
        gameStore.games.flatMap(game => game.genre)
    )]
})

const bannerGameIds = [4, 13, 24]

const banners = computed(() => {
    return bannerGameIds.map(id => {
        const game = gameStore.getGameById(id)

        if (!game) {
            throw new Error(`找不到 Banner 遊戲：${id}`)
        }

        return {
            id: game.id,
            image: game.banner,
            title: game.titleZh,
        }
    })
})

let bannerTimer
let isBannerHovered = false

function stopBannerTimer() {
    clearTimeout(bannerTimer)
}

function restartBannerTimer() {
    stopBannerTimer()
    if (isBannerHovered || banners.value.length < 2) return

    bannerTimer = setTimeout(() => {
        bannerIndex.value = (bannerIndex.value + 1) % banners.value.length
    }, 8000)
}

function pauseBanner() {
    isBannerHovered = true
    stopBannerTimer()
}

function resumeBanner() {
    isBannerHovered = false
    restartBannerTimer()
}

function selectBanner(index) {
    bannerIndex.value = index
    restartBannerTimer()
}

watch(bannerIndex, restartBannerTimer)
onMounted(restartBannerTimer)
onBeforeUnmount(stopBannerTimer)
</script>


<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

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

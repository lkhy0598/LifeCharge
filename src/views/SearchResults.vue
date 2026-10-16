<template>
    <div class="page-container">
        <div class="mt-10">
            <h2>搜尋結果</h2>
        </div>
        <section class="filters d-flex align-center ga-3 flex-wrap mb-5 mt-2">
            <v-select v-model="selectedGenre" :items="genreOptions" item-title="title" item-value="value"
                variant="outlined" hide-details density="compact" rounded="lg"
                :menu-props="{ contentClass: 'search-select-menu' }" />
            <v-select v-model="selectedSort" :items="sortOptions" item-title="title" item-value="value"
                variant="outlined" hide-details density="compact" rounded="lg"
                :menu-props="{ contentClass: 'search-select-menu' }" />
            <p>共 {{ filteredGames.length }} 款遊戲</p>
        </section>
        <section class="mt-4 d-flex flex-column ga-2" v-if="filteredGames.length > 0">
            <v-card class="search-game-card d-flex ga-2 pa-4" variant="outlined" rounded="lg"
                v-for="game in filteredGames" :key="game.id" @click="openGame(game.id)" :ripple="false">
                <v-img :src="getGameCover(game.id)" :alt="`${game.titleZh}封面`" rounded="lg" :aspect-ratio="3 / 4" cover
                    class="search-game-cover">
                    <div class="cover-status">
                        <span v-if="authStore.purchasedGames.includes(game.id)" class="status-badge is-owned">
                            已購買
                        </span>

                        <span v-else-if="cartStore.isInCart(game.id)" class="status-badge is-cart">
                            已加入購物車
                        </span>
                    </div>
                </v-img>
                <v-card-text class="search-game-content d-flex flex-grow-2 py-0 pr-0">
                    <div class="game-info d-flex flex-column justify-space-between ga-2">
                        <div>
                            <h3>{{ game.titleZh }}</h3>
                            <p>{{ game.title }}</p>
                        </div>
                        <span class="font-weight-light">{{game.genre.map(genre => genreLabels[genre] ??
                            genre).join('／')}}</span>
                        <span class="game-description font-weight-light">{{ game.description }}</span>
                        <span>
                            <v-icon icon="mdi-star" size="small" />
                            {{ game.rating }}
                        </span>
                    </div>
                    <v-spacer />
                    <div class="game-purchase d-flex flex-column ga-3 align-center justify-center">
                        <h3>NT$ {{ game.price.toLocaleString('en-US') }}</h3>
                        <v-btn v-if="
                            !authStore.purchasedGames.includes(game.id) &&
                            !cartStore.isInCart(game.id)
                        " class="add-cart-btn" variant="flat" rounded="lg"
                            @click.stop="cartStore.addToCart(game.id)">加入購物車</v-btn>
                    </div>
                </v-card-text>
            </v-card>
        </section>
        <section v-else class="text-center py-10">
            <h3 class="mb-3">沒有符合條件的遊戲</h3>
            <p class="mb-6">請嘗試其他關鍵字，或清除篩選條件。</p>

            <v-btn to="/search" color="primary" rounded="lg" :ripple="false">
                清除篩選
            </v-btn>
        </section>
    </div>

</template>

<script setup>
import { ref, computed } from 'vue'
import { useGameStore } from '../stores/gameStore'
import { genreLabels } from '../utils/gameLabels'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'

const authStore = useAuthStore()

const cartStore = useCartStore()

const route = useRoute()

const gameStore = useGameStore()

const keyword = computed(() => {
    return typeof route.query.q === 'string'
        ? route.query.q
        : ''
})

const selectedGenre = computed({
    get() {
        return typeof route.query.genre === 'string'
            ? route.query.genre
            : ''
    },
    set(genre) {
        const query = { ...route.query }

        if (genre) {
            query.genre = genre
        } else {
            delete query.genre
        }

        router.push({
            path: '/search',
            query
        })
    }
})

const filteredGames = computed(() => {
    const searchedGames = gameStore.searchGames(keyword.value)

    const matchedGames = searchedGames.filter(game => {
        return !selectedGenre.value ||
            game.genre.includes(selectedGenre.value)
    })

    // 複製陣列再排序，避免改動 Store 的原始順序
    const results = [...matchedGames]

    switch (selectedSort.value) {
        case 'newest':
            results.sort((a, b) => b.releaseDate.localeCompare(a.releaseDate))
            break

        case 'rating':
            results.sort((a, b) => b.rating - a.rating)
            break

        case 'asc':
            results.sort((a, b) => a.price - b.price)
            break

        case 'desc':
            results.sort((a, b) => b.price - a.price)
            break
    }

    return results
})

const coverImages = import.meta.glob(
    '../assets/images/gameDataWebp/*/*_cover.webp',
    {
        eager: true,
        import: 'default',
        query: '?url'
    }
)

function getGameCover(id) {
    const folder = `game${String(id).padStart(2, '0')}`
    const path = `../assets/images/gameDataWebp/${folder}/${folder}_cover.webp`
    const image = coverImages[path]

    if (!image) {
        throw new Error(`找不到遊戲封面：${path}`)
    }

    return image
}

const router = useRouter()

function openGame(id) {
    router.push(`/game/${id}`)
}

const genreOptions = computed(() => {
    const genres = [...new Set(
        gameStore.games.flatMap(game => game.genre)
    )]

    return [
        { title: '全部類型', value: '' },
        ...genres.map(genre => ({
            title: genreLabels[genre] ?? genre,
            value: genre
        }))
    ]
})

const sortOptions = [
    { title: '預設排序', value: 'default' },
    { title: '最新上市', value: 'newest' },
    { title: '評分排序', value: 'rating' },
    { title: '價格：低至高', value: 'asc' },
    { title: '價格：高至低', value: 'desc' }
]

const selectedSort = computed({
    get() {
        return typeof route.query.sort === 'string'
            ? route.query.sort
            : 'default'
    },
    set(sort) {
        const query = { ...route.query }

        if (sort === 'default') {
            delete query.sort
        } else {
            query.sort = sort
        }

        router.push({
            path: '/search',
            query
        })
    }
})


</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.filters {
    .v-select {
        flex: 0 1 180px;
    }

    :deep(.v-field) {
        background-color: #fff;
    }
}

.search-game-card {
    background-color: #fff;
    border: 1px solid var(--color-border);
}

.add-cart-btn {
    background-color: var(--color-primary);
    color: #fff;

    &:hover {
        filter: brightness(0.94);
    }

    &:hover :deep(.v-btn__overlay) {
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

.search-game-cover {
    width: 150px;
    flex: 0 0 150px;
    align-self: flex-start;
}

.game-purchase {
    flex: 0 0 120px;
}

@include bp.mobile {
    .search-game-cover {
        width: 90px;
        flex: 0 0 90px;
    }

    .search-game-content {
        flex-direction: column;
        min-width: 0;
        padding-left: 8px;
        gap: 12px;

        >.v-spacer {
            display: none;
        }
    }

    @include bp.mobile {
        .search-game-cover {
            width: 100px;
            flex: 0 0 100px;
            align-self: stretch;
        }

        .search-game-content {
            flex-direction: column;
            min-width: 0;
            padding-left: 8px;
            gap: 12px;

            >.v-spacer {
                display: none;
            }
        }

        .game-info {
            min-width: 0;
            gap: 4px;

            h3 {
                font-size: 16px;
            }

            p,
            span {
                font-size: 12px;
            }

            .game-description {
                display: none;
            }
        }

        .game-purchase {
            flex: 0 0 auto;
            margin-top: auto;
            align-items: flex-end !important;
            gap: 8px;

            h3 {
                font-size: 16px;
                padding-right: 6px;
            }
        }

        .filters {
            .v-select {
                flex: 0 0 calc((100% - 12px) / 2);
                min-width: 0;
            }

            >p {
                flex: 0 0 100%;
            }
        }
    }
}
</style>

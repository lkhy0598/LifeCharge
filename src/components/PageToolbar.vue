<template>
    <section class="page-container">
        <div class=" d-flex my-4 align-center toolbar-content">
            <v-breadcrumbs :items="items" />
            <v-spacer class="toolbar-spacer" />
            <v-text-field placeholder="搜尋遊戲" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact"
                hide-details rounded="lg" v-model="keyword" @keydown.enter.prevent="submitSearch"
                @click:prepend-inner="submitSearch" class="toolbar-search" />
        </div>
        <v-divider color="var(--color-muted)" opacity="0.3" />
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore'

const gameStore = useGameStore()

const route = useRoute()
const router = useRouter()
const keyword = ref('')

const items = computed(() => {
    const breadcrumbs = [
        {
            title: '首頁',
            to: '/',
            disabled: route.name === 'Home'
        }
    ]

    const pageTitles = {
        SearchResults: '搜尋結果',
        GameDetail: '遊戲詳細頁',
        Member: '會員中心',
        Checkout: '結帳'
    }

    let currentTitle = pageTitles[route.name]

    if (route.name === 'GameDetail') {
        breadcrumbs.push({
            title: '全部遊戲',
            to: '/search',
            disabled: false
        })

        const game = gameStore.getGameById(route.params.id)
        currentTitle = game ? game.titleZh : '找不到遊戲'
    }

    if (currentTitle) {
        breadcrumbs.push({
            title: currentTitle,
            disabled: true
        })
    }

    return breadcrumbs
})

function submitSearch() {
    const searchText = keyword.value.trim()

    const query = route.name === 'SearchResults'
        ? { ...route.query }
        : {}

    if (searchText) {
        query.q = searchText
    } else {
        delete query.q
    }

    router.push({
        path: '/search',
        query
    })
}

watch(
    () => route.query.q,
    value => {
        keyword.value = typeof value === 'string' ? value : ''
    },
    { immediate: true }
)
</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

:deep(.v-breadcrumbs-item--link:hover) {
    text-decoration: underline;
    text-underline-offset: 4px;
}

:deep(.v-field) {
    background-color: #fff;
}

.toolbar-search {
    max-width: 480px;
}

@include bp.mobile {
    .toolbar-content {
        flex-direction: column;
        align-items: stretch !important;
    }

    .toolbar-spacer {
        display: none;
    }

    .toolbar-search {
        width: 100%;
        max-width: none;
    }
}
</style>
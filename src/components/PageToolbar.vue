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
// 本元件顯示頁面上方的導覽路徑與搜尋框。
// 搜尋字、類型與排序以網址參數保存，重新整理或返回上一頁仍可還原，不在這裡查資料庫。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGameStore } from '../stores/gameStore'


// 取得初始化完成的商品清單供導覽取名稱；不在這個工具列重新抓整份 games。
const gameStore = useGameStore()

// useRoute 讀取「目前在哪個網址」，useRouter 取得「切換網址」的工具。
// keyword 是搜尋框目前輸入，不等於已提交到網址的搜尋條件。
const route = useRoute()
const router = useRouter()
const keyword = ref('')

// 計算麵包屑導覽，例如「首頁 → 全部遊戲 → 某款遊戲」。
// 每次路由或遊戲資料改變會重新計算；disabled 表示目前這一層不用再點擊。
const items = computed(() => {
    // 每次 computed 執行先建新清單，以首頁為起點；不是對上次清單一直追加，因此不會越切頁越長。
    const breadcrumbs = [
        {
            title: '首頁',
            to: '/',
            // 目前就在首頁時不讓首頁麵包屑再次點擊；這是 UI 行為，不是會員權限。
            disabled: route.name === 'Home'
        }
    ]

    // 把程式用的路由名稱對照為中文標題；GameDetail 會再改成實際遊戲中文名。
    // 這個物件像名稱對照表：route.name 是 SearchResults，就取出「搜尋結果」。
    // 這裡的名稱對應 Router 設定的 name，不是直接用 /search 這段網址當鍵。
    const pageTitles = {
        SearchResults: '搜尋結果',
        GameDetail: '遊戲詳細頁',
        Member: '會員中心',
        Checkout: '結帳',
        DemoAccountNews:'最新消息'
    }

    // let 允許詳細頁稍後將一般標題換成實際遊戲名；找不到對照會是 undefined，下面才判斷是否追加。
    let currentTitle = pageTitles[route.name]

    if (route.name === 'GameDetail') {
        // 詳細頁先補「全部遊戲」入口，再加目前遊戲名；push 只改這次計算出的本地清單。
        breadcrumbs.push({
            title: '全部遊戲',
            to: '/search',
            disabled: false
        })

        // route.params.id 是 /game/:id 的動態編號，交給 gameStore 查遊戲。
        // 條件 ? A : B 表示找到用遊戲名，找不到則顯示備用文字。
        const game = gameStore.getGameById(route.params.id)
        currentTitle = game ? game.titleZh : '找不到遊戲'
    }

    // 有可顯示標題才加最後一層，最後層 disabled true 表示已是當前頁。
    if (currentTitle) {
        breadcrumbs.push({
            title: currentTitle,
            disabled: true
        })
    }

    return breadcrumbs
})

// 用途：把搜尋框文字放進 /search 的 q 參數，讓搜尋頁依網址顯示結果。
// 先 trim 去前後空白；已在搜尋頁時保留原 genre／sort，其他頁則從空參數開始。
// 有文字就設定 query.q，空白則 delete 移除它，最後 router.push 導向搜尋頁。
function submitSearch() {
    // 送出時才取輸入文字並 trim；空字串就移除 q，代表搜尋全部，而不是查一個空白遊戲名。
    const searchText = keyword.value.trim()

    // 已有搜尋條件時用 {...route.query} 複製，避免直接改路由的內部物件，也保留 sort／genre。
    // 這段 ? : 分兩種情況：已在搜尋頁就複製原條件，其他頁則從空條件開始。
    // 例如搜尋頁原本有 genre=RPG、sort=asc，改打新關鍵字時仍保留這兩項。
    // ...route.query 是把現有每個條件複製進新物件，稍後修改 q 不直接改 Router 的原物件。
    const query = route.name === 'SearchResults'
        ? { ...route.query }
        : {}

    if (searchText) {
        query.q = searchText
    } else {
        // 輸入清空時移除 q 這個條件，但不移除 genre、sort；因此會顯示目前分類／排序下的所有遊戲。
        delete query.q
    }

    // 請 Router 切到 /search 並保存 query，搜尋頁再依網址條件計算結果；不是在這裡執行搜尋。
    router.push({
        path: '/search',
        query
    })
}

// 監聽網址中的 q，而不是每打一個字就跳頁；網址改變時才同步搜尋框。
// immediate true 表示元件剛建立也先執行一次，直接開搜尋網址就能還原文字。
watch(
    () => route.query.q,
    // 這個 value 是 watch 收到的新 q；只有字串才放進輸入框，避免網址參數陣列變成錯誤顯示。
    value => {
        // typeof value === string 檢查網址的 q 是否是單一文字；符合就填入搜尋框，否則填空字串。
        // 例如按瀏覽器上一頁回到 q=星，搜尋框也恢復「星」，不會仍留著後來輸入的內容。
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
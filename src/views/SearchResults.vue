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
                <v-img :src="game.cover" :alt="`${game.titleZh}封面`" rounded="lg" :aspect-ratio="3 / 4" cover
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
// 這個頁面做什麼？
// 從網站已載入的遊戲裡，找出符合「名稱、類型、排序」的結果，交給上方 template 畫出卡片。
// 它不另外向 PHP 發搜尋請求；Demo／Database 都使用目前 gameStore 已經準備好的遊戲。
//
// 使用者的選擇記在哪裡？
// 記在網址，不只放在輸入框。例如：#/search?q=星&genre=RPG&sort=asc
// q=星：名稱要包含「星」；genre=RPG：類型要包含 RPG；sort=asc：價格從低排到高。
// # 後面是我們的頁面路徑，? 後面是條件，& 分開不同條件。
// 使用 router.push() 改網址時不會重新整理整個網站；相關 computed 會跟著重新計算。
// 因此複製網址、重新整理或按瀏覽器上一頁，都能從網址讀回選過的條件。
//
// 整體順序：讀網址條件 → 搜尋名稱 → 篩選類型 → 排序 → 將結果交給畫面。
// 選單則走相反方向：使用者選新值 → 寫進網址 → 上面的流程重新計算。

// import 引用這頁需要的工具。computed 用來根據現有資料算結果，例如符合條件的遊戲。
// ref 也被引用，但目前這支 script 沒有使用它；這次只改註解，保留原程式。
import { ref, computed } from 'vue'
// 取得全站共用遊戲清單與搜尋函式，不在本頁建立另一份商品資料。
import { useGameStore } from '../stores/gameStore'
// 英文類型代碼的中文對照，例如 genreLabels['RPG'] 是「角色扮演」。
import { genreLabels } from '../utils/gameLabels'
// useRoute 用來讀「現在的網址」；useRouter 用來請程式「換到另一個網址」。
import { useRoute, useRouter } from 'vue-router'
// 購物車和會員資料，供卡片顯示已加入／已購買，以及判斷是否顯示加入按鈕。
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'

const authStore = useAuthStore()

// 上方 template 使用 cartStore.isInCart() 與 addToCart()，所以在 script 先取得這個共用工具。
const cartStore = useCartStore()

// route.query 是 ? 後面的條件資料，例如上面網址可讀 route.query.q 得到文字「星」。
// 不直接修改 route；需要改條件時，先準備新 query，再交給 router.push()。
const route = useRoute()

const gameStore = useGameStore()

// 一、讀取搜尋文字。
// computed(() => { ... }) 表示這個值由函式計算；當讀取的網址條件改變，下次使用就會重算。
// 在 script 用 keyword.value 取得結果；上方 template 讀 computed 時通常不用寫 .value。
const keyword = computed(() => {
    // typeof ... === 'string' 問「q 是不是單一段文字？」；沒有 q 時不是文字。
    // ? 後面是符合時回傳 q，: 後面是不符合時回傳空字串 ''，避免把陣列等錯誤資料交給搜尋。
    // 空字串交給 gameStore.searchGames() 時代表不限制名稱，不是去找一款名字為空的遊戲。
    return typeof route.query.q === 'string'
        ? route.query.q
        : ''
})

// 二、連接類型選單和網址。
// 上方 <v-select v-model="selectedGenre"> 會讀這個值決定顯示哪項，也會在改選時寫回新值。
// 所以這裡要有 get（讀值怎麼取）與 set（新值來了怎麼處理），不像 keyword 只有讀取。
// 例如網址是 genre=RPG，get 回 RPG；使用者選 Action，set 收到的 genre 就是文字 'Action'。
const selectedGenre = computed({
    // 選單要知道目前選哪項時走 get，不是在這裡發出選擇事件。
    get() {
        // 和 q 一樣只接受單一文字。缺少 genre 時回 ''，對應下方選單的「全部類型」。
        return typeof route.query.genre === 'string'
            ? route.query.genre
            : ''
    },
    // 使用者改選單時走 set；括號裡的 genre 是新選項，不是某款遊戲的完整類型清單。
    set(genre) {
        // ... 將舊條件複製到新物件；例如原本 {q:'星',sort:'asc'}，先保留這兩個選擇。
        // 如果直接從 {} 開始，改類型時就會忘記先前的搜尋文字與排序。
        const query = { ...route.query }

        if (genre) {
            // 有選特定類型，例如 'Action'，就新增／覆寫這一個條件，其他欄位不變。
            query.genre = genre
        } else {
            // 選「全部類型」的新值是 ''，在 if 判斷裡相當於不成立，因此進這裡。
            // delete 只刪新 query 裡的 genre，不刪商品的類型，也不清 q 或 sort。
            delete query.genre
        }

        // path 指要顯示搜尋頁；query 指新的條件。
        // { path:'/search', query } 中的 query 是 query: query 的簡寫。
        // 原本 ?q=星&sort=asc 改選 RPG 後，就會變成 ?q=星&sort=asc&genre=RPG，參數順序不影響意義。
        // 網址改完，selectedGenre.get 與 filteredGames 便會讀新條件，更新選單與結果。
        router.push({
            path: '/search',
            query
        })
    }
})

// 三、計算真正要顯示的遊戲。
// 上方 template 用 filteredGames.length 顯示筆數，用 v-for 逐款畫卡片；零筆時顯示找不到結果。
// 下面三個名稱是不同階段的清單：searchedGames 名稱符合、matchedGames 類型也符合、results 已排好順序。
// keyword、selectedGenre、selectedSort 或商品資料改了，這個 computed 會依新資料重新計算。
const filteredGames = computed(() => {
    // 第一步：只做名稱搜尋，傳 keyword.value 取得實際文字，而不是傳 computed 工具本身。
    // 中英文比對、去頭尾空白與忽略英文大小寫由 gameStore.searchGames() 負責，這裡不再寫一次。
    // 若關鍵字空白，這一步得到全部遊戲，下一步仍可限制類型。
    const searchedGames = gameStore.searchGames(keyword.value)

    // 第二步：filter 對上一步每款遊戲執行判斷；回 true 就放入新清單，false 就排除。
    // game 是這一輪正在檢查的一款，不是所有商品。原 searchedGames 不會被 filter 修改。
    const matchedGames = searchedGames.filter(game => {
        // 兩種情況都可以保留：沒有選特定類型，或這款真的包含所選類型。
        // || 左邊成立就直接回 true，不需要再檢查右邊；左邊不成立才看右邊。
        // 條件 1｜!selectedGenre.value
        // 選全部類型時值是 ''，! 把空值反過來判斷成 true，所以每款已符合名稱的遊戲都保留。
        return !selectedGenre.value ||
            // 條件 2｜game.genre.includes(selectedGenre.value)
            // 假設選 RPG，game.genre 是 ['RPG','Action']，includes('RPG') 回 true，就保留。
            // 若 game.genre 只有 ['Casual']，便回 false、排除。這裡比代碼，不比中文「角色扮演」。
            game.genre.includes(selectedGenre.value)
    })

    // 第三步：準備可排序的清單。[...matchedGames] 複製項目排列，不另複製每款遊戲內容。
    // sort 會直接重排它收到的陣列，所以在本地 results 排，讓來源清單的順序保持不變。
    const results = [...matchedGames]

    // 第四步：讀 selectedSort.value 的代碼，switch 找到相同 case 執行該種排序。
    // sort 的比較函式每次拿兩款遊戲 a、b：回負數就 a 在前，正數就 b 在前，0 則兩者無需交換先後。
    // 箭頭函式 (a,b) => ... 右邊的算式結果，會直接交回 sort 作比較依據。
    // 這些代碼來自下方 sortOptions 的 value，不是使用者看到的中文 title。
    switch (selectedSort.value) {
        case 'newest':
            // newest＝最新上市。日期固定 YYYY-MM-DD，文字先後和日期先後相同，因此可用 localeCompare 比較。
            // 這裡 b 與 a 反過來比，讓較新的日期在前，例如 2026-10-01 排在 2025-10-01 前面。
            results.sort((a, b) => b.releaseDate.localeCompare(a.releaseDate))
            // break 離開 switch，避免做完日期排序後又繼續跑評分排序；不是離開整個 computed。
            break

        case 'rating':
            // rating＝評分由高到低。若 a=3 分、b=5 分，b-a=2 是正數，所以 b（5 分）排到前面。
            results.sort((a, b) => b.rating - a.rating)
            break

        case 'asc':
            // asc＝價格低到高。若 a=100、b=300，a-b=-200 是負數，因此 a（100 元）排前面。
            results.sort((a, b) => a.price - b.price)
            break

        case 'desc':
            // desc＝價格高到低。同樣 a=100、b=300，b-a=200 是正數，因此 b（300 元）排前面。
            results.sort((a, b) => b.price - a.price)
            break
    }

    // default（預設）沒有對應 case，因此不進行排序，維持名稱與類型篩選後的原本順序。
    // 網址若手動填了未知排序代碼，也不會命中 case，結果同樣保持原順序。
    // 最後把排好的清單交給 filteredGames；畫面讀它就能畫出卡片，不在這裡重新抓 API 或寫資料。
    return results
})

// 取得換頁工具。雖然寫在幾個 computed 下方，但它們需要使用 set 時，這段設定已經執行完成。
const router = useRouter()

// 四、點商品卡片時開詳細頁。
// template 的 @click="openGame(game.id)" 傳入該款編號；id 是函式參數，不固定寫死哪一款。
function openGame(id) {
    // 反引號可用 ${id} 放進變數，例如 id=4 變成 /game/4；不是把文字「${id}」原樣放進網址。
    // Router 切到詳細頁後，再由 GameDetail.vue 向 gameStore 取得同一款資料。
    router.push(`/game/${id}`)
}

// 五、建立類型下拉選單的內容。注意：這裡看的是所有遊戲，不只當前搜尋結果。
// 因此搜不到某類遊戲時，該類型仍可在選單中找到，不會因這次篩選而消失。
const genreOptions = computed(() => {
    // 假設兩款遊戲的類型是 ['RPG','Action'] 和 ['Action','Casual']：
    // ① flatMap 把每款的 game.genre 接起來，得到 ['RPG','Action','Action','Casual']。
    // ② new Set(...) 移除重複值，Action 只留一個。
    // ③ 外面的 [...] 再轉成陣列，才能用下面 map 逐項建立選單。
    const genres = [...new Set(
        gameStore.games.flatMap(game => game.genre)
    )]

    return [
        // 第一項固定是全部類型，value 空字串會讓 selectedGenre 的判斷不限制類型。
        { title: '全部類型', value: '' },
        // genres.map 每次取一個類型代碼，建立 {title:顯示文字,value:實際代碼}。
        // 前面的 ... 把產生的多個選項放到外層清單，接在「全部類型」後，而不是塞入一個子陣列。
        // genre => ({ ... }) 的圓括號表示要直接回傳一個物件，不是開始函式的大括號區段。
        ...genres.map(genre => ({
            // title：例如 RPG 顯示「角色扮演」。?? 只有對照不存在（null／undefined）才使用右邊原代碼。
            title: genreLabels[genre] ?? genre,
            // value：仍是 RPG，不改成中文；商品裡也存 RPG，這樣 includes 才能比對到同一個值。
            value: genre
        }))
    ]
})

// 六、固定排序選單。template 的 item-title="title" 讓使用者看中文，item-value="value" 則把代碼交給 set。
// 例如看到「價格：低至高」，真正送給 selectedSort 的是 'asc'，再由上面的 switch 選擇怎麼排。
// 這裡的 'default' 只是我們選的字串代碼，並不是 switch 的 default 語法。
const sortOptions = [
    { title: '預設排序', value: 'default' },
    { title: '最新上市', value: 'newest' },
    { title: '評分排序', value: 'rating' },
    { title: '價格：低至高', value: 'asc' },
    { title: '價格：高至低', value: 'desc' }
]

// 七、連接排序選單和網址，做法和 selectedGenre 相同：讀值走 get，使用者改選走 set。
// 它沒有自己另存一份排序值；網址 sort 改變後，上面的 filteredGames 就會使用新條件重算。
const selectedSort = computed({
    // 選單讀目前值：網址有合法文字 sort 就使用它，沒有則選「預設排序」。
    get() {
        // typeof 檢查資料是不是文字，不在這行檢查代碼一定有對應選項；未知代碼的處理見上面 switch。
        return typeof route.query.sort === 'string'
            ? route.query.sort
            : 'default'
    },
    set(sort) {
        // sort 是這次新選項，例如 'rating'；先複製舊條件，保留 q 與 genre。
        const query = { ...route.query }

        // 分支一：選回預設，不需要留下 sort=default，刪除 sort 後 get 仍會自然回 default。
        if (sort === 'default') {
            delete query.sort
        } else {
            // 分支二：例如選 rating，就保存 query.sort='rating'，搜尋文字與類型都還在。
            query.sort = sort
        }

        // 例如原網址 ?q=星&genre=RPG，選評分排序後成為 ?q=星&genre=RPG&sort=rating。
        // push 會留下瀏覽歷程，所以按上一頁可回前一組條件；不是修改資料庫商品順序。
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

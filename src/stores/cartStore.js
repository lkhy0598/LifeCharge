// 本檔保存購物車編號並提供商品、總價等衍生資料；兩模式的購物車都存在分頁暫存。
// Store 是 Pinia 管理的共用狀態，讓不同 Vue 畫面使用同一份資料。
// defineStore 定義它，use...Store() 取得目前網站的實例；不是每次呼叫都建立另一份會員。
// ref() 建立畫面會跟著更新的資料，JS 內用 .value 讀寫；computed() 依資料算出結果。
// return {...} 列出供其他檔案使用的資料與函式，沒有列出的只在這個 Store 內使用。
// Promise 是尚待完成的工作；async／await 等待網路結果，catch 接失敗，finally 負責收尾。

import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useGameStore } from './gameStore'
import { useAuthStore } from './authStore'

// 定義名為 cart 的共用 Store；外面用 useCartStore() 取得它。
// 下方 return 會列出外面可讀的值與可呼叫的函式，不是每次進頁面就另建一台購物車。
export const useCartStore = defineStore('cart', () => {
    // 先準備空清單 []；每項只存 { gameId: 4 } 這樣的編號，不複製一份可能變動的商品價格。
    const cart = ref([])
    // 記住目前購物車所屬模式，稍後 storageKey() 據此選不同暫存名稱，避免兩模式互蓋。
    const source = ref('demo')

    // null 表示尚未初始化；初始化成功後記住模式。isRestoring 則是暫停自動保存的內部開關。
    let initializationMode = null
    let isRestoring = false

    // 用途：依目前模式挑對應的暫存名稱；三元寫法 條件 ? A : B 表示符合選 A，否則 B。
    function storageKey() {
        // 讀 ref 要加 .value；條件 ? A : B 表示 Database 選 A，其他狀態選 Demo 的 key。
        // 回傳的是暫存名稱文字，不是在這裡讀取內容。
        return source.value === 'database'
            ? 'lifecharge-database-cart'
            : 'lifecharge-cart'
    }

    // watch 監聽 cart 的變動，自動寫入目前模式的 sessionStorage。
    // deep true 包含清單內變動，flush sync 表示立即執行；還原期間先跳過，不存中間狀態。
    watch(
        cart,
        value => {
            // 還原資料時會先清空再填回；中間這些暫時變更不要寫回 storage，否則可能把原資料蓋掉。
            if (isRestoring) return

            // setItem 接收「名稱、內容」兩個參數；JSON.stringify 把清單轉成瀏覽器能保存的文字。
            // value 是 watch 傳來的最新購物車；寫入失敗會發出錯誤，不是保證瀏覽器一定可存。
            sessionStorage.setItem(
                storageKey(),
                JSON.stringify(value)
            )
        },
        { deep: true, flush: 'sync' }
    )

    // computed 依 cart 自動重算，length 是項目數；零件數不是花費金額。
    // 使用者加入或移除後，導覽上的購物車數字會跟著更新。
    const cartCount = computed(() => {
        return cart.value.length
    })

    // 遊戲 Store 用來查編號是否存在及取得商品；會員 Store 用來排除已擁有商品。
    const gameStore = useGameStore()
    const authStore = useAuthStore()

    // 用途：判斷這款遊戲是否已加入；some 只要有一項符合就回 true。
    function isInCart(gameId) {
        // 呼叫處可能給網址文字編號，例如「4」；先轉成數字 4，後面才能與 gameId 嚴格比較。
        const id = Number(gameId)

        // some 逐項檢查，任一 item.gameId 等於 id 就回 true；清單是空的會回 false。
        return cart.value.some(item => item.gameId === id)
    }

    // 用途：遊戲存在、沒有重複、尚未擁有才 push 加入編號並回 true，否則 false。
    // 只保存 gameId，不在購物車保存一份可能過期的成交價格。
    function addToCart(gameId) {
        const id = Number(gameId)

        // 加入前的第一關：商品不存在就回 false，這次函式立即結束，不執行下面 push。
        if (!gameStore.getGameById(id)) return false
        // 第二關：已經加入不重複放第二筆；第三關：會員已擁有也不能再加入。
        // includes 比較清單內是否有這個編號，不是比遊戲名稱。
        if (isInCart(id)) return false
        if (authStore.purchasedGames.includes(id)) return false

        // 三關都通過才在清單尾端加入新物件；watch 會偵測並保存。回 true 供畫面知道加入成功。
        cart.value.push({ gameId: id })
        return true
    }

    // 購物車只保存編號，這裡 map 從 gameStore 找出完整商品供畫面顯示。
    // 找不到就報錯，不自行造一個價格為 0 的商品。
    const cartGames = computed(() => {
        // map 依序將每個編號換成完整遊戲；回傳新清單，原 cart 仍只保存編號。
        return cart.value.map(item => {
            // 向已載入的遊戲清單查，不是重新發 API。找不到是資料不一致，throw Error 交外面處理。
            const game = gameStore.getGameById(item.gameId)

            if (!game) {
                throw new Error(`購物車找不到遊戲：${item.gameId}`)
            }

            return game
        })
    })

    // 用途：filter 保留編號不同的項目，相當於移除指定商品，後面的 watch 會保存變更。
    function removeFromCart(gameId) {
        const id = Number(gameId)

        // filter 逐項執行判斷，true 才留下；這裡排除指定編號，再把結果設為新的購物車。
        cart.value = cart.value.filter(item => {
            return item.gameId !== id
        })
    }

    // reduce 從 0 開始逐項加總，供畫面展示；Database 最後成交仍由後端報價決定。
    const cartTotal = computed(() => {
        // reduce 第一個參數是累加函式，第二個 0 是起始總額。
        // 每輪 total 是累計值、game 是目前商品；空車也能回 0，不改商品原價。
        return cartGames.value.reduce((total, game) => {
            return total + game.price
        }, 0)
    })

    // 用途：登入或還原後，排除目前會員已擁有的遊戲；! 表示不包含才保留。
    function removePurchasedGames() {
        cart.value = cart.value.filter(item => {
            // 目前會員擁有權有這個編號就排除，前面的 ! 將「有」反轉成「不保留」。
            return !authStore.purchasedGames.includes(item.gameId)
        })
    }

    // 用途：讀此模式暫存，JSON.parse 轉回資料，逐項驗證存在、整數、不重複，再排除已擁有。
    // Set 是不能重複的集合；壞格式會清成空車並移除壞暫存，不影響資料庫。
    function restoreCart() {
        // 先依目前模式找暫存；getItem 沒找到回 null，和內容是空陣列文字「[]」不同。
        const savedCart = sessionStorage.getItem(storageKey())
        if (savedCart === null) return

        try {
            // JSON.parse 將文字還原成 JS 資料；壞 JSON 會跳 catch，不繼續使用不確定的內容。
            const parsedCart = JSON.parse(savedCart)

            // 合法 JSON 也可能只是數字或物件，所以還要確認是購物車清單。
            if (!Array.isArray(parsedCart)) {
                throw new Error('購物車資料格式錯誤')
            }

            // Set 是記住不重複值的集合；has 查是否看過，add 記住已保留的編號。
            const seenIds = new Set()

            cart.value = parsedCart.filter(item => {
                // ?. 在暫存項目為 null 時避免取值出錯；得到 undefined 後，下面整數檢查會排除它。
                const id = item?.gameId

                // 逐項檢查：不是整數、不存在的商品、重複編號都回 false，因此不會放進還原後的購物車。
                if (!Number.isInteger(id)) return false
                if (!gameStore.getGameById(id)) return false
                if (seenIds.has(id)) return false

                seenIds.add(id)
                return true
            // 只複製 gameId 這個允許欄位；就算暫存多了價格或其他欄位也不拿來當正式資料。
            }).map(item => ({
                gameId: item.gameId
            }))

            removePurchasedGames()
        // 還原失敗就清空這個模式的購物車並移除壞紀錄；不清另一模式，也不刪 MySQL 資料。
        } catch {
            cart.value = []
            sessionStorage.removeItem(storageKey())
        }
    }

    // 用途：遊戲與會員初始化完成後，設定購物車模式、還原並存乾淨清單。
    // 初始化期間 isRestoring 暫停自動保存，避免模式剛切換就把舊資料蓋到另一模式。
    // 同次啟動模式固定，必須重新整理才重新判斷。
    function initialize(mode) {
        // 初始化第一關：只接受 demo／database；&& 表示兩個合法值都不是才失敗。
        if (mode !== 'demo' && mode !== 'database') {
            throw new Error('購物車模式不正確。')
        }

        // 已初始化過就只接受相同模式並直接 return，不重複還原；不同模式必須重整整個網站。
        if (initializationMode !== null) {
            if (initializationMode !== mode) {
                throw new Error('同一次初始化不能切換購物車模式。')
            }

            return
        }

        if (
            // 初始化第二關：商品與會員都要同模式且 ready，也不能正處理會員操作。
            // || 表示任一條件不符合就拒絕；否則可能用錯身份過濾另一模式購物車。
            gameStore.source !== mode
            || gameStore.loadStatus !== 'ready'
            || authStore.source !== mode
            || authStore.loadStatus !== 'ready'
            || authStore.isBusy
        ) {
            throw new Error('請先完成相同模式的遊戲與會員初始化。')
        }

        // 先暫停 watch 保存，再設定模式、清舊記憶體內容、讀正確模式暫存。
        isRestoring = true

        try {
            source.value = mode
            cart.value = []
            restoreCart()
        // finally 無論還原成功或失敗都解除暫停；避免之後正常加入商品卻永遠不保存。
        } finally {
            isRestoring = false
        }

        // 保存驗證、去重與排除已擁有遊戲後的清單。
        sessionStorage.setItem(
            storageKey(),
            JSON.stringify(cart.value)
        )

        // 最後記錄初始化已完成；外面再次同模式呼叫時就能直接結束。
        initializationMode = mode
    }

    // 這份物件是 Store 對外的介面，列出資料及功能。
    // 例如外面呼叫 cartStore.addToCart(4)，列出函式名稱不表示此刻執行它。
    return {
        cart,
        cartCount,
        isInCart,
        addToCart,
        cartGames,
        removeFromCart,
        cartTotal,
        removePurchasedGames,
        restoreCart,
        source,
        initialize
    }
})
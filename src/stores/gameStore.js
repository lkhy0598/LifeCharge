// 這支檔案負責什麼？
// 保存網站目前使用的遊戲清單，並提供「找一款遊戲、搜尋名稱、依標籤篩選」的功能。
// 首頁、搜尋頁、詳細頁與購物車都讀同一份清單，不各自重新向後端抓相同資料。
// 這種讓多個畫面共用資料的地方叫做 Store，由 Pinia 這個套件管理。
//
// 資料怎麼來？
// main.js 先判斷模式，再呼叫這裡的 initialize(mode)。
// 本檔請 gameService.js 依模式載入 JSON 或 API 資料，拿到結果後才保存。
// 本檔本身不寫 SQL、不建立訂單，也不負責會員登入。
//
// 閱讀順序：引用工具 → 準備狀態 → 初始化載入 → 各種查詢 → 列出對外提供的功能。


// import 表示從其他檔案／套件引用工具；{ defineStore } 是取出其中名叫 defineStore 的功能。
// defineStore 用來定義共用資料區；這裡只是引用，下面才會使用它建立遊戲 Store 的定義。
import { defineStore } from 'pinia'
// ref 是 Vue 提供的資料追蹤工具：值改變時，使用它的畫面也能跟著更新。
import { ref } from 'vue'
// loadDemoGames 立即讀出本機 Demo 資料；loadGames(mode) 則依指定模式載入資料。
// ../ 表示從目前 stores 資料夾往上一層，再進入 services 找 gameService。
import { loadDemoGames, loadGames } from '../services/gameService'

// export 讓其他檔案可以引用 useGameStore。
// const 表示不能把 useGameStore 這個名稱重新指派給其他東西，不代表裡面的遊戲資料不能更新。
// defineStore 的第一個參數 'game' 是這個 Store 的識別名稱，不是遊戲分類或資料表名稱。
// 第二個參數 () => { ... } 是稍後建立 Store 時要執行的函式，這種寫法叫箭頭函式。
// 其他檔案使用 const gameStore = useGameStore() 取得目前應用共用的實例。
export const useGameStore = defineStore('game', () => {
    // 步驟 1：建立存放遊戲資料與載入狀態的變數。
    // 先執行 loadDemoGames() 取得初始遊戲清單，再交給 ref 包起來追蹤變化。
    // 這裡有 Demo 初值，不代表正式入口就跳過模式判斷；main.js 仍會等 initialize 完成。
    // 在這支 JS 中用 games.value 讀寫真正的清單；其他檔案透過 Pinia Store 讀取通常不用 .value。
    const games = ref(loadDemoGames())
    // source 記錄 games 的資料來源：'demo'／'database'；載入失敗時會改成 null。
    const source = ref('demo')
    // loadStatus 記錄工作進度：ready 已可用、loading 載入中、error 載入失敗。
    // 它和 source 不同：source 說「哪裡的資料」，loadStatus 說「現在能不能用」。
    const loadStatus = ref('ready')
    // loadError 保存錯誤碼與訊息；null 表示目前沒有錯誤資訊，不是字串 'null'。
    const loadError = ref(null)

    // 步驟 2：記住這次載入工作，防止兩個地方同時要求初始化而重複抓資料。
    // let 表示後面可以重新賦值；這兩個內部控制值不直接給畫面顯示，所以不用 ref。
    // Promise 可以理解成「工作結果的領取單」：工作還沒做完也能先交給別人等待。
    // initializationPromise 之後保存這張領取單，讓同次啟動的呼叫者等待同一份結果。
    let initializationPromise = null
    // 記住這份工作選的是哪個模式；同一個網站執行期間不讓它一半 Demo、一半 Database。
    let initializationMode = null

    // 功能：依編號找一款遊戲，例如 gameStore.getGameById('4')。
    // id 是呼叫者傳入的編號；回傳完整遊戲物件，找不到回 undefined（沒有結果）。
    function getGameById(id) {
        // find 會逐款執行後面的判斷，找到第一款符合的就停止。
        // game => ... 表示「每次取出一款，先叫它 game，再檢查右邊條件」。
        // 網址的 '4' 是文字，JSON 的 4 是數字，Number(id) 先轉數字才能用 === 嚴格比較。
        // return 將找到的結果交回呼叫處；這個查詢不修改 games，也不另外發網路請求。
        return games.value.find(game => game.id === Number(id))
    }

    // 功能：啟動時載入選定模式的遊戲，例如 await gameStore.initialize('database')。
    // mode 是傳入的模式文字；成功時 Promise 完成並交回遊戲清單，失敗時讓等待者收到錯誤。
    // 這個函式不自行判斷後端是否能用，模式已經由 main.js／systemStore 決定。
    function initialize(mode) {
        // 第一步：只接受兩個合法模式。
        // !== 表示「不等於」，&& 表示兩邊都成立；也就是兩個合法值都不是才算錯。
        if (mode !== 'demo' && mode !== 'database') {
            // new Error 建立錯誤資料；Promise.reject 交回一個已失敗的工作結果。
            // 呼叫者 await 它時會進入自己的 catch；return 讓本函式到此結束，不繼續載入。
            return Promise.reject(new Error('遊戲資料模式不正確。'))
        }

        // 第二步：若已建立過載入工作，就不要再啟動第二次。
        // 這裡不只包含「正在載入」，也包含之前已成功或已失敗的那份工作。
        if (initializationPromise) {
            // 若另一個呼叫要求不同模式，直接拒絕，避免其他頁還拿著上一種模式資料。
            if (mode !== initializationMode) {
                return Promise.reject(
                    new Error('同一次初始化不能切換遊戲資料模式。')
                )
            }

            // 模式相同就交回原工作；兩個呼叫者可一起等待同一份資料，不重複呼叫 API。
            // 失敗後也不在這裡偷偷重試；目前網站的重新載入按鈕會重整整個頁面再啟動。
            return initializationPromise
        }

        // 第三步：記住選定模式，將狀態改為載入中，並清掉舊錯誤。
        // 只是準備狀態，還沒把初始 games 當成這次載入成功的資料。
        initializationMode = mode
        loadStatus.value = 'loading'
        loadError.value = null

        // 第四步：建立這次載入工作的 Promise，保存後供後續呼叫共用。
        // Promise.resolve() 建立已完成的起點；.then(...) 安排接著要做的非同步工作。
        // async 表示裡面可以用 await 等待結果；等待時不會卡死瀏覽器的其他工作。
        initializationPromise = Promise.resolve().then(async () => {
            // try 是嘗試執行的區段，發生錯誤會跳到 catch，不繼續走成功步驟。
            try {
                // 把資料取得工作交給 Service；Demo 讀 JSON，Database 則等待 API 回覆。
                // const loadedGames 保存成功拿到的清單；await 失敗時，這行就直接跳 catch。
                const loadedGames = await loadGames(mode)

                // 第五步：只有 Service 成功回傳後，才用新清單取代初值並標記 ready。
                // 這些是 ref 值，所以透過 .value 修改，相關畫面會知道資料已變。
                games.value = loadedGames
                source.value = mode
                loadStatus.value = 'ready'

                // 把成功清單交回這份工作；等待 initialize 的 main.js 才能繼續初始化會員。
                return games.value
            } catch (error) {
                // 失敗路徑：error 是這次抓資料或驗證資料時發出的錯誤。
                // 清空 games 與 source，避免明明選 Database，卻顯示最初的 Demo 資料冒充成功。
                games.value = []
                source.value = null
                loadStatus.value = 'error'
                // 保存畫面可用的簡短錯誤資料，不把整個 Error 物件當一般遊戲資料。
                // ?? 表示左側為 null／undefined 才改用右側備用值，例如錯誤沒有 code 時。
                loadError.value = {
                    code: error.code ?? 'GAMES_LOAD_FAILED',
                    message: error.message ?? '遊戲資料載入失敗。',
                }

                // throw 把錯誤往外傳，讓 main.js 顯示啟動失敗；只寫 loadError 不會通知 await 失敗。
                throw error
            }
        })

        // 第六步：將工作領取單交給呼叫者。不是現在就保證資料好了，必須 await 等它完成。
        // 與前面重複呼叫時回傳同一份 Promise 的規則一致。
        return initializationPromise
    }

    // 功能：以名稱搜尋，例如 gameStore.searchGames('星')；傳入文字，交回符合的遊戲清單。
    function searchGames(keyword) {
        // 第一步：去除前後空白，再把英文字母轉小寫。
        // 例如 '  EPOCH  ' 變成 'epoch'，讓多打空白或大小寫不同不影響查詢。
        const searchText = keyword.trim().toLowerCase()

        // 第二步：! 表示取相反的真假值；空字串在判斷時是假，因此 !空字串 為真。
        // 沒輸入關鍵字就回原清單，相當於顯示全部遊戲，不額外複製資料。
        if (!searchText) {
            return games.value
        }

        // 第三步：filter 逐款檢查，回 true 的保留，false 的排除，產生新的結果清單。
        // 英文名稱也先轉小寫，再用 includes 檢查「是否包含這段字」，不是要求完全相同。
        // || 表示任一條件符合即可，因此英文名或中文名其中一個找到就保留。
        // 只篩選目前記憶體中的資料，不呼叫後端，也不修改 Store 原本清單。
        return games.value.filter(game => {
            return (
                game.title.toLowerCase().includes(searchText) ||
                game.titleZh.toLowerCase().includes(searchText)
            )
        })
    }


    // 功能：傳入類型代碼（例如 'RPG'），回傳具有這個類型的遊戲。
    function getGamesByGenre(genre) {
        // 沒有選類型就顯示全部；這裡預期傳入文字代碼，不是中文顯示標籤。
        if (!genre) {
            return games.value
        }

        // 每款遊戲的 genre 是清單，例如 ['RPG', 'Action']。
        // 這裡 includes 比較清單內是否有完全相同的代碼，與上方文字包含搜尋的用途不同。
        return games.value.filter(game => {
            return game.genre.includes(genre)
        })
    }

    // 功能：不用傳參數，回傳標記為新品的遊戲，供首頁等畫面使用。
    function getNewGames() {
        // status 是商品展示標籤清單；'new' 是資料中的代碼，不是 JavaScript 建立物件的 new 語法。
        // 一款可能同時是新品與精選，因此不是用 status === 'new'，而是檢查清單是否包含它。
        return games.value.filter(game => {
            return game.status.includes('new')
        })
    }

    // 功能：不用傳參數，回傳資料中標記為暢銷的遊戲。
    function getBestsellers() {
        // 只讀 'bestseller' 標籤，不查 orders、不統計銷量，也不因篩選而改商品資料。
        return games.value.filter(game => {
            return game.status.includes('bestseller')
        })
    }

    // 功能：不用傳參數，回傳標記為精選的遊戲。
    function getFeaturedGames() {
        // 和新品／暢銷一樣，以 filter 建結果清單；'featured' 是資料代碼，中文顯示另有對照表。
        return games.value.filter(game => {
            return game.status.includes('featured')
        })
    }

    // 最後：把外面需要使用的資料與功能交給 Pinia。
    // 例如外面可以讀 gameStore.games，或呼叫 gameStore.searchGames('星')。
    // { games } 是 { games: games } 的簡寫，函式名稱也可以用同樣方式放進物件。
    // initializationPromise、initializationMode 沒列出，因此是這支 Store 內部使用的控制值。
    // 對外提供功能不代表此刻執行它；例如列出 initialize，不等於這裡又載入一次。
    return {
        games,
        getGameById,
        searchGames,
        getGamesByGenre,
        getNewGames,
        getBestsellers,
        getFeaturedGames,
        source,
        loadStatus,
        loadError,
        initialize
    }
})

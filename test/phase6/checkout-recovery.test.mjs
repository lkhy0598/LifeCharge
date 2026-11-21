// 使用實際 Checkout setup、Pinia 與 Service；只模擬網路與瀏覽器環境。
// 不啟動 HTTP 服務、不連線資料庫、不送出真實訂單。
// 執行方式：node test/phase6/checkout-recovery.test.mjs。
// assert 是檢查工具，不符合預期會讓測試失敗；PASS 代表該情境的所有檢查都通過。
// 這不是網頁入口，而是在 Node.js 中用假回覆重現斷線、逾時與身份改變。
import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createRenderer, h, nextTick, ssrContextKey } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'

// Vite 只當模組編譯工具，讓測試可讀 .vue 與原本的 Service，不對外開 HTTP 埠。
const server = await createServer({
    server: { middlewareMode: true, watch: null },
    optimizeDeps: { noDiscovery: true, include: [] },
})
// 測試會暫時換掉瀏覽器與計時功能，先記原值，最後恢復，避免影響其他程式。
// 先記全域工具的原值，最後還原；測試會替換 fetch、window、storage 和計時器，不是修改正式程式。
const originals = {
    fetch: globalThis.fetch,
    window: globalThis.window,
    sessionStorage: globalThis.sessionStorage,
    setTimeout: globalThis.setTimeout,
    clearTimeout: globalThis.clearTimeout,
}

// 假的目前時間以毫秒從 0 起算；timerId 產生計時編號，Map 保存到期工作、事件與暫存，供每項測試清空。
let clock = 0
let timerId = 0
const timers = new Map()
const listeners = new Map()
const storage = new Map()
let mounted = null

// 建立假的 sessionStorage、window 事件與計時器；資料只放這次測試的 Map。
// Map 是依名稱存取資料的容器；這不是使用者真正的瀏覽器暫存。
function installBrowser() {
    globalThis.sessionStorage = {
        // 模擬 sessionStorage 讀不到回 null、寫入轉字串、移除指定 key 的介面，讓真正 Service 可不改寫就參與測試。
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, String(value)),
        removeItem: key => storage.delete(key),
    }
    globalThis.window = {
        addEventListener(name, callback) {
            // 同事件可能有多個監聽者，先建立 Set 再放 callback，避免重複與便於卸載移除。
            if (!listeners.has(name)) listeners.set(name, new Set())
            listeners.get(name).add(callback)
        },
        removeEventListener(name, callback) {
            listeners.get(name)?.delete(callback)
        },
    }
    globalThis.setTimeout = (callback, delay = 0) => {
        // 先讓計時器編號加一再記工作；at 是假的現在時間加 delay，這裡不真的等三秒。
        const id = ++timerId
        timers.set(id, { callback, at: clock + delay })
        return id
    }
    globalThis.clearTimeout = id => timers.delete(id)
}

// 等目前非同步工作與 Vue 畫面狀態更新處理完，測試才讀取新的狀態。
// nextTick 是 Vue 提供的更新等待工具，與固定等幾秒不同。
async function flush() {
    await new Promise(setImmediate)
    await nextTick()
}

// 推進假的時鐘，不真的等三秒；依序執行到期的查詢計時器，讓測試快速且可重現。
async function advance(milliseconds) {
    // advance 決定想推進到哪個時間；while 每次找最早到期的工作，沒有就停止。
    const target = clock + milliseconds
    while (true) {
        // entries 將 Map 轉清單，filter 保留目標時間前到期者，sort 找最早的一個；不跳過中間應觸發的工作。
        const due = [...timers.entries()]
            .filter(([, timer]) => timer.at <= target)
            .sort((a, b) => a[1].at - b[1].at)[0]
        if (!due) break
        clock = due[1].at
        // 到期前先移除這次 timer，再執行 callback；callback 若安排下一次查詢，會成為新的計時工作。
        timers.delete(due[0])
        due[1].callback()
        await flush()
    }
    clock = target
    await flush()
}

// 用瀏覽器 Response 的相同介面建立假 API 回覆，讓真正的 apiClient 仍參與格式檢查。
const success = data => new Response(JSON.stringify({ ok: true, data }), {
    status: 200, headers: { 'Content-Type': 'application/json' },
})
const failure = (status, code) => new Response(JSON.stringify({
    ok: false, error: { code, message: code },
}), { status, headers: { 'Content-Type': 'application/json' } })

// 下列是測試資料，不會新增這位會員或訂單到 MySQL。
const memberId = 90001
const storageKey = `lifecharge-database-pending-purchase-${memberId}`
const record = {
    userId: memberId,
    quoteId: 'a'.repeat(64),
    requestKey: 'checkout-recovery-original-key',
    expiresAt: '2030-01-01T00:00:00Z',
    items: [{ gameId: 3, title: 'Game', titleZh: '測試遊戲', unitPrice: '1290.00' }],
    totalAmount: '1290.00', currency: 'TWD',
}
const purchaseResult = (requestKey = record.requestKey) => ({
    userId: memberId,
    order: {
        orderId: '101', requestKey, items: record.items,
        totalAmount: record.totalAmount, currency: 'TWD',
        status: 'completed', createdAt: '2026-10-21T00:00:00Z',
    },
    purchasedGames: [3],
})

try {
    const { default: Checkout } = await server.ssrLoadModule('/src/views/Checkout.vue')
    const { useGameStore } = await server.ssrLoadModule('/src/stores/gameStore.js')
    const { useAuthStore } = await server.ssrLoadModule('/src/stores/authStore.js')
    const { useCartStore } = await server.ssrLoadModule('/src/stores/cartStore.js')

    // 省略 DOM 繪製，保留實際元件 setup、watch 與生命週期。
    // 使用真 Checkout.setup 與 watch，但 render 回 null 不畫實際 UI；這測邏輯，不是像素或手機排版測試。
    const Component = { ...Checkout, render: () => null }
    const rootNode = {}
    const node = properties => ({ ...properties, getRootNode: () => rootNode })
    // 提供 Vue 所需的建立節點／插入等最小介面，讓生命週期能跑；不是開一個真正瀏覽器。
    const renderer = createRenderer({
        createElement: type => node({ type, children: [] }),
        createText: text => node({ text }),
        createComment: text => node({ text }),
        setText() {}, setElementText() {}, patchProp() {}, remove() {},
        insert(node, parent) { parent.children?.push(node) },
        parentNode: () => null, nextSibling: () => null,
    })

    // 每個情境建立獨立 Store、記憶體路由與元件；先清上次計時器、事件與假暫存。
    // mount 是把元件接到測試環境；pending 決定是否放一筆待確認購買來測恢復流程。
    async function mount({ pending = true, mode = 'database' } = {}) {
        // 每項測試前卸載上一元件，?. 在沒有上一元件時安全跳過；清所有假資料確保測試不互相污染。
        mounted?.app.unmount()
        timers.clear()
        listeners.clear()
        storage.clear()
        clock = 0
        installBrowser()

        // 每個情境建立獨立 Store，之後手動放測試身份；不去 /api/login 登入真會員。
        const pinia = createPinia()
        const games = useGameStore(pinia)
        const auth = useAuthStore(pinia)
        const cart = useCartStore(pinia)
        games.source = mode
        auth.source = mode
        auth.currentUser = {
            id: memberId, username: 'fixture', email: 'fixture@example.com',
            displayName: 'Fixture', avatar: '', purchasedGames: [],
        }
        cart.initialize(mode)
        cart.addToCart(3)
        cart.addToCart(4)
        // 預設先放一筆待確認紀錄，測「重新載入後恢復」；pending false 情境則測新送單回覆遺失。
        if (pending) sessionStorage.setItem(storageKey, JSON.stringify(record))

        // 記憶體路由不改瀏覽器網址，也不對外開網站；只讓 Checkout 的路由生命週期可執行。
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/', component: Component }],
        })
        const app = renderer.createApp({ render: () => h(RouterView) })
        app.provide(ssrContextKey, {})
        app.use(pinia)
        app.use(router)
        await router.push('/')
        await router.isReady()
        app.mount(node({ children: [] }))
        await flush()
        // 測試取出 setupState 才能呼叫確認與查看步驟；這是測試環境用法，正式 UI 不靠此內部路徑操作。
        const vm = app._instance.subTree.component.subTree.component.setupState
        mounted = { app, vm, auth, cart }
        return mounted
    }

    // action 是這個測試情境要執行的函式；await 確認整個情境結束才印 PASS。
    async function scenario(name, action) {
        await action()
        console.log(`PASS ${name}`)
    }

    // 情境 1：重新開啟結帳時查到已成交，直接完成，保留沒有在本單購買的購物車商品。
    await scenario('restore completed order automatically; update ownership and preserve other cart items', async () => {
        let gets = 0
        globalThis.fetch = async (path, options) => {
            assert.notEqual(options.method, 'POST')
            assert.match(path, /requestKey=checkout-recovery-original-key/)
            gets++
            return success(purchaseResult())
        }
        const { vm, auth, cart } = await mount()
        assert.equal(vm.currentStep, 3)
        await advance(0)
        // 檢查結果已進第四步；assert 不符合會立刻失敗，所以 PASS 只有整個情境都通過才印。
        assert.equal(vm.currentStep, 4)
        assert.deepEqual([...auth.purchasedGames], [3])
        assert.deepEqual(cart.cartGames.map(game => game.id), [4])
        assert.equal(sessionStorage.getItem(storageKey), null)
        await advance(30000)
        assert.equal(gets, 1)
    })

    // 情境 2：查詢遇到網路／資料庫失敗時，只重查 GET，不能自行再送購買 POST。
    await scenario('network and database failure retry GET automatically, never POST', async () => {
        let gets = 0
        globalThis.fetch = async (path, options) => {
            assert.equal(options.method, 'GET')
            gets++
            if (gets === 1) throw new TypeError('offline')
            if (gets === 2) return failure(503, 'PURCHASE_QUERY_UNAVAILABLE')
            return success(purchaseResult())
        }
        const { vm } = await mount()
        await advance(0)
        assert.equal(vm.currentStep, 3)
        assert.match(vm.checkoutMessage, /連線恢復後將自動/)
        assert.ok(sessionStorage.getItem(storageKey))
        await advance(3000)
        assert.equal(vm.pendingQueryNotFound, false)
        await advance(3000)
        assert.equal(vm.currentStep, 4)
        assert.equal(gets, 3)
    })

    // 情境 3：明確查無訂單才停止查詢，等使用者再次確認；重試仍沿用原購買識別碼。
    await scenario('404 stops polling and waits for confirmation; retry preserves the original key', async () => {
        let posts = 0
        let gets = 0
        globalThis.fetch = async (path, options) => {
            if (path === '/api/csrf') return success({ csrfToken: 'b'.repeat(64) })
            if (options.method === 'POST') {
                posts++
                assert.equal(JSON.parse(options.body).requestKey, record.requestKey)
                assert.ok(sessionStorage.getItem(storageKey))
                return success(purchaseResult())
            }
            gets++
            return failure(404, 'PURCHASE_NOT_FOUND')
        }
        const { vm } = await mount()
        await advance(0)
        assert.equal(vm.currentStep, 3)
        assert.equal(vm.pendingQueryNotFound, true)
        assert.equal(vm.checkoutMessage, '尚未完成購買，請再次確認購買。')
        await advance(30000)
        assert.equal(gets, 1)
        assert.equal(posts, 0)
        await vm.confirmPurchase()
        await flush()
        assert.equal(posts, 1)
        assert.equal(vm.currentStep, 4)
    })

    // 情境四先模擬 POST 已提交但回覆遺失，再查 GET 拿成功；posts 必須仍是 1，不能背景二次購買。
    await scenario('lost POST response recovers through GET without a second purchase', async () => {
        let posts = 0
        let submittedKey
        globalThis.fetch = async (path, options) => {
            if (path === '/api/csrf') return success({ csrfToken: 'b'.repeat(64) })
            if (options.method === 'POST') {
                posts++
                submittedKey = JSON.parse(options.body).requestKey
                assert.equal(JSON.parse(sessionStorage.getItem(storageKey)).requestKey, submittedKey)
                throw new TypeError('response lost after commit')
            }
            return success(purchaseResult(submittedKey))
        }
        const { vm, cart } = await mount({ pending: false })
        cart.removeFromCart(4)
        vm.checkoutQuote = { ...record }
        vm.currentStep = 3
        await vm.confirmPurchase()
        await advance(0)
        assert.equal(vm.currentStep, 4)
        assert.equal(posts, 1)
    })

    // 情境五讓查詢故意不回，再連續發 online；應只一個 GET。卸載後才回覆，仍不能更新身份或繼續輪詢。
    await scenario('online events cannot overlap queries; unmount discards late responses', async () => {
        let gets = 0
        let resolveQuery
        globalThis.fetch = () => {
            gets++
            return new Promise(resolve => { resolveQuery = resolve })
        }
        const { vm, app, auth } = await mount()
        await advance(0)
        for (const callback of listeners.get('online') ?? []) {
            callback()
            callback()
        }
        await advance(0)
        assert.equal(gets, 1)
        app.unmount()
        mounted = null
        resolveQuery(success(purchaseResult()))
        await flush()
        await advance(30000)
        assert.equal(gets, 1)
        assert.equal(vm.currentStep, 3)
        assert.deepEqual([...auth.purchasedGames], [])
        assert.ok(sessionStorage.getItem(storageKey))
        assert.equal(listeners.get('online')?.size, 0)
    })

    // 情境六在等 GET 時換會員，再放舊會員成功回應；新會員不能得到舊會員的遊戲。
    await scenario('changing member ignores the previous member query response', async () => {
        let resolveQuery
        globalThis.fetch = () => new Promise(resolve => { resolveQuery = resolve })
        const { vm, auth } = await mount()
        await advance(0)
        auth.currentUser = { ...auth.currentUser, id: memberId + 1 }
        await flush()
        resolveQuery(success(purchaseResult()))
        await flush()
        assert.equal(vm.currentStep, 1)
        assert.deepEqual([...auth.purchasedGames], [])
        assert.ok(sessionStorage.getItem(storageKey))
    })

    // 情境七先回 401，保留待確認並打開登入；模擬原會員回來後，只 GET 確認，不重買。
    await scenario('expired login preserves pending purchase and resumes after signing in again', async () => {
        let expired = true
        let posts = 0
        globalThis.fetch = async (path, options) => {
            if (options.method === 'POST') posts++
            if (path === '/api/csrf') return success({ csrfToken: 'b'.repeat(64) })
            if (expired) return failure(401, 'UNAUTHENTICATED')
            return success(purchaseResult())
        }
        const { vm, auth } = await mount()
        const originalMember = { ...auth.currentUser }
        await advance(0)
        assert.equal(auth.currentUser, null)
        assert.equal(auth.isLoginOpen, true)
        assert.ok(sessionStorage.getItem(storageKey))
        assert.match(vm.checkoutMessage, /重新登入/)
        expired = false
        auth.currentUser = originalMember
        await flush()
        await advance(0)
        assert.equal(vm.currentStep, 4)
        assert.equal(posts, 0)
    })

    // 情境八讓任何 fetch 都直接失敗，Demo 不應呼叫它；也不能恢復 Database 待確認單。
    await scenario('Demo never restores or queries a Database pending purchase', async () => {
        globalThis.fetch = async () => { throw new Error('Demo must not call API') }
        const { vm } = await mount({ mode: 'demo' })
        await advance(30000)
        assert.equal(vm.currentStep, 1)
        assert.equal(vm.pendingPurchase, null)
        assert.equal(vm.checkoutMessage, '')
    })
// 不論測試是否通過，都卸載元件、恢復全域工具並關閉 Vite，避免留下背景工作。
} finally {
    mounted?.app.unmount()
    // 最後把原本全域工具放回，關 Vite；就算某個 assert 發錯也執行 finally，避免留下假環境。
    Object.assign(globalThis, originals)
    await server.close()
}

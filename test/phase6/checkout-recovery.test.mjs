// 使用實際 Checkout setup、Pinia 與 Service；只模擬網路與瀏覽器環境。
// 不啟動 HTTP 服務、不連線資料庫、不送出真實訂單。
import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createRenderer, h, nextTick, ssrContextKey } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'

const server = await createServer({
    server: { middlewareMode: true, watch: null },
    optimizeDeps: { noDiscovery: true, include: [] },
})
const originals = {
    fetch: globalThis.fetch,
    window: globalThis.window,
    sessionStorage: globalThis.sessionStorage,
    setTimeout: globalThis.setTimeout,
    clearTimeout: globalThis.clearTimeout,
}

let clock = 0
let timerId = 0
const timers = new Map()
const listeners = new Map()
const storage = new Map()
let mounted = null

function installBrowser() {
    globalThis.sessionStorage = {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, String(value)),
        removeItem: key => storage.delete(key),
    }
    globalThis.window = {
        addEventListener(name, callback) {
            if (!listeners.has(name)) listeners.set(name, new Set())
            listeners.get(name).add(callback)
        },
        removeEventListener(name, callback) {
            listeners.get(name)?.delete(callback)
        },
    }
    globalThis.setTimeout = (callback, delay = 0) => {
        const id = ++timerId
        timers.set(id, { callback, at: clock + delay })
        return id
    }
    globalThis.clearTimeout = id => timers.delete(id)
}

async function flush() {
    await new Promise(setImmediate)
    await nextTick()
}

async function advance(milliseconds) {
    const target = clock + milliseconds
    while (true) {
        const due = [...timers.entries()]
            .filter(([, timer]) => timer.at <= target)
            .sort((a, b) => a[1].at - b[1].at)[0]
        if (!due) break
        clock = due[1].at
        timers.delete(due[0])
        due[1].callback()
        await flush()
    }
    clock = target
    await flush()
}

const success = data => new Response(JSON.stringify({ ok: true, data }), {
    status: 200, headers: { 'Content-Type': 'application/json' },
})
const failure = (status, code) => new Response(JSON.stringify({
    ok: false, error: { code, message: code },
}), { status, headers: { 'Content-Type': 'application/json' } })

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
    const Component = { ...Checkout, render: () => null }
    const rootNode = {}
    const node = properties => ({ ...properties, getRootNode: () => rootNode })
    const renderer = createRenderer({
        createElement: type => node({ type, children: [] }),
        createText: text => node({ text }),
        createComment: text => node({ text }),
        setText() {}, setElementText() {}, patchProp() {}, remove() {},
        insert(node, parent) { parent.children?.push(node) },
        parentNode: () => null, nextSibling: () => null,
    })

    async function mount({ pending = true, mode = 'database' } = {}) {
        mounted?.app.unmount()
        timers.clear()
        listeners.clear()
        storage.clear()
        clock = 0
        installBrowser()

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
        if (pending) sessionStorage.setItem(storageKey, JSON.stringify(record))

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
        const vm = app._instance.subTree.component.subTree.component.setupState
        mounted = { app, vm, auth, cart }
        return mounted
    }

    async function scenario(name, action) {
        await action()
        console.log(`PASS ${name}`)
    }

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
        assert.equal(vm.currentStep, 4)
        assert.deepEqual([...auth.purchasedGames], [3])
        assert.deepEqual(cart.cartGames.map(game => game.id), [4])
        assert.equal(sessionStorage.getItem(storageKey), null)
        await advance(30000)
        assert.equal(gets, 1)
    })

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

    await scenario('Demo never restores or queries a Database pending purchase', async () => {
        globalThis.fetch = async () => { throw new Error('Demo must not call API') }
        const { vm } = await mount({ mode: 'demo' })
        await advance(30000)
        assert.equal(vm.currentStep, 1)
        assert.equal(vm.pendingPurchase, null)
        assert.equal(vm.checkoutMessage, '')
    })
} finally {
    mounted?.app.unmount()
    Object.assign(globalThis, originals)
    await server.close()
}

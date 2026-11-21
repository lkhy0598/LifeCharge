// 本檔保存目前會員、擁有權與登入視窗狀態，並按模式呼叫會員 Service。
// Database 身份以後端 Session 為準，Demo 才從 JSON 與 sessionStorage 恢復。
// Store 是 Pinia 管理的共用狀態，讓不同 Vue 畫面使用同一份資料。
// defineStore 定義它，use...Store() 取得目前網站的實例；不是每次呼叫都建立另一份會員。
// ref() 建立畫面會跟著更新的資料，JS 內用 .value 讀寫；computed() 依資料算出結果。
// return {...} 列出供其他檔案使用的資料與函式，沒有列出的只在這個 Store 內使用。
// Promise 是尚待完成的工作；async／await 等待網路結果，catch 接失敗，finally 負責收尾。

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import usersData from '../data/users.json'
import { useGameStore } from './gameStore'
import {
    normalizeDemoMember,
    getDatabaseMember,
    loginDatabaseMember,
    registerDatabaseMember,
    logoutDatabaseMember,
    recoverDatabaseSession,
} from '../services/authService'

export const useAuthStore = defineStore('auth', () => {
    // 複製 Demo 資料，特別把 purchasedGames 也複製，避免模擬購買直接改到引用的 JSON 物件。
    // ... 是展開語法；currentUser null 表示目前沒有登入，source 是這份身份的模式來源。
    const users = ref(usersData.map(user => ({
        ...user,
        purchasedGames: [...user.purchasedGames],
    })))
    // currentUser 保存目前畫面可用的公開會員，null 表示未登入；不是儲存帳密的地方。
    const currentUser = ref(null)
    // source 是身份來源，isBusy 防連按，loadStatus 表示初始化進度，loadError 保存初始化錯誤。
    const source = ref('demo')
    const isBusy = ref(false)
    const loadStatus = ref('ready')
    const loadError = ref(null)

    // 保存同一次初始化工作與模式；Promise 就像工作結果領取單，讓多處等同一份結果而不重查 me。
    let initializationPromise = null
    let initializationMode = null
    // 這兩個 key 僅供 Demo：一個記登入編號，一個記各會員的模擬擁有權，Database 不靠它們認證。
    const LOGIN_STORAGE_KEY = 'lifecharge-current-user-id'
    const PURCHASES_STORAGE_KEY = 'lifecharge-user-purchases'

    const isLoggedIn = computed(() => {
        // 只要目前公開會員不是 null 就視為已登入；真正每次寫入仍由後端再次確認 Session。
        return currentUser.value !== null
    })

    // ?. 在沒有會員時避免讀取失敗，?? [] 表示沒值就提供空清單，畫面可以安全判斷已擁有。
    const purchasedGames = computed(() => {
        return currentUser.value?.purchasedGames ?? []
    })

    // 用途：只在 Demo 比對 JSON 帳號／Email 與測試密碼，成功更新會員並保存編號。
    // find 找符合的測試會員；回 true／false 供登入畫面判斷，不呼叫後端。
    function loginDemo(username, password) {
        // 登入 Demo 先去掉帳號前後空白並轉小寫，讓帳號與 Email 不分英文大小寫；密碼不這樣處理。
        const identifier = username.trim().toLowerCase()

        // find 逐位比對，找到第一位符合就停止；這裡查的是 Demo 記憶體資料，不是 MySQL。
        const user = users.value.find(user => {
            const identityMatches =
                user.username.toLowerCase() === identifier
                || user.email.toLowerCase() === identifier

            // 身份符合且密碼完全相同才成功；&& 要兩邊都成立。這是公開測試帳密，不是正式密碼驗證。
            return identityMatches && user.password === password
        })

        // 找不到就 return false；後續不更新 currentUser，登入視窗會顯示帳密錯誤。
        if (!user) {
            return false
        }

        // 成功後只放整理過的公開欄位與頭像，避免把 JSON 的密碼帶入目前會員物件。
        currentUser.value = normalizeDemoMember(user)

        // sessionStorage 只能存文字，所以 String(user.id) 先轉文字；之後重整可找回同一個 Demo 會員。
        sessionStorage.setItem(LOGIN_STORAGE_KEY, String(user.id))

        return true
    }

    // 用途：清除 Demo 目前身份與登入編號暫存，保留該分頁購買紀錄及購物車。
    function logoutDemo() {
        currentUser.value = null
        // Demo 登出只刪登入編號，不刪擁有權與購物車；Database 則有自己的後端登出流程。
        sessionStorage.removeItem(LOGIN_STORAGE_KEY)
    }
    // 用途：會員操作共用的忙碌保護與錯誤收尾，action 是傳入的「要執行的函式」。
    // CSRF 失效時重新查身份／token，更新提示但仍把原操作當失敗，不自行重送。
    // finally 一定解除 busy，避免畫面永遠鎖住。
    async function runMemberAction(action) {
        // 會員操作第一關：初始化必須先完成；否則畫面可能拿還沒確定的身份去操作。
        if (loadStatus.value !== 'ready') {
            throw new Error('會員資料尚未就緒，請重新載入後再試。')
        }

        // 正在處理上一個登入／註冊／登出時拒絕再執行。這和按鈕 disabled 一起防止重複操作。
        if (isBusy.value) {
            throw new Error('會員操作正在處理中，請稍候。')
        }

        isBusy.value = true

        try {
            // action 是外面傳入的操作函式；現在才真正執行它，await 等成功或讓失敗進 catch。
            return await action()
        } catch (error) {
            if (
                source.value === 'database'
                && error.status === 403
                && error.code === 'CSRF_TOKEN_INVALID'
            ) {
                // 先記下恢復前會員編號；重查後可能是原會員、另一位會員或已登出，提示必須分開。
                const previousId = currentUser.value?.id ?? null

                // 若重新驗證失敗，會直接拋出該錯誤，
                // 不清除原身份，也不假裝驗證成功。
                // 只重新取得身份與 token，不再執行 action，避免原操作有副作用時被重送。
                const user = await recoverDatabaseSession()

                currentUser.value = user

                // null 是後端明確未登入；不是 null 但編號不同代表身份已切換；相同則只是驗證更新。
                if (user === null) {
                    error.message =
                        '目前未登入或登入已過期，請重新登入後再操作。'
                } else if (user.id !== previousId) {
                    error.message =
                        '登入身份已更新，請確認目前會員後重新操作。'
                } else {
                    error.message =
                        '操作驗證已更新，請重新確認後再操作。'
                }
            }

            // 不重新呼叫 action，原操作仍以錯誤結束。
            // 把原操作仍以錯誤交回畫面，由使用者確認後再操作；身份恢復成功不等於原操作成功。
            throw error
        } finally {
            // finally 一定解除忙碌，包含 API 失敗或驗證恢復失敗，不讓視窗永遠不能操作。
            isBusy.value = false
        }
    }

    // 用途：對外統一的登入入口；Demo 本機比對，Database 呼叫 Service 等後端確認。
    // 確認完成才更新 currentUser；return true 表示畫面可處理登入成功。
    async function login(identifier, password) {
        // 把真正登入動作包成函式傳給共同保護層；這個箭頭函式稍後由 action() 呼叫。
        return runMemberAction(async () => {
            // 依已確定的模式分派：Demo 只用測試資料比對，Database 等後端 API；不能失敗後改另一模式。
            if (source.value === 'demo') {
                return loginDemo(identifier, password)
            }

            if (source.value !== 'database') {
                throw new Error('會員資料模式尚未就緒。')
            }

            // 等後端比對完成才接受公開會員結果並設 currentUser；瀏覽器不自行認定資料庫密碼正確。
            const user = await loginDatabaseMember(identifier, password)
            currentUser.value = user

            return true
        })
    }

    // 用途：按模式登出；Database 必須等後端確認後才清前端身份，避免假裝登出成功。
    async function logout() {
        return runMemberAction(async () => {
            if (source.value === 'demo') {
                logoutDemo()
                return
            }

            if (source.value !== 'database') {
                throw new Error('會員資料模式尚未就緒。')
            }

            // 先等後端清 Session 成功，後面才清 currentUser；網路斷線時不假裝已確認登出。
            await logoutDatabaseMember()

            // 後端確認登出成功，才清除前端身份。
            currentUser.value = null
        })
    }

    // 用途：只允許 Database 註冊，成功後同步後端自動登入的會員。
    // input 是表單物件，錯誤由 runMemberAction 往外傳給 LoginDialog。
    async function register(input) {
        return runMemberAction(async () => {
            if (source.value !== 'database') {
                throw new Error('目前模式不提供會員註冊。')
            }

            // 只在 Database 開放註冊；後端成功會自動登入，這裡同步結果，不直接寫 JSON。
            const user = await registerDatabaseMember(input)
            currentUser.value = user

            return true
        })
    }
    // 用途：恢復 Demo 先前購買與登入編號，再將 JSON 會員轉成畫面格式。
    // 不是 PHP Session 恢復；Database 若呼叫這裡會拒絕，避免身份混用。
    function restoreSession() {
        if (source.value !== 'demo') {
            throw new Error('Database 身份必須由後端 session 恢復。')
        }

        // 恢復 Demo 登入前先還原各會員的模擬購買，接著取得的 currentUser 才含最新擁有權。
        restorePurchases()

        // 暫存編號是文字，下面用 String(user.id) 比對；沒紀錄直接 return，維持未登入。
        const savedId = sessionStorage.getItem(LOGIN_STORAGE_KEY)

        if (savedId === null) return

        const user = users.value.find(user => {
            return String(user.id) === savedId
        })

        if (!user) {
            sessionStorage.removeItem(LOGIN_STORAGE_KEY)
            return
        }

        currentUser.value = normalizeDemoMember(user)
    }

    // 用途：main.js 啟動時傳模式，先確認同模式遊戲已 ready，再恢復會員。
    // Promise 共用單次工作；loading 期間 busy，成功 ready，失敗 error 並交給入口顯示。
    // Database 的未登入是 currentUser null，不代表載入失敗。
    function initialize(mode) {
        // 初始化只接受明確模式；Promise.reject 建立失敗結果，外面 await 會進入 catch。
        if (mode !== 'demo' && mode !== 'database') {
            return Promise.reject(new Error('會員資料模式不正確。'))
        }

        // 已開始的同模式初始化共用結果；若傳另一模式就拒絕，不讓身份中途混用。
        if (initializationPromise) {
            if (mode !== initializationMode) {
                return Promise.reject(
                    new Error('同一次初始化不能切換會員資料模式。')
                )
            }

            return initializationPromise
        }

        if (isBusy.value) {
            return Promise.reject(
                new Error('會員操作尚未完成，不能開始初始化。')
            )
        }

        const gameStore = useGameStore()

        if (
            // 先確認遊戲 Store 已同模式且 ready，因為 Demo 擁有權恢復需要驗證遊戲存在。
            gameStore.source !== mode
            || gameStore.loadStatus !== 'ready'
        ) {
            return Promise.reject(
                new Error('請先完成相同模式的遊戲資料初始化。')
            )
        }

        // 開始本次初始化：記模式、先清舊身份、標記 loading 與 busy，再建立非同步工作。
        initializationMode = mode
        source.value = mode
        currentUser.value = null
        loadStatus.value = 'loading'
        loadError.value = null
        isBusy.value = true

        initializationPromise = Promise.resolve().then(async () => {
            try {
                if (mode === 'demo') {
                    restoreSession()
                } else {
                    // Database 重新整理靠 /api/me 恢復後端身份；回 null 只是未登入，也可初始化成功。
                    currentUser.value = await getDatabaseMember()
                }

                loadStatus.value = 'ready'
                return currentUser.value
            } catch (error) {
                currentUser.value = null
                loadStatus.value = 'error'
                // 失敗保存錯誤摘要；?? 在缺 code／message 時給備用文字，然後 throw 通知 main 顯示失敗。
                loadError.value = {
                    code: error.code ?? 'MEMBER_LOAD_FAILED',
                    message: error.message ?? '會員資料載入失敗。',
                }

                throw error
            } finally {
                isBusy.value = false
            }
        })

        return initializationPromise
    }

    const isLoginOpen = ref(false)
    const loginDestination = ref(null)

    // 用途：保存登入後要去的 destination，並打開登入視窗。
    // 例如受保護的會員頁或結帳頁讓使用者登入後能接續，不直接執行購買。
    function requestLogin(destination = null) {
        // 保存登入成功後應接續的路徑，例如 /checkout；接著開視窗，不是立刻跳頁或購買。
        loginDestination.value = destination
        isLoginOpen.value = true
    }

    // 用途：關閉登入視窗並清除先前目的地，避免下一次登入誤跳舊頁。
    function cancelLogin() {
        isLoginOpen.value = false
        // 取消後清舊目的地，避免下一次從別處登入卻跳到之前的結帳。
        loginDestination.value = null
    }

    // 用途：只供 Demo 模擬購買，核對商品後加入擁有權並保存分頁紀錄。
    // Database 購買走 purchaseService 與 Checkout，不使用這個函式。
    // Set 排除清單重複，先保存成功才改畫面，不建立 MySQL 訂單。
    function purchaseGames(gameIds) {
        if (source.value !== 'demo') {
            throw new Error('Database 購買功能尚未接入。')
        }

        if (!currentUser.value) {
            throw new Error('請先登入')
        }

        const gameStore = useGameStore()
        // Set 去掉重複編號，再展開回陣列；Demo 一款遊戲只能取得一次擁有權。
        const ids = [...new Set(gameIds)]

        // some 只要發現任一不存在商品就整批拒絕，不略過壞項目後繼續購買。
        if (ids.some(id => !gameStore.getGameById(id))) {
            throw new Error('購買清單包含不存在的遊戲')
        }

        // filter 只保留目前還沒擁有的編號，之後的成交摘要也只計這些新取得商品。
        const newIds = ids.filter(id => {
            return !currentUser.value.purchasedGames.includes(id)
        })

        // 把原擁有權與新編號合併成新清單；... 表示把陣列項目一個個放進來。
        const purchasedIds = [
            ...currentUser.value.purchasedGames,
            ...newIds
        ]

        const user = users.value.find(user => {
            return user.id === currentUser.value.id
        })

        // 先保存；失敗會拋出錯誤，不執行後面的更新
        // 先確定能存入此分頁，再更新記憶體中的會員；保存失敗就不執行後面的成功更新。
        savePurchases(user.id, purchasedIds)

        // 保存成功後，才更新畫面使用的資料
        user.purchasedGames = [...purchasedIds]
        currentUser.value.purchasedGames = [...purchasedIds]

        return newIds
    }

    // 用途：組合每位 Demo 會員的擁有權對照，將指定會員換成新清單後存成 JSON。
    // 資料留在此分頁 sessionStorage，不更新原始 users.json 或資料庫。
    function savePurchases(userId, purchasedIds) {
        // 建立以會員編號為鍵的對照，例如 records[1] 是會員 1 的遊戲清單。
        const records = {}

        users.value.forEach(user => {
            // 只替本次指定會員換擁有權，其他會員複製原清單，避免一人購買蓋掉全部人的資料。
            records[user.id] = user.id === userId
                ? [...purchasedIds]
                : [...user.purchasedGames]
        })

        sessionStorage.setItem(
            PURCHASES_STORAGE_KEY,
            JSON.stringify(records)
        )
    }

    // 用途：讀回 Demo 購買，核對遊戲編號存在、合併初始擁有權並去重。
    // 暫存壞掉就移除該 key，不把壞資料當正式擁有權。
    function restorePurchases() {
        const saved = sessionStorage.getItem(PURCHASES_STORAGE_KEY)
        if (saved === null) return

        try {
            // 把文字還原資料；可解析的 JSON 也可能不是物件，所以接著再檢查形狀。
            const records = JSON.parse(saved)

            if (!records || typeof records !== 'object' || Array.isArray(records)) {
                throw new Error('購買紀錄格式錯誤')
            }

            const gameStore = useGameStore()

            users.value.forEach(user => {
                const savedIds = records[user.id]

                if (!Array.isArray(savedIds)) return

                // 暫存可能過期或被修改，只保留整數且真的存在的商品，再與初始擁有權合併去重。
                const validIds = savedIds.filter(id => {
                    return Number.isInteger(id) && gameStore.getGameById(id)
                })

                user.purchasedGames = [
                    ...new Set([...user.purchasedGames, ...validIds])
                ]
            })
        } catch {
            sessionStorage.removeItem(PURCHASES_STORAGE_KEY)
        }
    }

    // 用途：確認目前模式與會員仍符合成交結果，才更新 purchasedGames。
    // expectedUserId 是送單時會員，避免等待中換人後把舊結果套到別人；不寫資料庫。
    function applyDatabasePurchaseResult(result, expectedUserId) {
        if (
            // 套用成交結果前核對模式、初始化完成、目前會員與送單會員一致，防止換人後套用遲到回覆。
            source.value !== 'database'
            || loadStatus.value !== 'ready'
            || !currentUser.value
            || currentUser.value.id !== expectedUserId
            || result?.userId !== expectedUserId
        ) {
            throw new Error('會員身份已變更，請重新確認登入與購買結果。')
        }

        // 後端成功結果的擁有權仍要檢查是正整數且不重複；畫面不能隨便接受不完整結果。
        const ids = result.purchasedGames

        if (
            !Array.isArray(ids)
            || !ids.every(id => Number.isInteger(id) && id > 0)
            || new Set(ids).size !== ids.length
        ) {
            throw new Error('購買結果的遊戲清單格式不正確。')
        }

        // 複製目前會員的其他欄位，只替換 purchasedGames；不在這裡造訂單或把資料寫進 MySQL。
        currentUser.value = {
            ...currentUser.value,
            purchasedGames: [...ids],
        }
    }

    // 用途：重新向後端確認會員與 token，再一次同步身份。
    // 服務故障仍拋錯，不因查不到回覆就直接清除會員。
    async function revalidateDatabaseMember() {
        return runMemberAction(async () => {
            if (source.value !== 'database') {
                throw new Error('目前不是 Database 會員模式。')
            }

            // 重查身份與 token 都成功後才同步；失敗會經共同保護層往外拋，不先清空原身份。
            const user = await recoverDatabaseSession()

            // 確認身份與 token 均可取得後，才同步狀態。
            currentUser.value = user

            return user
        })
    }

    // 對外列出會員狀態與操作；例如元件用 authStore.login()，內部的初始化 Promise 不提供畫面修改。
    return {
        users,
        currentUser,
        isLoggedIn,
        purchasedGames,
        login,
        logout,
        restoreSession,
        isLoginOpen,
        loginDestination,
        requestLogin,
        cancelLogin,
        purchaseGames,
        source,
        isBusy,
        register,
        loadStatus,
        loadError,
        initialize,
        applyDatabasePurchaseResult,
        revalidateDatabaseMember,
    }
})
<template>
    <div class="page-container">
        <div class="mt-10 mb-8">
            <h2>結帳</h2>
        </div>
        <section class="checkout-steps mb-6">
            <v-row>
                <v-col cols="3" class="checkout-step" :class="{
                    'is-current': currentStep === 1,
                    'is-complete': currentStep > 1
                }">
                    <v-avatar class="step-circle" :size="42">
                        <v-icon v-if="currentStep > 1" icon="mdi-check" />
                        <span v-else>1</span>
                    </v-avatar>
                    <p class="mt-2">確認商品</p>
                    <v-sheet class="mt-3 step-line" :height="5" rounded />
                </v-col>
                <v-col cols="3" class="checkout-step" :class="{
                    'is-current': currentStep === 2,
                    'is-complete': currentStep > 2
                }">
                    <v-avatar class="step-circle" :size="42">
                        <v-icon v-if="currentStep > 2" icon="mdi-check" />
                        <span v-else>2</span>
                    </v-avatar>
                    <p class="mt-2">付款資料</p>
                    <v-sheet class="mt-3 step-line" :height="5" rounded />
                </v-col>
                <v-col cols="3" class="checkout-step" :class="{
                    'is-current': currentStep === 3,
                    'is-complete': currentStep > 3
                }">
                    <v-avatar class="step-circle" :size="42">
                        <v-icon v-if="currentStep > 3" icon="mdi-check" />
                        <span v-else>3</span>
                    </v-avatar>
                    <p class="mt-2">確認訂單</p>
                    <v-sheet class="mt-3 step-line" :height="5" rounded />
                </v-col>
                <v-col cols="3" class="checkout-step" :class="{
                    'is-current': currentStep === 4,
                    'is-complete': currentStep === 4
                }">
                    <v-avatar class="step-circle" :size="42">
                        <v-icon v-if="currentStep === 4" icon="mdi-check" />
                        <span v-else>4</span>
                    </v-avatar>
                    <p class="mt-2">購買完成</p>
                    <v-sheet class="mt-3 step-line" :height="5" rounded />
                </v-col>
            </v-row>
        </section>
        <v-alert v-if="checkoutMessage" :type="purchaseError ? 'error' : 'info'" variant="tonal" class="mb-6"
            :role="purchaseError ? 'alert' : 'status'" aria-live="polite">
            {{ checkoutMessage }}
        </v-alert>
        <v-card class="checkout-card pa-6" rounded="xl">
            <section v-if="currentStep === 1">
                <h2>1. 確認商品</h2>
                <template v-if="cartStore.cartCount > 0">
                    <template v-for="game in cartStore.cartGames" :key="game.id">
                        <div class="checkout-item d-flex align-center ga-4 py-4">
                            <v-img class="checkout-cover" :src="game.cover" :alt="`${game.titleZh}封面`" width="120"
                                max-width="120" :aspect-ratio="3 / 4" rounded="lg" cover />
                            <div class="checkout-item-info flex-grow-1">
                                <h3>{{ game.titleZh }}</h3>
                                <p>{{ game.title }}</p>
                            </div>
                            <div class="checkout-item-actions d-flex align-center ga-4">
                                <h3>NT$ {{ game.price.toLocaleString('en-US') }}</h3>
                                <v-btn icon="mdi mdi-trash-can-outline" variant="text" size="default"
                                    color="var(--color-primary)" :aria-label="`移除${game.titleZh}`"
                                    @click="cartStore.removeFromCart(game.id)" />
                            </div>
                        </div>
                        <v-divider color="var(--color-border)" opacity="1" class="mt-2" />
                    </template>
                    <div class="my-6 d-flex justify-space-between align-center">
                        <p>共 {{ cartStore.cartCount }} 款遊戲</p>
                        <div class="d-flex align-center ga-4 pr-2">
                            <p class="font-weight-bold">訂單總額</p>
                            <p class="order-total font-weight-bold">NT$ {{ cartStore.cartTotal.toLocaleString('en-US')
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="checkout-actions d-flex justify-end">
                        <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6" @click="currentStep = 2">
                            下一步：付款資料
                            <v-icon end icon="mdi-arrow-right" />
                        </v-btn>
                    </div>
                </template>

                <div v-else class="text-center py-10">
                    <p class="mb-4">目前購物車尚無商品</p>
                    <v-btn to="/search" color="primary" rounded="lg">
                        探索遊戲
                    </v-btn>
                </div>
            </section>
            <section v-else-if="currentStep === 2">
                <h2>2. 付款資料</h2>
                <v-sheet class="payment-notice pa-4 my-6 d-flex align-center" rounded="lg">
                    <v-icon class="mdi mdi-alert-circle-outline flex-shrink-0 mr-2" :size="24" />
                    本網站為前端作品展示，僅模擬信用卡付款，不會進行實際扣款，也不會儲存信用卡資訊。
                </v-sheet>
                <v-form ref="paymentForm" @submit.prevent="submitPayment">
                    <label for="card-number" class="d-block mb-2">信用卡卡號</label>
                    <v-text-field v-model="formattedCardNumber" id="card-number" variant="outlined" rounded="xl"
                        placeholder="4242 4242 4242 4242" inputmode="numeric" :rules="[validateCardNumber]"
                        validate-on="blur" :disabled="isQuoting">
                    </v-text-field>
                    <v-row class="payment-fields mt-2">
                        <v-col cols="12" sm="6">
                            <label for="card-expiry" class="d-block mb-2">有效期限</label>
                            <v-text-field v-model="formattedCardExpiry" id="card-expiry" variant="outlined" rounded="xl"
                                placeholder="MM/YY" maxlength="5" inputmode="numeric" :rules="[validateCardExpiry]"
                                validate-on="blur" :disabled="isQuoting">
                            </v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <label for="card-cvv" class="d-block mb-2">安全碼 CVV</label>
                            <v-text-field v-model="cardCvv" id="card-cvv" variant="outlined" rounded="xl"
                                placeholder="3～4 位數字" maxlength="4" inputmode="numeric" :rules="[validateCardCvv]"
                                validate-on="blur" :disabled="isQuoting">
                            </v-text-field>
                        </v-col>
                    </v-row>
                    <v-divider color="var(--color-border)" opacity="1" class="mt-4" />
                    <div class="checkout-actions d-flex justify-space-between mt-6 ga-3">
                        <v-btn rounded="lg" height="auto" class="py-3 px-6" variant="outlined" type="button"
                            @click="backToCart" :disabled="isQuoting">
                            <v-icon start icon="mdi-arrow-left" />
                            上一步：確認商品
                        </v-btn>
                        <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6" type="submit"
                            :loading="isQuoting" :disabled="isQuoting">
                            下一步：確認訂單
                            <v-icon end icon="mdi-arrow-right" />
                        </v-btn>
                    </div>
                </v-form>
            </section>
            <section v-else-if="currentStep === 3">
                <h2>3. 確認訂單</h2>
                <p class="mt-4">
                    請確認以下資料，確認購買後將完成模擬交易。
                </p>
                <template v-for="game in confirmationItems" :key="game.id">
                    <div class="checkout-item d-flex align-center ga-4 py-4">
                        <v-img class="checkout-cover" :src="game.cover" :alt="`${game.titleZh}封面`" width="120"
                            max-width="120" :aspect-ratio="3 / 4" rounded="lg" cover />
                        <div class="checkout-item-info flex-grow-1">
                            <h3>{{ game.titleZh }}</h3>
                            <p>{{ game.title }}</p>
                        </div>
                        <div class="checkout-item-actions d-flex align-center ga-4">
                            <h3>NT$ {{ game.price.toLocaleString('en-US') }}</h3>
                        </div>
                    </div>
                    <v-divider color="var(--color-border)" opacity="1" class="mt-2" />
                </template>
                <div class="d-flex justify-space-between my-4">
                    <p>商品數量</p>
                    <p class="font-weight-bold">{{ confirmationItems.length }} 款</p>
                </div>
                <div class="d-flex justify-space-between mb-4">
                    <p>訂單總額</p>
                    <p class="order-total font-weight-bold">NT$ {{ confirmationTotal.toLocaleString('en-US') }}</p>
                </div>
                <v-sheet class="payment-notice pa-4 my-6" rounded="lg">
                    <h4 class="mb-2">付款方式</h4>
                    <p class="d-flex align-center ga-2">
                        <v-icon icon="mdi-credit-card-outline" />
                        {{ cardLastFour ? `信用卡 •••• ${cardLastFour}` : '信用卡（模擬付款）' }}
                    </p>
                </v-sheet>
                <div class="checkout-actions d-flex justify-space-between mt-6 ga-3">
                    <v-btn rounded="lg" height="auto" class="py-3 px-6" variant="outlined" @click="currentStep = 2"
                        :disabled="isSubmitting || !!pendingPurchase || pendingReadError">
                        <v-icon start icon="mdi-arrow-left" />
                        上一步：付款資料
                    </v-btn>
                    <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6" :loading="isSubmitting || isCheckingPurchase"
                        @click="confirmPurchase" :disabled="isSubmitting
                            || pendingReadError
                            || (!!pendingPurchase && !pendingQueryNotFound)">
                        <v-icon start icon="mdi-check" />
                        確認購買
                    </v-btn>
                </div>
            </section>
            <section v-else-if="currentStep === 4 && completedOrder">
                <div class="text-center">
                    <v-avatar class="success-icon my-4" :size="90">
                        <v-icon icon="mdi-check" :size="46" />
                    </v-avatar>
                    <h2 class="my-2">購買完成</h2>
                    <p>感謝您的購買，遊戲已加入您的收藏庫。</p>
                </div>
                <v-sheet class="payment-notice pa-6 my-8" rounded="lg">
                    <div class="d-flex justify-space-between my-4">
                        <p>商品數量</p>
                        <p class="font-weight-bold">{{ completedOrder.count }} 款</p>
                    </div>
                    <div class="d-flex justify-space-between mb-4">
                        <p>訂單總額</p>
                        <p class="order-total font-weight-bold">NT$ {{ completedOrder.total.toLocaleString('en-US') }}
                        </p>
                    </div>
                </v-sheet>
                <div class="checkout-actions d-flex justify-center ga-3">
                    <v-btn to="/member" color="primary" rounded="lg" height="auto" class="py-3 px-6">
                        前往我的收藏庫
                    </v-btn>

                    <v-btn to="/" variant="outlined" rounded="lg" height="auto" class="py-3 px-6">
                        回到首頁
                    </v-btn>
                </div>
            </section>
        </v-card>

    </div>
</template>

<script setup>
// 本頁控制四步結帳：1 商品 → 2 模擬付款資料 → 3 確認報價 → 4 完成。
// Database 由 purchaseService 送後端報價／成交；Demo 只更新 authStore 的分頁擁有權。
// 卡號、到期與 CVV 只供模擬表單檢查，不送購買 API、不存 sessionStorage，也不實際扣款。
// 結果不明時只背景查詢原購買，不能自動重送；消費者不需要操作識別碼或手動按查詢。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'
import { onBeforeRouteLeave } from 'vue-router'
import {
    createDatabaseQuote,
    loadPendingDatabasePurchase,
    findDatabasePurchase,
    clearPendingDatabasePurchase,
    createPurchaseRequestKey,
    savePendingDatabasePurchase,
    submitDatabasePurchase,
} from '../services/purchaseService'
import { useGameStore } from '../stores/gameStore'

// 讀已初始化的身份，決定走 Demo 或 Database；不在此重做健康檢查或自動換模式。
const authStore = useAuthStore()

// isSubmitting 表示送單中；completedOrder 保存完成畫面用的訂單摘要，初始 null 代表尚未完成。
const isSubmitting = ref(false)
const completedOrder = ref(null)

const cartStore = useCartStore()
const gameStore = useGameStore()

// currentStep 是目前步驟；checkoutQuote 是已取得的後端報價，isQuoting 是等報價中的狀態。
// 1 商品、2 付款欄位、3 確認、4 完成；checkoutQuote null 表示尚無可用後端報價。
const currentStep = ref(1)
const checkoutQuote = ref(null)
const isQuoting = ref(false)

// pendingPurchase 是尚待確認的購買紀錄，不代表一定失敗；pendingReadError 表示暫存讀寫不安全，先阻擋新單。
const pendingPurchase = ref(null)
const pendingReadError = ref(false)

// 只有明確查無訂單才設 pendingQueryNotFound；isCheckingPurchase 防止重疊查詢。
// purchaseNotice 是一般進度文字，purchaseError 則是錯誤文字，template 分別用藍色與紅色。
// false 不代表訂單存在，而是還沒得到明確查無；只有後端指定 404 才設 true 允許再次確認。
const pendingQueryNotFound = ref(false)
const isCheckingPurchase = ref(false)
const purchaseNotice = ref('')

// 查詢只用來確認結果，永遠不在背景重送購買。
const PURCHASE_QUERY_INTERVAL_MS = 3000
// 下面三個值用來管理自動查詢：哪個倒數還在等、這是第幾輪確認、使用者是否已離開結帳。它們不直接顯示在畫面。
let purchaseQueryTimer = null
// purchaseQueryVersion 像確認工作的編號。開始新一輪時加一；上一輪的回覆即使晚到，也不能拿來改現在的畫面。
let purchaseQueryVersion = 0
let checkoutDisposed = false

// 停止這一輪自動確認：取消下一次倒數，並更換工作編號，讓先前已送出但還沒回來的查詢不再影響畫面。
// 取消倒數不能把已送到後端的請求收回，所以回覆抵達後還要檢查：現在是否仍在處理同一筆購買。
function stopPurchaseQuery() {
    // 清掉「未來要查」的計時；已經送出的 fetch 不會因此撤回，所以還需要版本檢查。
    clearTimeout(purchaseQueryTimer)
    purchaseQueryTimer = null
    // ++ 將版本加一，等於宣告先前工作的回覆已過期；不是訂單識別碼，不存資料庫。
    purchaseQueryVersion++
}

// 使用查詢結果前先檢查：還在結帳頁嗎？還是原會員嗎？還在等這筆購買嗎？全部符合才回 true，允許繼續。
// 例如會員 A 送出購買後換成 B，即使 A 的成功回覆此時才到，也不能把遊戲加到 B 的畫面上。
// && 表示全部符合，!!record 把有無紀錄轉成布林；?. 安全讀可能不存在的欄位。
function canResolvePurchase(version, record = pendingPurchase.value) {
    // 下方逐項核對這次查詢是否還可以使用；每項都列出實際比較內容與阻擋的情況。
    // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
    // 條件 1｜!checkoutDisposed
    // checkoutDisposed 在離開結帳頁時會設為 true。前面的 ! 要求「尚未離開」，避免在別頁時還改結帳結果。
    return !checkoutDisposed
        // 條件 2｜version === purchaseQueryVersion
        // version 是這次查詢送出時帶的工作編號；purchaseQueryVersion 是現在有效的編號。兩者相同才表示不是已停止的舊工作。
        && version === purchaseQueryVersion
        // 條件 3｜!!record
        // record 是本次要查的待確認購買。!! 將有沒有這份資料轉成 true／false；沒有資料就不能繼續讀它的識別碼或會員編號。
        && !!record
        // 條件 4｜pendingPurchase.value?.requestKey === record.requestKey
        // 左邊是畫面現在等待的購買識別碼，右邊是這次查詢的識別碼。必須相同，避免把上一筆結果當成現在這筆；?. 讓目前已沒紀錄時安全取得 undefined。
        && pendingPurchase.value?.requestKey === record.requestKey
        // 條件 5｜authStore.source === 'database'
        // 目前身份資料必須來自 Database。若是 Demo，這段 Database 專用操作就不能繼續。
        && authStore.source === 'database'
        // 條件 6｜authStore.currentUser?.id === record.userId
        // 現在登入的會員編號，必須等於這筆待確認購買的會員編號；若登出或換人，就不能套用這筆結果。
        && authStore.currentUser?.id === record.userId
        // 條件 7｜!pendingReadError.value
        // pendingReadError 為 true 代表瀏覽器待確認紀錄讀寫出問題；! 要求沒有這種錯誤，才繼續處理依賴該紀錄的流程。
        && !pendingReadError.value
        // 條件 8｜currentStep.value !== 4
        // 第四步就是購買完成。!== 4 表示尚未完成，才需要繼續查；已完成就不讓後來的回覆再改一次。
        && currentStep.value !== 4
}

// 用途：只排下一次查詢，預設等 3000 毫秒；delay 傳 0 就盡快查。
// 先取消前一個倒數，確認還需要查結果，才安排下一次。setTimeout 是「等指定時間後執行」；不是讓整個網頁停住。
// 這個計時器只會 GET 查結果，不 POST 購買。
function schedulePurchaseQuery(version, delay = PURCHASE_QUERY_INTERVAL_MS) {
    clearTimeout(purchaseQueryTimer)
    purchaseQueryTimer = null

    // 先確認這次流程還有效才排計時；delay 0 只表示盡快執行，仍由查詢函式再擋重疊。
    if (canResolvePurchase(version)) {
        purchaseQueryTimer = setTimeout(() => {
            purchaseQueryTimer = null
            // 計時到了才真正查；void 不使用回傳 Promise，queryPendingPurchase 自己處理錯誤，不讓計時器自動 POST。
            void queryPendingPurchase(version)
        }, delay)
    }
}

// 開始新一輪確認：先停止舊倒數，再清掉先前提示、顯示「正在確認購買結果」，最後安排立即查一次。
// 即使此刻觸發好幾次，也會先檢查有沒有查詢正在等回覆，不同時送出重複查詢。
function startPurchaseQuery() {
    // 重新確認前先停止舊工作；這樣就算舊查詢晚回來，也不會跟這輪結果互相覆蓋。
    stopPurchaseQuery()

    if (!canResolvePurchase(purchaseQueryVersion)) return

    pendingQueryNotFound.value = false
    // 此處是啟動新一輪結果確認，先清前次錯誤再放一般提示；不是證明原購買成功或失敗。
    purchaseError.value = ''
    purchaseNotice.value = '正在確認購買結果，請稍候。'
    // 提示已準備好，立刻排一次 GET；其他重試預設每三秒，不是固定每三秒都送新訂單。
    schedulePurchaseQuery(purchaseQueryVersion, 0)
}

// 用途：瀏覽器回報 online 時提早喚醒查詢；online 不保證資料庫已恢復，仍須真正發 GET 確認。
// 若前次已明確 404，就不再查，等使用者按正常的確認購買。
function resumePurchaseQuery() {
    // 如果後端已明確說沒這筆成功訂單，連線恢復時就不再一直查，等使用者重新按「確認購買」。
    if (pendingQueryNotFound.value) return
    schedulePurchaseQuery(purchaseQueryVersion, 0)
}

// 確認畫面的商品：Demo 讀購物車，Database 以報價商品與單價為準，再補 Store 的封面。
// map 把每個報價商品整理成確認畫面需要的名稱、封面與價格。Number 將金額文字轉成顯示用數字；實際成交金額仍以後端為準。
const confirmationItems = computed(() => {
    // Demo 確認商品直接用購物車；Database 必須以後端 quote 內容為準，不能只顯示瀏覽器現價。
    if (authStore.source !== 'database') {
        return cartStore.cartGames
    }

    // 還沒拿報價就不顯示假明細；空清單和成功取得零元訂單不同。
    if (!checkoutQuote.value) return []

    return checkoutQuote.value.items.map(item => {
        // 報價有商品資訊但還要從 Store 找封面；商品不存在代表兩份資料不一致，停止而不是用空圖片冒充。
        const game = gameStore.getGameById(item.gameId)

        if (!game) {
            throw new Error('報價商品與遊戲資料不一致，請重新載入。')
        }

        return {
            id: item.gameId,
            title: item.title,
            titleZh: item.titleZh,
            cover: game.cover,
            // 只轉成畫面可顯示的數值；真正成交與核對保留服務層字串金額／整數分，不靠此浮點數。
            price: Number(item.unitPrice),
        }
    })
})

// Database 顯示後端報價總額，Demo 顯示 cartTotal；不能用這個畫面值指定資料庫成交金額。
const confirmationTotal = computed(() => {
    return authStore.source === 'database'
        ? Number(checkoutQuote.value?.totalAmount ?? '0.00')
        : cartStore.cartTotal
})

// 這三個 ref 只暫存在目前元件記憶體，使用測試資料即可；不是存入資料庫的付款紀錄。
const cardNumber = ref('')
const cardExpiry = ref('')
const cardCvv = ref('')

// 用途：模擬卡號格式檢查，通過回 true，失敗回文字給 Vuetify 表單顯示。
// 先移除空白，檢查 13～19 位且非全 0，再做 Luhn 檢查碼運算；這不能證明卡真的存在或可扣款。
function validateCardNumber(value) {
    // 先讓空值變空字串、轉文字再去空白；正規表示式 /\s/g 的 g 代表處理全部空白，不只第一個。
    const digits = String(value ?? '').replace(/\s/g, '')

    if (!/^\d{13,19}$/.test(digits)) {
        return '請輸入 13～19 位數字的信用卡卡號'
    }
    if (/^0+$/.test(digits)) {
        return '請輸入有效的信用卡卡號'
    }

    // Luhn 檢查碼：由右向左隔位乘 2，超過 9 減 9，再加總；能被 10 整除才通過格式驗證。
    let sum = 0
    let shouldDouble = false

    // 由最右邊開始逐位檢查，i-- 每輪往左；shouldDouble 每輪反轉，控制隔位乘二。
    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = Number(digits[i])

        // 需要加倍的那位乘 2，大於 9 減 9；sum 累加後 % 10 必須 0，僅是 Luhn 格式檢查。
        if (shouldDouble) {
            digit *= 2

            if (digit > 9) {
                digit -= 9
            }
        }

        sum += digit
        shouldDouble = !shouldDouble
    }

    return sum % 10 === 0
        ? true
        : '請輸入有效的信用卡卡號'
}

// 用途：檢查 MM/YY 格式、月份與是否已過期，依瀏覽器當前年月比較。
// Date.getMonth() 從 0 起算，所以加 1 才是人看到的月份；這和後端報價期限無關。
function validateCardExpiry(value) {
    // 要求兩位月／兩位年，match[1]、match[2] 是正規表示式括號捕捉的文字；不符合則 null。
    const match = String(value ?? '').trim().match(/^(\d{2})\/(\d{2})$/)

    if (!match) {
        return '請輸入 MM/YY 格式'
    }

    // 將月份轉數字，兩位年加 2000 成完整年；先檢查 1～12，再與瀏覽器現在年月比是否過期。
    const month = Number(match[1])
    const year = 2000 + Number(match[2])

    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜month < 1
    // 月份小於 1 不合法，例如輸入 00；下面會回覆月份錯誤。
    // 條件 2｜month > 12
    // 月份大於 12 也不合法，例如 13；不能只檢查有兩位數字就放行。
    if (month < 1 || month > 12) {
        return '月份需介於 01～12'
    }

    const now = new Date()
    const currentYear = now.getFullYear()
    const currentMonth = now.getMonth() + 1

    if (
        // 這組有兩種過期情況：①年份早於今年，或②年份等於今年「且」月份早於本月。未來年份不會只因月份較小就被誤判過期。
        // 條件 1｜year < currentYear
        // 卡片年份早於今年，代表已過期，不需要再比較月份。
        year < currentYear ||
        // 條件 2｜year === currentYear
        // 卡片年份剛好等於今年，才需要接著比較是不是已過了卡片月份。
        // 條件 3｜month < currentMonth
        // 卡片月份早於這個月；這項要和「年份是今年」一起成立才算過期，明年的一月不能只因月份較小就被誤判。
        (year === currentYear && month < currentMonth)
    ) {
        return '信用卡已過期'
    }

    return true
}

// 用途：模擬 CVV 只接受 3～4 位數字；正規表示式中的 ^ 與 $ 要求整段文字都符合。
// 通過不代表銀行已驗證，整個網站沒有真實金流。
function validateCardCvv(value) {
    // 只接受整段 3～4 位數字；條件 ? true : 訊息 是 Vuetify rules 的約定，不是真銀行驗證。
    return /^\d{3,4}$/.test(String(value ?? ''))
        ? true
        : 'CVV 需為 3～4 位數字'
}

// template 的 ref 會把 Vuetify 表單工具放進這裡，才可呼叫 validate()，不是付款資料本身。
const paymentForm = ref(null)

// 用途：第二步按下一步時，先檢查表單，Database 再取得報價，通過才進第三步。
// 已有待確認購買不能另外開新單；isQuoting 與 isSubmitting 防止等待時重複操作。
// 報價回來仍核對會員與購物車沒變，避免等待期間改商品卻確認舊報價。
async function submitPayment() {
    // 已有結果未明的購買或暫存錯誤時，不取得另一份新流程；先保留追查原單的能力。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜pendingReadError.value
    // 瀏覽器保存的待確認購買讀寫有問題，不能安全接續；先顯示提示，不開另一筆新購買。
    // 條件 2｜pendingPurchase.value
    // 目前仍有一筆結果尚未確認的購買。這個值有資料就視為 true，先處理原單，不直接另建一筆。
    if (pendingReadError.value || pendingPurchase.value) {
        purchaseError.value = '上一筆購買尚待確認，請稍候。'
        return
    }
    // 正在等報價或送單就停止這次點擊，不要同一筆付款資料同時取多份互相覆蓋的報價。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜isQuoting.value
    // 正在等待後端報價，避免使用者連按而取得好幾份報價互相覆蓋。
    // 條件 2｜isSubmitting.value
    // 購買請求正在送出／處理，不能因又按一次按鈕就再送一次。
    if (isQuoting.value || isSubmitting.value) return

    purchaseError.value = ''
    purchaseNotice.value = ''

    // 空購物車回第一步，不繼續驗卡或取得零商品報價；後端也有獨立清單檢查。
    if (cartStore.cartCount === 0) {
        currentStep.value = 1
        return
    }

    // 先標忙碌再驗表單；驗證本身也可能非同步，finally 會確保失敗能解除。
    isQuoting.value = true

    try {
        // validate() 會檢查付款表單，回覆裡的 valid 告訴我們是否通過。{ valid } 是只拿出這項結果；沒通過就留在原步驟。
        const { valid } = await paymentForm.value.validate()

        // 這次付款格式未通過就留原頁，return 不跳過 finally，所以 isQuoting 最後仍會恢復 false。
        if (!valid) return

        if (authStore.source === 'database') {
            // 在發請求前記住原會員；等回覆後再次比對，避免等待途中身份改變卻仍套用報價。
            const userId = authStore.currentUser?.id

            if (!userId) {
                authStore.requestLogin('/checkout')
                purchaseError.value = '請先登入後再取得報價。'
                return
            }

            // 把當下購物車映射成編號並排序，保留一份請求快照，不傳圖片、卡號或前端指定單價。
            const gameIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            // 新報價開始前先清舊 quote，避免取得失敗後還能按舊報價繼續確認。
            checkoutQuote.value = null

            // 只等待報價，這裡沒有購買；後端驗證會員與商品並保存 Session 報價，回覆含期限與價格。
            const quote = await createDatabaseQuote(gameIds)

            // await 期間使用者可能換會員或改購物車，現在再讀一次，排序後和原請求清單比較。
            const currentIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            if (
                // 等待報價時可能已換會員或改商品，所以再確認現在的會員、後端回覆會員與商品都和送出時相同；不同就不用這份報價。
                // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
                // 條件 1｜authStore.currentUser?.id !== userId
                // 現在登入的人已不是送出報價時記住的 userId，或已登出；這份回覆不該繼續用。
                authStore.currentUser?.id !== userId
                // 條件 2｜quote.userId !== userId
                // 這份報價不屬於現在登入的人，不能借別人的報價建立訂單。
                || quote.userId !== userId
                // 條件 3｜JSON.stringify(currentIds) !== JSON.stringify(gameIds)
                // 兩邊都是排序後的商品編號：currentIds 是現在的車，gameIds 是送出時的車。內容不同表示等待時商品已變，必須重取報價。
                || JSON.stringify(currentIds) !== JSON.stringify(gameIds)
            ) {
                throw new Error('會員或購物車已變更，請重新確認。')
            }

            checkoutQuote.value = quote
        }

        // 只有表單與所需報價成功後才進確認；Demo 不需後端 quote 但仍要先驗模擬付款欄位。
        currentStep.value = 3
    } catch (error) {
        purchaseError.value =
            // 先用後端／程式提供的 error.message；如果沒有訊息或是空文字，才顯示右邊寫好的備用提示。
            // 這個 || 是挑要顯示的文字，不是在檢查兩個條件是否同時成立。
            error.message || '目前無法取得報價，請稍後再試。'
    } finally {
        // finally 一定取消忙碌，包含表單不合、會員過期或 API 失敗，讓使用者能修正後再操作。
        isQuoting.value = false
    }
}

// 這裡整理卡號的顯示方式：get 給輸入框目前文字，set 在使用者打字時去掉非數字，再每四位加空格。
// 這是格式化，不代替上方卡號驗證；v-model 寫入時會走 set。
const formattedCardNumber = computed({
    get() {
        return cardNumber.value
    },
    set(value) {
        // set 只整理使用者輸入的樣子：去掉非數字，最多保留 19 位。卡號是否符合檢查規則仍由 validateCardNumber 判斷。
        const digits = String(value ?? '')
            .replace(/\D/g, '')
            .slice(0, 19)

        // 每四位後面若還有數字就加空格，$1 代表捕捉到的四位；只改顯示，不送卡號到 API。
        cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ')
    }
})

// 同樣用 get／set 整理期限輸入，只留數字並插入 /，實際月份與過期檢查交 validateCardExpiry。
const formattedCardExpiry = computed({
    get() {
        return cardExpiry.value
    },
    set(value) {
        const text = String(value ?? '')
        // 期限只留四位數字；text 保留使用者原輸入，下面藉此分辨正在往後輸入或刪字。
        const digits = text.replace(/\D/g, '').slice(0, 4)

        // 超過兩位就拆成 MM/YY；剛輸入滿兩位且是在新增字時先補 /，刪字時則不強制補回。
        if (digits.length > 2) {
            cardExpiry.value = `${digits.slice(0, 2)}/${digits.slice(2)}`
        // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
        // 條件 1｜digits.length === 2
        // 目前剛好有兩位期限數字，也就是月份輸入完成；才考慮自動補上 /。
        // 條件 2｜text.length > cardExpiry.value.length
        // 使用者這次輸入比舊顯示文字長，表示正在新增文字而不是刪字；刪 / 時不要又強制立刻補回。
        } else if (digits.length === 2 && text.length > cardExpiry.value.length) {
            cardExpiry.value = `${digits}/`
        } else {
            cardExpiry.value = digits
        }
    }
})

// 用途：將卡號、期限、CVV 清空；完成、送單結束與離頁等流程共用，不將敏感欄位存暫存。
function clearPaymentFields() {
    cardNumber.value = ''
    cardExpiry.value = ''
    cardCvv.value = ''
}

// 用途：沒有待確認購買才允許回第一步，清付款欄位、報價與訊息。
// 結果未明時保留原購買紀錄，不讓使用者改商品後又送出另一筆。
function backToCart() {
    // 結果還未確認時不回商品頁另開一單；顯示原流程待確認提示，避免遺忘可能已成交的操作。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜pendingReadError.value
    // 瀏覽器保存的待確認購買讀寫有問題，不能安全接續；先顯示提示，不開另一筆新購買。
    // 條件 2｜pendingPurchase.value
    // 目前仍有一筆結果尚未確認的購買。這個值有資料就視為 true，先處理原單，不直接另建一筆。
    if (pendingReadError.value || pendingPurchase.value) {
        purchaseError.value = '上一筆購買尚待確認，請稍候。'
        return
    }
    clearPaymentFields()
    checkoutQuote.value = null
    purchaseError.value = ''
    purchaseNotice.value = ''
    currentStep.value = 1
}

// 只取卡號數字的最後四位作確認畫面摘要；replace(/\D/g, 空字串) 去掉非數字，slice(-4) 從尾端取。
const cardLastFour = computed(() => {
    return cardNumber.value.replace(/\D/g, '').slice(-4)
})

const purchaseError = ref('')
// || 表示優先顯示非空的錯誤，否則一般進度。訊息區塊只在有文字時出現，不預留高度。
// 這裡的 || 是選擇要顯示的文字，不是 if 判斷；左邊有文字就直接使用，空文字才取右邊。
// 第一個候選文字｜purchaseError.value
// 先取購買錯誤文字；非空時優先顯示紅色錯誤，不讓一般進度蓋住它。
// 第二個候選文字｜purchaseNotice.value
// 只有左邊沒有錯誤文字時才取一般提示，例如「正在確認購買結果」；兩個都空時訊息區不出現。
const checkoutMessage = computed(() => purchaseError.value || purchaseNotice.value)

// 用途：只套用已確認成交的後端結果，不在這裡新建訂單。
// 核對 requestKey、金額與各商品單價一致，再同步身份擁有權、移除本單商品、進完成步驟。
// 最後清待確認暫存；若清暫存失敗，仍如實顯示已成功，不把已成交變成未成交。
function applyConfirmedDatabasePurchase(result, record) {
    // 把商品簡化為編號與單價並排序，轉 JSON 文字比較；這裡叫 signature 只是內容比對，不是加密簽章。
    const itemSignature = items => JSON.stringify(
        // 把兩份商品都整理成「編號、單價」後比較。例如都是遊戲 4／1290.00 才算相同，不以它們是不是同一個 JS 物件判斷。
        items
            .map(item => [item.gameId, item.unitPrice])
            .sort((a, b) => a[0] - b[0])
    )

    if (
        // 回覆即使已在 Service 驗過格式，還要對照這次 pending 的內容，不能套用別筆合法訂單。
        // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
        // 條件 1｜result.order.requestKey !== record.requestKey
        // 後端成功訂單的購買識別碼，和目前等待的那筆不同；不能把別筆訂單顯示成這次成功。
        result.order.requestKey !== record.requestKey
        // 條件 2｜result.order.totalAmount !== record.totalAmount
        // 後端成交總額與原確認總額不同；即使訂單存在，也不能直接當作本次確認內容。
        || result.order.totalAmount !== record.totalAmount
        // 條件 3｜itemSignature(result.order.items) !== itemSignature(record.items)
        // 把後端明細和原確認明細都整理成「商品編號、單價」再比較；不一致可能是不同商品或單價，必須停止更新成功畫面。
        || itemSignature(result.order.items)
        !== itemSignature(record.items)
    ) {
        throw new Error('訂單資料與確認內容不一致，暫時無法完成確認。')
    }

    // 先請 Store 驗身份並同步擁有權，成功後才顯示完成摘要，不能替另一個現在登入的人更新。
    authStore.applyDatabasePurchaseResult(result, record.userId)

    completedOrder.value = {
        orderId: result.order.orderId,
        count: result.order.items.length,
        total: Number(result.order.totalAmount),
    }

    // 只移除本張成功訂單內的遊戲，保留等待過程中另加但未買的商品。forEach 是逐項執行，不建立結果清單。
    result.order.items.forEach(item => {
        cartStore.removeFromCart(item.gameId)
    })

    clearPaymentFields()
    // 此時成交已確認，先進完成並停查詢，後面即使前端暫存清理失敗也不應宣布未成交。
    currentStep.value = 4
    stopPurchaseQuery()
    pendingQueryNotFound.value = false
    purchaseNotice.value = ''

    try {
        // 成交已確認後才清除對應會員、key 的紀錄；只移除前端追查暫存，不刪 MySQL 訂單。
        clearPendingDatabasePurchase(
            record.userId,
            record.requestKey
        )

        pendingPurchase.value = null
        purchaseError.value = ''
    } catch {
        // 清前端紀錄失敗要保留提示；資料庫已成交不 rollback，之後重載仍可用原 key 查原訂單。
        pendingReadError.value = true
        purchaseError.value =
            '購買已成功，但瀏覽器無法更新紀錄；重新載入後系統將再次確認結果。'
    }
}

// 用途：查原 requestKey 的結果。查到成交就完成；明確 404 才允許再次確認購買。
// 連不上就保持待確認並安排下次 GET；登入過期先恢復身份，換人後不套用舊回覆。
// await 等回覆的這段時間，使用者可能已離開或換人登入；回來時要再檢查是否仍能使用。finally 則確保不論結果都解除「查詢中」。
async function queryPendingPurchase(version = purchaseQueryVersion) {
    // 查詢前先抓本次紀錄快照；下面 await 回來需再次核對它仍是目前那筆。
    const record = pendingPurchase.value
    // 送出查詢前先檢查一次；等後端回覆後再查一次。因為等回覆時使用者仍能換頁或換會員，不能假設一切都沒變。
    if (!canResolvePurchase(version, record)) return

    // 包含 online 事件在內的所有觸發，共用同一個查詢工作。
    // 如果正在購買，或前一次查詢還沒回來，就晚一點再查；不要同時有好幾份查詢互相干擾。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜isSubmitting.value
    // 購買請求正在送出／處理，不能因又按一次按鈕就再送一次。
    // 條件 2｜isCheckingPurchase.value
    // 上一個結果查詢還在等回覆，不能同時再開查詢或和購買提交互相干擾。
    if (isSubmitting.value || isCheckingPurchase.value) {
        schedulePurchaseQuery(version)
        return
    }

    isCheckingPurchase.value = true

    try {
        // 這是 GET 查詢；Service 只有明確 PURCHASE_NOT_FOUND 才回 null，其餘錯誤進 catch。
        const result = await findDatabasePurchase(record.requestKey)
        if (!canResolvePurchase(version, record)) return

        // 後端已明確回覆找不到成功訂單，所以停止每三秒查詢，顯示紅色提示並讓使用者再次按「確認購買」。
        // 仍保留原 key，下一次使用者確認時沿用，不讓同次購買變成另一個識別碼。
        if (result === null) {
            stopPurchaseQuery()
            pendingQueryNotFound.value = true
            purchaseError.value = '尚未完成購買，請再次確認購買。'
            purchaseNotice.value = ''
            return
        }

        applyConfirmedDatabasePurchase(result, record)
    } catch (error) {
        if (!canResolvePurchase(version, record)) return

        // 未登入錯誤才重新確認 me／token；不是所有連線失敗都清會員或叫人重新登入。
        // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
        // 條件 1｜error.status === 401
        // HTTP 狀態是 401，表示這次請求被當成未登入；還要和下一項專案錯誤碼一起核對。
        // 條件 2｜error.code === 'UNAUTHENTICATED'
        // 後端明確使用未登入錯誤碼，才走身份重新確認；不能把其他 401 都當作相同情況。
        if (error.status === 401 && error.code === 'UNAUTHENTICATED') {
            let member
            try {
                member = await authStore.revalidateDatabaseMember()
            } catch {
                // 重新驗證亦無法連線時，維持原紀錄並繼續等待。
                if (canResolvePurchase(version, record)) {
                    purchaseNotice.value = '目前連線異常，連線恢復後將自動確認購買結果。'
                    schedulePurchaseQuery(version)
                }
                return
            }

            if (checkoutDisposed) return

            // 重新查仍是同一會員且流程有效就排再查；不同會員的回覆由身份 watch 管理，不套舊單。
            // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
            // 條件 1｜member?.id === record.userId
            // 重查後的會員必須還是原購買者；member 為 null 或換成別人都不能接續這筆。
            // 條件 2｜canResolvePurchase(version, record)
            // 再執行上面那組完整檢查：頁面未離開、工作編號、購買識別碼、模式與會員等仍相符，才繼續查結果。
            if (member?.id === record.userId && canResolvePurchase(version, record)) {
                schedulePurchaseQuery(version)
                return
            }

            // 不讓舊查詢干擾已切換會員的新流程。
            // 有另一位會員就讓舊查詢退出；只有 null 的明確未登入才顯示登入過期與要求原會員登入。
            if (member !== null) return
            if (canResolvePurchase(version, record)) stopPurchaseQuery()
            purchaseNotice.value = ''
            purchaseError.value = '登入已過期，請重新登入後繼續確認購買結果。'
            authStore.requestLogin('/checkout')
            return
        }

        purchaseError.value = ''
        purchaseNotice.value = '目前連線異常，連線恢復後將自動確認購買結果。'
        schedulePurchaseQuery(version)
    } finally {
        // 無論成功、失敗或回覆太晚不能使用，最後都把「正在查詢」設為 false，後面需要查時才不會被擋住。
        isCheckingPurchase.value = false
    }
}

// 用途：第三步確認購買的統一入口；按當前身份模式分派，不因後端失敗臨時改用 Demo。
async function confirmPurchase() {
    if (authStore.source === 'database') {
        await confirmDatabasePurchase()
    } else {
        confirmDemoPurchase()
    }
}

// 用途：由使用者明確按鈕才送 Database 購買，不在背景自行下單。
// 先恢復既有待確認紀錄，沒有才用目前報價建立新 requestKey；先存紀錄成功才 POST。
// 送出後區分後端明確拒絕與結果不明，前者重新確認報價，後者用原 key 查結果。
async function confirmDatabasePurchase() {
    // 送 Database 前再擋三種忙碌工作；畫面 disabled 是體驗，函式內檢查才是所有入口共同防護。
    // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
    // 條件 1｜isSubmitting.value
    // 購買請求正在送出／處理，不能因又按一次按鈕就再送一次。
    // 條件 2｜isQuoting.value
    // 正在等待後端報價，避免使用者連按而取得好幾份報價互相覆蓋。
    // 條件 3｜isCheckingPurchase.value
    // 上一個結果查詢還在等回覆，不能同時再開查詢或和購買提交互相干擾。
    if (isSubmitting.value || isQuoting.value || isCheckingPurchase.value) return

    if (pendingReadError.value) {
        purchaseError.value = '瀏覽器無法讀取購買紀錄，請保留此頁並稍後再試。'
        return
    }

    const userId = authStore.currentUser?.id

    if (!userId) {
        purchaseError.value = '請登入原會員後再確認購買。'
        authStore.requestLogin('/checkout')
        return
    }

    isSubmitting.value = true
    stopPurchaseQuery()
    purchaseError.value = ''
    purchaseNotice.value = ''

    let record = pendingPurchase.value

    try {
        // 按確認前再讀暫存，若有另一筆未處理完的購買先恢復並查它，不直接覆蓋新單。
        // 送單前再讀瀏覽器紀錄，可能同頁其他操作已留下待確認資料；原單優先，不直接覆寫。
        const saved = loadPendingDatabasePurchase(userId)

        if (
            // 這組要同時滿足：①瀏覽器有紀錄，且②畫面沒紀錄「或」畫面的識別碼不同。括號把後面兩種情況當成同一組，避免沒有資料時讀取 record.requestKey。
            // 條件 1｜saved !== null
            // 瀏覽器確實保存了一筆尚待確認的購買；沒有保存內容就不用走恢復原單的分支。
            saved !== null
            && (
                // 條件 2｜record === null
                // 畫面記憶體目前還沒拿到這筆資料，雖然瀏覽器暫存有，因此要先恢復它再查結果。
                record === null
                // 條件 3｜record.requestKey !== saved.requestKey
                // 畫面記住的購買與瀏覽器保存的不是同一筆；優先恢復已保存的待確認購買，避免覆蓋原單。
                || record.requestKey !== saved.requestKey
            )
        ) {
            pendingPurchase.value = saved
            checkoutQuote.value = saved
            pendingQueryNotFound.value = false
            currentStep.value = 3
            startPurchaseQuery()
            return
        }

        // 原待確認單存在時，只有明確查無才允許使用者按鈕重試；其餘仍先查結果，不直接 POST。
        if (record !== null) {
            if (!pendingQueryNotFound.value) {
                startPurchaseQuery()
                return
            }

            if (record.userId !== userId) {
                throw new Error('請登入原購買會員後再操作。')
            }
        } else {
            const quote = checkoutQuote.value

            // 以下用 || 串接，任一項成立就進入這個分支；左邊已成立時不再計算右邊。
            // 條件 1｜!quote
            // 還沒有報價，不能直接按確認就購買；先回去取得商品與價格。
            // 條件 2｜quote.userId !== userId
            // 這份報價不屬於現在登入的人，不能借別人的報價建立訂單。
            if (!quote || quote.userId !== userId) {
                throw new Error('請重新取得目前會員的報價。')
            }

            // 新單最後核對購物車與報價商品編號完全相同；避免報價後使用者加減商品仍以舊內容成交。
            const cartIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            const quotedIds = quote.items
                .map(item => item.gameId)
                .sort((a, b) => a - b)

            if (JSON.stringify(cartIds) !== JSON.stringify(quotedIds)) {
                throw new Error('購物車已變更，請返回確認商品並重新取得報價。')
            }

            // 只有沒有既有紀錄、報價與購物車也一致時，才建立本次購買資料與新的隨機 key。
            record = {
                ...quote,
                // 只在建立新的購買意圖時產生 key；重查／重試同筆操作沿用 record 裡的 key，不重新產生。
                requestKey: createPurchaseRequestKey(),
            }
        }

        // 保存成功才送單。重試仍使用相同 quoteId 與 requestKey。
        try {
            // 先保存才能送單；若瀏覽器不能保存追查資訊就停止，不冒著回覆遺失後無從查詢的風險。
            record = savePendingDatabasePurchase(record)
        } catch (error) {
            pendingReadError.value = true
            throw error
        }

        // 保存成功後才標目前待確認，緊接著 POST；結果不明也已有原 key 可查，沒有盲目新買。
        pendingPurchase.value = record
        pendingQueryNotFound.value = false

        // 此處才真正 POST，內容只有 quoteId 與 requestKey，沒有卡號／CVV，也沒有前端指定的成交價格。
        const result = await submitDatabasePurchase(
            record.quoteId,
            record.requestKey
        )

        applyConfirmedDatabasePurchase(result, record)
    } catch (error) {
        purchaseError.value = pendingReadError.value
            ? '瀏覽器無法保存購買紀錄，尚未送出購買，請稍後再試。'
            // 先用後端／程式提供的 error.message；如果沒有訊息或是空文字，才顯示右邊寫好的備用提示。
            // 這個 || 是挑要顯示的文字，不是在檢查兩個條件是否同時成立。
            : error.message || '目前無法完成購買，請稍後再試。'

        // 列出後端明確拒絕購買的原因，例如報價過期、價格改變、遊戲已擁有。Set 保存這份名單，下面用 has() 問錯誤碼是否在其中。
        // 一般 503 或網路錯誤不在這份清單，不能因收不到結果就刪除追查紀錄。
        const rejectedCodes = new Set([
            'INVALID_QUOTE_ID',
            'INVALID_REQUEST_KEY',
            'QUOTE_NOT_FOUND',
            'QUOTE_EXPIRED',
            'PRICE_CHANGED',
            'GAMES_NOT_FOUND',
            'GAMES_ALREADY_OWNED',
        ])

        try {
            if (
                // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
                // 條件 1｜record
                // 必須先有原購買資料，才有會員編號與購買識別碼可核對；沒有時不能接著讀 record.userId。
                record
                // 條件 2｜authStore.currentUser?.id === record.userId
                // 現在登入的會員編號，必須等於這筆待確認購買的會員編號；若登出或換人，就不能套用這筆結果。
                && authStore.currentUser?.id === record.userId
                // 必須是指定的 400／409 錯誤，而且原因在上述拒絕名單、現在仍是原會員，才清掉這筆待確認資料，讓使用者重新確認價格。
                // 條件 3｜[400, 409].includes(error.status)
                // HTTP 狀態是 400（請求不合法）或 409（內容條件衝突），才可能是明確拒絕；503 或斷線不能直接當作沒成交。
                && [400, 409].includes(error.status)
                // 條件 4｜rejectedCodes.has(error.code)
                // 錯誤原因還必須在上方指定名單，例如報價過期、價格改變；不能只看 400／409 就清原購買紀錄。
                && rejectedCodes.has(error.code)
            ) {
                // 後端明確拒絕本次交易，才解除這筆待確認狀態。
                clearPendingDatabasePurchase(
                    record.userId,
                    record.requestKey
                )

                pendingPurchase.value = null
                stopPurchaseQuery()
                pendingQueryNotFound.value = false
                checkoutQuote.value = null
                currentStep.value = 1

                // 若後端說遊戲已擁有，可能已在別處買成功；先重新讀會員的已購買清單，再把已買商品從購物車拿掉。
                if (error.code === 'GAMES_ALREADY_OWNED') {
                    const member = await authStore.revalidateDatabaseMember()

                    if (member?.id === record.userId) {
                        cartStore.removePurchasedGames()
                    }
                }

                purchaseError.value =
                    `${error.message} 請更新商品清單後重新確認報價。`
            } else if (
                // 操作驗證碼過期時，先確認目前登入誰，再查這次購買結果。不能直接重新送購買，因為前一次可能已建立訂單。
                // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
                // 條件 1｜error.status === 403
                // HTTP 狀態是 403，代表操作被拒絕；下一項再確認是不是驗證碼失效，而非其他權限問題。
                error.status === 403
                // 條件 2｜error.code === 'CSRF_TOKEN_INVALID'
                // 確認被拒絕的原因是操作驗證碼不一致，才重新確認身份與 token；不直接重送購買。
                && error.code === 'CSRF_TOKEN_INVALID'
            ) {
                await authStore.revalidateDatabaseMember()

                if (authStore.currentUser?.id === record?.userId) {
                    startPurchaseQuery()
                } else {
                    purchaseError.value = '請登入原購買會員後繼續確認結果。'
                    if (!authStore.isLoggedIn) authStore.requestLogin('/checkout')
                }
            // 其他不確定失敗仍保留紀錄並進自動 GET，網路恢復後才知道是否成交；不清空後新建 key。
            // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
            // 條件 1｜record
            // 必須先有原購買資料，才有會員編號與購買識別碼可核對；沒有時不能接著讀 record.userId。
            // 條件 2｜pendingPurchase.value
            // 目前仍有一筆結果尚未確認的購買。這個值有資料就視為 true，先處理原單，不直接另建一筆。
            } else if (record && pendingPurchase.value) {
                startPurchaseQuery()
            }
        // 連恢復身份／清理也出錯時，再判斷原流程是否仍可追查；不可用才提示重新登入原會員。
        } catch (recoveryError) {
            // 以下條件用 && 串接，要全部成立才往下處理；左邊不成立就不再檢查右邊。
            // 條件 1｜record
            // 必須先有原購買資料，才有會員編號與購買識別碼可核對；沒有時不能接著讀 record.userId。
            // 條件 2｜canResolvePurchase(purchaseQueryVersion, record)
            // 使用現在有效的工作編號，再檢查原購買仍屬於目前會員且頁面仍有效；只有這樣才可安排結果查詢。
            if (record && canResolvePurchase(purchaseQueryVersion, record)) {
                startPurchaseQuery()
            } else {
                purchaseError.value = '目前無法完成驗證，請登入原購買會員後再試。'
            }
        }
    } finally {
        // finally 不論結果都清模擬付款資料並解除送單忙碌；待確認購買有自己的暫存，不依賴卡號恢復。
        clearPaymentFields()
        isSubmitting.value = false
    }
}

// 用途：Demo 模擬成交，核對登入並排除已擁有商品，再交 authStore 更新分頁擁有權。
// 先保存本次清單才移除購物車，方便計算完成摘要；不呼叫 Database 購買 API，不建立 MySQL 訂單。
function confirmDemoPurchase() {
    if (isSubmitting.value) return

    purchaseError.value = ''
    purchaseNotice.value = ''

    if (!authStore.isLoggedIn) {
        purchaseError.value = '登入狀態已失效，請重新登入'
        authStore.requestLogin('/checkout')
        return
    }

    // 再次排除會員已擁有的遊戲
    cartStore.removePurchasedGames()

    if (cartStore.cartCount === 0) {
        clearPaymentFields()
        currentStep.value = 1
        return
    }

    isSubmitting.value = true

    try {
        // 保留本次商品，避免移除後無法計算摘要
        // Demo 成交前複製清單，後面移除購物車時仍有本次商品可算成功摘要。
        const items = [...cartStore.cartGames]
        const gameIds = items.map(game => game.id)

        // 這是 Demo 寫分頁擁有權的唯一操作，沒有呼叫購買 API；回傳實際新取得編號供摘要與移除。
        const purchasedIds = authStore.purchaseGames(gameIds)

        // 只計真正新增的擁有權，不能把已擁有或未取得商品也算進完成金額。
        const purchasedItems = items.filter(game => {
            return purchasedIds.includes(game.id)
        })

        if (purchasedItems.length === 0) {
            currentStep.value = 1
            return
        }

        completedOrder.value = {
            count: purchasedItems.length,
            total: purchasedItems.reduce((sum, game) => {
                return sum + game.price
            }, 0)
        }

        // 逐款移除已成功的 Demo 商品；只用此次回傳編號，不清空不相關項目。
        purchasedIds.forEach(id => {
            cartStore.removeFromCart(id)
        })

        clearPaymentFields()
        currentStep.value = 4
    } catch (error) {
        purchaseError.value = error instanceof Error
            ? error.message
            : '購買未完成，請稍後再試'
    } finally {
        isSubmitting.value = false
    }
}

// 監聽的是模式與會員編號，初始化 immediate true 也先執行；身份改變就清畫面並停舊查詢。
// 不監聽整個會員物件，避免成交更新擁有權時把完成畫面誤重設。
// 同一會員若有待確認暫存，就恢復第三步並自動查結果。
watch(
    [
        () => authStore.source,
        () => authStore.currentUser?.id ?? null,
    ],
    // watch 發現模式或會員改了，就把新的兩個值交給這裡。先停止舊查詢並清畫面，再決定是否讀這位會員尚未確認的購買。
    ([source, userId]) => {
        stopPurchaseQuery()
        pendingPurchase.value = null
        pendingReadError.value = false
        pendingQueryNotFound.value = false
        purchaseNotice.value = ''
        purchaseError.value = ''

        // Demo 不讀 Database 待確認暫存、不呼叫購買查詢 API，保留兩模式隔離。
        if (source !== 'database') return

        checkoutQuote.value = null
        clearPaymentFields()
        currentStep.value = 1

        if (!userId) return

        try {
            // 只讀目前會員的 key；沒有紀錄正常停第一步，壞紀錄阻止新買，合法紀錄恢復第三步自動查。
            const record = loadPendingDatabasePurchase(userId)

            if (record === null) return

            pendingPurchase.value = record
            checkoutQuote.value = record
            currentStep.value = 3
            startPurchaseQuery()
        } catch (error) {
            pendingReadError.value = true
            purchaseError.value = '瀏覽器無法讀取購買紀錄，請保留此頁並稍後再試。'
        }
    },
    { immediate: true }
)

// 監聽瀏覽器 online 通知以提早查詢；它只代表瀏覽器認為已連網，仍可能連不上後端。
window.addEventListener('online', resumePurchaseQuery)

// 離開結帳頁、這個畫面要被移除前：告訴程式不要再用晚到回覆、取消倒數，也取消連線恢復的通知，避免離頁後還一直查。
onBeforeUnmount(() => {
    // 先記住「已離開」。就算之前的查詢此刻才回來，canResolvePurchase 也會看見這個記錄，不再改目前畫面。
    checkoutDisposed = true
    stopPurchaseQuery()
    window.removeEventListener('online', resumePurchaseQuery)
})

// 路由準備離開結帳時清除付款欄位；不清尚待確認的購買，之後才能恢復查詢。
onBeforeRouteLeave(() => {
    clearPaymentFields()
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.checkout-card {
    background-color: #fff;
    border: 1px solid var(--color-border);
}

.checkout-step {
    text-align: center;
    color: var(--color-muted);

    .step-circle {
        background-color: var(--color-secondary);
        color: var(--color-muted);
    }

    .step-line {
        background-color: var(--color-border);
    }

    &.is-current {
        .step-circle {
            background-color: var(--color-primary);
            color: #fff;
        }

        .step-line {
            background-color: var(--color-primary);
        }
    }

    &.is-complete {
        .step-circle {
            background-color: #486C58;
            color: #fff;
        }

        .step-line {
            background-color: #486C58;
        }
    }
}

.order-total {
    color: var(--color-primary);
}

.payment-notice {
    background-color: var(--color-background);
    color: var(--color-muted);
    line-height: 1.65;
}

.success-icon {
    background-color: #E3EDE4;
    color: #486C58;
}

@include bp.mobile {
    .checkout-card {
        padding: 16px !important;
    }

    .checkout-item {
        display: grid !important;
        grid-template-columns: 80px minmax(0, 1fr);
        gap: 8px 12px !important;
    }

    .checkout-cover {
        grid-column: 1;
        grid-row: 1 / 3;
        width: 80px !important;
        max-width: 80px !important;
        align-self: start;
    }

    .checkout-item-info {
        grid-column: 2;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .checkout-item-actions {
        grid-column: 2;
        justify-content: flex-end;
        gap: 8px !important;

        h3 {
            white-space: nowrap;
        }
    }

    .checkout-step {
        display: none;

        &.is-current {
            display: block;
            flex: 0 0 100%;
            max-width: 100%;
        }

        .step-line {
            display: none;
        }
    }

    .checkout-actions {
        flex-direction: column;
        gap: 12px;

        >.v-btn {
            width: 100%;
            min-height: 48px;
        }
    }

    .payment-fields {
        row-gap: 8px;
    }
}
</style>

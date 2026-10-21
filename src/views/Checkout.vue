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

const authStore = useAuthStore()

const isSubmitting = ref(false)
const completedOrder = ref(null)

const cartStore = useCartStore()
const gameStore = useGameStore()

const currentStep = ref(1)
const checkoutQuote = ref(null)
const isQuoting = ref(false)

const pendingPurchase = ref(null)
const pendingReadError = ref(false)

const pendingQueryNotFound = ref(false)
const isCheckingPurchase = ref(false)
const purchaseNotice = ref('')

// 查詢只用來確認結果，永遠不在背景重送購買。
const PURCHASE_QUERY_INTERVAL_MS = 3000
let purchaseQueryTimer = null
let purchaseQueryVersion = 0
let checkoutDisposed = false

function stopPurchaseQuery() {
    clearTimeout(purchaseQueryTimer)
    purchaseQueryTimer = null
    purchaseQueryVersion++
}

function canResolvePurchase(version, record = pendingPurchase.value) {
    return !checkoutDisposed
        && version === purchaseQueryVersion
        && !!record
        && pendingPurchase.value?.requestKey === record.requestKey
        && authStore.source === 'database'
        && authStore.currentUser?.id === record.userId
        && !pendingReadError.value
        && currentStep.value !== 4
}

function schedulePurchaseQuery(version, delay = PURCHASE_QUERY_INTERVAL_MS) {
    clearTimeout(purchaseQueryTimer)
    purchaseQueryTimer = null

    if (canResolvePurchase(version)) {
        purchaseQueryTimer = setTimeout(() => {
            purchaseQueryTimer = null
            void queryPendingPurchase(version)
        }, delay)
    }
}

function startPurchaseQuery() {
    stopPurchaseQuery()

    if (!canResolvePurchase(purchaseQueryVersion)) return

    pendingQueryNotFound.value = false
    purchaseError.value = ''
    purchaseNotice.value = '正在確認購買結果，請稍候。'
    schedulePurchaseQuery(purchaseQueryVersion, 0)
}

function resumePurchaseQuery() {
    if (pendingQueryNotFound.value) return
    schedulePurchaseQuery(purchaseQueryVersion, 0)
}

const confirmationItems = computed(() => {
    if (authStore.source !== 'database') {
        return cartStore.cartGames
    }

    if (!checkoutQuote.value) return []

    return checkoutQuote.value.items.map(item => {
        const game = gameStore.getGameById(item.gameId)

        if (!game) {
            throw new Error('報價商品與遊戲資料不一致，請重新載入。')
        }

        return {
            id: item.gameId,
            title: item.title,
            titleZh: item.titleZh,
            cover: game.cover,
            price: Number(item.unitPrice),
        }
    })
})

const confirmationTotal = computed(() => {
    return authStore.source === 'database'
        ? Number(checkoutQuote.value?.totalAmount ?? '0.00')
        : cartStore.cartTotal
})

const cardNumber = ref('')
const cardExpiry = ref('')
const cardCvv = ref('')

function validateCardNumber(value) {
    const digits = String(value ?? '').replace(/\s/g, '')

    if (!/^\d{13,19}$/.test(digits)) {
        return '請輸入 13～19 位數字的信用卡卡號'
    }
    if (/^0+$/.test(digits)) {
        return '請輸入有效的信用卡卡號'
    }

    let sum = 0
    let shouldDouble = false

    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = Number(digits[i])

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

function validateCardExpiry(value) {
    const match = String(value ?? '').trim().match(/^(\d{2})\/(\d{2})$/)

    if (!match) {
        return '請輸入 MM/YY 格式'
    }

    const month = Number(match[1])
    const year = 2000 + Number(match[2])

    if (month < 1 || month > 12) {
        return '月份需介於 01～12'
    }

    const now = new Date()
    const currentYear = now.getFullYear()
    const currentMonth = now.getMonth() + 1

    if (
        year < currentYear ||
        (year === currentYear && month < currentMonth)
    ) {
        return '信用卡已過期'
    }

    return true
}

function validateCardCvv(value) {
    return /^\d{3,4}$/.test(String(value ?? ''))
        ? true
        : 'CVV 需為 3～4 位數字'
}

const paymentForm = ref(null)

async function submitPayment() {
    if (pendingReadError.value || pendingPurchase.value) {
        purchaseError.value = '上一筆購買尚待確認，請稍候。'
        return
    }
    if (isQuoting.value || isSubmitting.value) return

    purchaseError.value = ''
    purchaseNotice.value = ''

    if (cartStore.cartCount === 0) {
        currentStep.value = 1
        return
    }

    isQuoting.value = true

    try {
        const { valid } = await paymentForm.value.validate()

        if (!valid) return

        if (authStore.source === 'database') {
            const userId = authStore.currentUser?.id

            if (!userId) {
                authStore.requestLogin('/checkout')
                purchaseError.value = '請先登入後再取得報價。'
                return
            }

            const gameIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            checkoutQuote.value = null

            const quote = await createDatabaseQuote(gameIds)

            const currentIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            if (
                authStore.currentUser?.id !== userId
                || quote.userId !== userId
                || JSON.stringify(currentIds) !== JSON.stringify(gameIds)
            ) {
                throw new Error('會員或購物車已變更，請重新確認。')
            }

            checkoutQuote.value = quote
        }

        currentStep.value = 3
    } catch (error) {
        purchaseError.value =
            error.message || '目前無法取得報價，請稍後再試。'
    } finally {
        isQuoting.value = false
    }
}

const formattedCardNumber = computed({
    get() {
        return cardNumber.value
    },
    set(value) {
        const digits = String(value ?? '')
            .replace(/\D/g, '')
            .slice(0, 19)

        cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ')
    }
})

const formattedCardExpiry = computed({
    get() {
        return cardExpiry.value
    },
    set(value) {
        const text = String(value ?? '')
        const digits = text.replace(/\D/g, '').slice(0, 4)

        if (digits.length > 2) {
            cardExpiry.value = `${digits.slice(0, 2)}/${digits.slice(2)}`
        } else if (digits.length === 2 && text.length > cardExpiry.value.length) {
            cardExpiry.value = `${digits}/`
        } else {
            cardExpiry.value = digits
        }
    }
})

function clearPaymentFields() {
    cardNumber.value = ''
    cardExpiry.value = ''
    cardCvv.value = ''
}

function backToCart() {
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

const cardLastFour = computed(() => {
    return cardNumber.value.replace(/\D/g, '').slice(-4)
})

const purchaseError = ref('')
const checkoutMessage = computed(() => purchaseError.value || purchaseNotice.value)

function applyConfirmedDatabasePurchase(result, record) {
    const itemSignature = items => JSON.stringify(
        items
            .map(item => [item.gameId, item.unitPrice])
            .sort((a, b) => a[0] - b[0])
    )

    if (
        result.order.requestKey !== record.requestKey
        || result.order.totalAmount !== record.totalAmount
        || itemSignature(result.order.items)
        !== itemSignature(record.items)
    ) {
        throw new Error('訂單資料與確認內容不一致，暫時無法完成確認。')
    }

    authStore.applyDatabasePurchaseResult(result, record.userId)

    completedOrder.value = {
        orderId: result.order.orderId,
        count: result.order.items.length,
        total: Number(result.order.totalAmount),
    }

    result.order.items.forEach(item => {
        cartStore.removeFromCart(item.gameId)
    })

    clearPaymentFields()
    currentStep.value = 4
    stopPurchaseQuery()
    pendingQueryNotFound.value = false
    purchaseNotice.value = ''

    try {
        clearPendingDatabasePurchase(
            record.userId,
            record.requestKey
        )

        pendingPurchase.value = null
        purchaseError.value = ''
    } catch {
        pendingReadError.value = true
        purchaseError.value =
            '購買已成功，但瀏覽器無法更新紀錄；重新載入後系統將再次確認結果。'
    }
}

async function queryPendingPurchase(version = purchaseQueryVersion) {
    const record = pendingPurchase.value
    if (!canResolvePurchase(version, record)) return

    // 包含 online 事件在內的所有觸發，共用同一個查詢工作。
    if (isSubmitting.value || isCheckingPurchase.value) {
        schedulePurchaseQuery(version)
        return
    }

    isCheckingPurchase.value = true

    try {
        const result = await findDatabasePurchase(record.requestKey)
        if (!canResolvePurchase(version, record)) return

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

            if (member?.id === record.userId && canResolvePurchase(version, record)) {
                schedulePurchaseQuery(version)
                return
            }

            // 不讓舊查詢干擾已切換會員的新流程。
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
        isCheckingPurchase.value = false
    }
}

async function confirmPurchase() {
    if (authStore.source === 'database') {
        await confirmDatabasePurchase()
    } else {
        confirmDemoPurchase()
    }
}

async function confirmDatabasePurchase() {
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
        const saved = loadPendingDatabasePurchase(userId)

        if (
            saved !== null
            && (
                record === null
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

            if (!quote || quote.userId !== userId) {
                throw new Error('請重新取得目前會員的報價。')
            }

            const cartIds = cartStore.cartGames
                .map(game => game.id)
                .sort((a, b) => a - b)

            const quotedIds = quote.items
                .map(item => item.gameId)
                .sort((a, b) => a - b)

            if (JSON.stringify(cartIds) !== JSON.stringify(quotedIds)) {
                throw new Error('購物車已變更，請返回確認商品並重新取得報價。')
            }

            record = {
                ...quote,
                requestKey: createPurchaseRequestKey(),
            }
        }

        // 保存成功才送單。重試仍使用相同 quoteId 與 requestKey。
        try {
            record = savePendingDatabasePurchase(record)
        } catch (error) {
            pendingReadError.value = true
            throw error
        }

        pendingPurchase.value = record
        pendingQueryNotFound.value = false

        const result = await submitDatabasePurchase(
            record.quoteId,
            record.requestKey
        )

        applyConfirmedDatabasePurchase(result, record)
    } catch (error) {
        purchaseError.value = pendingReadError.value
            ? '瀏覽器無法保存購買紀錄，尚未送出購買，請稍後再試。'
            : error.message || '目前無法完成購買，請稍後再試。'

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
                record
                && authStore.currentUser?.id === record.userId
                && [400, 409].includes(error.status)
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

                if (error.code === 'GAMES_ALREADY_OWNED') {
                    const member = await authStore.revalidateDatabaseMember()

                    if (member?.id === record.userId) {
                        cartStore.removePurchasedGames()
                    }
                }

                purchaseError.value =
                    `${error.message} 請更新商品清單後重新確認報價。`
            } else if (
                error.status === 403
                && error.code === 'CSRF_TOKEN_INVALID'
            ) {
                await authStore.revalidateDatabaseMember()

                if (authStore.currentUser?.id === record?.userId) {
                    startPurchaseQuery()
                } else {
                    purchaseError.value = '請登入原購買會員後繼續確認結果。'
                    if (!authStore.isLoggedIn) authStore.requestLogin('/checkout')
                }
            } else if (record && pendingPurchase.value) {
                startPurchaseQuery()
            }
        } catch (recoveryError) {
            if (record && canResolvePurchase(purchaseQueryVersion, record)) {
                startPurchaseQuery()
            } else {
                purchaseError.value = '目前無法完成驗證，請登入原購買會員後再試。'
            }
        }
    } finally {
        clearPaymentFields()
        isSubmitting.value = false
    }
}

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
        const items = [...cartStore.cartGames]
        const gameIds = items.map(game => game.id)

        const purchasedIds = authStore.purchaseGames(gameIds)

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

watch(
    [
        () => authStore.source,
        () => authStore.currentUser?.id ?? null,
    ],
    ([source, userId]) => {
        stopPurchaseQuery()
        pendingPurchase.value = null
        pendingReadError.value = false
        pendingQueryNotFound.value = false
        purchaseNotice.value = ''
        purchaseError.value = ''

        if (source !== 'database') return

        checkoutQuote.value = null
        clearPaymentFields()
        currentStep.value = 1

        if (!userId) return

        try {
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

window.addEventListener('online', resumePurchaseQuery)

onBeforeUnmount(() => {
    checkoutDisposed = true
    stopPurchaseQuery()
    window.removeEventListener('online', resumePurchaseQuery)
})

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

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
        <v-card class="checkout-card pa-6" rounded="xl">
            <section v-if="currentStep === 1">
                <h2>1. 確認商品</h2>
                <template v-if="cartStore.cartCount > 0">
                    <template v-for="game in cartStore.cartGames" :key="game.id">
                        <div class="checkout-item d-flex align-center ga-4 py-4">
                            <v-img class="checkout-cover" :src="getGameCover(game.id)" :alt="`${game.titleZh}封面`"
                                width="120" max-width="120" :aspect-ratio="3 / 4" rounded="lg" cover />
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
                        <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6"
                            @click="currentStep = 2">
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
                        validate-on="blur">
                    </v-text-field>
                    <v-row class="payment-fields mt-2">
                        <v-col cols="12" sm="6">
                            <label for="card-expiry" class="d-block mb-2">有效期限</label>
                            <v-text-field v-model="formattedCardExpiry" id="card-expiry" variant="outlined" rounded="xl"
                                placeholder="MM/YY" maxlength="5" inputmode="numeric" :rules="[validateCardExpiry]"
                                validate-on="blur">
                            </v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <label for="card-cvv" class="d-block mb-2">安全碼 CVV</label>
                            <v-text-field v-model="cardCvv" id="card-cvv" variant="outlined" rounded="xl"
                                placeholder="3～4 位數字" maxlength="4" inputmode="numeric" :rules="[validateCardCvv]"
                                validate-on="blur">
                            </v-text-field>
                        </v-col>
                    </v-row>
                    <v-divider color="var(--color-border)" opacity="1" class="mt-4" />
                    <div class="checkout-actions d-flex justify-space-between mt-6 ga-3">
                        <v-btn rounded="lg" height="auto" class="py-3 px-6" variant="outlined" type="button"
                            @click="backToCart">
                            <v-icon start icon="mdi-arrow-left" />
                            上一步：確認商品
                        </v-btn>
                        <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6" type="submit">
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
                <template v-for="game in cartStore.cartGames" :key="game.id">
                    <div class="checkout-item d-flex align-center ga-4 py-4">
                        <v-img class="checkout-cover" :src="getGameCover(game.id)" :alt="`${game.titleZh}封面`" width="120" max-width="120"
                            :aspect-ratio="3 / 4" rounded="lg" cover />
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
                    <p class="font-weight-bold">{{ cartStore.cartCount }} 款</p>
                </div>
                <div class="d-flex justify-space-between mb-4">
                    <p>訂單總額</p>
                    <p class="order-total font-weight-bold">NT$ {{ cartStore.cartTotal.toLocaleString('en-US') }}</p>
                </div>
                <v-sheet class="payment-notice pa-4 my-6" rounded="lg">
                    <h4 class="mb-2">付款方式</h4>
                    <p class="d-flex align-center ga-2">
                        <v-icon icon="mdi-credit-card-outline" />
                        信用卡 •••• {{ cardLastFour }}
                    </p>
                </v-sheet>
                <v-alert v-if="purchaseError" type="error" variant="tonal" class="mt-4">
                    {{ purchaseError }}
                </v-alert>
                <div class="checkout-actions d-flex justify-space-between mt-6 ga-3">
                    <v-btn rounded="lg" height="auto" class="py-3 px-6" variant="outlined" @click="currentStep = 2">
                        <v-icon start icon="mdi-arrow-left" />
                        上一步：付款資料
                    </v-btn>
                    <v-btn color="primary" rounded="lg" height="auto" class="py-3 px-6"
                        :loading="isSubmitting" :disabled="isSubmitting" @click="confirmPurchase">
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
import { ref, computed } from 'vue';
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'
import { onBeforeRouteLeave } from 'vue-router'

const authStore = useAuthStore()

const isSubmitting = ref(false)
const completedOrder = ref(null)

const cartStore = useCartStore()

const currentStep = ref(1)

const coverImages = import.meta.glob(
    '../assets/images/gameDataWebp/*/*_cover.webp',
    {
        eager: true,
        import: 'default',
        query: '?url'
    }
)
function getGameCover(id) {
    const folder = `game${String(id).padStart(2, '0')}`
    const path = `../assets/images/gameDataWebp/${folder}/${folder}_cover.webp`
    const image = coverImages[path]

    if (!image) {
        throw new Error(`找不到遊戲封面：${path}`)
    }

    return image
}

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
    if (cartStore.cartCount === 0) {
        currentStep.value = 1
        return
    }

    const { valid } = await paymentForm.value.validate()

    if (!valid) return

    currentStep.value = 3
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
    clearPaymentFields()
    currentStep.value = 1
}

const cardLastFour = computed(() => {
    return cardNumber.value.replace(/\D/g, '').slice(-4)
})

const purchaseError = ref('')

function confirmPurchase() {
    if (isSubmitting.value) return

    purchaseError.value = ''

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

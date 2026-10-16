<template>
    <v-dialog v-model="isOpen" width="calc(100% - 32px)" max-width="430">
        <v-card class="login-card" rounded="xl">
            <v-card-title class="d-flex align-center">
                會員登入
                <v-spacer />
                <v-btn icon="mdi mdi-close" aria-label="關閉登入視窗" variant="text" @click="isOpen = false" />
            </v-card-title>
            <v-card-text>
                <v-form @submit.prevent="handleLogin">
                    <label for="login-username" class="d-block mb-2">
                        帳號
                    </label>
                    <v-text-field id="login-username" v-model="username" autocomplete="username" variant="outlined"
                        placeholder="請輸入帳號" rounded="lg" bg-color="white" />
                    <label for="login-password" class="d-block mb-2">
                        密碼
                    </label>
                    <v-text-field id="login-password" v-model="password" type="password" autocomplete="current-password"
                        variant="outlined" placeholder="請輸入密碼" rounded="lg" bg-color="white" />

                    <v-alert v-if="loginError" type="error" variant="tonal" class="mb-4">
                        {{ loginError }}
                    </v-alert>

                    <v-btn type="submit" block color="primary" height="auto" class="py-4 mt-3">
                        登入
                    </v-btn>

                </v-form>
            </v-card-text>

        </v-card>
    </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useNotificationStore } from '../stores/notificationStore'
import { useCartStore } from '../stores/cartStore'
import { useRouter } from 'vue-router'

const router = useRouter()

const cartStore = useCartStore()

const notificationStore = useNotificationStore()

const isOpen = defineModel({ type: Boolean, default: false })

const authStore = useAuthStore()
const username = ref('')
const password = ref('')
const loginError = ref('')

function handleLogin() {
    loginError.value = ''

    if (!username.value.trim() || !password.value) {
        loginError.value = '請輸入帳號與密碼'
        return
    }

    const success = authStore.login(username.value, password.value)

    if (!success) {
        loginError.value = '帳號或密碼錯誤'
        return
    }

    cartStore.removePurchasedGames()

    // 關閉前先取出目的地
    const destination = authStore.loginDestination

    authStore.cancelLogin()
    notificationStore.showMessage('登入成功')

    if (destination) {
        router.push(destination)
    }
}

watch(isOpen, open => {
    if (!open) {
        username.value = ''
        password.value = ''
        loginError.value = ''

        authStore.cancelLogin()
    }
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.loginWrapper {
    border-radius: 500px;
}

:deep(.v-field__input::placeholder) {
    color: var(--color-muted);
    opacity: 0.8;
}

.login-card {
    padding: 16px;
    border-radius: 24px;

    @include bp.mobile {
        padding: 8px;
        border-radius: 12px;

        :deep(.v-card-title),
        :deep(.v-card-text) {
            padding-inline: 12px;
        }
    }
}
</style>

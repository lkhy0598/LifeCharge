<template>
    <v-dialog v-model="isOpen" width="calc(100% - 32px)" max-width="430" :persistent="isSubmitting">
        <v-card class="login-card" rounded="xl">
            <v-card-title class="d-flex align-center">
                {{ isRegister ? '會員註冊' : '會員登入' }}
                <v-spacer />
                <v-btn icon="mdi-close" aria-label="關閉會員視窗" variant="text" :disabled="isSubmitting"
                    @click="isOpen = false" />
            </v-card-title>

            <v-card-text>
                <v-alert v-if="loginError" type="error" variant="tonal" class="mb-4" role="alert">
                    {{ loginError }}
                </v-alert>

                <v-form v-if="!isRegister" @submit.prevent="handleLogin">
                    <label for="login-username" class="d-block mb-2">
                        帳號或 Email
                    </label>
                    <v-text-field id="login-username" v-model="username" autocomplete="username"
                        placeholder="請輸入帳號或 Email" variant="outlined" rounded="lg" bg-color="white"
                        :disabled="isSubmitting" />

                    <label for="login-password" class="d-block mb-2">
                        密碼
                    </label>
                    <v-text-field id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'"
                        :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" autocomplete="current-password"
                        placeholder="請輸入密碼" variant="outlined" rounded="lg" bg-color="white" :disabled="isSubmitting"
                        @click:append-inner="showPassword = !showPassword" />

                    <v-btn type="submit" block color="primary" height="auto" class="py-4 mt-3" :loading="isSubmitting"
                        :disabled="isSubmitting">
                        登入
                    </v-btn>

                    <v-btn v-if="canRegister" type="button" block variant="outlined" height="auto" class="py-3 mt-3"
                        :disabled="isSubmitting" @click="switchForm(true)">
                        會員註冊
                    </v-btn>
                </v-form>

                <v-form v-else @submit.prevent="handleRegister">
                    <template v-for="field in registrationFields" :key="field.key">
                        <label :for="`register-${field.key}`" class="d-block mb-2">
                            {{ field.label }}
                        </label>

                        <v-text-field :id="`register-${field.key}`" v-model="registration[field.key]" :type="field.secret
                            ? (showPassword ? 'text' : 'password')
                            : field.type" :append-inner-icon="field.secret
                                ? (showPassword ? 'mdi-eye-off' : 'mdi-eye')
                                : undefined" :autocomplete="field.autocomplete" :placeholder="`請輸入${field.label}`"
                            :error-messages="fieldErrors[field.key] ?? []" variant="outlined" rounded="lg"
                            bg-color="white" :disabled="isSubmitting"
                            @click:append-inner="field.secret && (showPassword = !showPassword)" />
                    </template>

                    <p class="text-body-small mb-4">
                        帳號為 3～50 字元，可使用英文字母、數字、底線與連字號。
                        密碼至少 8 字元、最多 72 bytes，不可包含空白；
                        中文字元會占用多個 bytes。
                    </p>

                    <v-btn type="submit" block color="primary" height="auto" class="py-4" :loading="isSubmitting"
                        :disabled="isSubmitting">
                        註冊並登入
                    </v-btn>

                    <v-btn type="button" block variant="text" class="mt-3" :disabled="isSubmitting"
                        @click="switchForm(false)">
                        返回登入
                    </v-btn>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useNotificationStore } from '../stores/notificationStore'
import { useCartStore } from '../stores/cartStore'
import { useRouter } from 'vue-router'

const router = useRouter()
const cartStore = useCartStore()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()

const isOpen = defineModel({ type: Boolean, default: false })

const username = ref('')
const password = ref('')
const loginError = ref('')
const isSubmitting = ref(false)
const isRegister = ref(false)
const showPassword = ref(false)
const fieldErrors = ref({})

const canRegister = computed(() => {
    return authStore.source === 'database'
        && authStore.loadStatus === 'ready'
})

const registration = reactive({
    displayName: '',
    username: '',
    email: '',
    password: '',
    confirmPassword: '',
})

const registrationFields = [
    {
        key: 'displayName',
        label: '顯示名稱',
        autocomplete: 'nickname',
        type: 'text',
    },
    {
        key: 'username',
        label: '帳號',
        autocomplete: 'username',
        type: 'text',
    },
    {
        key: 'email',
        label: 'Email',
        autocomplete: 'email',
        type: 'email',
    },
    {
        key: 'password',
        label: '密碼',
        autocomplete: 'new-password',
        secret: true,
    },
    {
        key: 'confirmPassword',
        label: '確認密碼',
        autocomplete: 'new-password',
        secret: true,
    },
]

function clearPasswords() {
    password.value = ''
    registration.password = ''
    registration.confirmPassword = ''
    showPassword.value = false
}

function switchForm(register) {
    if (isSubmitting.value) return
    if (register && !canRegister.value) return

    isRegister.value = register
    clearPasswords()
    loginError.value = ''
    fieldErrors.value = {}

    // 不關閉視窗，也不清除原目的地。
}

function validateRegistration() {
    const errors = {}

    if (
        !registration.displayName.trim()
        || [...registration.displayName.trim()].length > 100
    ) {
        errors.displayName = '顯示名稱須為 1～100 字元。'
    }

    if (!/^[A-Za-z0-9_-]{3,50}$/.test(registration.username.trim())) {
        errors.username =
            '帳號須為 3～50 字元，只能使用英文字母、數字、底線與連字號。'
    }

    if (
        !registration.email.trim()
        || new TextEncoder().encode(
            registration.email.trim()
        ).length > 254
        || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            registration.email.trim()
        )
    ) {
        errors.email = '請輸入有效的 Email。'
    }

    if (
        [...registration.password].length < 8
        || new TextEncoder().encode(registration.password).length > 72
        || /[\s\p{Z}\uFEFF\u0000]/u.test(registration.password)
    ) {
        errors.password =
            '密碼至少 8 字元、最多 72 bytes，且不可包含空白。'
    }

    if (registration.password !== registration.confirmPassword) {
        errors.confirmPassword = '兩次輸入的密碼不一致。'
    }

    fieldErrors.value = errors
    return Object.keys(errors).length === 0
}

async function handleLogin() {
    await submitMember(false)
}

async function handleRegister() {
    await submitMember(true)
}

async function submitMember(register) {
    if (isSubmitting.value) return

    loginError.value = ''
    fieldErrors.value = {}

    if (register) {
        if (!canRegister.value) {
            loginError.value = '目前模式不提供會員註冊。'
            return
        }

        if (!validateRegistration()) return
    } else if (!username.value.trim() || !password.value) {
        loginError.value = '請輸入帳號或 Email 與密碼'
        return
    }

    isSubmitting.value = true

    try {
        const success = register
            ? await authStore.register({
                displayName: registration.displayName,
                username: registration.username,
                email: registration.email,
                password: registration.password,
            })
            : await authStore.login(username.value, password.value)

        if (!success) {
            loginError.value = '帳號、Email 或密碼錯誤'
            return
        }

        cartStore.removePurchasedGames()

        const destination = authStore.loginDestination
        authStore.cancelLogin()

        notificationStore.showMessage(
            register ? '註冊成功，已自動登入' : '登入成功'
        )

        if (destination) {
            await router.push(destination)
        } else if (register) {
            await router.push('/member')
        }
    } catch (error) {
        loginError.value = error.message || '目前無法完成操作，請稍後再試。'
        fieldErrors.value = error.fields ?? {}
    } finally {
        clearPasswords()
        isSubmitting.value = false
    }
}

watch(isOpen, open => {
    if (!open) {
        username.value = ''
        clearPasswords()
        Object.assign(registration, {
            displayName: '',
            username: '',
            email: '',
            password: '',
            confirmPassword: '',
        })

        isRegister.value = false
        loginError.value = ''
        fieldErrors.value = {}
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

    max-height: calc(100dvh - 32px);
    overflow: hidden;

    :deep(.v-card-text) {
        min-height: 0;
        overflow-y: auto;
    }

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

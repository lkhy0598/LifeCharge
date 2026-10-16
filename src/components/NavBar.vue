<template>
    <v-app-bar tag="header" :height="65" color="var(--color-secondary)" elevation="0">
        <section class="page-container d-flex align-center">
            <RouterLink to="/">
                <img :src="logo" alt="Life Charge" width="180" class="navbar-logo">
            </RouterLink>
            <v-spacer />
            <nav aria-label="主要導覽" class="d-flex align-center ga-2">
                <v-badge class="cart-badge" :content="cartStore.cartCount" :model-value="cartStore.cartCount > 0"
                    color="var(--color-primary)" :offset-x="10" :offset-y="10">
                    <v-btn icon="mdi mdi-cart" aria-label="開啟購物車" @click="openCart" color="var(--color-text)"
                        :disabled="route.name === 'Checkout'" />
                </v-badge>
                <v-btn icon aria-label="會員中心或登入" @click="handleMemberClick" color="var(--color-text)">
                    <v-avatar v-if="authStore.isLoggedIn" :size="32">
                        <v-img :src="currentAvatar" :alt="`${authStore.currentUser.displayName}的頭像`" cover />
                    </v-avatar>

                    <v-icon v-else icon="mdi-account" />
                </v-btn>
            </nav>
        </section>
    </v-app-bar>

</template>

<script setup>
import { RouterLink, useRouter, useRoute } from 'vue-router'
import logo from '../assets/images/logoWebp/logoDw.webp'
import { useAuthStore } from '../stores/authStore'
import { computed } from 'vue'
import { useCartStore } from '../stores/cartStore'

const route = useRoute()

const cartStore = useCartStore()

const emit = defineEmits(['open-cart', 'open-login'])

const router = useRouter()
const authStore = useAuthStore()

function openCart() {
    emit('open-cart')
}

function handleMemberClick() {
    if (authStore.isLoggedIn) {
        router.push('/member')
    } else {
        emit('open-login')
    }
}

const avatarImages = import.meta.glob(
    '/src/assets/images/usersDataWebp/*/*.webp',
    {
        eager: true,
        import: 'default',
        query: '?url'
    }
)

const currentAvatar = computed(() => {
    if (!authStore.currentUser) return null

    const path = authStore.currentUser.avatar
    const image = avatarImages[path]

    if (!image) {
        throw new Error(`找不到會員頭像：${path}`)
    }

    return image
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.cart-badge {
    :deep(.v-badge__badge) {
        min-width: 16px;
        height: 16px;
        padding-inline: 4px;
        font-size: 10px;
        line-height: 16px;
        color: #fff;
        pointer-events: none;
    }
}

.navbar-logo {
    display: block;

    @include bp.mobile {
        width: 140px;
    }
}
</style>
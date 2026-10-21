<template>
    <v-navigation-drawer v-model="isOpen" location="right" temporary :width="drawerWidth">
        <v-list-item class="align-center mt-5 mb-4">
            <v-list-item-title class="text-headline-small">
                購物車
            </v-list-item-title>
            <template #append>
                <v-btn icon="mdi mdi-close" aria-label="關閉購物車" variant="text" @click="isOpen = false" />
            </template>
        </v-list-item>

        <template v-if="cartStore.cartCount > 0">
            <v-card class="pa-4" v-for="game in cartStore.cartGames" :key="game.id">
                <section class="d-flex align-center ga-5">
                    <v-img :src="game.cover" :alt="`${game.titleZh}封面`" width="80" max-width="80"
                        aspect-ratio="0.75" cover rounded="lg" />
                    <div class="flex-grow-1 d-flex flex-column align-self-stretch">
                        <h3 class="text-title-large">{{ game.titleZh }}</h3>
                        <p class="text-body-medium">{{ game.title }}</p>
                        <v-spacer />
                        <p class="text-body-large font-weight-bold">NT$ {{ game.price.toLocaleString('en-US') }}</p>
                    </div>

                    <div class="d-flex flex-column align-end ">
                        <v-btn icon="mdi mdi-trash-can-outline" :aria-label="`移除${game.titleZh}`" variant="text"
                            size="default" color="var(--color-primary)" @click="cartStore.removeFromCart(game.id)" />
                    </div>
                </section>
            </v-card>
            <v-divider class="mx-4" color="var(--color-muted)" opacity="1" />

            <v-sheet class="pa-4">
                <div class="d-flex mb-4">
                    <p>共 {{ cartStore.cartCount }} 款遊戲</p>
                    <v-spacer />
                    <p class="font-weight-bold" style="color: var(--color-primary)">NT$ {{
                        cartStore.cartTotal.toLocaleString('en-US') }}</p>
                </div>
                <v-btn block color="primary" class="py-4" height="auto" @click="handleCheckout">
                    前往結帳
                </v-btn>
            </v-sheet>
        </template>

        <div v-else class="text-center pa-6">
            <p class="mb-4">目前購物車尚無商品</p>

            <v-btn to="/search" color="primary" rounded="lg" @click="isOpen = false">
                探索遊戲
            </v-btn>
        </div>


    </v-navigation-drawer>
</template>

<script setup>
import { useCartStore } from '../stores/cartStore'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

const isOpen = defineModel({ type: Boolean, default: false })
const cartStore = useCartStore()

const router = useRouter()
const authStore = useAuthStore()

function handleCheckout() {
    if (cartStore.cartCount === 0) return

    isOpen.value = false

    if (authStore.isLoggedIn) {
        router.push('/checkout')
    } else {
        authStore.requestLogin('/checkout')
    }
}

const { xs, width } = useDisplay()

const drawerWidth = computed(() => {
    if (xs.value) return '100%'

    return Math.max(440, width.value * 0.25)
})

</script>


<style scoped></style>

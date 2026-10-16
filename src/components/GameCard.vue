<template>
    <v-card @click="openGame">
        <v-img :src="gameCover" :alt="`${props.game.titleZh}封面`" rounded="lg" :aspect-ratio="3 / 4" cover>
            <div v-if="props.mode === 'store'" class="cover-status">
                <span v-if="isPurchased" class="status-badge is-owned">
                    已購買
                </span>

                <span v-else-if="cartStore.isInCart(props.game.id)" class="status-badge is-cart">
                    已加入購物車
                </span>
            </div>
        </v-img>
        <v-card-text class="game-card-text">
            <section class="d-flex align-center">
                <h3>{{ props.game.titleZh }}</h3>
                <v-spacer />
                <span v-if="props.mode === 'store'">
                    <v-icon icon="mdi-star" size="small" />
                    {{ props.game.rating }}
                </span>

            </section>
            <p>{{ props.game.title }}</p>
            <section v-if="props.mode === 'store'" class="game-price-row d-flex mt-4 align-center">
                <h3>NT$ {{ props.game.price.toLocaleString('en-US') }}</h3>
                <v-spacer />
                <v-btn v-if="!isPurchased && !cartStore.isInCart(props.game.id)" class="cart-button" icon variant="text" :size="32"
                    aria-label="加入購物車" @click.stop="handleAddToCart">
                    <v-icon icon="mdi-cart" :size="24" />
                </v-btn>
            </section>
        </v-card-text>
    </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cartStore'
import { useAuthStore } from '../stores/authStore'

const cartStore = useCartStore()

function handleAddToCart() {
    cartStore.addToCart(props.game.id)
}

const coverImages = import.meta.glob(
    '../assets/images/gameDataWebp/*/*_cover.webp',
    {
        eager: true,
        import: 'default',
        query: '?url'
    }
)

const gameCover = computed(() => {
    const folder = `game${String(props.game.id).padStart(2, '0')}`
    const path = `../assets/images/gameDataWebp/${folder}/${folder}_cover.webp`
    const image = coverImages[path]

    if (!image) {
        throw new Error(`找不到遊戲封面：${path}`)
    }

    return image
})

const props = defineProps({
    mode: {
        type: String,
        default: 'store'
    },
    game: {
        type: Object,
        required: true
    }
})

const router = useRouter()

function openGame() {
    router.push(`/game/${props.game.id}`)
}

const authStore = useAuthStore()
const isPurchased = computed(() => {
    return authStore.purchasedGames.includes(props.game.id)
})

</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.game-price-row {
    min-height: 32px;
}

.cart-button {
    &:hover {
        color: var(--color-primary);
    }

    &:active {
        background-color: var(--color-secondary);
    }

    &:active :deep(.v-btn__overlay) {
        opacity: 0;
    }

}

.cover-status {
    position: absolute;
    top: 8px;
    right: 8px;
}

.status-badge {
    display: inline-block;
    padding: 6px 8px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;

    &.is-owned {
        color: #486C58;
        background-color: #E3EDE4;
    }

    &.is-cart {
        color: #42648B;
        background-color: #E5EAF1;
    }
}

.game-card-text{
    padding: 0px 6px;
    padding-top: 2px;
}

@include bp.mobile {

    .game-card-text{
    padding: 0px;
    padding-top: 2px;
}
}
</style>

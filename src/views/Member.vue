<template>
    <div v-if="authStore.currentUser" class="page-container">
        <div class="mt-10 mb-4">
            <h2>會員中心</h2>
        </div>
        <section aria-label="會員資料">
            <v-card class="pa-6 member-profile" rounded="xl">
                <div class="profile-layout d-flex align-center ga-4 ">
                    <v-avatar :size="96">
                        <v-img :src="currentAvatar" :alt="`${authStore.currentUser.displayName}的頭像`" cover />
                    </v-avatar>
                    <v-card-text class="profile-info">
                        <h3>{{ authStore.currentUser.displayName }}</h3>
                        <p>@{{ authStore.currentUser.username }}</p>
                        <p>{{ authStore.currentUser.email }}</p>
                    </v-card-text>
                    <v-btn variant="outlined" @click="handleLogout" class="logout-btn" :loading="isLoggingOut"
                        :disabled="isLoggingOut">登出</v-btn>
                </div>
            </v-card>
        </section>
        <section aria-label="遊戲收藏資料" class="mt-10">
            <h2 class="mb-4">我的遊戲收藏庫 ({{ libraryGames.length }})</h2>
            <v-row v-if="libraryGames.length > 0">
                <v-col cols="6" md="4" xl="3" v-for="game in libraryGames" :key="game.id">
                    <GameCard mode="library" :game="game" />
                </v-col>
            </v-row>
            <div v-else class="text-center py-10">
                <h3 class="mb-3">目前還沒有收藏遊戲</h3>
                <p class="mb-6">前往探索遊戲，找到你喜歡的作品。</p>

                <v-btn to="/search" color="primary" rounded="lg">
                    探索遊戲
                </v-btn>
            </div>
        </section>
    </div>
</template>

<script setup>
import GameCard from '../components/GameCard.vue'
import { useAuthStore } from '../stores/authStore'
import { computed, ref } from 'vue'
import { useGameStore } from '../stores/gameStore'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../stores/notificationStore'

const notificationStore = useNotificationStore()

const authStore = useAuthStore()
const isLoggingOut = ref(false)

const currentAvatar = computed(() => {
    return authStore.currentUser?.avatar ?? null
})

const gameStore = useGameStore()

const libraryGames = computed(() => {
    return gameStore.games.filter(game => {
        return authStore.purchasedGames.includes(game.id)
    })
})

const router = useRouter()

async function handleLogout() {
    if (isLoggingOut.value) return

    isLoggingOut.value = true

    try {
        await authStore.logout()
    } catch (error) {
        notificationStore.showMessage(
            error.message || '目前無法登出，請稍後再試。'
        )
        return
    } finally {
        isLoggingOut.value = false
    }

    notificationStore.showMessage('登出成功')
    await router.push('/')
}
</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.member-profile {
    background-color: #fff;
    border: 1px solid var(--color-border);
}

@include bp.mobile {
    .member-profile {
        padding: 16px !important;
    }

    .profile-layout {
        flex-direction: column;
        text-align: center;
    }

    .profile-info {
        width: 100%;
        min-width: 0;
        padding: 0;
        overflow-wrap: anywhere;
    }

    .logout-btn {
        margin-left: 0;
    }
}
</style>
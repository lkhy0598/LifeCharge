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
// 本頁顯示目前會員與遊戲庫，資料來自 authStore 與 gameStore。
// 路由層先檢查登入，後端 API 仍會獨立驗證身份；頁面本身不直接查 MySQL。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。
// computed 是依現有資料算出畫面要顯示的結果；watch 是等某項資料變了，再執行指定動作。下方會配實際用途說明。

import GameCard from '../components/GameCard.vue'
import { useAuthStore } from '../stores/authStore'
import { computed, ref } from 'vue'
import { useGameStore } from '../stores/gameStore'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../stores/notificationStore'

const notificationStore = useNotificationStore()

const authStore = useAuthStore()
// isLoggingOut 記錄是否正在登出，用來停用按鈕、防止連按；會員 Store 也會檢查是否已有操作在執行。
const isLoggingOut = ref(false)

// 會員頭像已由 Service 整理成可用網址；沒有會員時回 null，不自己拼圖片路徑。
const currentAvatar = computed(() => {
    return authStore.currentUser?.avatar ?? null
})

const gameStore = useGameStore()

// filter 從全部遊戲保留擁有權清單包含的項目，把純編號轉成卡片需要的完整遊戲。
// 會員或擁有權改變就會重算；不在這裡自行新增購買紀錄。
const libraryGames = computed(() => {
    // 以完整遊戲清單為基礎，保留會員 purchasedGames 包含的編號；不是直接顯示 JSON 原始擁有權。
    return gameStore.games.filter(game => {
        return authStore.purchasedGames.includes(game.id)
    })
})

const router = useRouter()

// 用途：等待 authStore 確認登出，成功才顯示通知並回首頁。
// isLoggingOut 防止連按；async／await 等待後端操作，catch 處理失敗，finally 一定解除忙碌。
// 失敗時 return 留在目前頁面，不先清掉會員假裝成功。
async function handleLogout() {
    // 已有登出工作就 return，不讓連按重複呼叫後端；通過後先設 true 才 await。
    if (isLoggingOut.value) return

    isLoggingOut.value = true

    try {
        // 等後端確認後才往下走；失敗進 catch 顯示訊息並 return，因此不執行成功通知與回首頁。
        await authStore.logout()
    } catch (error) {
        notificationStore.showMessage(
            // 先用後端／程式提供的 error.message；如果沒有訊息或是空文字，才顯示右邊寫好的備用提示。
            // 這個 || 是挑要顯示的文字，不是在檢查兩個條件是否同時成立。
            error.message || '目前無法登出，請稍後再試。'
        )
        return
    } finally {
        // finally 不論成功失敗都恢復按鈕；不在這裡自己修改會員身份，交 Store 維持一致。
        isLoggingOut.value = false
    }

    notificationStore.showMessage('登出成功')
    // 只有成功登出才導向首頁；如果還停在受保護會員頁可能造成畫面與身份不一致。
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
<template>
  <v-app>
    <NavBar @open-cart="isCartOpen = !isCartOpen" @open-login="authStore.requestLogin()" />
    <CartMenu v-model="isCartOpen" />
    <LoginDialog v-model="authStore.isLoginOpen" />
    <v-main>
      <PageToolbar />
      <RouterView />
    </v-main>
    <Footer class="mt-5" />
    <v-snackbar v-model="notificationStore.isOpen" :timeout="1000" location="center" class="success-snackbar">
      <div class="d-flex justify-center align-center ga-3">
        <v-icon icon="mdi-check-circle-outline" :size="28" />
        <span>{{ notificationStore.message }}</span>
      </div>
    </v-snackbar>
  </v-app>
</template>

<script setup>
// 本檔是網站外框：組合導覽、購物車、登入視窗、目前路由頁面、頁尾與共用通知。
// main.js 確認資料準備好後才顯示這個元件；這裡不再次決定模式或發登入請求。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。
// ref 用來保存會改變的值，例如視窗是否打開。這裡改值要寫「變數.value」，Vue 才能連帶更新畫面。

import { ref } from 'vue'
import { RouterView } from 'vue-router'
// 先讓這支程式認得 NavBar 等畫面零件，再由上面的 template 決定每個零件放在哪裡；光 import 不會直接顯示。
import NavBar from './components/NavBar.vue';
import CartMenu from './components/CartMenu.vue';
import LoginDialog from './components/LoginDialog.vue';
import PageToolbar from './components/PageToolbar.vue';
import Footer from './components/Footer.vue';
import { useNotificationStore } from './stores/notificationStore'
import { useAuthStore } from './stores/authStore'

// 取得同一份會員 Store，讓 LoginDialog 的開關與各頁要求登入的動作同步。
// Store 中的 isLoginOpen 與 LoginDialog v-model 連接；任何頁呼叫 requestLogin 都會反映在同一個視窗。
const authStore = useAuthStore()

// 通知 Store 保存訊息與是否開啟；template 的 Snackbar 負責實際顯示與關閉時間。
// 讀通知文字與開關；Snackbar 在 template 設 timeout，自動關閉不代表清除會員身份。
const notificationStore = useNotificationStore()

// false 表示購物車抽屜關閉。NavBar 發 open-cart 時，template 切換此值。
// CartMenu 用 v-model 與此值雙向同步，因此在抽屜內關閉也會更新這裡。
// 購物車一開始關著。點導覽的購物車圖示時，App 會把關改開、開改關；在抽屜裡按關閉也會更新同一個值。
const isCartOpen = ref(false)
</script>

<style scoped>
.success-snackbar :deep(.v-snackbar__wrapper) {
  background-color: #E3EDE4;
  color: #486C58;
}
</style>

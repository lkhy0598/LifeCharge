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
import { ref } from 'vue'
import { RouterView } from 'vue-router'
import NavBar from './components/NavBar.vue';
import CartMenu from './components/CartMenu.vue';
import LoginDialog from './components/LoginDialog.vue';
import PageToolbar from './components/PageToolbar.vue';
import Footer from './components/Footer.vue';
import { useNotificationStore } from './stores/notificationStore'
import { useAuthStore } from './stores/authStore'

const authStore = useAuthStore()

const notificationStore = useNotificationStore()

const isCartOpen = ref(false)
</script>

<style scoped>
.success-snackbar :deep(.v-snackbar__wrapper) {
  background-color: #E3EDE4;
  color: #486C58;
}
</style>

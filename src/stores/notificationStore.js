import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
    const isOpen = ref(false)
    const message = ref('')

    function showMessage(text) {
        message.value = text
        isOpen.value = true
    }

    return {
        isOpen,
        message,
        showMessage
    }
})
// 本檔保存共用的短暫通知，例如登入成功。
// ref 建立會更新畫面的狀態；App.vue 的 Snackbar 讀取 isOpen 與 message 顯示。
// 這不是錯誤恢復、會員或資料庫工具，不保存購買紀錄。

import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
    // 先以 false 關閉通知，message 空字串表示還沒有文字；兩者分開保存，方便重用通知畫面。
    const isOpen = ref(false)
    const message = ref('')

    // 傳入要顯示的文字，先改訊息再開通知；關閉時間由 App.vue 的 Snackbar 設定。
    function showMessage(text) {
        // 第一步把傳入 text 放入訊息，第二步設 isOpen true；順序讓通知一打開就有新文字。
        message.value = text
        isOpen.value = true
    }

    // 把 isOpen、message、showMessage 交出去；App.vue 可讀開關與文字，其他畫面呼叫 showMessage。
    return {
        isOpen,
        message,
        showMessage
    }
})
<template>
    <main class="startup-screen" aria-live="polite">
        <h1>LifeCharge</h1>
        <p>{{ error || message }}</p>

        <button v-if="error" type="button" @click="reload">
            重新載入
        </button>
    </main>
</template>

<script setup>
// 本元件只呈現啟動提示或載入失敗畫面，由 main.js 決定何時顯示。
// 它不呼叫健康檢查、不改模式；message 與 error 都由外面傳入。
// 畫面打開時會執行這段 <script setup>。上面 <template> 寫畫面長相，這裡寫要顯示的資料與按鈕按下後的動作。
// import 讓這個畫面能使用其他檔案的功能；Store 是全站共用的資料，例如換頁後仍是同一份購物車。

// main.js 會傳入提示文字 message 和錯誤文字 error。這裡先說明兩者都是文字，沒傳就使用 default 寫好的預設內容。
// template 用 error || message，意思是有錯誤先顯示錯誤，否則顯示一般提示。
defineProps({
    // props 的 String 與 default 是輸入規格；沒傳 message 時使用預設文字，不在元件中判斷 API。
    message: {
        type: String,
        default: '正在判斷 DEMO 或 DATABASE 模式…',
    },
    // error 沒文字時只顯示載入提示；error 有文字時才出現重新載入按鈕。這個畫面只呈現結果，沒有自己決定網站模式。
    error: {
        type: String,
        default: '',
    },
})

// 用途：重新載入目前網址，讓 main.js 從頭檢查後端與資料，不直接重送任何購買。
// window 是瀏覽器提供的全域物件，location.reload() 相當於重新整理網頁。
function reload() {
    // 重新載入當前網址，main.js 從頭初始化；不是只重送最後一筆購買 POST，所以不會在此盲目重買。
    window.location.reload()
}
</script>

<style scoped>
.startup-screen {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 24px;
    text-align: center;
}

button {
    padding: 10px 20px;
    cursor: pointer;
}
</style>
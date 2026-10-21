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
defineProps({
    message: {
        type: String,
        default: '正在判斷 DEMO 或 DATABASE 模式…',
    },
    error: {
        type: String,
        default: '',
    },
})

function reload() {
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
<template>
    <div class="page-container">
        <article class="news-article mt-10">
            <h1>生活充電站 Demo 版體驗帳號</h1>
            <section class="news-content">
                <p>歡迎來到生活充電站！</p>
                <p>目前網站為 Demo 展示版本，暫不開放註冊會員。
                    您可以選擇以下任一組體驗帳號登入，探索遊戲、
                    加入購物車，並體驗結帳流程。</p>
                <h2>Demo 體驗帳號</h2>
                <p>每位會員預設擁有的遊戲內容不同，
                    您可以使用不同帳號登入，體驗各會員的遊戲收藏。</p>
                <ul class="account-list">
                    <li v-for="account in demoAccounts" :key="account.id" class="account-box">
                        <p>帳號：<strong>{{ account.username }}</strong></p>
                        <p>密碼：<strong>{{ account.password }}</strong></p>
                    </li>
                </ul>
                <h2>使用方式</h2>
                <ol>
                    <li>點選網站上方的「登入」，或下方的「立即登入」。</li>
                    <li>選擇一組體驗帳號並輸入帳號與密碼。</li>
                    <li>挑選喜歡的遊戲，加入購物車並前往結帳。</li>
                </ol>
                <h2>請注意</h2>
                <ul>
                    <li>本網站僅供作品展示，購買與付款流程皆為模擬，不會產生實際交易。</li>
                    <li>請勿輸入真實的信用卡資料或其他敏感個人資訊。</li>
                </ul>

                <v-btn v-if="!authStore.isLoggedIn" color="primary" class="login-btn mt-6" @click="authStore.requestLogin()">
                    立即登入
                </v-btn>
            </section>
        </article>
    </div>
</template>

<script setup>
import demoAccounts from '../data/users.json'
import { useAuthStore } from '../stores/authStore';

const authStore = useAuthStore()
</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;


.news-content {
    display: grid;
    gap: 16px;
    margin-top: 24px;
    line-height: 1.8;

    h2 {
        margin-top: 16px;
    }

    ol,
    ul:not(.account-list) {
        padding-left: 24px;
    }

    .login-btn {
        justify-self: start;
    }
}

.account-list {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    padding: 0;
    list-style: none;
}

.account-box {
    padding: 16px 20px;
    border: 1px solid var(--color-border);
    border-radius: 12px;
}

@include bp.mobile {
    .account-list {
        grid-template-columns: 1fr;
    }
}
</style>
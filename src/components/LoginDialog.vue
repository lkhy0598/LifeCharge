<template>
    <v-dialog v-model="isOpen" width="calc(100% - 32px)" max-width="430" :persistent="isSubmitting">
        <v-card class="login-card" rounded="xl">
            <v-card-title class="d-flex align-center">
                {{ isRegister ? '會員註冊' : '會員登入' }}
                <v-spacer />
                <v-btn icon="mdi-close" aria-label="關閉會員視窗" variant="text" :disabled="isSubmitting"
                    @click="isOpen = false" />
            </v-card-title>

            <v-card-text>
                <v-alert v-if="loginError" type="error" variant="tonal" class="mb-4" role="alert">
                    {{ loginError }}
                </v-alert>

                <v-form v-if="!isRegister" @submit.prevent="handleLogin">
                    <label for="login-username" class="d-block mb-2">
                        帳號或 Email
                    </label>
                    <v-text-field id="login-username" v-model="username" autocomplete="username"
                        placeholder="請輸入帳號或 Email" variant="outlined" rounded="lg" bg-color="white"
                        :disabled="isSubmitting" />

                    <label for="login-password" class="d-block mb-2">
                        密碼
                    </label>
                    <v-text-field id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'"
                        :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'" autocomplete="current-password"
                        placeholder="請輸入密碼" variant="outlined" rounded="lg" bg-color="white" :disabled="isSubmitting"
                        @click:append-inner="showPassword = !showPassword" />

                    <v-btn type="submit" block color="primary" height="auto" class="py-4 mt-3" :loading="isSubmitting"
                        :disabled="isSubmitting">
                        登入
                    </v-btn>

                    <v-btn v-if="canRegister" type="button" block variant="outlined" height="auto" class="py-3 mt-3"
                        :disabled="isSubmitting" @click="switchForm(true)">
                        會員註冊
                    </v-btn>
                </v-form>

                <v-form v-else @submit.prevent="handleRegister">
                    <template v-for="field in registrationFields" :key="field.key">
                        <label :for="`register-${field.key}`" class="d-block mb-2">
                            {{ field.label }}
                        </label>

                        <v-text-field :id="`register-${field.key}`" v-model="registration[field.key]" :type="field.secret
                            ? (showPassword ? 'text' : 'password')
                            : field.type" :append-inner-icon="field.secret
                                ? (showPassword ? 'mdi-eye-off' : 'mdi-eye')
                                : undefined" :autocomplete="field.autocomplete" :placeholder="`請輸入${field.label}`"
                            :error-messages="fieldErrors[field.key] ?? []" variant="outlined" rounded="lg"
                            bg-color="white" :disabled="isSubmitting"
                            @click:append-inner="field.secret && (showPassword = !showPassword)" />
                    </template>

                    <p class="text-body-small mb-4">
                        帳號為 3～50 字元，可使用英文字母、數字、底線與連字號。
                        密碼至少 8 字元、最多 72 bytes，不可包含空白；
                        中文字元會占用多個 bytes。
                    </p>

                    <v-btn type="submit" block color="primary" height="auto" class="py-4" :loading="isSubmitting"
                        :disabled="isSubmitting">
                        註冊並登入
                    </v-btn>

                    <v-btn type="button" block variant="text" class="mt-3" :disabled="isSubmitting"
                        @click="switchForm(false)">
                        返回登入
                    </v-btn>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<script setup>

// 這支檔案控制「會員登入／註冊視窗」的行為，上方 template 負責畫輸入框與按鈕。
// 先看整體：輸入 → 檢查欄位 → 等待登入／註冊結果 → 更新購物車 → 關閉視窗 → 接續原頁面。
// 本頁不直接讀資料庫，也不自己認定誰已登入；身份判斷交給 authStore 與 PHP 後端。
//
// 下面從 Vue 取四個工具：
// computed：根據資料算結果，例如能不能註冊；reactive：一起管理多個表單欄位。
// ref：保存單個會變的值，例如密碼文字；watch：某個值改變時，執行指定收尾動作。
import { computed, reactive, ref, watch } from 'vue'
// 會員工具保存全站目前身份，並提供 login／register；這個視窗只呼叫，不重寫密碼比對。
import { useAuthStore } from '../stores/authStore'
// 通知工具讓 App 顯示「登入成功」等訊息；不是將這些文字寫入資料庫。
import { useNotificationStore } from '../stores/notificationStore'
// 登入後要從購物車移除這位會員已買過的遊戲，因此取得共用購物車工具。
import { useCartStore } from '../stores/cartStore'
// router 是換頁工具，例如登入成功後回原本想進入的結帳頁。
import { useRouter } from 'vue-router'

// 先取得上述工具的實際使用對象；這幾行沒有開始登入、註冊或送 HTTP 請求。
// 呼叫 useAuthStore() 取的是全站同一份會員狀態，不是另外建立一位會員。
const router = useRouter()
const cartStore = useCartStore()
const notificationStore = useNotificationStore()
const authStore = useAuthStore()

// 一、視窗開關。App 用 v-model 把自己的登入開關交給這個視窗，defineModel 把兩邊接起來。
// 例如關閉按鈕設 isOpen=false，App 的開關也會更新；App 要求登入時設 true，視窗就出現。
// type Boolean 表示只用 true／false；default false 表示沒提供值時先關閉。
// defineModel 是 Vue 在編譯 <script setup> 時提供的功能，不需要另外 import。
const isOpen = defineModel({ type: Boolean, default: false })

// 二、登入欄位。username 名稱雖然叫帳號，實際也可填 Email；password 是使用者原樣輸入的密碼。
// ref('') 是先保存空文字；script 讀寫要加 .value，上方輸入框的 v-model 則會自動幫忙處理。
const username = ref('')
const password = ref('')
// 整個表單共同的失敗訊息，例如帳密錯誤或後端無法連線；有文字時 template 的紅色 v-alert 才出現。
const loginError = ref('')
// 是否正在等登入／註冊結果。true 時顯示按鈕轉圈、停用欄位與關閉按鈕，也避免點外面關掉視窗。
// 這個值只記錄目前工作，不代表登入成功；結果確認後由 finally 改回 false。
const isSubmitting = ref(false)
// false 顯示登入表單，true 顯示註冊表單；這不是 Demo／Database 模式，也不是是否已註冊會員。
const isRegister = ref(false)
// false 時密碼用遮蔽符號顯示，true 時顯示原文字；眼睛按鈕只是切這個開關，不會改密碼內容。
const showPassword = ref(false)
// 保存每個註冊欄位的錯誤，例如 {email: 'Email 已使用'}。
// 它和 loginError 不同：這裡的訊息放在指定輸入框下方，loginError 放在整個視窗上方。
const fieldErrors = ref({})

// 三、計算是否顯示／允許註冊。computed 會隨讀到的會員狀態改變而重新計算。
// 下面用 &&：兩項都 true 才能註冊，不是其中一項符合就行。
const canRegister = computed(() => {

    // 條件 1：目前會員來源必須是 database。Demo 只提供既有測試會員登入，不新增正式會員。
    return authStore.source === 'database'

        // 條件 2：會員初始化必須 ready。還在載入或發生錯誤時，不能讓使用者開始註冊。
        && authStore.loadStatus === 'ready'
})

// 四、註冊的五個欄位放在同一個 registration 物件，reactive 讓 Vue 能追蹤其中每個欄位。
// 例如 registration.email 變了，畫面輸入與檢查都能讀新值；這種物件不需 registration.value。
// 這些只是目前表單輸入，尚未送後端，也不是資料庫中的會員紀錄。
const registration = reactive({
    // displayName 是畫面顯示名稱，username 才是登入用帳號；兩者可以不同。
    displayName: '',
    username: '',
    email: '',
    password: '',
    // 確認密碼只用來防止兩次打錯；送註冊時不會將這欄交給 PHP，也不存資料庫。
    confirmPassword: '',
})

// 五、輸入框的設定清單，不是使用者填的資料。
// template 用 v-for 逐項讀它，畫出五個輸入框；真實輸入則寫到 registration[field.key]。
// 例如 field.key 是 email 時，registration[field.key] 就是 registration.email。
// 每一項的 key、label、autocomplete、type／secret 有不同用途，下面逐項說明。
const registrationFields = [
    {
        // key 決定讀寫 registration 哪個欄位；label 是顯示給使用者的中文。
        // autocomplete 告訴瀏覽器這欄用途；nickname 表示顯示暱稱，type text 表示一般文字輸入。
        key: 'displayName',
        label: '顯示名稱',
        autocomplete: 'nickname',
        type: 'text',
    },
    {
        // 登入帳號欄位；autocomplete username 讓瀏覽器知道這是帳號，不是顯示名稱。
        key: 'username',
        label: '帳號',
        autocomplete: 'username',
        type: 'text',
    },
    {
        // Email 欄位；type email 可提供合適的輸入方式，例如手機 Email 鍵盤，但不保證地址真的有效。
        key: 'email',
        label: 'Email',
        autocomplete: 'email',
        type: 'email',
    },
    {
        // 註冊密碼；autocomplete new-password 提醒瀏覽器這是新密碼，不是讓它套目前登入密碼。
        key: 'password',
        label: '密碼',
        autocomplete: 'new-password',
        // 這個自訂設定告訴 template：要用密碼顯示方式、提供眼睛圖示；顯示或遮蔽取決於 showPassword。
        secret: true,
    },
    {
        // 確認密碼和密碼欄位一起使用 showPassword 開關，所以按眼睛後兩欄會同步顯示／遮蔽。
        key: 'confirmPassword',
        label: '確認密碼',
        autocomplete: 'new-password',
        secret: true,
    },
]

// 六、集中清密碼。切換表單、操作結束或關閉視窗時都需要，因此寫成可重複呼叫的函式。
// 只清密碼與顯示開關，不清帳號或顯示名稱；這些其他欄位是否清除由呼叫它的流程決定。
function clearPasswords() {

    // 先清登入密碼，再清註冊兩次密碼；最後恢復遮蔽，下一次操作不沿用明碼顯示。
    password.value = ''
    registration.password = ''
    registration.confirmPassword = ''
    showPassword.value = false
}

// 七、切換登入／註冊。按會員註冊時傳 true，按返回登入時傳 false。
// register 是這次切換的參數，isRegister 才是畫面目前選的表單；它們不是同一個名稱。
function switchForm(register) {

    // 如果上一個登入／註冊還在等結果，就停止這次切換，避免回覆到另一種表單上。
    if (isSubmitting.value) return

    // 只有「想切到註冊」且「現在不允許註冊」時拒絕。
    // register false 是返回登入，即使不開放註冊也應可以返回，所以不能只檢查 !canRegister。
    if (register && !canRegister.value) return

    // 通過兩關後才換表單，清密碼與舊錯誤。沒有關閉整個視窗，因此保留 authStore 裡原本要去的頁面。
    isRegister.value = register
    clearPasswords()
    loginError.value = ''
    fieldErrors.value = {}

}

// 八、註冊的前端檢查。依序檢查顯示名稱、帳號、Email、密碼與確認密碼，最後一次顯示全部錯誤。
// 這只是讓使用者早點知道格式問題；帳號是否重複及真正能不能新增，PHP 後端仍會再檢查。
// 通過回 true，不通過回 false；這裡不直接送出註冊、不修改會員身份。
function validateRegistration() {

    // 先建立本次的空錯誤物件。例如名稱不對就加 errors.displayName，Email 不對就加 errors.email。
    // 檢查到一項錯誤不立刻 return，因此使用者可一次看到多個需要修正的欄位。
    const errors = {}

    if (

        // 名稱條件 1：trim 去頭尾空白，! 檢查是否沒剩任何文字；只填空白也算沒填。
        // 這兩條用 ||，任一不合理就執行下方 errors.displayName，不是兩條都錯才提示。
        !registration.displayName.trim()

        // 名稱條件 2：[...] 按 Unicode code point 逐個展開，length 算數量；大於 100 就超出限制。
        // 一般中文一個字算一項；有些表情由多個編碼組合，計數不一定等於眼睛看到的圖案數。
        || [...registration.displayName.trim()].length > 100
    ) {
        errors.displayName = '顯示名稱須為 1～100 字元。'
    }

    // 帳號使用一組文字格式規則（正規表示式）：^ 從開頭、$ 到結尾，整段都要符合。
    // [A-Za-z0-9_-] 允許大小寫英文、數字、底線、連字號；{3,50} 要求長度 3～50。
    // .test(...) 符合回 true；前面的 ! 反過來，所以不符合才記錯誤。先 trim 帳號，但不會對密碼這樣做。
    if (!/^[A-Za-z0-9_-]{3,50}$/.test(registration.username.trim())) {
        errors.username =
            '帳號須為 3～50 字元，只能使用英文字母、數字、底線與連字號。'
    }

    if (

        // Email 條件 1：不能沒填或只填空白。下面三條用 ||，一條不合就提示 Email 格式有問題。
        !registration.email.trim()

        // Email 條件 2：先去頭尾空白，再以 UTF-8 計算實際資料大小，不得超過 254 bytes。
        // new TextEncoder() 建立文字編碼工具，encode() 得到位元組清單，length 才是 bytes；中文通常不只 1 byte。
        || new TextEncoder().encode(
            registration.email.trim()
        ).length > 254

        // Email 條件 3：檢查大致像 名稱@網域.後綴。[^\s@]+ 是至少一個非空白且非 @ 的字元；\. 是真的小數點符號。
        // 開頭 ^ 與結尾 $ 要求整段符合，! 表示格式不對才進錯誤；這不是寄信驗證，不能證明信箱存在。
        || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            registration.email.trim()
        )
    ) {
        errors.email = '請輸入有效的 Email。'
    }

    if (

        // 密碼條件 1：至少 8 個 Unicode code point，長度不足要提示；這裡不先 trim 密碼。
        // 密碼三條也是任一錯就失敗，不需要三條都錯。
        [...registration.password].length < 8

        // 密碼條件 2：UTF-8 的儲存大小不能超過 72 bytes，因目前後端使用的 bcrypt 有此限制。
        // 例：8 個中文字通常已占 24 bytes，所以「字數足夠」和「沒有超過 bytes」要分開查。
        || new TextEncoder().encode(registration.password).length > 72

        // 密碼條件 3：不能包含空白或特殊空字元。\s 查常見空白，\p{Z} 查 Unicode 分隔空白，\uFEFF／\u0000 查指定特殊字元。
        // 結尾 u 讓規則依 Unicode 處理；.test 找到其中任一字元就 true，代表要拒絕這個密碼。
        // 密碼有空白時直接提示，不先移除，因移除會把使用者輸入改成另一個密碼。
        || /[\s\p{Z}\uFEFF\u0000]/u.test(registration.password)
    ) {
        errors.password =
            '密碼至少 8 字元、最多 72 bytes，且不可包含空白。'
    }

    // 最後比兩次原始密碼是否完全一樣；!== 是不相同才記錯。
    // 就算主要密碼有其他格式錯誤，這裡仍可另顯示「兩次不一致」，讓使用者一次修正。
    if (registration.password !== registration.confirmPassword) {
        errors.confirmPassword = '兩次輸入的密碼不一致。'
    }

    // 把收集結果交給畫面。template 用 fieldErrors[field.key] 對到各欄下方，不是一份訊息放全部輸入框。
    fieldErrors.value = errors

    // Object.keys 取已記錄的錯誤欄位名，例如 [email,password]；長度 === 0 才表示沒有任何錯誤。
    // 回 false 會讓提交函式提早停止；函式本身不打開視窗或通知後端。
    return Object.keys(errors).length === 0
}

// 九、表單送出的兩個入口。async 表示這個函式可能需要等待；await 讓它等 submitMember 結束。
// 登入用 false、註冊用 true，讓同一個送出流程知道這次是哪種操作，不複製兩份相同錯誤處理。
async function handleLogin() {

    await submitMember(false)
}

async function handleRegister() {
    await submitMember(true)
}

// 十、共用提交主流程。register true 是註冊，false 是登入。
// 分成：阻擋重複 → 前端檢查 → 標忙碌 → 等結果 → 處理成功或失敗 → 一定清密碼並恢復按鈕。
async function submitMember(register) {

    // 函式內再次擋連按，不只依賴 template 的 disabled。return 立即結束，不送第二次請求。
    if (isSubmitting.value) return

    // 開始新一次提交前清舊錯誤；不清輸入文字，讓使用者剛修正的內容能拿去檢查。
    loginError.value = ''
    fieldErrors.value = {}

    // 分支一：這次是註冊。先檢查網站現在是否提供註冊，再跑上面五欄檢查。
    if (register) {
        // 就算按鈕因切模式或其他原因不該出現，函式內仍再次拒絕，不能只靠隱藏按鈕保護。
        if (!canRegister.value) {
            loginError.value = '目前模式不提供會員註冊。'
            return
        }

        // 先執行驗證，再用 ! 讀是否失敗；失敗時錯誤已放到 fieldErrors，因此直接停，不送 API。
        if (!validateRegistration()) return

    // 分支二：這次是登入。|| 只要帳號／Email 去空白後為空，或密碼本身是空文字，就提示先填完。
    // 登入不套註冊的新密碼最低 8 字規則，否則可能擋住匯入的舊測試會員。密碼也不 trim。
    } else if (!username.value.trim() || !password.value) {
        loginError.value = '請輸入帳號或 Email 與密碼'
        return
    }

    // 所有前端檢查通過才標為處理中。上方按鈕、欄位及關閉方式會跟著限制，直到 finally 恢復 false。
    isSubmitting.value = true

    try {

        // 三元寫法：register true 執行 ? 後的註冊，false 執行 : 後的登入，只會選一邊。
        // await 等 Store 完成；Store 再依模式決定用 JSON 比對或呼叫 PHP，視窗不自己發 SQL。
        const success = register
            ? await authStore.register({
                // 明確只送四個後端需要的註冊欄位：名稱、帳號、Email、密碼；不送確認密碼或表單 UI 狀態。
                displayName: registration.displayName,
                username: registration.username,
                email: registration.email,
                password: registration.password,
            })
            : await authStore.login(username.value, password.value)

        // Demo 登入可能正常回 false 代表帳密不合，而 Database 錯誤通常會 throw 直接跳 catch。
        // 這裡處理 false，顯示錯誤後 return；仍會執行 finally，但不會清購物車或顯示成功。
        if (!success) {
            loginError.value = '帳號、Email 或密碼錯誤'
            return
        }

        // 現在身份才確定。不同會員可能已買過車內商品，因此先移除這位會員已擁有的遊戲，不清掉其他還沒買的商品。
        cartStore.removePurchasedGames()

        // 關視窗前先記原目的地，例如之前點結帳、被要求登入時保存的 /checkout。
        // 必須先讀再 cancelLogin，因 cancelLogin 會把 Store 的目的地清成 null；本地 destination 仍保留讀出的文字。
        const destination = authStore.loginDestination

        authStore.cancelLogin()

        // 成功通知的字由 register 決定：註冊顯示已自動登入，登入則顯示登入成功；真正顯示由 App 的通知區負責。
        notificationStore.showMessage(
            register ? '註冊成功，已自動登入' : '登入成功'
        )

        // 導頁情況一：原本有要去的頁面就優先接續，不因這次是註冊就忽略原目的地。
        if (destination) {
            // 請 Router 換到原頁，await 等這次導覽結束；這不是重新整理網頁，也不自動提交購買。
            await router.push(destination)
        // 導頁情況二：沒原目的地且剛完成註冊，就進會員頁。
        // 若沒目的地又只是一般登入，兩個分支都不走，使用者留在原本頁面。
        } else if (register) {
            await router.push('/member')
        }

    // 失敗路徑：Store／Service 送出的錯誤會進這裡，例如帳號已用、CSRF 失效、後端連不上。
    // 不在失敗時切成 Demo，也不自動再送註冊或登入；顯示原因讓使用者確認後操作。
    } catch (error) {

        // || 先取有內容的 error.message，沒有才顯示右側通用提示；這個寫法是挑文字，不是驗兩項條件。
        loginError.value = error.message || '目前無法完成操作，請稍後再試。'

        // 後端若有個別欄位錯誤就放進輸入框下方；?? 只有 null／undefined 時才用空物件 {}，表示沒有欄位提示。
        fieldErrors.value = error.fields ?? {}

    // 不論成功、失敗，甚至 try 裡提早 return，都會執行這段收尾。
    // 先清三份密碼，再解除忙碌；只還原按鈕、不自行清會員身份，避免把已成功登入的人又登出。
    } finally {
        clearPasswords()
        isSubmitting.value = false
    }
}

// 十一、關閉視窗後的完整清理。watch 注意 isOpen 開關，改變時把新值傳給 open 這個參數。
// isOpen 是 Vue 追蹤的開關，open 是這次通知的 true／false，不需要 open.value。
watch(isOpen, open => {

    // 只有新值 false（關閉）才執行；打開時不清目的地，否則登入後不知道原本要回哪頁。
    if (!open) {
        // 關閉才清登入帳號，再呼叫 clearPasswords 清三份密碼與明碼開關。
        username.value = ''
        clearPasswords()

        // 將下面五個空值寫回原來的 registration；不建立與輸入框脫離的新表單物件。
        // 這次是關閉視窗，所以連名稱、帳號、Email 都清；和單純切表單只清密碼的行為不同。
        Object.assign(registration, {
            displayName: '',
            username: '',
            email: '',
            password: '',
            confirmPassword: '',
        })

        // 下次打開預設是登入，舊整體／欄位錯誤也清空；最後 cancelLogin 清原目的地，這輪操作正式結束。
        isRegister.value = false
        loginError.value = ''
        fieldErrors.value = {}
        authStore.cancelLogin()
    }
})
</script>

<style scoped lang="scss">
@use '../assets/styles/breakpoints' as bp;

.loginWrapper {
    border-radius: 500px;
}

:deep(.v-field__input::placeholder) {
    color: var(--color-muted);
    opacity: 0.8;
}

.login-card {
    padding: 16px;
    border-radius: 24px;

    max-height: calc(100dvh - 32px);
    overflow: hidden;

    :deep(.v-card-text) {
        min-height: 0;
        overflow-y: auto;
    }

    @include bp.mobile {
        padding: 8px;
        border-radius: 12px;

        :deep(.v-card-title),
        :deep(.v-card-text) {
            padding-inline: 12px;
        }
    }
}
</style>

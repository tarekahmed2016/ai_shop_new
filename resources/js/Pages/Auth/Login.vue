<script setup>
import { ref, computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faEnvelope, faLock, faSignInAlt, faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()
const page = usePage()

const companyName = computed(() =>
    resolveBilingualField(page.props.companyInfo, 'name', locale.value) || 'ai_shop_new'
)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

const handleLogin = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>
<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-md">
            <div class="glass-card p-6 sm:p-8 shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-hero text-white">
                        {{ t('auth.login.title') }}
                    </h1>
                </div>
                <div class="mb-6">
                    <p class="text-small text-lighter text-center mt-2">
                        {{ t('auth.login.subtitle', { company: companyName }) }}
                    </p>
                </div>
                <form @submit.prevent="handleLogin" class="space-y-4">
                    <div>
                        <label for="email" class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faEnvelope" class="me-2" />
                            {{ t('auth.login.emailLabel') }}
                        </label>
                        <input id="email" v-model="form.email" type="text"
                            class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 backdrop-blur-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            :class="{ 'border-red-500 focus:ring-red-500': form.errors.email }"
                            :placeholder="t('auth.login.emailPlaceholder')" required />
                        <p v-if="form.errors.email" class="text-red-400 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="password" class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faLock" class="me-2" />
                            {{ t('auth.login.passwordLabel') }}
                        </label>
                        <div class="relative">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'"
                                class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 backdrop-blur-sm text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                :class="{ 'border-red-500 focus:ring-red-500': form.errors.password }"
                                :placeholder="t('auth.login.passwordPlaceholder')" required />
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute top-1/2 cursor-pointer -translate-y-1/2 end-3 text-blue-400 hover:text-blue-300 transition-colors">
                                <font-awesome-icon :icon="showPassword ? faEyeSlash : faEye" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-400 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>
                    <button :disabled="form.processing"
                        class="w-full btn bg-blue-600 hover:bg-blue-700 text-white py-4 px-6 shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <font-awesome-icon :icon="faSignInAlt" class="me-2" />
                        {{ form.processing ? t('auth.login.loggingIn') : t('auth.login.loginButton') }}
                    </button>
                </form>
                <p class="text-center text-small text-lighter mt-6">
                    {{ t('auth.login.noAccount') }}
                    <Link :href="route('customer.register')" class="text-blue-400 hover:text-blue-300">{{ t('auth.login.registerLink') }}</Link>
                </p>
            </div>
        </div>
    </div>
</template>

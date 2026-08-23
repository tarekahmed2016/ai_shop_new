<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faEnvelope, faLock, faUser, faPhone, faUserPlus, faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons'

const { t } = useI18n()
const showPassword = ref(false)

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-md">
            <div class="glass-card p-6 sm:p-8 shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-hero text-white">{{ t('auth.register.title') }}</h1>
                    <p class="text-small text-lighter mt-2">{{ t('auth.register.subtitle') }}</p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faUser" class="me-2" />
                            {{ t('auth.register.name') }}
                        </label>
                        <input v-model="form.name" type="text" required class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faEnvelope" class="me-2" />
                            {{ t('auth.register.email') }}
                        </label>
                        <input v-model="form.email" type="email" required class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.email" class="text-red-400 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faPhone" class="me-2" />
                            {{ t('auth.register.phone') }}
                        </label>
                        <input v-model="form.phone" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.phone" class="text-red-400 text-sm mt-1">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">
                            <font-awesome-icon :icon="faLock" class="me-2" />
                            {{ t('auth.register.password') }}
                        </label>
                        <div class="relative">
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                            <button type="button" class="absolute end-3 top-1/2 -translate-y-1/2 text-blue-400" @click="showPassword = !showPassword">
                                <font-awesome-icon :icon="showPassword ? faEyeSlash : faEye" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-400 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">{{ t('auth.register.passwordConfirmation') }}</label>
                        <input v-model="form.password_confirmation" type="password" required class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full btn bg-blue-600 hover:bg-blue-700 text-white py-4 px-6 disabled:opacity-50">
                        <font-awesome-icon :icon="faUserPlus" class="me-2" />
                        {{ form.processing ? t('auth.register.submitting') : t('auth.register.submit') }}
                    </button>
                </form>
                <p class="text-center text-small text-lighter mt-6">
                    {{ t('auth.register.haveAccount') }}
                    <Link :href="route('login')" class="text-blue-400 hover:text-blue-300">{{ t('auth.register.login') }}</Link>
                </p>
            </div>
        </div>
    </div>
</template>

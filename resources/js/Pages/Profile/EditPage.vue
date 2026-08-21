<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()

const user = computed(() => page.props.user || {})
const merchantContext = computed(() => page.props.merchantContext || null)

const profileForm = useForm({
    name: user.value.name || '',
    email: user.value.email || '',
    phone: user.value.phone || '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const submitProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
    })
}

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('profile.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('profile.pageSubtitle') }}</p>
            </div>

            <div v-if="merchantContext" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100 mb-3">{{ t('profile.contextTitle') }}</h2>
                <p class="text-body text-gray-700 dark:text-gray-300">
                    {{ t('profile.currentMerchant') }}: {{ merchantContext.name }}
                </p>
                <p class="text-body text-gray-700 dark:text-gray-300 mt-1">
                    {{ t('profile.merchantRole') }}: {{ merchantContext.role }}
                </p>
                <p class="text-muted muted-color mt-2">{{ t('profile.contextHint') }}</p>
            </div>

            <form class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4" @submit.prevent="submitProfile">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('profile.accountTitle') }}</h2>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.nameLabel') }}</label>
                    <input v-model="profileForm.name" type="text"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.emailLabel') }}</label>
                    <input v-model="profileForm.email" type="email"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.phoneLabel') }}</label>
                    <input v-model="profileForm.phone" type="text"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                    <p v-if="profileForm.errors.phone" class="mt-1 text-sm text-red-600">{{ profileForm.errors.phone }}</p>
                </div>

                <div>
                    <p class="text-label text-gray-700 dark:text-gray-300">{{ t('profile.statusLabel') }}</p>
                    <p class="mt-1 text-body text-gray-800 dark:text-gray-200">{{ user.status_formatted?.label || '—' }}</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary px-4 py-2" :disabled="profileForm.processing">
                        {{ profileForm.processing ? t('profile.saving') : t('profile.save') }}
                    </button>
                </div>
            </form>

            <form class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4" @submit.prevent="submitPassword">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('profile.passwordTitle') }}</h2>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.currentPasswordLabel') }}</label>
                    <input v-model="passwordForm.current_password" type="password" autocomplete="current-password"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.newPasswordLabel') }}</label>
                    <input v-model="passwordForm.password" type="password" autocomplete="new-password"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-label text-gray-700 dark:text-gray-300 mb-1">{{ t('profile.confirmPasswordLabel') }}</label>
                    <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password"
                        class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-body text-gray-900 dark:text-gray-100" />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary px-4 py-2" :disabled="passwordForm.processing">
                        {{ passwordForm.processing ? t('profile.updatingPassword') : t('profile.updatePassword') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

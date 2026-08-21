<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const user = page.props.user || {}

const form = useForm({
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const submitProfile = () => {
    form.patch(route('customer.profile.update'))
}

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        onSuccess: () => passwordForm.reset(),
    })
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.profile.title') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customerPortal.profile.subtitle') }}</p>
            </div>

            <form @submit.prevent="submitProfile" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.register.name') }}</label>
                    <input v-model="form.name" type="text" required class="form-input text-body" />
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.register.email') }}</label>
                    <input v-model="form.email" type="email" required class="form-input text-body" />
                    <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.register.phone') }}</label>
                    <input v-model="form.phone" type="text" class="form-input text-body" />
                    <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
                        {{ form.processing ? t('customerPortal.profile.saving') : t('customerPortal.profile.save') }}
                    </button>
                </div>
            </form>

            <form @submit.prevent="submitPassword" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.profile.passwordTitle') }}</h2>
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.profile.currentPassword') }}</label>
                    <input v-model="passwordForm.current_password" type="password" required class="form-input text-body" autocomplete="current-password" />
                    <p v-if="passwordForm.errors.current_password" class="form-error">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.profile.newPassword') }}</label>
                    <input v-model="passwordForm.password" type="password" required class="form-input text-body" autocomplete="new-password" />
                    <p v-if="passwordForm.errors.password" class="form-error">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label class="form-label text-label">{{ t('customerPortal.profile.confirmPassword') }}</label>
                    <input v-model="passwordForm.password_confirmation" type="password" required class="form-input text-body" autocomplete="new-password" />
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="passwordForm.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
                        {{ passwordForm.processing ? t('customerPortal.profile.saving') : t('customerPortal.profile.updatePassword') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

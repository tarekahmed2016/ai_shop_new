<script setup>
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const form = useForm({})

const submit = () => {
    form.post(route('account.customer.enable.store'))
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-md">
            <div class="glass-card p-6 sm:p-8 shadow-xl">
                <h1 class="text-hero text-white text-center mb-4">
                    {{ t('account.customerEnable.title') }}
                </h1>
                <p class="text-small text-lighter text-center mb-6">
                    {{ t('account.customerEnable.subtitle') }}
                </p>
                <button
                    type="button"
                    class="w-full btn-primary"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? t('account.customerEnable.submitting') : t('account.customerEnable.submit') }}
                </button>
            </div>
        </div>
    </div>
</template>

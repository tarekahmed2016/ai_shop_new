<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const form = useForm({})

const marketer = computed(() => page.props.marketer || null)
const status = computed(() => marketer.value?.status || null)

const submit = () => {
    if (status.value === 'Rejected') {
        form.post(route('marketer.application.reapply'))
        return
    }

    form.post(route('marketer.application.store'))
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-lg">
            <div class="glass-card p-6 sm:p-8 shadow-xl">
                <h1 class="text-hero text-white text-center mb-4">
                    {{ t('account.marketerApply.title') }}
                </h1>
                <p class="text-small text-lighter text-center mb-4">
                    {{ t('account.marketerApply.subtitle') }}
                </p>
                <ul class="text-small text-lighter space-y-2 mb-6 list-disc ps-5">
                    <li>{{ t('account.marketerApply.benefitLink') }}</li>
                    <li>{{ t('account.marketerApply.benefitInvite') }}</li>
                    <li>{{ t('account.marketerApply.benefitTrack') }}</li>
                    <li>{{ t('account.marketerApply.benefitLater') }}</li>
                </ul>

                <p v-if="status === 'Pending'" class="text-sm text-amber-300 text-center mb-4">
                    {{ t('account.marketerApply.pending') }}
                </p>
                <p v-else-if="status === 'Rejected'" class="text-sm text-rose-300 text-center mb-4">
                    {{ t('account.marketerApply.rejected') }}
                </p>
                <p v-else-if="status === 'Inactive'" class="text-sm text-amber-300 text-center mb-4">
                    {{ t('account.marketerApply.inactive') }}
                </p>

                <button
                    v-if="!status || status === 'Rejected'"
                    type="button"
                    class="w-full btn-primary"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ status === 'Rejected' ? t('account.marketerApply.reapply') : t('account.marketerApply.submit') }}
                </button>

                <Link
                    v-else
                    :href="route('marketer.application.status')"
                    class="block w-full btn-primary text-center"
                >
                    {{ t('account.marketerApply.viewStatus') }}
                </Link>
            </div>
        </div>
    </div>
</template>

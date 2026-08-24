<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const form = useForm({})
const marketer = computed(() => page.props.marketer || {})
const status = computed(() => marketer.value.status)

const reapply = () => {
    form.post(route('marketer.application.reapply'))
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-lg">
            <div class="glass-card p-6 sm:p-8 shadow-xl text-center">
                <h1 class="text-hero text-white mb-4">
                    {{ t('account.marketerStatus.title') }}
                </h1>
                <p v-if="status === 'Pending'" class="text-small text-lighter mb-6">
                    {{ t('account.marketerStatus.pending') }}
                </p>
                <p v-else-if="status === 'Rejected'" class="text-small text-lighter mb-6">
                    {{ t('account.marketerStatus.rejected') }}
                </p>
                <p v-else-if="status === 'Inactive'" class="text-small text-lighter mb-6">
                    {{ t('account.marketerStatus.inactive') }}
                </p>
                <button
                    v-if="status === 'Rejected'"
                    type="button"
                    class="w-full btn-primary"
                    :disabled="form.processing"
                    @click="reapply"
                >
                    {{ t('account.marketerApply.reapply') }}
                </button>
                <Link
                    v-else
                    :href="route('account.get-started')"
                    class="text-blue-400 hover:text-blue-300"
                >
                    {{ t('account.getStarted.title') }}
                </Link>
            </div>
        </div>
    </div>
</template>

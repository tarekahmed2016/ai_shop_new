<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const metrics = computed(() => page.props.metrics || {})
const copied = ref('')

const copy = async (value, key) => {
    try {
        await navigator.clipboard.writeText(value)
        copied.value = key
        setTimeout(() => {
            copied.value = ''
        }, 1500)
    } catch {
        copied.value = ''
    }
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('marketerPortal.home.title') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('marketerPortal.home.subtitle') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.total') }}</p>
                    <p class="text-2xl font-semibold">{{ metrics.total_referred_users ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.customers') }}</p>
                    <p class="text-2xl font-semibold">{{ metrics.customers ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.merchants') }}</p>
                    <p class="text-2xl font-semibold">{{ metrics.merchants ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.dual') }}</p>
                    <p class="text-2xl font-semibold">{{ metrics.dual ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.thisMonth') }}</p>
                    <p class="text-2xl font-semibold">{{ metrics.registrations_this_month ?? 0 }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.totalPayments') }}</p>
                    <p class="text-2xl font-semibold">{{ page.props.financeSummary?.referral_payments ?? page.props.paymentSummary?.total_amount ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.commissionEarned') }}</p>
                    <p class="text-2xl font-semibold">{{ page.props.financeSummary?.approved_commission ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.paidToYou') }}</p>
                    <p class="text-2xl font-semibold">{{ page.props.financeSummary?.paid ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.outstanding') }}</p>
                    <p class="text-2xl font-semibold">{{ page.props.financeSummary?.outstanding ?? '0.000' }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-3">
                <h2 class="text-card-title">{{ t('marketerPortal.home.referralCard') }}</h2>
                <p class="font-mono text-lg">{{ metrics.referral_code }}</p>
                <p class="text-sm break-all">{{ metrics.referral_url }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary" @click="copy(metrics.referral_code, 'code')">
                        {{ copied === 'code' ? t('marketerPortal.home.copied') : t('marketerPortal.home.copyCode') }}
                    </button>
                    <button type="button" class="btn btn-secondary" @click="copy(metrics.referral_url, 'url')">
                        {{ copied === 'url' ? t('marketerPortal.home.copied') : t('marketerPortal.home.copyLink') }}
                    </button>
                    <Link :href="route('marketer.referrals')" class="btn btn-primary">
                        {{ t('marketerPortal.home.viewReferrals') }}
                    </Link>
                    <Link :href="route('marketer.payments')" class="btn btn-secondary">
                        {{ t('marketerPortal.home.viewPayments') }}
                    </Link>
                    <Link :href="route('marketer.commissions')" class="btn btn-secondary">
                        {{ t('marketerPortal.home.viewCommissions') }}
                    </Link>
                    <Link :href="route('marketer.payouts')" class="btn btn-secondary">
                        {{ t('marketerPortal.home.viewPayouts') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CustomerPushStatusCard from '../../Components/Features/CustomerPortal/CustomerPushStatusCard.vue'

const { t } = useI18n()
const page = usePage()
const customer = computed(() => page.props.customer || {})
const stats = computed(() => page.props.stats || {})
const recentRequests = computed(() => page.props.recentRequests || [])
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.home.title') }}</h1>
                    <p class="mt-2 text-muted muted-color">{{ t('customerPortal.home.subtitle', { name: customer.name }) }}</p>
                </div>
                <Link
                    v-if="!page.props.customerContext?.is_suspended && (page.props.requestQuota?.remaining ?? page.props.customerContext?.request_quota?.remaining ?? 1) > 0"
                    :href="route('customer.requests.create')"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md text-white bg-blue-600 hover:bg-blue-700"
                >
                    {{ t('customerPortal.home.createRequest') }}
                </Link>
            </div>

            <CustomerPushStatusCard />

            <div v-if="page.props.customerContext?.is_suspended" class="rounded-md border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-4">
                <p class="text-body text-red-800 dark:text-red-200">{{ t('customerPortal.suspended.message') }}</p>
            </div>

            <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-body text-gray-900 dark:text-gray-100">
                    {{ t('customerPortal.quota.today', {
                        used: page.props.requestQuota?.used ?? page.props.customerContext?.request_quota?.used ?? 0,
                        limit: page.props.requestQuota?.daily_limit ?? page.props.customerContext?.request_quota?.daily_limit ?? 0,
                        remaining: page.props.requestQuota?.remaining ?? page.props.customerContext?.request_quota?.remaining ?? 0,
                    }) }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('customerPortal.home.total') }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.total ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('customerPortal.home.open') }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.new_or_open ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('customerPortal.home.ready') }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.ready ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('customerPortal.home.closed') }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.closed ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.home.recent') }}</h2>
                    <Link :href="route('customer.requests.index')" class="text-blue-600 text-sm">{{ t('customerPortal.home.viewAll') }}</Link>
                </div>
                <div v-if="recentRequests.length === 0" class="p-6 text-muted">{{ t('customerPortal.requests.empty') }}</div>
                <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="item in recentRequests" :key="item.id" class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <p class="text-body text-gray-900 dark:text-gray-100">{{ item.request_text?.slice(0, 120) }}</p>
                            <p class="text-muted text-sm mt-1">{{ item.status_formatted?.label }} · {{ item.category?.name_en || item.category?.name_ar || '—' }}</p>
                        </div>
                        <Link :href="route('customer.requests.show', item.public_id)" class="text-blue-600 text-sm">{{ t('customerPortal.requests.view') }}</Link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

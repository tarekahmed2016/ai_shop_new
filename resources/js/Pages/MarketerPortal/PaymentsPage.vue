<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.payments || {})
const payments = computed(() => paginationData.value.data || [])
const summary = computed(() => page.props.summary || {})

const { isPaginating } = useTableFilters('marketer.payments', 'payments')

const typeLabel = (row) => {
  const name = row.type?.name
  if (!name) return row.type?.label || '—'
  return t(`marketerPortal.payments.types.${name}`)
}

const capabilityLabel = (row) => {
  const name = row.capability_name || 'Other'
  return t(`marketerPortal.payments.capabilities.${name}`)
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-page-title text-gray-900 dark:text-gray-100 mb-2">{{ t('marketerPortal.payments.title') }}</h1>
            <p class="text-muted muted-color mb-6">{{ t('marketerPortal.payments.subtitle') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.totalPayments') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.total_amount ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.monthPayments') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.month_amount ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.payingUsers') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.paying_users ?? 0 }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="table-header-cell">{{ t('marketerPortal.payments.date') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.payments.user') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.payments.type') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.payments.capability') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.payments.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in payments" :key="row.public_id" class="table-row">
                            <td class="table-cell">{{ String(row.paid_at || '').replace('T', ' ').slice(0, 16) }}</td>
                            <td class="table-cell">{{ row.payer_name }}</td>
                            <td class="table-cell">{{ typeLabel(row) }}</td>
                            <td class="table-cell">{{ capabilityLabel(row) }}</td>
                            <td class="table-cell">{{ row.amount }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="payments.length === 0" class="p-6 text-muted">{{ t('marketerPortal.payments.empty') }}</p>
                <Pagination :paginationData="paginationData" routeName="marketer.payments" />
            </div>
        </div>
    </div>
</template>

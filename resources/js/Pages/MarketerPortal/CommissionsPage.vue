<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.commissions || {})
const commissions = computed(() => paginationData.value.data || [])
const summary = computed(() => page.props.summary || {})

const { isPaginating } = useTableFilters('marketer.commissions', 'commissions')

const typeLabel = (row) => {
  const name = row.payment_type?.name
  if (!name) return row.payment_type?.label || '—'
  return t(`marketerPortal.payments.types.${name}`)
}

const statusLabel = (row) => {
  const name = row.status?.name
  if (!name) return row.status?.label || '—'
  return t(`marketerPortal.commissions.statuses.${name}`)
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-page-title text-gray-900 dark:text-gray-100 mb-2">{{ t('marketerPortal.commissions.title') }}</h1>
            <p class="text-muted muted-color mb-6">{{ t('marketerPortal.commissions.subtitle') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.commissionEarned') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.approved_commission ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.paidToYou') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.paid ?? '0.000' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <p class="text-muted text-sm">{{ t('marketerPortal.home.outstanding') }}</p>
                    <p class="text-2xl font-semibold">{{ summary.outstanding ?? '0.000' }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.date') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.user') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.type') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.paymentAmount') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.rate') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.amount') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.commissions.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in commissions" :key="row.public_id" class="table-row">
                            <td class="table-cell">{{ String(row.earned_at || '').replace('T', ' ').slice(0, 16) }}</td>
                            <td class="table-cell">{{ row.referred_user_name }}</td>
                            <td class="table-cell">{{ typeLabel(row) }}</td>
                            <td class="table-cell">{{ row.payment_amount }}</td>
                            <td class="table-cell">{{ row.commission_rate }}%</td>
                            <td class="table-cell">{{ row.commission_amount }}</td>
                            <td class="table-cell">{{ statusLabel(row) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="commissions.length === 0" class="p-6 text-muted">{{ t('marketerPortal.commissions.empty') }}</p>
                <Pagination :paginationData="paginationData" routeName="marketer.commissions" />
            </div>
        </div>
    </div>
</template>

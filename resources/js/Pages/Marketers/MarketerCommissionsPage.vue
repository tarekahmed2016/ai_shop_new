<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const marketer = computed(() => page.props.marketer || {})
const paginationData = computed(() => page.props.commissions || {})
const commissions = computed(() => paginationData.value.data || [])
const summary = computed(() => page.props.summary || {})

const typeLabel = (row) => row.payment_type?.label || row.payment_type?.name || '—'
const statusLabel = (row) => row.status?.label || row.status?.name || '—'
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 flex items-center justify-between gap-3">
        <div>
          <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('marketerFinance.viewCommissions') }}</h1>
          <p class="mt-2 text-muted muted-color">{{ marketer.name }}</p>
        </div>
        <Link :href="route('marketers.show', marketer.public_id)" class="btn btn-secondary">{{ t('marketerFinance.back') }}</Link>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.approvedCommission') }}</p>
          <p class="text-xl font-semibold">{{ summary.approved_commission }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.pendingCommission') }}</p>
          <p class="text-xl font-semibold">{{ summary.pending_commission }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.outstanding') }}</p>
          <p class="text-xl font-semibold">{{ summary.outstanding }}</p>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
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
        <Pagination :paginationData="paginationData" routeName="marketers.commissions" :routeParams="{ marketer: marketer.public_id }" />
      </div>
    </div>
  </div>
</template>

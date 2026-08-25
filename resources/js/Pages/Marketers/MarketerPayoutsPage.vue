<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const marketer = computed(() => page.props.marketer || {})
const paginationData = computed(() => page.props.payouts || {})
const payouts = computed(() => paginationData.value.data || [])
const summary = computed(() => page.props.summary || {})
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 flex items-center justify-between gap-3">
        <div>
          <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('marketerFinance.viewPayouts') }}</h1>
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
          <p class="text-muted text-sm">{{ t('marketerFinance.totalPaid') }}</p>
          <p class="text-xl font-semibold">{{ summary.paid }}</p>
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
              <th class="table-header-cell">{{ t('marketerPortal.payouts.date') }}</th>
              <th class="table-header-cell">{{ t('marketerPortal.payouts.amount') }}</th>
              <th class="table-header-cell">{{ t('marketerPortal.payouts.method') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.reference') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.notes') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in payouts" :key="row.public_id" class="table-row">
              <td class="table-cell">{{ String(row.paid_at || '').replace('T', ' ').slice(0, 16) }}</td>
              <td class="table-cell">{{ row.amount }}</td>
              <td class="table-cell">{{ row.payment_method?.label }}</td>
              <td class="table-cell">{{ row.reference || '—' }}</td>
              <td class="table-cell">{{ row.notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <Pagination :paginationData="paginationData" routeName="marketers.payouts" :routeParams="{ marketer: marketer.public_id }" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.marketers || {})
const marketers = computed(() => paginationData.value.data || [])
const rates = computed(() => page.props.rates || {})

const settingsForm = useForm({
  customer_commission_rate: rates.value.customer_extra_requests ?? '10.000',
  merchant_commission_rate: rates.value.merchant_offer_credits ?? '20.000',
  notes: '',
})

const submitSettings = () => {
  settingsForm.put(route('marketer-commissions.settings'), { preserveScroll: true })
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
      <div>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('marketerFinance.globalTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('marketerFinance.globalSubtitle') }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <h2 class="text-card-title mb-4">{{ t('marketerFinance.settingsTitle') }}</h2>
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3 md:items-end" @submit.prevent="submitSettings">
          <div>
            <label class="form-label text-label">{{ t('marketerFinance.customerRate') }}</label>
            <input v-model="settingsForm.customer_commission_rate" type="number" step="0.001" min="0" max="100" class="form-input w-full" />
            <p v-if="settingsForm.errors.customer_commission_rate" class="text-red-600 text-sm mt-1">{{ settingsForm.errors.customer_commission_rate }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('marketerFinance.merchantRate') }}</label>
            <input v-model="settingsForm.merchant_commission_rate" type="number" step="0.001" min="0" max="100" class="form-input w-full" />
            <p v-if="settingsForm.errors.merchant_commission_rate" class="text-red-600 text-sm mt-1">{{ settingsForm.errors.merchant_commission_rate }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('marketerFinance.notes') }}</label>
            <input v-model="settingsForm.notes" type="text" class="form-input w-full" />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="settingsForm.processing">{{ t('marketerFinance.saveRates') }}</button>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-800">
              <th class="table-header-cell">{{ t('marketers.table.name') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.referralPayments') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.approvedCommission') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.pendingCommission') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.totalPaid') }}</th>
              <th class="table-header-cell">{{ t('marketerFinance.outstanding') }}</th>
              <th class="table-header-cell">{{ t('marketers.table.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in marketers" :key="row.public_id" class="table-row">
              <td class="table-cell">{{ row.name }}</td>
              <td class="table-cell">{{ row.referral_payments }}</td>
              <td class="table-cell">{{ row.approved_commission }}</td>
              <td class="table-cell">{{ row.pending_commission }}</td>
              <td class="table-cell">{{ row.paid }}</td>
              <td class="table-cell">{{ row.outstanding }}</td>
              <td class="table-cell">
                <Link :href="route('marketers.show', row.public_id)" class="text-blue-600">{{ t('marketerFinance.details') }}</Link>
              </td>
            </tr>
          </tbody>
        </table>
        <Pagination :paginationData="paginationData" routeName="marketer-commissions.index" />
      </div>
    </div>
  </div>
</template>

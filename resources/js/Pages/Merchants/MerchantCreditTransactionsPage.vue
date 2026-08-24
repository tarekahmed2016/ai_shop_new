<script setup>
import { computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.transactions || {})
const transactions = computed(() => paginationData.value.data || [])
const merchants = computed(() => page.props.merchants || [])
const filterSources = computed(() => page.props.filterSources || [])
const filterTypes = computed(() => page.props.filterTypes || [])
const summary = computed(() => page.props.summary || {})
const currentFilters = computed(() => page.props.filters || {})

const filters = reactive({
  merchant: currentFilters.value.merchant ?? '',
  type: currentFilters.value.type ?? '',
  source: currentFilters.value.source ?? '',
  date_from: currentFilters.value.date_from ?? '',
  date_to: currentFilters.value.date_to ?? '',
  paid_only: currentFilters.value.paid_only === true,
})

const filterQuery = computed(() => {
  const query = {}
  if (filters.merchant) query.merchant = filters.merchant
  if (filters.type !== '' && filters.type !== null) query.type = filters.type
  if (filters.source !== '' && filters.source !== null) query.source = filters.source
  if (filters.date_from) query.date_from = filters.date_from
  if (filters.date_to) query.date_to = filters.date_to
  if (filters.paid_only) query.paid_only = 1
  return query
})

const applyFilters = () => {
  router.get(route('merchants.credits.transactions'), filterQuery.value, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  filters.merchant = ''
  filters.type = ''
  filters.source = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.paid_only = false
  router.get(route('merchants.credits.transactions'), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

const formatAmount = (amount) => {
  const value = Number(amount) || 0
  return value > 0 ? `+${value}` : String(value)
}

const openMerchantCredits = (merchant) => {
  if (!merchant?.public_id) {
    return
  }

  router.visit(route('merchants.credits.index', merchant.public_id))
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchants.credits.globalPageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('merchants.credits.globalPageSubtitle') }}</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 md:mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <p class="text-muted muted-color">{{ t('merchants.credits.summary.totalPaid') }}</p>
          <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ summary.total_paid_amount ?? '0.000' }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <p class="text-muted muted-color">{{ t('merchants.credits.summary.creditsAdded') }}</p>
          <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ summary.credits_added ?? 0 }}</p>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
        <form class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 md:items-end" @submit.prevent="applyFilters">
          <div>
            <label class="form-label text-label">{{ t('merchants.credits.filters.merchant') }}</label>
            <select v-model="filters.merchant" class="form-input text-body">
              <option value="">{{ t('merchants.credits.filters.allMerchants') }}</option>
              <option v-for="option in merchants" :key="option.public_id" :value="option.public_id">{{ option.name }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchants.credits.filters.type') }}</label>
            <select v-model="filters.type" class="form-input text-body">
              <option value="">{{ t('merchants.credits.filters.allTypes') }}</option>
              <option v-for="option in filterTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchants.credits.filters.source') }}</label>
            <select v-model="filters.source" class="form-input text-body">
              <option value="">{{ t('merchants.credits.filters.allSources') }}</option>
              <option v-for="option in filterSources" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchants.credits.filters.dateFrom') }}</label>
            <input v-model="filters.date_from" type="date" class="form-input text-body" />
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchants.credits.filters.dateTo') }}</label>
            <input v-model="filters.date_to" type="date" class="form-input text-body" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="inline-flex items-center gap-2 text-body">
              <input v-model="filters.paid_only" type="checkbox" class="rounded border-gray-300" />
              {{ t('merchants.credits.filters.paidOnly') }}
            </label>
            <div class="flex gap-2">
              <button type="submit" class="btn btn-primary px-4 py-2">{{ t('merchants.credits.filters.apply') }}</button>
              <button type="button" class="btn btn-secondary px-4 py-2" @click="resetFilters">{{ t('merchants.credits.filters.reset') }}</button>
            </div>
          </div>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.date') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.merchant') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.type') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.offerCredits') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.paidAmount') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.balanceAfter') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.source') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.reference') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.notes') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.performedBy') }}</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in transactions" :key="row.id" class="table-row">
                <td class="table-cell table-cell-secondary text-body">{{ String(row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                <td class="table-cell table-cell-secondary text-body">
                  <button
                    v-if="row.merchant?.public_id"
                    type="button"
                    class="text-blue-600 hover:underline cursor-pointer"
                    @click="openMerchantCredits(row.merchant)"
                  >
                    {{ row.merchant.name }}
                  </button>
                  <span v-else>—</span>
                </td>
                <td class="table-cell table-cell-secondary text-body">{{ row.type_formatted?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ formatAmount(row.amount) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.paid_amount || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.balance_after ?? '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.source_formatted?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.reference || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.notes || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">
                  {{ row.created_by?.name || row.created_by?.email || '—' }}
                </td>
              </tr>
              <tr v-if="transactions.length === 0">
                <td colspan="10" class="table-cell text-center text-muted py-8">{{ t('merchants.credits.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination
          :paginationData="paginationData"
          routeName="merchants.credits.transactions"
          :query="filterQuery"
        />
      </div>
    </div>
  </div>
</template>

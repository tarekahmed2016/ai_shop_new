<script setup>
import { computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.payments || {})
const payments = computed(() => paginationData.value.data || [])
const types = computed(() => page.props.types || [])
const methods = computed(() => page.props.methods || [])
const statuses = computed(() => page.props.statuses || [])
const currentFilters = computed(() => page.props.filters || {})

const filters = reactive({
  payer: currentFilters.value.payer ?? '',
  type: currentFilters.value.type ?? '',
  method: currentFilters.value.method ?? '',
  status: currentFilters.value.status ?? '',
  date_from: currentFilters.value.date_from ?? '',
  date_to: currentFilters.value.date_to ?? '',
})

const filterQuery = computed(() => {
  const query = {}
  if (filters.payer) query.payer = filters.payer
  if (filters.type !== '' && filters.type !== null) query.type = filters.type
  if (filters.method !== '' && filters.method !== null) query.method = filters.method
  if (filters.status !== '' && filters.status !== null) query.status = filters.status
  if (filters.date_from) query.date_from = filters.date_from
  if (filters.date_to) query.date_to = filters.date_to
  return query
})

const applyFilters = () => {
  router.get(route('payments.index'), filterQuery.value, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  filters.payer = ''
  filters.type = ''
  filters.method = ''
  filters.status = ''
  filters.date_from = ''
  filters.date_to = ''
  router.get(route('payments.index'), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

const contextLabel = (row) => {
  if (row.customer?.name) return row.customer.name
  if (row.merchant?.name) return row.merchant.name
  return '—'
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('payments.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('payments.pageSubtitle') }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
        <form class="grid grid-cols-1 md:grid-cols-6 gap-3 md:items-end" @submit.prevent="applyFilters">
          <div>
            <label class="form-label text-label">{{ t('payments.filters.payer') }}</label>
            <input v-model="filters.payer" type="text" class="form-input text-body" />
          </div>
          <div>
            <label class="form-label text-label">{{ t('payments.filters.type') }}</label>
            <select v-model="filters.type" class="form-input text-body">
              <option value="">{{ t('payments.filters.allTypes') }}</option>
              <option v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('payments.filters.method') }}</label>
            <select v-model="filters.method" class="form-input text-body">
              <option value="">{{ t('payments.filters.allMethods') }}</option>
              <option v-for="option in methods" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('payments.filters.status') }}</label>
            <select v-model="filters.status" class="form-input text-body">
              <option value="">{{ t('payments.filters.allStatuses') }}</option>
              <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </div>
          <div>
            <label class="form-label text-label">{{ t('payments.filters.dateFrom') }}</label>
            <input v-model="filters.date_from" type="date" class="form-input text-body" />
          </div>
          <div>
            <label class="form-label text-label">{{ t('payments.filters.dateTo') }}</label>
            <input v-model="filters.date_to" type="date" class="form-input text-body" />
          </div>
          <div class="md:col-span-6 flex gap-2">
            <button type="submit" class="btn btn-primary px-4 py-2">{{ t('payments.filters.apply') }}</button>
            <button type="button" class="btn btn-secondary px-4 py-2" @click="resetFilters">{{ t('payments.filters.reset') }}</button>
          </div>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <th class="table-header-cell text-table-header">{{ t('payments.table.date') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.payer') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.type') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.context') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.amount') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.method') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.reference') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.createdBy') }}</th>
                <th class="table-header-cell text-table-header">{{ t('payments.table.status') }}</th>
                <th class="table-header-cell text-table-header"></th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in payments" :key="row.public_id" class="table-row">
                <td class="table-cell table-cell-secondary text-body">{{ String(row.paid_at || row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.payer?.name || row.payer?.email || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.type?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ contextLabel(row) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.amount || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.payment_method?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.reference || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.created_by?.name || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.status?.label || '—' }}</td>
                <td class="table-cell table-cell-actions">
                  <button type="button" class="btn btn-secondary" @click="router.visit(route('payments.show', row.public_id))">
                    {{ t('payments.details') }}
                  </button>
                </td>
              </tr>
              <tr v-if="payments.length === 0">
                <td colspan="10" class="table-cell text-center text-muted py-8">{{ t('payments.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination :paginationData="paginationData" routeName="payments.index" :query="filterQuery" />
      </div>
    </div>
  </div>
</template>

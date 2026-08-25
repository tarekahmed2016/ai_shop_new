<script setup>
import { computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CustomerExtraRequestFormModal from '../../Components/Features/Customers/CustomerExtraRequestFormModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()
const customer = computed(() => page.props.customer || {})
const balance = computed(() => page.props.balance ?? 0)
const paginationData = computed(() => page.props.transactions || {})
const transactions = computed(() => paginationData.value.data || [])
const sources = computed(() => page.props.sources || [])
const filterSources = computed(() => page.props.filterSources || [])
const filterTypes = computed(() => page.props.filterTypes || [])
const currentFilters = computed(() => page.props.filters || {})

const filters = reactive({
  type: currentFilters.value.type ?? '',
  source: currentFilters.value.source ?? '',
  date_from: currentFilters.value.date_from ?? '',
  date_to: currentFilters.value.date_to ?? '',
})

const filterQuery = computed(() => {
  const query = {}
  if (filters.type !== '' && filters.type !== null) query.type = filters.type
  if (filters.source !== '' && filters.source !== null) query.source = filters.source
  if (filters.date_from) query.date_from = filters.date_from
  if (filters.date_to) query.date_to = filters.date_to
  return query
})

const applyFilters = () => {
  router.get(route('customers.extra-requests.index', customer.value.public_id), filterQuery.value, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  filters.type = ''
  filters.source = ''
  filters.date_from = ''
  filters.date_to = ''
  router.get(route('customers.extra-requests.index', customer.value.public_id), {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

const addModal = useModal()
const deductModal = useModal()

const formatAmount = (amount) => {
  const value = Number(amount) || 0
  return value > 0 ? `+${value}` : String(value)
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="router.visit(route('customers.index'))">
          ← {{ t('customers.extraRequests.backToCustomers') }}
        </button>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customers.extraRequests.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('customers.extraRequests.pageSubtitle', { name: customer.name }) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-6">
        <p class="text-muted muted-color">{{ t('customers.extraRequests.balanceLabel') }}</p>
        <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ balance }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
          <button type="button" class="btn btn-primary px-4 py-2" @click="addModal.open()">
            {{ t('customers.extraRequests.addButton') }}
          </button>
          <button type="button" class="btn btn-secondary px-4 py-2" @click="deductModal.open()">
            {{ t('customers.extraRequests.deductButton') }}
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
        <form class="grid grid-cols-1 md:grid-cols-5 gap-3 md:items-end" @submit.prevent="applyFilters">
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
          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary px-4 py-2">{{ t('merchants.credits.filters.apply') }}</button>
            <button type="button" class="btn btn-secondary px-4 py-2" @click="resetFilters">{{ t('merchants.credits.filters.reset') }}</button>
          </div>
        </form>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.date') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.type') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.amount') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.source') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.reference') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.notes') }}</th>
                <th class="table-header-cell text-table-header">{{ t('merchants.credits.table.performedBy') }}</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in transactions" :key="row.id" class="table-row">
                <td class="table-cell table-cell-secondary text-body">{{ String(row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.type_formatted?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ formatAmount(row.amount) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.source_formatted?.label || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.reference || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.notes || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.created_by?.name || row.created_by?.email || '—' }}</td>
              </tr>
              <tr v-if="transactions.length === 0">
                <td colspan="7" class="table-cell text-center text-muted py-8">{{ t('customers.extraRequests.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination
          :paginationData="paginationData"
          routeName="customers.extra-requests.index"
          :routeParams="{ customer: customer.public_id }"
          :query="filterQuery"
        />
      </div>
    </div>

    <CustomerExtraRequestFormModal
      :isOpen="addModal.isOpen.value"
      mode="add"
      :sources="sources"
      :customerPublicId="customer.public_id"
      @close="addModal.close"
    />
    <CustomerExtraRequestFormModal
      :isOpen="deductModal.isOpen.value"
      mode="deduct"
      :sources="sources"
      :customerPublicId="customer.public_id"
      @close="deductModal.close"
    />
  </div>
</template>

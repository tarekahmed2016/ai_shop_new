<script setup>
import { computed, reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import MerchantCreditFormModal from '../../Components/Features/Merchants/MerchantCreditFormModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()
const merchant = computed(() => page.props.merchant || {})
const balance = computed(() => page.props.balance ?? 0)
const enforcementEnabled = computed(() => page.props.enforcement_enabled === true)
const paginationData = computed(() => page.props.transactions || {})
const transactions = computed(() => paginationData.value.data || [])
const sources = computed(() => page.props.sources || [])
const filterSources = computed(() => page.props.filterSources || [])
const filterTypes = computed(() => page.props.filterTypes || [])
const permissions = computed(() => page.props.permissions || {})
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
  router.get(route('merchants.credits.index', merchant.value.public_id), filterQuery.value, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  filters.type = ''
  filters.source = ''
  filters.date_from = ''
  filters.date_to = ''
  router.get(route('merchants.credits.index', merchant.value.public_id), {}, {
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
        <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="router.visit(route('merchants.index'))">
          ← {{ t('merchants.credits.backToMerchants') }}
        </button>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchants.credits.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('merchants.credits.pageSubtitle', { name: merchant.name }) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-6">
        <p class="text-muted muted-color">{{ t('merchants.credits.balanceLabel') }}</p>
        <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ balance }}</p>
        <p class="mt-2 text-muted muted-color">
          {{ enforcementEnabled ? t('merchants.credits.enforcementOn') : t('merchants.credits.enforcementOff') }}
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
          <button v-if="permissions.add" type="button" class="btn btn-primary px-4 py-2" @click="addModal.open()">
            {{ t('merchants.credits.addButton') }}
          </button>
          <button v-if="permissions.deduct" type="button" class="btn btn-secondary px-4 py-2" @click="deductModal.open()">
            {{ t('merchants.credits.deductButton') }}
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
                <td colspan="9" class="table-cell text-center text-muted py-8">{{ t('merchants.credits.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination
          :paginationData="paginationData"
          routeName="merchants.credits.index"
          :routeParams="{ merchant: merchant.public_id }"
          :query="filterQuery"
        />
      </div>
    </div>

    <MerchantCreditFormModal
      :isOpen="addModal.isOpen.value"
      mode="add"
      :sources="sources"
      :merchantPublicId="merchant.public_id"
      @close="addModal.close"
    />
    <MerchantCreditFormModal
      :isOpen="deductModal.isOpen.value"
      mode="deduct"
      :sources="sources"
      :merchantPublicId="merchant.public_id"
      @close="deductModal.close"
    />
  </div>
</template>

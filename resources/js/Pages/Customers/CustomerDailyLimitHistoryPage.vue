<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'

const { t } = useI18n()
const page = usePage()
const customer = computed(() => page.props.customer || {})
const paginationData = computed(() => page.props.changes || {})
const changes = computed(() => paginationData.value.data || [])

const formatOverride = (value) => {
  if (value === null || value === undefined || value === '') {
    return t('customers.limitHistory.global')
  }

  return String(value)
}

const formatChangeType = (row) => {
  const value = row.change_type || row.change_type_formatted?.value
  if (!value) {
    return row.change_type_formatted?.label || '—'
  }

  return t(`customers.limitHistory.changeTypes.${value}`)
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="router.visit(route('customers.index'))">
          ← {{ t('customers.limitHistory.backToCustomers') }}
        </button>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customers.limitHistory.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('customers.limitHistory.pageSubtitle', { name: customer.name }) }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.date') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.oldOverride') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.newOverride') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.oldEffective') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.newEffective') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.changeType') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.changedBy') }}</th>
                <th class="table-header-cell text-table-header">{{ t('customers.limitHistory.table.notes') }}</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in changes" :key="row.id" class="table-row">
                <td class="table-cell table-cell-secondary text-body">{{ String(row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ formatOverride(row.old_override) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ formatOverride(row.new_override) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.old_effective_limit ?? '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.new_effective_limit ?? '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ formatChangeType(row) }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.changed_by?.name || row.changed_by?.email || '—' }}</td>
                <td class="table-cell table-cell-secondary text-body">{{ row.notes || '—' }}</td>
              </tr>
              <tr v-if="changes.length === 0">
                <td colspan="8" class="table-cell text-center text-muted py-8">{{ t('customers.limitHistory.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination
          :paginationData="paginationData"
          routeName="customers.daily-limit-history"
          :routeParams="{ customer: customer.public_id }"
        />
      </div>
    </div>
  </div>
</template>

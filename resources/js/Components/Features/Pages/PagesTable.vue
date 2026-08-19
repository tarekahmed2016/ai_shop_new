<template>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse">
      <thead>
        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
          <th
            v-for="column in columns"
            :key="column.key"
            @click="column.sortable ? handleSort(column.key) : null"
            :class="[
              'table-header-cell text-table-header',
              column.sortable ? 'table-header-cell-sortable' : '',
            ]"
          >
            <div class="flex items-center gap-2">
              {{ column.label }}
              <span v-if="column.sortable && sortColumn === column.key" class="text-sm">
                {{ sortDirection === 'asc' ? '↑' : '↓' }}
              </span>
            </div>
          </th>
          <th class="table-header-cell-actions text-table-header">
            {{ t('pages.table.actions') }}
          </th>
        </tr>
      </thead>
      <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
        <tr v-for="pageItem in pages" :key="pageItem.id" class="table-row">
          <td class="table-cell table-cell-primary text-body">{{ displayTitle(pageItem) }}</td>
          <td class="table-cell table-cell-secondary text-body" dir="ltr">
            <code class="inline-block font-mono text-muted px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
              {{ pageItem.slug }}
            </code>
          </td>
          <td class="table-cell table-cell-secondary text-body">
            <span :class="menuBadgeClass(pageItem.show_in_main_menu)">
              {{ displayMenuVisibility(pageItem) }}
            </span>
          </td>
          <td class="table-cell table-cell-secondary text-body">{{ pageItem.menu_order }}</td>
          <td class="table-cell table-cell-secondary text-body">
            <span :class="statusBadgeClass(pageItem.is_active)">
              {{ displayStatus(pageItem) }}
            </span>
          </td>
          <td class="table-cell table-cell-actions">
            <button class="btn btn-primary me-2" @click="$emit('edit', pageItem)">
              {{ t('pages.table.edit') }}
            </button>
            <button class="btn btn-danger" @click="$emit('delete', pageItem)">
              {{ t('pages.table.delete') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <EmptyState v-if="pages.length === 0" :title="t('pages.noPagesFound')" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
  pages: {
    type: Array,
    required: true,
  },
  sortColumn: {
    type: String,
    default: 'menu_order',
  },
  sortDirection: {
    type: String,
    default: 'asc',
  },
})

const emit = defineEmits(['edit', 'delete', 'sort'])

const columns = computed(() => [
  { key: 'title_ar', label: t('pages.table.title'), sortable: true },
  { key: 'slug', label: t('pages.table.slug'), sortable: true },
  { key: 'show_in_main_menu', label: t('pages.table.mainMenu'), sortable: false },
  { key: 'menu_order', label: t('pages.table.menuOrder'), sortable: true },
  { key: 'is_active', label: t('pages.table.status'), sortable: true },
])

const displayTitle = (pageItem) => resolveBilingualField(pageItem, 'title', locale.value)
const displayStatus = (pageItem) =>
  locale.value === 'ar' ? pageItem.is_active_formatted.label : pageItem.is_active_formatted.name
const displayMenuVisibility = (pageItem) =>
  locale.value === 'ar' ? pageItem.show_in_main_menu_formatted.label : pageItem.show_in_main_menu_formatted.name

const statusBadgeClass = (isActive) => [
  'inline-flex items-center px-3.5 py-0.5 rounded-full text-badge',
  isActive
    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
]

const menuBadgeClass = (visible) => [
  'inline-flex items-center px-3.5 py-0.5 rounded-full text-badge',
  visible
    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
]

const handleSort = (column) => {
  let newDirection = 'asc'

  if (props.sortColumn === column) {
    newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
  }

  emit('sort', { column, direction: newDirection })
}
</script>

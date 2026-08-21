<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th v-for="column in columns" :key="column.key"
                        @click="column.sortable ? handleSort(column.key) : null" :class="[
                            'table-header-cell text-table-header',
                            column.sortable ? 'table-header-cell-sortable' : ''
                        ]">
                        <div class="flex items-center gap-2">
                            {{ column.label }}
                            <span v-if="column.sortable && props.sortColumn === column.key" class="text-sm">
                                {{ props.sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </div>
                    </th>
                    <th class="table-header-cell-actions text-table-header">
                        {{ t('customerRequests.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="item in requests" :key="item.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">{{ item.id }}</td>
                    <td class="table-cell table-cell-primary text-body">{{ item.customer?.display_name || item.customer?.name || '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">{{ item.request_text }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ item.category ? `${item.category.name_ar} / ${item.category.name_en}` : '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.status_formatted?.label }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.source_formatted?.label }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.matches_count ?? 0 }}</td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('view', item)" class="btn btn-secondary me-2">
                            {{ t('customerRequests.viewDetails') }}
                        </button>
                        <button @click="$emit('edit', item)" class="btn btn-primary me-2">
                            {{ t('customerRequests.table.edit') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :disabled="!item.category"
                            @click="$emit('match', item)">
                            {{ t('customerRequests.matchMerchants') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="requests.length === 0" :title="t('customerRequests.noRequestsFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    requests: { type: Array, required: true },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' }
})

const emit = defineEmits(['edit', 'view', 'match', 'sort'])

const columns = computed(() => [
    { key: 'id', label: t('customerRequests.table.id'), sortable: true },
    { key: 'customer', label: t('customerRequests.table.customer'), sortable: false },
    { key: 'text', label: t('customerRequests.table.text'), sortable: false },
    { key: 'category', label: t('customerRequests.table.category'), sortable: false },
    { key: 'status', label: t('customerRequests.table.status'), sortable: true },
    { key: 'source', label: t('customerRequests.table.source'), sortable: false },
    { key: 'matches', label: t('customerRequests.table.matches'), sortable: false },
])

const handleSort = (column) => {
    let newDirection = 'asc'
    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }
    emit('sort', { column, direction: newDirection })
}
</script>

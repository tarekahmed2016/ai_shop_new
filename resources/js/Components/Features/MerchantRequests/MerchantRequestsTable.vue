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
                        {{ t('merchantRequests.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="item in requests" :key="item.public_id" class="table-row">
                    <td class="table-cell table-cell-primary text-body max-w-md truncate">{{ item.request_text }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ item.category ? `${item.category.name_ar} / ${item.category.name_en}` : '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ formatDate(item.created_at) }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.match_status?.label || '—' }}</td>
                    <td class="table-cell table-cell-actions">
                        <button type="button" class="btn btn-primary" @click="$emit('view', item)">
                            {{ t('merchantRequests.viewDetails') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="requests.length === 0" :title="t('merchantRequests.noRequestsFound')" />
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

const emit = defineEmits(['view', 'sort'])

const columns = computed(() => [
    { key: 'text', label: t('merchantRequests.table.text'), sortable: false },
    { key: 'category', label: t('merchantRequests.table.category'), sortable: false },
    { key: 'created_at', label: t('merchantRequests.table.date'), sortable: true },
    { key: 'status', label: t('merchantRequests.table.matchStatus'), sortable: false },
])

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    return String(value).replace('T', ' ').slice(0, 16)
}

const handleSort = (column) => {
    let newDirection = 'asc'
    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }
    emit('sort', { column, direction: newDirection })
}
</script>

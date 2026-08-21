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
                        {{ t('matching.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="item in matches" :key="item.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">{{ item.id }}</td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">
                        {{ item.customer_request?.request_text || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ item.customer_request?.customer?.name || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ categoryLabel(item.customer_request?.category) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.merchant?.name || '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ item.status_formatted?.label }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ formatDate(item.matched_at) }}</td>
                    <td class="table-cell table-cell-actions">
                        <button
                            v-if="item.customer_request?.public_id"
                            type="button"
                            class="btn btn-secondary"
                            @click="$emit('recalculate', item.customer_request)">
                            {{ t('matching.recalculate') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="matches.length === 0" :title="t('matching.noMatchesFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    matches: { type: Array, required: true },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' }
})

const emit = defineEmits(['sort', 'recalculate'])

const columns = computed(() => [
    { key: 'id', label: t('matching.table.id'), sortable: true },
    { key: 'request', label: t('matching.table.request'), sortable: false },
    { key: 'customer', label: t('matching.table.customer'), sortable: false },
    { key: 'category', label: t('matching.table.category'), sortable: false },
    { key: 'merchant', label: t('matching.table.merchant'), sortable: false },
    { key: 'status', label: t('matching.table.status'), sortable: true },
    { key: 'matched_at', label: t('matching.table.matchedAt'), sortable: true },
])

const categoryLabel = (category) => {
    if (!category) {
        return '—'
    }

    return `${category.name_ar} / ${category.name_en}`
}

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

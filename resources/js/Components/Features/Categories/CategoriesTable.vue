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
                        {{ t('categories.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="category in categories" :key="category.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">{{ category.id }}</td>
                    <td class="table-cell table-cell-primary text-body">{{ category.name_ar }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ category.name_en }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ category.parent ? `${category.parent.name_ar} / ${category.parent.name_en}` : '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ category.sort_order }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(category.status)">
                            {{ category.status_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', category)" class="btn btn-primary">
                            {{ t('categories.table.edit') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="categories.length === 0" :title="t('categories.noCategoriesFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    categories: {
        type: Array,
        required: true
    },
    sortColumn: {
        type: String,
        default: 'sort_order'
    },
    sortDirection: {
        type: String,
        default: 'asc'
    }
})

const emit = defineEmits(['edit', 'sort'])

const columns = computed(() => [
    { key: 'id', label: t('categories.table.id'), sortable: true },
    { key: 'name_ar', label: t('categories.table.nameAr'), sortable: true },
    { key: 'name_en', label: t('categories.table.nameEn'), sortable: true },
    { key: 'parent', label: t('categories.table.parent'), sortable: false },
    { key: 'sort_order', label: t('categories.table.sortOrder'), sortable: true },
    { key: 'status', label: t('categories.table.status'), sortable: true },
])

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const statusBadgeClass = (status) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    status === 1
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

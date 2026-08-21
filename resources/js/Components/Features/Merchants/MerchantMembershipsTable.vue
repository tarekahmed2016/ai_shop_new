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
                        {{ t('merchantMemberships.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="membership in memberships" :key="membership.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">{{ membership.user?.name }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ membership.user?.email }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ membership.role_formatted?.label }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(membership.status)">
                            {{ membership.status_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', membership)" class="btn btn-primary me-2">
                            {{ t('merchantMemberships.table.edit') }}
                        </button>
                        <button @click="$emit('delete', membership)" class="btn btn-danger">
                            {{ t('merchantMemberships.table.remove') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="memberships.length === 0" :title="t('merchantMemberships.noMembersFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    memberships: { type: Array, required: true },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' }
})

const emit = defineEmits(['edit', 'delete', 'sort'])

const columns = computed(() => [
    { key: 'name', label: t('merchantMemberships.table.name'), sortable: false },
    { key: 'email', label: t('merchantMemberships.table.email'), sortable: false },
    { key: 'role', label: t('merchantMemberships.table.role'), sortable: false },
    { key: 'status', label: t('merchantMemberships.table.status'), sortable: true },
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

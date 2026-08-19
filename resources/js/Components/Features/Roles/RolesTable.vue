<template>
    <!-- Mobile: Horizontal scroll wrapper -->
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
                        {{ t('roles.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="role in roles" :key="role.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">
                        {{ role.id }}
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ role.name }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ permissionNames(role) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ formatDate(role.created_at) }}
                    </td>

                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', role)" class="btn btn-primary me-2">
                            {{ t('roles.table.edit') }}
                        </button>
                        <button @click="$emit('delete', role)" class="btn btn-danger">
                            {{ t('roles.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="roles.length === 0" :title="t('roles.noRolesFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { formatDate } from '../../../Utils/formatDate.js'

const { t } = useI18n()

const props = defineProps({
    roles: {
        type: Array,
        required: true
    },
    sortColumn: {
        type: String,
        default: 'created_at'
    },
    sortDirection: {
        type: String,
        default: 'desc'
    }
})

const emit = defineEmits(['edit', 'delete', 'sort'])

const columns = computed(() => [
    { key: 'id', label: t('roles.table.id'), sortable: true },
    { key: 'name', label: t('roles.table.name'), sortable: true },
    { key: 'permissions', label: t('roles.table.permissions'), sortable: false },
    { key: 'created_at', label: t('roles.table.createdAt'), sortable: true },
])

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const permissionNames = (role) => {
    const names = (role.permissions || []).map(permission => permission.name)

    return names.length ? names.join(', ') : '—'
}
</script>

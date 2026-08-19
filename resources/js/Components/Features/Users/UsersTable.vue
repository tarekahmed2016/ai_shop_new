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
                        {{ t('users.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="user in users" :key="user.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">
                        {{ user.id }}
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ user.name }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ user.email }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ user.phone || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ roleName(user) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(user.status)">
                            {{ user.status_formatted.label }}
                        </span>
                    </td>

                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', user)" class="btn btn-primary me-2">
                            {{ t('users.table.edit') }}
                        </button>
                        <button v-if="user.id !== currentUserId" @click="$emit('delete', user)" class="btn btn-danger">
                            {{ t('users.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="users.length === 0" :title="t('users.noUsersFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { usePage } from '@inertiajs/vue3'

const { t } = useI18n()
const page = usePage()

const currentUserId = computed(() => page.props.auth.user.id)

const props = defineProps({
    users: {
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
    { key: 'id', label: t('users.table.id'), sortable: true },
    { key: 'name', label: t('users.table.name'), sortable: true },
    { key: 'email', label: t('users.table.email'), sortable: true },
    { key: 'phone', label: t('users.table.phone'), sortable: false },
    { key: 'role', label: t('users.table.role'), sortable: false },
    { key: 'status', label: t('users.table.status'), sortable: false },
])

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const roleName = (user) => user.roles?.[0]?.name || '—'

// Active status value (1) gets a green badge, anything else (e.g. inactive) gets gray
const statusBadgeClass = (status) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    status === 1
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

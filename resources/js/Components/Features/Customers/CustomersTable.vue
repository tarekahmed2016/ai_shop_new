<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th class="table-header-cell text-table-header w-10">
                        <input
                            type="checkbox"
                            :checked="allSelected"
                            @change="$emit('toggle-select-all', !allSelected)"
                        />
                    </th>
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
                        {{ t('customers.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="customer in customers" :key="customer.id" class="table-row">
                    <td class="table-cell table-cell-secondary text-body">
                        <input
                            type="checkbox"
                            :checked="selectedPublicIds.includes(customer.public_id)"
                            @change="$emit('toggle-select', customer.public_id)"
                        />
                    </td>
                    <td class="table-cell table-cell-primary text-body">{{ customer.id }}</td>
                    <td class="table-cell table-cell-primary text-body">{{ customer.display_name }}</td>
                    <td class="table-cell table-cell-secondary text-body" dir="ltr">{{ customer.phone || '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ customer.email || '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(customer.status)">
                            {{ customer.status_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ customer.requests_today ?? 0 }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ customer.daily_limit ?? '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ customer.remaining_today ?? 0 }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ customer.extra_request_balance ?? 0 }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span v-if="customer.has_portal_access || customer.user_id" class="text-green-700 dark:text-green-300">
                            {{ t('customers.portal.enabled') }}
                        </span>
                        <span v-else class="text-amber-700 dark:text-amber-300">
                            {{ t('customers.portal.disabled') }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button type="button" @click="$emit('requests', customer)" class="btn btn-secondary me-2">
                            {{ t('customers.table.requests') }}
                        </button>
                        <button type="button" @click="$emit('dailyLimitHistory', customer)" class="btn btn-secondary me-2">
                            {{ t('customers.table.dailyLimitHistory') }}
                        </button>
                        <button type="button" @click="$emit('extraRequests', customer)" class="btn btn-secondary me-2">
                            {{ t('customers.table.extraRequestsManage') }}
                        </button>
                        <button
                            v-if="!(customer.has_portal_access || customer.user_id)"
                            type="button"
                            @click="$emit('enablePortal', customer)"
                            class="btn btn-secondary me-2"
                        >
                            {{ t('customers.portal.createLogin') }}
                        </button>
                        <button type="button" @click="$emit('edit', customer)" class="btn btn-primary">
                            {{ t('customers.table.edit') }}
                        </button>
                        <button
                            v-if="customer.status === 3"
                            type="button"
                            @click="$emit('reactivate', customer)"
                            class="btn btn-secondary ms-2"
                        >
                            {{ t('customers.reactivate') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-if="customers.length === 0" :title="t('customers.noCustomersFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    customers: { type: Array, required: true },
    sortColumn: { type: String, default: 'created_at' },
    sortDirection: { type: String, default: 'desc' },
    selectedPublicIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['edit', 'requests', 'enablePortal', 'reactivate', 'sort', 'dailyLimitHistory', 'extraRequests', 'toggle-select', 'toggle-select-all'])

const allSelected = computed(() => props.customers.length > 0 && props.customers.every((customer) => props.selectedPublicIds.includes(customer.public_id)))

const columns = computed(() => [
    { key: 'id', label: t('customers.table.id'), sortable: true },
    { key: 'name', label: t('customers.table.name'), sortable: true },
    { key: 'phone', label: t('customers.table.phone'), sortable: true },
    { key: 'email', label: t('customers.table.email'), sortable: true },
    { key: 'status', label: t('customers.table.status'), sortable: true },
    { key: 'requests_today', label: t('customers.table.requestsToday'), sortable: false },
    { key: 'daily_limit', label: t('customers.table.dailyLimit'), sortable: false },
    { key: 'remaining_today', label: t('customers.table.remainingToday'), sortable: false },
    { key: 'extra_request_balance', label: t('customers.table.extraRequests'), sortable: false },
    { key: 'portal', label: t('customers.table.portal'), sortable: false },
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
        : status === 3
            ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

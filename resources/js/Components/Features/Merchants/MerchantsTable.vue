<template>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th v-if="showSelection" class="table-header-cell text-table-header w-10">
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
                        {{ t('merchants.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="merchant in merchants" :key="merchant.id" class="table-row">
                    <td v-if="showSelection" class="table-cell table-cell-secondary text-body">
                        <input
                            type="checkbox"
                            :checked="selectedPublicIds.includes(merchant.public_id)"
                            @change="$emit('toggle-select', merchant.public_id)"
                        />
                    </td>
                    <td class="table-cell table-cell-primary text-body">{{ merchant.id }}</td>
                    <td class="table-cell table-cell-primary text-body">{{ merchant.name }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ merchant.display_email || merchant.email || '—' }}</td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span dir="ltr">{{ merchant.phone || '—' }}</span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(merchant.status)">
                            {{ merchant.status_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">{{ merchant.requests_received_count ?? 0 }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ merchant.offers_submitted_count ?? 0 }}</td>
                    <td class="table-cell table-cell-secondary text-body">{{ merchant.offer_submission_rate ?? 0 }}%</td>
                    <td class="table-cell table-cell-secondary text-body">{{ merchant.offer_credit_balance ?? 0 }}</td>
                    <td class="table-cell table-cell-actions">
                        <button v-if="creditPermissions.view" @click="$emit('credits', merchant)" class="btn btn-secondary me-2">
                            {{ t('merchants.table.credits') }}
                        </button>
                        <button @click="$emit('members', merchant)" class="btn btn-secondary me-2">
                            {{ t('merchants.table.members') }}
                        </button>
                        <button @click="$emit('categories', merchant)" class="btn btn-secondary me-2">
                            {{ t('merchants.table.categories') }}
                        </button>
                        <button @click="$emit('edit', merchant)" class="btn btn-primary">
                            {{ t('merchants.table.edit') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="merchants.length === 0" :title="t('merchants.noMerchantsFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'

const { t } = useI18n()

const props = defineProps({
    merchants: {
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
    },
    selectedPublicIds: {
        type: Array,
        default: () => []
    },
    creditPermissions: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['edit', 'members', 'categories', 'credits', 'sort', 'toggle-select', 'toggle-select-all'])

const showSelection = computed(() => props.creditPermissions.add === true)
const allSelected = computed(() => props.merchants.length > 0 && props.merchants.every((merchant) => props.selectedPublicIds.includes(merchant.public_id)))

const columns = computed(() => [
    { key: 'id', label: t('merchants.table.id'), sortable: true },
    { key: 'name', label: t('merchants.table.name'), sortable: true },
    { key: 'email', label: t('merchants.table.email'), sortable: true },
    { key: 'phone', label: t('merchants.table.phone'), sortable: false },
    { key: 'status', label: t('merchants.table.status'), sortable: true },
    { key: 'requests_received_count', label: t('merchants.table.requestsReceived'), sortable: false },
    { key: 'offers_submitted_count', label: t('merchants.table.offersSubmitted'), sortable: false },
    { key: 'offer_submission_rate', label: t('merchants.table.offerSubmissionRate'), sortable: false },
    { key: 'offer_credit_balance', label: t('merchants.table.offerCredits'), sortable: false },
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

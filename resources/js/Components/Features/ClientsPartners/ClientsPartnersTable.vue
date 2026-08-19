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
                        {{ t('clientsPartners.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="record in clientPartners" :key="record.id" class="table-row">
                    <td class="table-cell table-cell-secondary text-body">
                        <img v-if="record.attachment?.asset_path" :src="record.attachment.asset_path"
                            :alt="displayName(record)"
                            class="h-12 w-24 rounded border border-gray-200 dark:border-gray-700 object-contain bg-white p-1" />
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="typeBadgeClass(record.type?.value || record.type)">
                            {{ typeLabel(record) }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ displayName(record) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">
                        <a v-if="record.website" :href="record.website" target="_blank" rel="noopener noreferrer"
                            class="text-blue-600 hover:text-blue-700">
                            {{ record.website }}
                        </a>
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ record.ordering }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(record.is_active)">
                            {{ record.is_active_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', record)" class="btn btn-primary me-2">
                            {{ t('clientsPartners.table.edit') }}
                        </button>
                        <button @click="$emit('delete', record)" class="btn btn-danger">
                            {{ t('clientsPartners.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="clientPartners.length === 0" :title="t('clientsPartners.noRecordsFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { bilingualFieldKey, resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
    clientPartners: {
        type: Array,
        required: true
    },
    sortColumn: {
        type: String,
        default: 'type'
    },
    sortDirection: {
        type: String,
        default: 'asc'
    }
})

const emit = defineEmits(['edit', 'delete', 'sort'])

const nameSortKey = computed(() => bilingualFieldKey('name', locale.value))

const columns = computed(() => [
    { key: 'logo', label: t('clientsPartners.table.logo'), sortable: false },
    { key: 'type', label: t('clientsPartners.table.type'), sortable: true },
    { key: nameSortKey.value, label: t('clientsPartners.table.name'), sortable: true },
    { key: 'website', label: t('clientsPartners.table.website'), sortable: true },
    { key: 'ordering', label: t('clientsPartners.table.ordering'), sortable: true },
    { key: 'is_active', label: t('clientsPartners.table.status'), sortable: false },
])

const displayName = (record) => resolveBilingualField(record, 'name', locale.value)

const typeLabel = (record) => {
    if (locale.value === 'ar') {
        return record.type_formatted?.label || record.type?.label || record.type
    }

    return record.type_formatted?.name || record.type?.name || record.type
}

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const typeBadgeClass = (type) => [
    'inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium',
    type === 'partner'
        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
        : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
]

const statusBadgeClass = (isActive) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

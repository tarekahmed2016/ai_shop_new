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
                        {{ t('certificatesAwards.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="record in certificateAwards" :key="record.id" class="table-row">
                    <td class="table-cell table-cell-secondary text-body">
                        <img v-if="record.attachment?.asset_path" :src="record.attachment.asset_path"
                            :alt="displayTitle(record)"
                            class="h-12 w-24 rounded border border-gray-200 dark:border-gray-700 object-contain bg-white p-1" />
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="typeBadgeClass(record.type?.value || record.type)">
                            {{ typeLabel(record) }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ displayTitle(record) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">
                        {{ displayIssuer(record) || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ formatDate(record.issued_date) }}
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
                            {{ t('certificatesAwards.table.edit') }}
                        </button>
                        <button @click="$emit('delete', record)" class="btn btn-danger">
                            {{ t('certificatesAwards.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="certificateAwards.length === 0" :title="t('certificatesAwards.noRecordsFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { bilingualFieldKey, resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
    certificateAwards: {
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

const titleSortKey = computed(() => bilingualFieldKey('title', locale.value))
const issuerSortKey = computed(() => bilingualFieldKey('issuer', locale.value))

const columns = computed(() => [
    { key: 'image', label: t('certificatesAwards.table.image'), sortable: false },
    { key: 'type', label: t('certificatesAwards.table.type'), sortable: true },
    { key: titleSortKey.value, label: t('certificatesAwards.table.title'), sortable: true },
    { key: issuerSortKey.value, label: t('certificatesAwards.table.issuer'), sortable: true },
    { key: 'issued_date', label: t('certificatesAwards.table.issueDate'), sortable: true },
    { key: 'ordering', label: t('certificatesAwards.table.ordering'), sortable: true },
    { key: 'is_active', label: t('certificatesAwards.table.status'), sortable: false },
])

const displayTitle = (record) => resolveBilingualField(record, 'title', locale.value)
const displayIssuer = (record) => resolveBilingualField(record, 'issuer', locale.value)

const formatDate = (date) => {
    if (!date) return '—'

    return new Date(date).toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en')
}

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
    type === 'award'
        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
        : 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300'
]

const statusBadgeClass = (isActive) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

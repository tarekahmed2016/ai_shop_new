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
                        {{ t('homepagePromos.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="record in homepagePromoBlocks" :key="record.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">
                        {{ record.id }}
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
                        {{ displayDescription(record) || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ layoutLabel(record) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ record.ordering }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(record.is_active)">
                            {{ statusLabel(record) }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <img v-if="record.attachment?.asset_path" :src="record.attachment.asset_path"
                            :alt="displayTitle(record)"
                            class="h-16 rounded border border-gray-200 dark:border-gray-700 object-cover" />
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', record)" class="btn btn-primary me-2">
                            {{ t('homepagePromos.table.edit') }}
                        </button>
                        <button @click="$emit('delete', record)" class="btn btn-danger">
                            {{ t('homepagePromos.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="homepagePromoBlocks.length === 0" :title="t('homepagePromos.noPromosFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { bilingualFieldKey, resolveBilingualField } from '../../../Composables/useBilingualContent.js'
import { plainTextFromHtml } from '../../../Composables/useRichText.js'

const { t, locale } = useI18n()

const props = defineProps({
    homepagePromoBlocks: {
        type: Array,
        required: true
    },
    sortColumn: {
        type: String,
        default: 'ordering'
    },
    sortDirection: {
        type: String,
        default: 'asc'
    }
})

const emit = defineEmits(['edit', 'delete', 'sort'])

const titleSortKey = computed(() => bilingualFieldKey('title', locale.value))

const columns = computed(() => [
    { key: 'id', label: t('homepagePromos.table.id'), sortable: true },
    { key: 'type', label: t('homepagePromos.table.type'), sortable: true },
    { key: titleSortKey.value, label: t('homepagePromos.table.title'), sortable: true },
    { key: 'description', label: t('homepagePromos.table.description'), sortable: false },
    { key: 'layout_variant', label: t('homepagePromos.table.layout'), sortable: false },
    { key: 'ordering', label: t('homepagePromos.table.ordering'), sortable: true },
    { key: 'is_active', label: t('homepagePromos.table.status'), sortable: false },
    { key: 'image', label: t('homepagePromos.table.image'), sortable: false },
])

const displayTitle = (record) => resolveBilingualField(record, 'title', locale.value)
const displayDescription = (record) => plainTextFromHtml(resolveBilingualField(record, 'description', locale.value))

const typeLabel = (record) => {
    if (locale.value === 'ar') {
        return record.type_formatted?.label || record.type?.label || record.type
    }

    return record.type_formatted?.name || record.type?.name || record.type
}

const layoutLabel = (record) => {
    if (locale.value === 'ar') {
        return record.layout_formatted?.label || record.layout_variant || '—'
    }

    return record.layout_formatted?.name || record.layout_variant || '—'
}

const statusLabel = (record) => {
    if (locale.value === 'ar') {
        return record.is_active_formatted?.label || (record.is_active ? 'نشط' : 'غير نشط')
    }

    return record.is_active_formatted?.name || (record.is_active ? 'Active' : 'Inactive')
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
    type === 'business_cta'
        ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
        : type === 'promo_strip'
            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
            : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
]

const statusBadgeClass = (isActive) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

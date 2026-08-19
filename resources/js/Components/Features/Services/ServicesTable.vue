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
                        {{ t('services.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="service in services" :key="service.id" class="table-row">
                    <td class="table-cell table-cell-primary text-body">
                        {{ service.id }}
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ displayName(service) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">
                        {{ displayDescription(service) || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ service.ordering }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(service.is_active)">
                            {{ service.is_active_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <img v-if="service.attachment?.asset_path" :src="service.attachment.asset_path"
                            :alt="displayName(service)"
                            class="h-16 rounded border border-gray-200 dark:border-gray-700 object-cover" />
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', service)" class="btn btn-primary me-2">
                            {{ t('services.table.edit') }}
                        </button>
                        <button @click="$emit('delete', service)" class="btn btn-danger">
                            {{ t('services.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="services.length === 0" :title="t('services.noServicesFound')" />
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
    services: {
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

const nameSortKey = computed(() => bilingualFieldKey('name', locale.value))

const columns = computed(() => [
    { key: 'id', label: t('services.table.id'), sortable: true },
    { key: nameSortKey.value, label: t('services.table.name'), sortable: true },
    { key: 'description', label: t('services.table.description'), sortable: false },
    { key: 'ordering', label: t('services.table.ordering'), sortable: true },
    { key: 'is_active', label: t('services.table.status'), sortable: false },
    { key: 'image', label: t('services.table.image'), sortable: false },
])

const displayName = (service) => resolveBilingualField(service, 'name', locale.value)
const displayDescription = (service) => plainTextFromHtml(resolveBilingualField(service, 'description', locale.value))

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const statusBadgeClass = (isActive) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

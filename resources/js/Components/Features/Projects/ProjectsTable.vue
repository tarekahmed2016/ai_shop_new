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
                        {{ t('projects.table.actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="project in projects" :key="project.id" class="table-row">
                    <td class="table-cell table-cell-secondary text-body">
                        <img v-if="project.attachment?.asset_path" :src="project.attachment.asset_path"
                            :alt="displayName(project)"
                            class="h-16 rounded border border-gray-200 dark:border-gray-700 object-cover" />
                        <span v-else>—</span>
                    </td>
                    <td class="table-cell table-cell-primary text-body">
                        {{ displayName(project) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body max-w-xs truncate">
                        {{ displayClientName(project) || '—' }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ formatDate(project.project_date) }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        {{ project.ordering }}
                    </td>
                    <td class="table-cell table-cell-secondary text-body">
                        <span :class="statusBadgeClass(project.is_active)">
                            {{ project.is_active_formatted.label }}
                        </span>
                    </td>
                    <td class="table-cell table-cell-actions">
                        <button @click="$emit('edit', project)" class="btn btn-primary me-2">
                            {{ t('projects.table.edit') }}
                        </button>
                        <button @click="$emit('delete', project)" class="btn btn-danger">
                            {{ t('projects.table.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="projects.length === 0" :title="t('projects.noProjectsFound')" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '../../Common/EmptyState.vue'
import { bilingualFieldKey, resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
    projects: {
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
const clientSortKey = computed(() => bilingualFieldKey('client_name', locale.value))

const columns = computed(() => [
    { key: 'image', label: t('projects.table.image'), sortable: false },
    { key: nameSortKey.value, label: t('projects.table.name'), sortable: true },
    { key: clientSortKey.value, label: t('projects.table.client'), sortable: true },
    { key: 'project_date', label: t('projects.table.date'), sortable: true },
    { key: 'ordering', label: t('projects.table.ordering'), sortable: true },
    { key: 'is_active', label: t('projects.table.status'), sortable: false },
])

const displayName = (project) => resolveBilingualField(project, 'name', locale.value)
const displayClientName = (project) => resolveBilingualField(project, 'client_name', locale.value)

const handleSort = (column) => {
    let newDirection = 'asc'

    if (props.sortColumn === column) {
        newDirection = props.sortDirection === 'asc' ? 'desc' : 'asc'
    }

    emit('sort', { column, direction: newDirection })
}

const formatDate = (date) => {
    if (!date) return '—'

    return new Date(date).toLocaleDateString()
}

const statusBadgeClass = (isActive) => [
    'inline-flex items-center px-3.5 py-0.5 rounded-full',
    isActive
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
]
</script>

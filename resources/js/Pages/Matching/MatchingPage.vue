<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import RequestMatchesTable from '../../Components/Features/Matching/RequestMatchesTable.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useRequestMatches } from '../../Composables/useRequestMatches.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.matches || {})
const matches = computed(() => paginationData.value.data || [])
const statuses = computed(() => page.props.statuses || [])

const searchQuery = ref(page.props.filters?.search || '')
const sortColumn = ref(page.props.filters?.sort_column || 'created_at')
const sortDirection = ref(page.props.filters?.sort_direction || 'desc')
const statusFilter = ref(page.props.filters?.status ?? '')
const isPaginating = ref(false)

const { recalculateMatches } = useRequestMatches()

watch([searchQuery, sortColumn, sortDirection, statusFilter], () => {
    router.get(route('matching.index'), {
        search: searchQuery.value || undefined,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
        status: statusFilter.value === '' ? undefined : statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['matches', 'filters'],
        onStart: () => { isPaginating.value = true },
        onFinish: () => { isPaginating.value = false },
    })
})

const handleSort = (sortData) => {
    sortColumn.value = sortData.column
    sortDirection.value = sortData.direction
}

const handlePaginating = (status) => {
    isPaginating.value = status
}

const handleRecalculate = (customerRequest) => {
    if (!customerRequest?.public_id) {
        return
    }

    recalculateMatches(customerRequest.public_id)
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('matching.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('matching.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <input v-model="searchQuery" type="text" :placeholder="t('matching.searchPlaceholder')"
                            class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100" />
                    </div>
                    <select v-model="statusFilter"
                        class="w-full sm:w-56 ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100">
                        <option value="">{{ t('matching.allStatuses') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <RequestMatchesTable :matches="matches" :sortColumn="sortColumn" :sortDirection="sortDirection"
                    @sort="handleSort" @recalculate="handleRecalculate" />
                <Pagination :paginationData="paginationData" routeName="matching.index" @paginating="handlePaginating" />
            </div>
        </div>
    </div>
</template>

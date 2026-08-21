<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import MerchantRequestsTable from '../../Components/Features/MerchantRequests/MerchantRequestsTable.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.requests || {})
const requests = computed(() => paginationData.value.data || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('merchant.requests.index', 'requests')

const openDetails = (item) => {
    router.visit(route('merchant.requests.show', item.public_id))
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantRequests.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchantRequests.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="w-full sm:w-96">
                    <input v-model="searchQuery" type="text" :placeholder="t('merchantRequests.searchPlaceholder')"
                        class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <MerchantRequestsTable :requests="requests" :sortColumn="sortColumn" :sortDirection="sortDirection"
                    @view="openDetails" @sort="handleSort" />
                <Pagination :paginationData="paginationData" routeName="merchant.requests.index" @paginating="handlePaginating" />
            </div>
        </div>
    </div>
</template>

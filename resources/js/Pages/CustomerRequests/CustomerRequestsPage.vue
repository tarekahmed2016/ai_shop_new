<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CustomerRequestsTable from '../../Components/Features/CustomerRequests/CustomerRequestsTable.vue'
import CustomerRequestFormModal from '../../Components/Features/CustomerRequests/CustomerRequestFormModal.vue'
import CustomerRequestDetailsModal from '../../Components/Features/CustomerRequests/CustomerRequestDetailsModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useModal } from '../../Composables/General/useModal.js'
import { useRequestMatches } from '../../Composables/useRequestMatches.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.requests || {})
const requests = computed(() => paginationData.value.data || [])
const customers = computed(() => page.props.customers || [])
const statuses = computed(() => page.props.statuses || [])
const availableCategories = computed(() => page.props.availableCategories || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('customer-requests.index', 'requests')

const formModal = useModal()
const detailsModal = useModal()
const { recalculateMatches } = useRequestMatches()

const handleMatch = (customerRequest) => {
    if (!customerRequest?.public_id || !customerRequest?.category) {
        return
    }

    recalculateMatches(customerRequest.public_id)
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerRequests.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customerRequests.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <input v-model="searchQuery" type="text" :placeholder="t('customerRequests.searchPlaceholder')"
                            class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100" />
                    </div>
                    <button @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 cursor-pointer">
                        {{ t('customerRequests.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <CustomerRequestsTable :requests="requests" :sortColumn="sortColumn" :sortDirection="sortDirection"
                    @edit="formModal.open" @view="detailsModal.open" @match="handleMatch" @sort="handleSort" />
                <Pagination :paginationData="paginationData" routeName="customer-requests.index" @paginating="handlePaginating" />
            </div>
        </div>

        <CustomerRequestFormModal
            :isOpen="formModal.isOpen.value"
            :customerRequest="formModal.selectedItem.value"
            :customers="customers"
            :availableCategories="availableCategories"
            :statuses="statuses"
            @close="formModal.close" />

        <CustomerRequestDetailsModal
            :isOpen="detailsModal.isOpen.value"
            :customerRequest="detailsModal.selectedItem.value"
            @close="detailsModal.close"
            @match="handleMatch" />
    </div>
</template>

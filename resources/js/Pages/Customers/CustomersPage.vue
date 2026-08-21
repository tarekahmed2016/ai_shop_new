<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CustomersTable from '../../Components/Features/Customers/CustomersTable.vue'
import CustomerFormModal from '../../Components/Features/Customers/CustomerFormModal.vue'
import CustomerEnablePortalModal from '../../Components/Features/Customers/CustomerEnablePortalModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.customers || {})
const customers = computed(() => paginationData.value.data || [])
const statuses = computed(() => page.props.statuses || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('customers.index', 'customers')

const formModal = useModal()
const portalModal = useModal()

const openRequests = (customer) => {
    router.visit(route('customer-requests.index', { customer: customer.public_id }))
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customers.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customers.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <input v-model="searchQuery" type="text" :placeholder="t('customers.searchPlaceholder')"
                            class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100" />
                    </div>
                    <button @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 cursor-pointer">
                        {{ t('customers.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <CustomersTable :customers="customers" :sortColumn="sortColumn" :sortDirection="sortDirection"
                    @edit="formModal.open" @enablePortal="portalModal.open" @requests="openRequests" @sort="handleSort" />
                <Pagination :paginationData="paginationData" routeName="customers.index" @paginating="handlePaginating" />
            </div>
        </div>

        <CustomerFormModal
            :isOpen="formModal.isOpen.value"
            :customer="formModal.selectedItem.value"
            :statuses="statuses"
            @close="formModal.close" />

        <CustomerEnablePortalModal
            :isOpen="portalModal.isOpen.value"
            :customer="portalModal.selectedItem.value"
            @close="portalModal.close" />
    </div>
</template>

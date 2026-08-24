<script setup>
import { computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
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
const dailyLimitForm = useForm({
    daily_limit: page.props.dailyCustomerRequestLimit ?? 5,
})
const globalLimitHistory = computed(() => page.props.globalLimitHistory || {})
const globalLimitChanges = computed(() => globalLimitHistory.value.data || [])

const submitDailyLimit = () => {
    dailyLimitForm.put(route('customers.settings.daily-request-limit'), {
        preserveScroll: true,
    })
}

const reactivate = (customer) => {
    router.post(route('customers.reactivate', customer.public_id), {}, { preserveScroll: true })
}

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

const openDailyLimitHistory = (customer) => {
    router.visit(route('customers.daily-limit-history', customer.public_id))
}

const customerListQuery = computed(() => ({
    search: searchQuery.value || undefined,
    sort_column: sortColumn.value,
    sort_direction: sortDirection.value,
    global_limit_page: globalLimitHistory.value.current_page || undefined,
}))

const globalHistoryQuery = computed(() => ({
    search: searchQuery.value || undefined,
    sort_column: sortColumn.value,
    sort_direction: sortDirection.value,
    page: paginationData.value.current_page || undefined,
}))
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customers.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customers.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <form class="flex flex-col sm:flex-row gap-3 sm:items-end" @submit.prevent="submitDailyLimit">
                    <div class="w-full sm:w-64">
                        <label class="form-label text-label">{{ t('customers.settings.dailyLimit') }}</label>
                        <input v-model="dailyLimitForm.daily_limit" type="number" min="1" max="100" class="form-input text-body" />
                        <p v-if="dailyLimitForm.errors.daily_limit" class="form-error">{{ dailyLimitForm.errors.daily_limit }}</p>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700" :disabled="dailyLimitForm.processing">
                        {{ t('customers.settings.saveLimit') }}
                    </button>
                </form>
                <p class="mt-2 text-muted muted-color">{{ t('customers.settings.dailyLimitHint') }}</p>
                <div class="mt-4 overflow-x-auto">
                    <h2 class="text-card-title text-gray-900 dark:text-gray-100 mb-3">{{ t('customers.settings.globalHistoryTitle') }}</h2>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <th class="table-header-cell text-table-header">{{ t('customers.settings.globalHistory.date') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customers.settings.globalHistory.oldValue') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customers.settings.globalHistory.newValue') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customers.settings.globalHistory.changedBy') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="row in globalLimitChanges" :key="row.id" class="table-row">
                                <td class="table-cell table-cell-secondary text-body">{{ String(row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                                <td class="table-cell table-cell-secondary text-body">{{ row.old_value ?? '—' }}</td>
                                <td class="table-cell table-cell-secondary text-body">{{ row.new_value ?? '—' }}</td>
                                <td class="table-cell table-cell-secondary text-body">{{ row.changed_by?.name || row.changed_by?.email || '—' }}</td>
                            </tr>
                            <tr v-if="globalLimitChanges.length === 0">
                                <td colspan="4" class="table-cell text-center text-muted py-6">{{ t('customers.settings.globalHistoryEmpty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <Pagination
                        :paginationData="globalLimitHistory"
                        routeName="customers.index"
                        pageParam="global_limit_page"
                        :query="globalHistoryQuery"
                    />
                </div>
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
                    @edit="formModal.open" @enablePortal="portalModal.open" @requests="openRequests"
                    @dailyLimitHistory="openDailyLimitHistory" @reactivate="reactivate" @sort="handleSort" />
                <Pagination :paginationData="paginationData" routeName="customers.index" :query="customerListQuery" @paginating="handlePaginating" />
            </div>
        </div>

        <CustomerFormModal
            :isOpen="formModal.isOpen.value"
            :customer="formModal.selectedItem.value"
            :statuses="statuses"
            :globalLimit="page.props.dailyCustomerRequestLimit"
            @close="formModal.close" />

        <CustomerEnablePortalModal
            :isOpen="portalModal.isOpen.value"
            :customer="portalModal.selectedItem.value"
            @close="portalModal.close" />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import MerchantsTable from '../../Components/Features/Merchants/MerchantsTable.vue'
import MerchantFormModal from '../../Components/Features/Merchants/MerchantFormModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.merchants || {})
const merchants = computed(() => paginationData.value.data || [])
const statuses = computed(() => page.props.statuses || [])
const availableCategories = computed(() => page.props.availableCategories || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('merchants.index', 'merchants')

const formModal = useModal()

const openMembers = (merchant) => {
    router.visit(route('merchants.memberships.index', merchant.public_id))
}

const openCategories = (merchant) => {
    router.visit(route('merchants.categories.index', merchant.public_id))
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchants.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchants.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <div class="relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input v-model="searchQuery" type="text" :placeholder="t('merchants.searchPlaceholder')"
                                class="block w-full ps-10 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                    </div>

                    <button @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer transition-colors">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        {{ t('merchants.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />

                <MerchantsTable :merchants="merchants" :sortColumn="sortColumn" :sortDirection="sortDirection"
                    @edit="formModal.open" @members="openMembers" @categories="openCategories" @sort="handleSort" />

                <Pagination
                    :paginationData="paginationData"
                    routeName="merchants.index"
                    @paginating="handlePaginating" />
            </div>
        </div>

        <MerchantFormModal
            :isOpen="formModal.isOpen.value"
            :merchant="formModal.selectedItem.value"
            :statuses="statuses"
            :availableCategories="availableCategories"
            @close="formModal.close" />
    </div>
</template>

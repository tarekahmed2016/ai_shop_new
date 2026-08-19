<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import ClientsPartnersTable from '../../Components/Features/ClientsPartners/ClientsPartnersTable.vue'
import ClientPartnerFormModal from '../../Components/Features/ClientsPartners/ClientPartnerFormModal.vue'
import ClientPartnerDeleteModal from '../../Components/Features/ClientsPartners/ClientPartnerDeleteModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useClientsPartners } from '../../Composables/useClientsPartners.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()

const paginationData = computed(() => page.props.clientPartners || {})
const clientPartners = computed(() => paginationData.value.data || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('clients-partners.index', 'clientPartners')

const typeFilter = ref(page.props.filters?.type || 'all')

watch(typeFilter, () => {
    router.get(route('clients-partners.index'), {
        search: searchQuery.value || undefined,
        type: typeFilter.value === 'all' ? undefined : typeFilter.value,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['clientPartners', 'filters'],
        onStart: () => {
            isPaginating.value = true
        },
        onFinish: () => {
            isPaginating.value = false
        }
    })
})

const { deleteForm, deleteClientPartner, fetchNextOrdering } = useClientsPartners()

const defaultCreateType = computed(() => typeFilter.value === 'all' ? 'client' : typeFilter.value)

const formModal = useModal({
    onOpen: async (item) => {
        if (item) {
            return { ordering: item.ordering, type: item.type?.value || item.type }
        }

        const type = defaultCreateType.value
        const ordering = await fetchNextOrdering(type)

        return { ordering, type }
    }
})

const deleteModal = useModal()

const handleDeleteConfirm = () => {
    if (!deleteModal.selectedItem.value) return

    deleteClientPartner(deleteModal.selectedItem.value.id, {
        onSuccess: () => deleteModal.close()
    })
}

const typeOptions = [
    { value: 'all', labelKey: 'clientsPartners.filters.all' },
    { value: 'client', labelKey: 'clientsPartners.filters.clients' },
    { value: 'partner', labelKey: 'clientsPartners.filters.partners' },
]
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('clientsPartners.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('clientsPartners.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4 space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in typeOptions"
                        :key="option.value"
                        type="button"
                        @click="typeFilter = option.value"
                        :class="[
                            'px-4 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer',
                            typeFilter === option.value
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'
                        ]"
                    >
                        {{ t(option.labelKey) }}
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <div class="relative">
                            <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input v-model="searchQuery" type="text" :placeholder="t('clientsPartners.searchPlaceholder')"
                                class="block w-full ps-10 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                    </div>

                    <button @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer transition-colors">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        {{ t('clientsPartners.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />

                <ClientsPartnersTable
                    :clientPartners="clientPartners"
                    :sortColumn="sortColumn"
                    :sortDirection="sortDirection"
                    @edit="formModal.open"
                    @delete="deleteModal.open"
                    @sort="handleSort" />

                <Pagination
                    :paginationData="paginationData"
                    routeName="clients-partners.index"
                    @paginating="handlePaginating" />
            </div>
        </div>

        <ClientPartnerFormModal
            :isOpen="formModal.isOpen.value"
            :clientPartner="formModal.selectedItem.value"
            :nextData="formModal.extraData.value"
            :defaultType="defaultCreateType"
            @close="formModal.close" />

        <ClientPartnerDeleteModal
            :isOpen="deleteModal.isOpen.value"
            :clientPartner="deleteModal.selectedItem.value"
            :loading="deleteForm.processing"
            @close="deleteModal.close"
            @confirm="handleDeleteConfirm" />
    </div>
</template>

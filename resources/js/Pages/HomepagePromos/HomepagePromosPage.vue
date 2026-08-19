<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import HomepagePromosTable from '../../Components/Features/HomepagePromos/HomepagePromosTable.vue'
import HomepagePromoFormModal from '../../Components/Features/HomepagePromos/HomepagePromoFormModal.vue'
import HomepagePromoDeleteModal from '../../Components/Features/HomepagePromos/HomepagePromoDeleteModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useHomepagePromos } from '../../Composables/useHomepagePromos.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t, locale } = useI18n()
const page = usePage()

const paginationData = computed(() => page.props.homepagePromoBlocks || {})
const homepagePromoBlocks = computed(() => paginationData.value.data || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('homepage-promos.index', 'homepagePromoBlocks')

const typeFilter = ref(page.props.filters?.type || 'all')

watch(typeFilter, () => {
    router.get(route('homepage-promos.index'), {
        search: searchQuery.value || undefined,
        type: typeFilter.value === 'all' ? undefined : typeFilter.value,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['homepagePromoBlocks', 'filters'],
        onStart: () => {
            isPaginating.value = true
        },
        onFinish: () => {
            isPaginating.value = false
        }
    })
})

const { deleteForm, deleteHomepagePromo, fetchNextOrdering } = useHomepagePromos()

const defaultCreateType = computed(() => typeFilter.value === 'all' ? 'feature_band' : typeFilter.value)

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

    deleteHomepagePromo(deleteModal.selectedItem.value.id, {
        onSuccess: () => deleteModal.close()
    })
}

const typeOptions = computed(() => {
    const allOption = { value: 'all', labelKey: 'homepagePromos.filters.all' }

    const promoTypes = page.props.promoTypes || []
    const dynamicOptions = promoTypes.map((promoType) => ({
        value: promoType.value,
        labelKey: null,
        label: promoType.label,
        name: promoType.name,
    }))

    return [allOption, ...dynamicOptions]
})

const typeOptionLabel = (option) => {
    if (option.labelKey) {
        return t(option.labelKey)
    }

    return locale.value === 'ar' ? option.label : option.name
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('homepagePromos.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('homepagePromos.pageSubtitle') }}</p>
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
                        {{ typeOptionLabel(option) }}
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
                            <input v-model="searchQuery" type="text" :placeholder="t('homepagePromos.searchPlaceholder')"
                                class="block w-full ps-10 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                    </div>

                    <button @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer transition-colors">
                        <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        {{ t('homepagePromos.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />

                <HomepagePromosTable
                    :homepagePromoBlocks="homepagePromoBlocks"
                    :sortColumn="sortColumn"
                    :sortDirection="sortDirection"
                    @edit="formModal.open"
                    @delete="deleteModal.open"
                    @sort="handleSort" />

                <Pagination
                    :paginationData="paginationData"
                    routeName="homepage-promos.index"
                    @paginating="handlePaginating" />
            </div>
        </div>

        <HomepagePromoFormModal
            :isOpen="formModal.isOpen.value"
            :homepagePromo="formModal.selectedItem.value"
            :nextData="formModal.extraData.value"
            :defaultType="defaultCreateType"
            :promoTypes="page.props.promoTypes || []"
            @close="formModal.close" />

        <HomepagePromoDeleteModal
            :isOpen="deleteModal.isOpen.value"
            :homepagePromo="deleteModal.selectedItem.value"
            :loading="deleteForm.processing"
            @close="deleteModal.close"
            @confirm="handleDeleteConfirm" />
    </div>
</template>

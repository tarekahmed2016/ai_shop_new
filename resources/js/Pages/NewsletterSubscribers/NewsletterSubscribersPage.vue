<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import NewsletterSubscribersTable from '../../Components/Features/NewsletterSubscribers/NewsletterSubscribersTable.vue'
import NewsletterSubscriberDeleteModal from '../../Components/Features/NewsletterSubscribers/NewsletterSubscriberDeleteModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'
import { useNewsletterSubscribers } from '../../Composables/useNewsletterSubscribers.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()

const paginationData = computed(() => page.props.newsletterSubscribers || {})
const newsletterSubscribers = computed(() => paginationData.value.data || [])

const {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
} = useTableFilters('newsletter-subscribers.index', 'newsletterSubscribers')

const {
    deleteForm,
    unsubscribeForm,
    deleteSubscriber,
    unsubscribeSubscriber,
} = useNewsletterSubscribers()

const deleteModal = useModal()

const handleDeleteConfirm = () => {
    if (!deleteModal.selectedItem.value) return

    deleteSubscriber(deleteModal.selectedItem.value.id, {
        onSuccess: () => deleteModal.close()
    })
}

const handleUnsubscribe = (subscriber) => {
    unsubscribeSubscriber(subscriber.id)
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('newsletterSubscribers.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('newsletterSubscribers.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="w-full sm:w-96">
                    <div class="relative">
                        <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('newsletterSubscribers.searchPlaceholder')"
                            class="block w-full ps-10 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />

                <NewsletterSubscribersTable
                    :newsletterSubscribers="newsletterSubscribers"
                    :sortColumn="sortColumn"
                    :sortDirection="sortDirection"
                    :unsubscribeLoading="unsubscribeForm.processing"
                    @delete="deleteModal.open"
                    @unsubscribe="handleUnsubscribe"
                    @sort="handleSort"
                />

                <Pagination
                    :paginationData="paginationData"
                    routeName="newsletter-subscribers.index"
                    @paginating="handlePaginating"
                />
            </div>
        </div>

        <NewsletterSubscriberDeleteModal
            :isOpen="deleteModal.isOpen.value"
            :subscriber="deleteModal.selectedItem.value"
            :loading="deleteForm.processing"
            @close="deleteModal.close"
            @confirm="handleDeleteConfirm"
        />
    </div>
</template>

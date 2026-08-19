<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import ContactMessagesTable from '../../Components/Features/ContactMessages/ContactMessagesTable.vue'
import ContactMessageDetailModal from '../../Components/Features/ContactMessages/ContactMessageDetailModal.vue'
import ContactMessageDeleteModal from '../../Components/Features/ContactMessages/ContactMessageDeleteModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useContactMessages } from '../../Composables/useContactMessages.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()

const paginationData = computed(() => page.props.contactMessages || {})
const contactMessages = computed(() => paginationData.value.data || [])

const searchQuery = ref(page.props.filters?.search || '')
const statusFilter = ref(page.props.filters?.status || 'all')
const isPaginating = ref(false)

const applyFilters = () => {
    router.get(route('contact-messages.index'), {
        search: searchQuery.value || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['contactMessages', 'filters'],
        onStart: () => {
            isPaginating.value = true
        },
        onFinish: () => {
            isPaginating.value = false
        }
    })
}

let searchTimeout = null
watch(searchQuery, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
})

watch(statusFilter, applyFilters)

const {
    deleteForm,
    readForm,
    unreadForm,
    markAsRead,
    markAsUnread,
    deleteContactMessage,
} = useContactMessages()

const detailModal = useModal()
const deleteModal = useModal()

const handleMarkRead = (record) => {
    markAsRead(record.id, {
        onSuccess: () => detailModal.close()
    })
}

const handleMarkUnread = (record) => {
    markAsUnread(record.id, {
        onSuccess: () => detailModal.close()
    })
}

const handleDeleteConfirm = () => {
    if (!deleteModal.selectedItem.value) return

    deleteContactMessage(deleteModal.selectedItem.value.id, {
        onSuccess: () => {
            deleteModal.close()
            detailModal.close()
        }
    })
}

const handlePaginating = (status) => {
    isPaginating.value = status
}

const statusOptions = [
    { value: 'all', labelKey: 'contactMessages.filters.all' },
    { value: 'unread', labelKey: 'contactMessages.filters.unread' },
    { value: 'read', labelKey: 'contactMessages.filters.read' },
]
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('contactMessages.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('contactMessages.pageSubtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4 space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        @click="statusFilter = option.value"
                        :class="[
                            'px-4 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer',
                            statusFilter === option.value
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'
                        ]"
                    >
                        {{ t(option.labelKey) }}
                    </button>
                </div>

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
                            :placeholder="t('contactMessages.searchPlaceholder')"
                            class="block w-full ps-10 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />

                <ContactMessagesTable
                    :contactMessages="contactMessages"
                    @view="detailModal.open"
                    @mark-read="handleMarkRead"
                    @mark-unread="handleMarkUnread"
                    @delete="deleteModal.open"
                />

                <Pagination
                    :paginationData="paginationData"
                    routeName="contact-messages.index"
                    @paginating="handlePaginating"
                />
            </div>
        </div>

        <ContactMessageDetailModal
            :isOpen="detailModal.isOpen.value"
            :contactMessage="detailModal.selectedItem.value"
            :readLoading="readForm.processing"
            :unreadLoading="unreadForm.processing"
            @close="detailModal.close"
            @mark-read="handleMarkRead"
            @mark-unread="handleMarkUnread"
        />

        <ContactMessageDeleteModal
            :isOpen="deleteModal.isOpen.value"
            :contactMessage="deleteModal.selectedItem.value"
            :loading="deleteForm.processing"
            @close="deleteModal.close"
            @confirm="handleDeleteConfirm"
        />
    </div>
</template>

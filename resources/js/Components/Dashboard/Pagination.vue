<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
    paginationData: {
        type: Object,
        required: true,
        default: () => ({})
    },
    routeName: {
        type: String,
        required: true
    },
    routeParams: {
        type: Object,
        default: () => ({})
    },
    query: {
        type: Object,
        default: () => ({})
    },
    pageParam: {
        type: String,
        default: 'page'
    }
})

const emit = defineEmits(['paginating'])

const summaryText = computed(() => t('pagination.summary', {
    from: props.paginationData.from || 0,
    to: props.paginationData.to || 0,
    total: props.paginationData.total || 0,
}))

const visiblePageNumbers = computed(() => {
    const pages = []
    const maxVisible = 5
    const currentPage = props.paginationData.current_page || 1
    const lastPage = props.paginationData.last_page || 1

    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2))
    let end = Math.min(lastPage, start + maxVisible - 1)

    if (end - start < maxVisible - 1) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

const goToPage = (pageNum) => {
    emit('paginating', true)
    router.get(route(props.routeName, props.routeParams), {
        ...props.query,
        [props.pageParam]: pageNum,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            emit('paginating', false)
        }
    })
}
</script>

<template>
    <div v-if="paginationData.last_page > 1" class="px-3 md:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-muted muted-color text-center sm:text-start">
                {{ summaryText }}
            </div>

            <div class="flex gap-2">
                <button
                    @click="goToPage(paginationData.current_page - 1)"
                    :disabled="paginationData.current_page === 1"
                    class="px-3 py-1 text-button text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    {{ t('pagination.previous') }}
                </button>

                <div class="flex gap-1">
                    <button
                        v-for="pageNum in visiblePageNumbers"
                        :key="pageNum"
                        @click="goToPage(pageNum)"
                        :class="[
                            'px-3 py-1 text-button rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500',
                            paginationData.current_page === pageNum
                                ? 'text-white bg-blue-600 hover:bg-blue-700'
                                : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer'
                        ]">
                        {{ pageNum }}
                    </button>
                </div>

                <button
                    @click="goToPage(paginationData.current_page + 1)"
                    :disabled="paginationData.current_page === paginationData.last_page"
                    class="px-3 py-1 text-button text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    {{ t('pagination.next') }}
                </button>
            </div>
        </div>
    </div>
</template>

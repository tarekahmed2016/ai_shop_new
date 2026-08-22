<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'

const { t, locale } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.requests || {})
const requests = computed(() => paginationData.value.data || [])
const searchQuery = ref(page.props.filters?.search || '')
const isPaginating = ref(false)

watch(searchQuery, () => {
    router.get(route('customer.requests.index'), {
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['requests'],
        onStart: () => { isPaginating.value = true },
        onFinish: () => { isPaginating.value = false },
    })
})

const categoryName = (category) => {
    if (!category) return '—'
    return locale.value === 'ar' ? (category.name_ar || category.name_en) : (category.name_en || category.name_ar)
}

const formatDate = (value) => value ? new Date(value).toLocaleDateString() : '—'
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.requests.title') }}</h1>
                    <p class="mt-2 text-muted muted-color">{{ t('customerPortal.requests.subtitle') }}</p>
                </div>
                <Link :href="route('customer.requests.create')" class="inline-flex items-center justify-center px-4 py-2 rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    {{ t('customerPortal.home.createRequest') }}
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 p-4">
                <input v-model="searchQuery" type="text" :placeholder="t('customerPortal.requests.search')"
                    class="block w-full sm:w-96 form-input text-body" />
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.reference') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.category') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.text') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.status') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.offers') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.date') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.image') }}</th>
                                <th class="table-header-cell text-table-header">{{ t('customerPortal.requests.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="item in requests" :key="item.id" class="table-row">
                                <td class="table-cell text-body font-mono text-sm">{{ item.public_id }}</td>
                                <td class="table-cell text-body">{{ categoryName(item.category) }}</td>
                                <td class="table-cell text-body">{{ item.request_text?.slice(0, 80) }}</td>
                                <td class="table-cell text-body">{{ item.status_formatted?.label }}</td>
                                <td class="table-cell text-body">{{ item.submitted_offers_count ?? 0 }}</td>
                                <td class="table-cell text-body">{{ formatDate(item.created_at) }}</td>
                                <td class="table-cell text-body">{{ item.image ? t('customerPortal.requests.hasImage') : '—' }}</td>
                                <td class="table-cell">
                                    <Link :href="route('customer.requests.show', item.public_id)" class="text-blue-600">
                                        {{ item.status_formatted?.name === 'PendingClassification' ? t('customerPortal.requests.continue') : t('customerPortal.requests.view') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="requests.length === 0" class="p-6 text-muted">{{ t('customerPortal.requests.empty') }}</div>
                </div>
                <Pagination :paginationData="paginationData" routeName="customer.requests.index" @paginating="isPaginating = $event" />
            </div>
        </div>
    </div>
</template>

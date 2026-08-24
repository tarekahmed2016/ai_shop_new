<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.referrals || {})
const referrals = computed(() => paginationData.value.data || [])

const {
    searchQuery,
    isPaginating,
} = useTableFilters('marketer.referrals', 'referrals')
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-page-title text-gray-900 dark:text-gray-100 mb-6">{{ t('marketerPortal.referrals.title') }}</h1>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 p-3">
                <input v-model="searchQuery" type="text" :placeholder="t('marketerPortal.referrals.search')"
                    class="block w-full sm:w-96 ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700" />
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.name') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.email') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.registeredAt') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.customer') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.merchant') }}</th>
                            <th class="table-header-cell">{{ t('marketerPortal.referrals.dual') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in referrals" :key="row.id" class="table-row">
                            <td class="table-cell">{{ row.name }}</td>
                            <td class="table-cell">{{ row.email }}</td>
                            <td class="table-cell">{{ row.registered_at }}</td>
                            <td class="table-cell">{{ row.is_customer ? t('marketerPortal.yes') : t('marketerPortal.no') }}</td>
                            <td class="table-cell">{{ row.is_merchant ? t('marketerPortal.yes') : t('marketerPortal.no') }}</td>
                            <td class="table-cell">{{ row.is_dual ? t('marketerPortal.yes') : t('marketerPortal.no') }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="referrals.length === 0" class="p-6 text-muted">{{ t('marketerPortal.referrals.empty') }}</p>
                <Pagination :paginationData="paginationData" routeName="marketer.referrals" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import DashboardModalShell from '../../Components/Common/DashboardModalShell.vue'
import { useTableFilters } from '../../Composables/Dashboard/useTableFilters.js'

const { t } = useI18n()
const page = usePage()
const paginationData = computed(() => page.props.marketers || {})
const marketers = computed(() => paginationData.value.data || [])
const statuses = computed(() => page.props.statuses || [])
const pendingCount = computed(() => page.props.pendingCount || 0)
const filters = computed(() => page.props.filters || {})
const isCreateOpen = ref(false)

const {
    searchQuery,
    isPaginating,
} = useTableFilters('marketers.index', 'marketers')

const createForm = useForm({
    mode: 'attach',
    status: 3,
    user_email: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
})

const setStatusFilter = (value) => {
    router.get(route('marketers.index'), {
        search: searchQuery.value || undefined,
        status: value || undefined,
    }, { preserveState: true, preserveScroll: true })
}

const submitCreate = () => {
    createForm.post(route('marketers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset()
            createForm.mode = 'attach'
            createForm.status = 3
            isCreateOpen.value = false
        },
    })
}

const postAction = (name, marketer) => {
    router.post(route(name, marketer.public_id), {}, { preserveScroll: true })
}

const statusLabel = (status) => {
    const name = status?.name || status?.status_formatted?.name
    const key = name ? `marketers.status${name}` : ''

    return key && t(key) !== key
        ? t(key)
        : (status?.label || status?.status_formatted?.label || '')
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('marketers.pageTitle') }}</h1>
                    <p class="mt-2 text-muted muted-color">{{ t('marketers.pageSubtitle') }}</p>
                </div>
                <button type="button" class="btn btn-primary" @click="isCreateOpen = true">{{ t('marketers.addNew') }}</button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 p-3 flex flex-col sm:flex-row gap-3">
                <input v-model="searchQuery" type="text" :placeholder="t('marketers.search')"
                    class="block w-full sm:w-80 ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md" />
                <select :value="filters.status || ''" class="border rounded-md px-3 py-2 bg-white dark:bg-gray-700" @change="setStatusFilter($event.target.value)">
                    <option value="">{{ t('marketers.allStatuses') }}</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ statusLabel(status) }}</option>
                </select>
                <span class="inline-flex items-center text-sm text-amber-700 dark:text-amber-300">
                    {{ t('marketers.pendingCount', { count: pendingCount }) }}
                </span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto relative">
                <LoadingOverlay :show="isPaginating" />
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="table-header-cell">{{ t('marketers.table.name') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.email') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.code') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.status') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.referrals') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.customers') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.merchants') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.appliedAt') }}</th>
                            <th class="table-header-cell">{{ t('marketers.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in marketers" :key="row.public_id" class="table-row">
                            <td class="table-cell">{{ row.user?.name }}</td>
                            <td class="table-cell">{{ row.user?.email }}</td>
                            <td class="table-cell font-mono">{{ row.referral_code }}</td>
                            <td class="table-cell">
                                <span :class="row.status === 3 ? 'text-amber-600 font-semibold' : ''">
                                    {{ statusLabel(row) }}
                                </span>
                            </td>
                            <td class="table-cell">{{ row.referrals_count }}</td>
                            <td class="table-cell">{{ row.customer_count }}</td>
                            <td class="table-cell">{{ row.merchant_count }}</td>
                            <td class="table-cell">{{ row.created_at }}</td>
                            <td class="table-cell whitespace-nowrap">
                                <Link :href="route('marketers.show', row.public_id)" class="text-blue-600 me-2">{{ t('marketerFinance.details') }}</Link>
                                <button v-if="row.status === 3 || row.status === 4" type="button" class="btn btn-secondary me-1" @click="postAction('marketers.approve', row)">{{ t('marketers.approve') }}</button>
                                <button v-if="row.status === 3" type="button" class="btn btn-secondary me-1" @click="postAction('marketers.reject', row)">{{ t('marketers.reject') }}</button>
                                <button v-if="row.status === 1" type="button" class="btn btn-secondary me-1" @click="postAction('marketers.deactivate', row)">{{ t('marketers.deactivate') }}</button>
                                <button v-if="row.status === 2" type="button" class="btn btn-secondary" @click="postAction('marketers.reactivate', row)">{{ t('marketers.reactivate') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :paginationData="paginationData" routeName="marketers.index" />
            </div>
        </div>

        <DashboardModalShell :isOpen="isCreateOpen" titleId="create-marketer-title" maxWidthClass="max-w-lg" @close="isCreateOpen = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 text-start">
                <h2 id="create-marketer-title" class="text-lg font-semibold mb-4">{{ t('marketers.addNew') }}</h2>
                <div class="space-y-4">
                    <div>
                        <label class="form-label text-label" for="marketer-mode">{{ t('marketers.modeLabel') }}</label>
                        <select id="marketer-mode" v-model="createForm.mode" class="form-input w-full">
                            <option value="create">{{ t('marketers.createUser') }}</option>
                            <option value="attach">{{ t('marketers.attachExisting') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-label" for="marketer-status">{{ t('marketers.statusLabel') }}</label>
                        <select id="marketer-status" v-model="createForm.status" class="form-input w-full">
                            <option :value="3">{{ t('marketers.statusPending') }}</option>
                            <option :value="1">{{ t('marketers.statusActive') }}</option>
                            <option :value="2" disabled>{{ t('marketers.statusInactive') }}</option>
                            <option :value="4" disabled>{{ t('marketers.statusRejected') }}</option>
                        </select>
                    </div>
                    <div v-if="createForm.mode === 'attach'">
                        <label class="form-label text-label" for="marketer-user-email">{{ t('marketers.userEmail') }}</label>
                        <input id="marketer-user-email" v-model="createForm.user_email" type="email" :placeholder="t('marketers.email')" class="form-input w-full" />
                    </div>
                    <template v-else>
                        <div>
                            <label class="form-label text-label" for="marketer-name">{{ t('marketers.name') }}</label>
                            <input id="marketer-name" v-model="createForm.name" type="text" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="form-label text-label" for="marketer-email">{{ t('marketers.email') }}</label>
                            <input id="marketer-email" v-model="createForm.email" type="email" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="form-label text-label" for="marketer-phone">{{ t('marketers.phone') }}</label>
                            <input id="marketer-phone" v-model="createForm.phone" type="text" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="form-label text-label" for="marketer-password">{{ t('marketers.password') }}</label>
                            <input id="marketer-password" v-model="createForm.password" type="password" class="form-input w-full" />
                        </div>
                        <div>
                            <label class="form-label text-label" for="marketer-password-confirmation">{{ t('marketers.passwordConfirmation') }}</label>
                            <input id="marketer-password-confirmation" v-model="createForm.password_confirmation" type="password" class="form-input w-full" />
                        </div>
                    </template>
                    <div>
                        <p class="form-label text-label">{{ t('marketers.referralCode') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('marketers.referralCodeHint') }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-secondary" @click="isCreateOpen = false">{{ t('common.cancel') }}</button>
                        <button type="button" class="btn btn-primary" :disabled="createForm.processing" @click="submitCreate">{{ t('common.save') }}</button>
                    </div>
                </div>
            </div>
        </DashboardModalShell>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import MerchantTeamTable from '../../Components/Features/Merchants/MerchantTeamTable.vue'
import MerchantTeamFormModal from '../../Components/Features/Merchants/MerchantTeamFormModal.vue'
import MerchantMembershipDeleteModal from '../../Components/Features/Merchants/MerchantMembershipDeleteModal.vue'
import Pagination from '../../Components/Dashboard/Pagination.vue'
import LoadingOverlay from '../../Components/Common/LoadingOverlay.vue'
import { useMerchantTeam } from '../../Composables/useMerchantTeam.js'
import { useModal } from '../../Composables/General/useModal.js'

const { t } = useI18n()
const page = usePage()
const merchant = computed(() => page.props.merchant || {})
const paginationData = computed(() => page.props.memberships || {})
const memberships = computed(() => paginationData.value.data || [])
const statuses = computed(() => page.props.statuses || [])
const roles = computed(() => page.props.roles || [])
const canManageTeam = computed(() => page.props.canManageTeam === true)
const permissionCatalog = computed(() => page.props.permissionCatalog || [])
const assignablePermissions = computed(() => page.props.assignablePermissions || {})
const canCustomizePermissions = computed(() => page.props.canCustomizePermissions === true)

const searchQuery = ref(page.props.filters?.search || '')
const sortColumn = ref(page.props.filters?.sort_column || 'created_at')
const sortDirection = ref(page.props.filters?.sort_direction || 'desc')
const isPaginating = ref(false)

watch([searchQuery, sortColumn, sortDirection], () => {
    router.get(route('merchant.team.index'), {
        search: searchQuery.value || undefined,
        sort_column: sortColumn.value,
        sort_direction: sortDirection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['memberships'],
        onStart: () => { isPaginating.value = true },
        onFinish: () => { isPaginating.value = false },
    })
})

const handleSort = (sortData) => {
    sortColumn.value = sortData.column
    sortDirection.value = sortData.direction
}

const handlePaginating = (status) => {
    isPaginating.value = status
}

const { deleteForm, deleteMembership } = useMerchantTeam()
const formModal = useModal()
const deleteModal = useModal()

const handleDeleteConfirm = () => {
    if (!deleteModal.selectedItem.value) return

    deleteMembership(deleteModal.selectedItem.value.id, {
        onSuccess: () => deleteModal.close()
    })
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto">
            <div class="mb-6 md:mb-8">
                <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="router.visit(route('merchant.home'))">
                    ← {{ t('merchantTeam.backToHome') }}
                </button>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantTeam.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchantTeam.pageSubtitle', { name: merchant.name }) }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-4 md:mb-6 p-3 md:p-4">
                <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                    <div class="w-full sm:w-96">
                        <input v-model="searchQuery" type="text" :placeholder="t('merchantTeam.searchPlaceholder')"
                            class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <button
                        v-if="canManageTeam"
                        type="button"
                        @click="formModal.open()"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-button text-white bg-blue-600 hover:bg-blue-700 cursor-pointer"
                    >
                        {{ t('merchantTeam.addNew') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden relative">
                <LoadingOverlay :show="isPaginating" />
                <MerchantTeamTable
                    :memberships="memberships"
                    :sortColumn="sortColumn"
                    :sortDirection="sortDirection"
                    @edit="formModal.open"
                    @delete="deleteModal.open"
                    @sort="handleSort"
                />
                <Pagination
                    :paginationData="paginationData"
                    routeName="merchant.team.index"
                    @paginating="handlePaginating"
                />
            </div>
        </div>

        <MerchantTeamFormModal
            :isOpen="formModal.isOpen.value"
            :membership="formModal.selectedItem.value"
            :statuses="statuses"
            :roles="roles"
            :permissionCatalog="permissionCatalog"
            :assignablePermissions="assignablePermissions"
            :canCustomizePermissions="canCustomizePermissions"
            @close="formModal.close"
        />

        <MerchantMembershipDeleteModal
            :isOpen="deleteModal.isOpen.value"
            :membership="deleteModal.selectedItem.value"
            :loading="deleteForm.processing"
            @close="deleteModal.close"
            @confirm="handleDeleteConfirm"
        />
    </div>
</template>

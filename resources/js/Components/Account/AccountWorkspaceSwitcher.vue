<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAccountWorkspaces } from '../../Composables/useAccountWorkspaces.js'

const { t } = useI18n()
const isOpen = ref(false)
const rootClass = 'workspace-switcher'

const {
    merchants,
    merchantContext,
    hasActiveCustomer,
    hasInactiveCustomer,
    hasActiveMerchants,
    isCustomerWorkspace,
    currentLabel,
    currentHint,
    goToCustomerHome,
    goToCreateRequest,
    selectMerchant,
    goToStartMerchant,
} = useAccountWorkspaces()

const createBusinessLabel = computed(() => (
    hasActiveMerchants.value
        ? t('account.workspace.createAnotherBusiness')
        : t('account.workspace.startSelling')
))

const toggle = () => {
    isOpen.value = !isOpen.value
}

const close = () => {
    isOpen.value = false
}

const onCustomerHome = () => {
    close()
    goToCustomerHome()
}

const onCreateRequest = () => {
    close()
    goToCreateRequest()
}

const onSelectMerchant = (publicId) => {
    close()
    selectMerchant(publicId)
}

const onStartMerchant = () => {
    close()
    goToStartMerchant()
}

const handleClickOutside = (event) => {
    const target = event.target
    if (!target.closest(`.${rootClass}`)) {
        close()
    }
}

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        close()
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div :class="['relative', rootClass]">
        <button
            type="button"
            class="flex max-w-[11rem] sm:max-w-xs items-center gap-2 px-3 py-2 text-button text-gray-300 hover:text-gray-100 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
            :aria-label="t('account.workspace.openSwitcher')"
            :aria-expanded="isOpen"
            aria-haspopup="menu"
            @click.stop="toggle"
        >
            <span class="truncate text-start">
                <span class="block text-xs text-gray-400 leading-tight">{{ currentHint }}</span>
                <span class="block truncate leading-tight">{{ currentLabel }}</span>
            </span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                :class="['w-4 h-4 shrink-0 transition-transform duration-200', isOpen ? 'rotate-180' : '']"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>

        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute end-0 mt-2 w-72 max-w-[calc(100vw-2rem)] max-h-80 overflow-y-auto bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-1 z-50"
                role="menu"
            >
                <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-gray-400">
                    {{ t('account.workspace.usingAs') }}
                </p>

                <button
                    v-if="hasActiveCustomer"
                    type="button"
                    class="w-full text-start px-4 py-2.5 text-body transition-colors cursor-pointer"
                    :class="isCustomerWorkspace ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100'"
                    role="menuitem"
                    @click="onCustomerHome"
                >
                    {{ t('account.workspace.myRequests') }}
                </button>
                <button
                    v-else-if="!hasInactiveCustomer"
                    type="button"
                    class="w-full text-start px-4 py-2.5 text-body text-gray-300 hover:bg-gray-700 hover:text-gray-100 transition-colors cursor-pointer"
                    role="menuitem"
                    @click="onCustomerHome"
                >
                    {{ t('account.workspace.myRequests') }}
                </button>
                <p v-else class="px-4 py-2 text-sm text-amber-400">
                    {{ t('account.workspace.inactiveCustomer') }}
                </p>

                <button
                    v-if="hasActiveCustomer || !hasInactiveCustomer"
                    type="button"
                    class="w-full text-start px-4 py-2.5 text-body text-gray-300 hover:bg-gray-700 hover:text-gray-100 transition-colors cursor-pointer"
                    role="menuitem"
                    @click="onCreateRequest"
                >
                    {{ t('account.workspace.createRequest') }}
                </button>

                <div class="border-t border-gray-700 my-1"></div>

                <p class="px-4 pt-2 pb-1 text-xs uppercase tracking-wide text-gray-400">
                    {{ t('account.workspace.myBusinesses') }}
                </p>

                <p v-if="!hasActiveMerchants" class="px-4 py-2 text-sm text-gray-400">
                    {{ t('account.workspace.noBusinessesYet') }}
                </p>

                <button
                    v-for="merchant in merchants"
                    :key="merchant.public_id"
                    type="button"
                    class="w-full text-start px-4 py-2.5 text-body transition-colors cursor-pointer"
                    :class="merchant.current || merchantContext?.public_id === merchant.public_id
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100'"
                    role="menuitem"
                    @click="onSelectMerchant(merchant.public_id)"
                >
                    <span class="block truncate">{{ merchant.name }}</span>
                    <span v-if="merchant.current || merchantContext?.public_id === merchant.public_id" class="block text-xs opacity-80">
                        {{ t('account.workspace.currentBusiness') }}
                    </span>
                </button>

                <button
                    type="button"
                    class="w-full text-start px-4 py-2.5 text-body text-gray-300 hover:bg-gray-700 hover:text-gray-100 transition-colors cursor-pointer"
                    role="menuitem"
                    @click="onStartMerchant"
                >
                    {{ createBusinessLabel }}
                </button>
            </div>
        </transition>
    </div>
</template>

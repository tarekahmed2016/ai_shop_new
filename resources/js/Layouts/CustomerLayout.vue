<script setup>
import SideMenu from '../Components/Layout/Dashboard/SideMenu.vue'
import Navbar from '../Components/Layout/Dashboard/Navbar.vue'
import { useSidebar } from '../Composables/Dashboard/useSidebar.js'
import { useCustomerNav } from '../Composables/Customer/useCustomerNav.js'
import CustomCursor from '../Components/Common/CustomCursor.vue'
import FlashMessage from '../Components/Common/FlashMessage.vue'
import { useCustomerPush } from '../Composables/useCustomerPush.js'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { isCollapsed, toggle } = useSidebar()
const { menuItems } = useCustomerNav()
useCustomerPush()
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 dashboard-layout">
        <SideMenu :items="menuItems" :collapsed="isCollapsed" @toggle="toggle" />
        <Navbar :sidebar-collapsed="isCollapsed" @toggle-sidebar="toggle" />
        <main :class="[
            'pt-16 transition-all duration-300',
            isCollapsed ? 'md:ms-20' : 'md:ms-64'
        ]">
            <FlashMessage />
            <div
                v-if="$page.props.customerContext?.is_suspended"
                class="mx-3 md:mx-6 mt-4 rounded-md border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-4"
            >
                <p class="text-body text-red-800 dark:text-red-200">{{ t('customerPortal.suspended.message') }}</p>
            </div>
            <Transition mode="out-in" enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-4">
                <div :key="$page.component">
                    <slot />
                </div>
            </Transition>
        </main>
    </div>
    <CustomCursor />
</template>

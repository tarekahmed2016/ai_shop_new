<script setup>
import SideMenu from '../Components/Layout/Dashboard/SideMenu.vue'
import Navbar from '../Components/Layout/Dashboard/Navbar.vue'
import { useSidebar } from '../Composables/Dashboard/useSidebar.js'
import { useDashboardNav } from '../Composables/Dashboard/useDashboardNav.js'
import CustomCursor from '../Components/Common/CustomCursor.vue'
import FlashMessage from '../Components/Common/FlashMessage.vue'

const { isCollapsed, toggle } = useSidebar()
const { menuItems } = useDashboardNav()
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

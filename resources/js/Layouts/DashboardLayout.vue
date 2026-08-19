<script setup>
import SideMenu from '../Components/Layout/Dashboard/SideMenu.vue'
import Navbar from '../Components/Layout/Dashboard/Navbar.vue'
import { useSidebar } from '../Composables/Dashboard/useSidebar.js'
import CustomCursor from '../Components/Common/CustomCursor.vue'
import FlashMessage from '../Components/Common/FlashMessage.vue'
import { faCog, faUsers, faHome, faUserShield, faBuilding, faBriefcase, faFolderOpen, faUserGroup, faHandshake, faAward, faEnvelope, faImages, faBullhorn, faNewspaper, faFileLines, faPalette, faCode } from '@fortawesome/free-solid-svg-icons'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const { isCollapsed, toggle } = useSidebar()
const { t } = useI18n()
const page = usePage()

const isAdmin = computed(() => page.props.auth?.isAdmin === true)

const menuItems = computed(() => {
    const items = [
        {
            label: t('sidebar.dashboard'),
            icon: faHome,
            route: route('dashboard'),
        },
    ]

    if (isAdmin.value) {
        items.push(
            {
                label: t('sidebar.roles'),
                icon: faUserShield,
                route: route('roles.index'),
            },
            {
                label: t('sidebar.users'),
                icon: faUsers,
                route: route('users.index'),
            },
            {
                label: t('sidebar.services'),
                icon: faBriefcase,
                route: route('services.index'),
            },
            {
                label: t('sidebar.projects'),
                icon: faFolderOpen,
                route: route('projects.index'),
            },
            {
                label: t('sidebar.teamMembers'),
                icon: faUserGroup,
                route: route('team-members.index'),
            },
            {
                label: t('sidebar.clientsPartners'),
                icon: faHandshake,
                route: route('clients-partners.index'),
            },
            {
                label: t('sidebar.certificatesAwards'),
                icon: faAward,
                route: route('certificates-awards.index'),
            },
            {
                label: t('sidebar.contactMessages'),
                icon: faEnvelope,
                route: route('contact-messages.index'),
            },
            {
                label: t('sidebar.heroSlides'),
                icon: faImages,
                route: route('hero-slides.index'),
            },
            {
                label: t('sidebar.homepagePromos'),
                icon: faBullhorn,
                route: route('homepage-promos.index'),
            },
            {
                label: t('sidebar.pages'),
                icon: faFileLines,
                route: route('pages.index'),
            },
            {
                label: t('sidebar.newsletterSubscribers'),
                icon: faNewspaper,
                route: route('newsletter-subscribers.index'),
            },
            {
                label: t('sidebar.settings'),
                icon: faCog,
                children: [
                    { label: t('sidebar.companyInfo'), icon: faBuilding, route: route('company-info.index') },
                    { label: t('sidebar.themeColors'), icon: faPalette, route: route('theme-colors.index') },
                    { label: t('sidebar.customAssets'), icon: faCode, route: route('custom-assets.index') },
                ],
            },
        )
    }

    return items
})
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

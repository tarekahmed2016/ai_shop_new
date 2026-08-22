import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    faAward,
    faBriefcase,
    faBuilding,
    faBullhorn,
    faCode,
    faCog,
    faCreditCard,
    faEnvelope,
    faFileLines,
    faFolderOpen,
    faHandshake,
    faHome,
    faImages,
    faInbox,
    faBell,
    faClipboardList,
    faNewspaper,
    faPalette,
    faShuffle,
    faStore,
    faTags,
    faUserGroup,
    faUserShield,
    faUsers,
} from '@fortawesome/free-solid-svg-icons'

function namedRoute(name) {
    if (!name || typeof route !== 'function') {
        return null
    }

    try {
        const ziggy = route()
        if (ziggy && typeof ziggy.has === 'function' && !ziggy.has(name)) {
            return null
        }

        return route(name)
    } catch {
        return null
    }
}

export function useDashboardNav() {
    const { t } = useI18n()
    const page = usePage()
    const isAdmin = computed(() => page.props.auth?.isAdmin === true)

    const menuItems = computed(() => {
        const items = [
            {
                id: 'dashboard',
                label: t('sidebar.dashboard'),
                icon: faHome,
                route: namedRoute('dashboard'),
            },
        ]

        // Marketplace modules stay top-level. Only modules with a real Ziggy route are shown.
        const marketplaceModules = [
            { id: 'merchants', label: t('sidebar.merchants'), icon: faStore, routeName: 'merchants.index' },
            { id: 'categories', label: t('sidebar.categories'), icon: faTags, routeName: 'categories.index' },
            { id: 'customers', label: t('sidebar.customers'), icon: faUserGroup, routeName: 'customers.index' },
            { id: 'customer-requests', label: t('sidebar.customerRequests'), icon: faInbox, routeName: 'customer-requests.index' },
            { id: 'matching', label: t('sidebar.matching'), icon: faShuffle, routeName: 'matching.index' },
            { id: 'offers', label: t('sidebar.offers'), icon: faHandshake, routeName: 'offers.index' },
            { id: 'notifications', label: t('sidebar.notifications'), icon: faBell, routeName: 'notifications.index' },
            { id: 'subscriptions', label: t('sidebar.subscriptions'), icon: faCreditCard, routeName: 'subscriptions.index' },
        ]

        if (isAdmin.value) {
            marketplaceModules.forEach((module) => {
                const href = namedRoute(module.routeName)
                if (!href) {
                    return
                }

                items.push({
                    id: module.id,
                    label: module.label,
                    icon: module.icon,
                    route: href,
                })
            })
        }

        const availableMerchants = page.props.availableMerchants || []
        if (availableMerchants.length > 0) {
            if (page.props.merchantContext) {
                items.push({
                    id: 'merchant-home',
                    label: t('sidebar.merchantHome'),
                    icon: faStore,
                    route: namedRoute('merchant.home'),
                })

                const merchantRequestsHref = namedRoute('merchant.requests.index')
                if (merchantRequestsHref) {
                    items.push({
                        id: 'merchant-requests',
                        label: t('sidebar.merchantRequests'),
                        icon: faClipboardList,
                        route: merchantRequestsHref,
                    })
                }

                const merchantActivitiesHref = namedRoute('merchant.activities.index')
                if (merchantActivitiesHref) {
                    items.push({
                        id: 'merchant-activities',
                        label: t('sidebar.merchantActivities'),
                        icon: faTags,
                        route: merchantActivitiesHref,
                    })
                }

                const merchantTeamHref = namedRoute('merchant.team.index')
                if (merchantTeamHref) {
                    items.push({
                        id: 'merchant-team',
                        label: t('sidebar.merchantTeam'),
                        icon: faUsers,
                        route: merchantTeamHref,
                    })
                }

                const merchantBusinessProfileHref = namedRoute('merchant.business-profile.edit')
                if (merchantBusinessProfileHref) {
                    items.push({
                        id: 'merchant-business-profile',
                        label: t('sidebar.merchantBusinessProfile'),
                        icon: faBuilding,
                        route: merchantBusinessProfileHref,
                    })
                }
            } else {
                items.push({
                    id: 'merchant-workspace',
                    label: t('sidebar.merchantWorkspace'),
                    icon: faStore,
                    route: namedRoute('merchant.select'),
                })
            }
        }

        if (isAdmin.value) {
            const companyProfileChildren = [
                    { id: 'roles', label: t('sidebar.roles'), icon: faUserShield, route: namedRoute('roles.index') },
                    { id: 'users', label: t('sidebar.users'), icon: faUsers, route: namedRoute('users.index') },
                    { id: 'services', label: t('sidebar.services'), icon: faBriefcase, route: namedRoute('services.index') },
                    { id: 'projects', label: t('sidebar.projects'), icon: faFolderOpen, route: namedRoute('projects.index') },
                    { id: 'team-members', label: t('sidebar.teamMembers'), icon: faUserGroup, route: namedRoute('team-members.index') },
                    { id: 'clients-partners', label: t('sidebar.clientsPartners'), icon: faHandshake, route: namedRoute('clients-partners.index') },
                    { id: 'certificates-awards', label: t('sidebar.certificatesAwards'), icon: faAward, route: namedRoute('certificates-awards.index') },
                    { id: 'contact-messages', label: t('sidebar.contactMessages'), icon: faEnvelope, route: namedRoute('contact-messages.index') },
                    { id: 'hero-slides', label: t('sidebar.heroSlides'), icon: faImages, route: namedRoute('hero-slides.index') },
                    { id: 'homepage-promos', label: t('sidebar.homepagePromos'), icon: faBullhorn, route: namedRoute('homepage-promos.index') },
                    { id: 'pages', label: t('sidebar.pages'), icon: faFileLines, route: namedRoute('pages.index') },
                    { id: 'newsletter-subscribers', label: t('sidebar.newsletterSubscribers'), icon: faNewspaper, route: namedRoute('newsletter-subscribers.index') },
                    {
                        id: 'settings',
                        label: t('sidebar.settings'),
                        icon: faCog,
                        children: [
                            { id: 'company-info', label: t('sidebar.companyInfo'), icon: faBuilding, route: namedRoute('company-info.index') },
                            { id: 'theme-colors', label: t('sidebar.themeColors'), icon: faPalette, route: namedRoute('theme-colors.index') },
                            { id: 'custom-assets', label: t('sidebar.customAssets'), icon: faCode, route: namedRoute('custom-assets.index') },
                        ].filter((child) => child.route),
                    },
            ].filter((child) => child.route || (child.children && child.children.length > 0))

            items.push({
                id: 'company-profile',
                label: t('sidebar.companyProfile'),
                icon: faBuilding,
                children: companyProfileChildren,
            })
        }

        return items.filter((item) => item.route || (item.children && item.children.length > 0))
    })

    return {
        menuItems,
    }
}

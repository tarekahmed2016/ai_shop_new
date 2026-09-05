import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    faAward,
    faBriefcase,
    faBuilding,
    faBullhorn,
    faClockRotateLeft,
    faCode,
    faCog,
    faCreditCard,
    faEnvelope,
    faFileLines,
    faFolderOpen,
    faHandshake,
    faImages,
    faInbox,
    faBell,
    faMoneyBill,
    faNewspaper,
    faPalette,
    faShuffle,
    faStore,
    faTags,
    faUserGroup,
    faUserShield,
    faUsers,
} from '@fortawesome/free-solid-svg-icons'
import { useAccountNav } from '../useAccountNav.js'

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
    const permissions = computed(() => page.props.auth?.permissions || [])
    const showUnifiedAccountNav = computed(() => page.props.auth?.showUnifiedAccountNav === true)
    const { accountSections } = useAccountNav()

    function hasPermission(permission) {
        if (!isAdmin.value) {
            return false
        }

        if (permissions.value.length === 0) {
            return true
        }

        return permissions.value.includes(permission)
    }

    const menuItems = computed(() => {
        // Admin dashboard shows admin modules only. Unified Customer/Merchant/Marketer
        // account sections stay available to non-admin users from this same menuItems source
        // (desktop sidebar and mobile drawer). Merchant tools live inside Merchant Account.
        const items = showUnifiedAccountNav.value
            ? [...accountSections.value]
            : []

        // Marketplace modules stay top-level. Only modules with a real Ziggy route are shown.
        const marketplaceModules = [
            { id: 'categories', label: t('sidebar.categories'), icon: faTags, routeName: 'categories.index', permission: 'categories.view' },
            { id: 'merchants', label: t('sidebar.merchants'), icon: faStore, routeName: 'merchants.index', permission: 'merchants.view' },
            { id: 'merchant-credit-history', label: t('sidebar.merchantCreditHistory'), icon: faClockRotateLeft, routeName: 'merchants.credits.transactions', permission: 'merchant-credits.view' },
            { id: 'customers', label: t('sidebar.customers'), icon: faUserGroup, routeName: 'customers.index', permission: 'customers.view' },
            { id: 'customer-requests', label: t('sidebar.customerRequests'), icon: faInbox, routeName: 'customer-requests.index', permission: 'customer-requests.view' },
            { id: 'matching', label: t('sidebar.matching'), icon: faShuffle, routeName: 'matching.index', permission: 'matching.view' },
            { id: 'payments', label: t('sidebar.payments'), icon: faMoneyBill, routeName: 'payments.index', activePatterns: ['payments.index', 'payments.show'], permission: 'payments.view' },
            { id: 'marketers', label: t('sidebar.marketers'), icon: faBullhorn, routeName: 'marketers.index', activePatterns: ['marketers.index'], permission: 'marketers.view' },
            { id: 'marketer-commissions', label: t('sidebar.marketerCommissions'), icon: faAward, routeName: 'marketer-commissions.index', activePatterns: ['marketer-commissions.*', 'marketers.show', 'marketers.commissions', 'marketers.payouts'], permission: 'marketers.view' },
            { id: 'offers', label: t('sidebar.offers'), icon: faHandshake, routeName: 'offers.index' },
            { id: 'notifications', label: t('sidebar.notifications'), icon: faBell, routeName: 'notifications.index' },
            { id: 'subscriptions', label: t('sidebar.subscriptions'), icon: faCreditCard, routeName: 'subscriptions.index' },
        ]

        if (isAdmin.value) {
            marketplaceModules.forEach((module) => {
                if (module.permission && !hasPermission(module.permission)) {
                    return
                }

                const href = namedRoute(module.routeName)
                if (!href) {
                    return
                }

                items.push({
                    id: module.id,
                    label: module.label,
                    icon: module.icon,
                    route: href,
                    activePatterns: module.activePatterns,
                })
            })
        }

        if (isAdmin.value) {
            const companyProfileChildren = [
                    { id: 'roles', label: t('sidebar.roles'), icon: faUserShield, route: namedRoute('roles.index'), permission: 'roles.view' },
                    { id: 'users', label: t('sidebar.users'), icon: faUsers, route: namedRoute('users.index'), permission: 'users.view' },
                    { id: 'services', label: t('sidebar.services'), icon: faBriefcase, route: namedRoute('services.index'), permission: 'services.view' },
                    { id: 'projects', label: t('sidebar.projects'), icon: faFolderOpen, route: namedRoute('projects.index'), permission: 'projects.view' },
                    { id: 'team-members', label: t('sidebar.teamMembers'), icon: faUserGroup, route: namedRoute('team-members.index'), permission: 'team-members.view' },
                    { id: 'clients-partners', label: t('sidebar.clientsPartners'), icon: faHandshake, route: namedRoute('clients-partners.index'), permission: 'clients-partners.view' },
                    { id: 'certificates-awards', label: t('sidebar.certificatesAwards'), icon: faAward, route: namedRoute('certificates-awards.index'), permission: 'certificates-awards.view' },
                    { id: 'contact-messages', label: t('sidebar.contactMessages'), icon: faEnvelope, route: namedRoute('contact-messages.index'), permission: 'contact-messages.view' },
                    { id: 'hero-slides', label: t('sidebar.heroSlides'), icon: faImages, route: namedRoute('hero-slides.index'), permission: 'hero-slides.view' },
                    { id: 'homepage-promos', label: t('sidebar.homepagePromos'), icon: faBullhorn, route: namedRoute('homepage-promos.index'), permission: 'homepage-promos.view' },
                    { id: 'pages', label: t('sidebar.pages'), icon: faFileLines, route: namedRoute('pages.index'), permission: 'pages.view' },
                    { id: 'newsletter-subscribers', label: t('sidebar.newsletterSubscribers'), icon: faNewspaper, route: namedRoute('newsletter-subscribers.index'), permission: 'newsletter-subscribers.view' },
                    {
                        id: 'settings',
                        label: t('sidebar.settings'),
                        icon: faCog,
                        permission: 'settings.update',
                        children: [
                            { id: 'company-info', label: t('sidebar.companyInfo'), icon: faBuilding, route: namedRoute('company-info.index') },
                            { id: 'theme-colors', label: t('sidebar.themeColors'), icon: faPalette, route: namedRoute('theme-colors.index') },
                            { id: 'custom-assets', label: t('sidebar.customAssets'), icon: faCode, route: namedRoute('custom-assets.index') },
                        ].filter((child) => child.route),
                    },
            ].filter((child) => (
                (!child.permission || hasPermission(child.permission))
                && (child.route || (child.children && child.children.length > 0))
            ))

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

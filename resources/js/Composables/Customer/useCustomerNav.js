import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
    faClipboardList,
    faHome,
    faPlus,
    faUser,
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

export function useCustomerNav() {
    const { t } = useI18n()

    const menuItems = computed(() => [
        {
            id: 'customer-home',
            label: t('customerPortal.nav.home'),
            icon: faHome,
            route: namedRoute('customer.home'),
        },
        {
            id: 'customer-requests',
            label: t('customerPortal.nav.requests'),
            icon: faClipboardList,
            route: namedRoute('customer.requests.index'),
        },
        {
            id: 'customer-requests-create',
            label: t('customerPortal.nav.create'),
            icon: faPlus,
            route: namedRoute('customer.requests.create'),
        },
        {
            id: 'customer-profile',
            label: t('customerPortal.nav.profile'),
            icon: faUser,
            route: namedRoute('customer.profile.edit'),
        },
    ].filter((item) => item.route))

    return { menuItems }
}

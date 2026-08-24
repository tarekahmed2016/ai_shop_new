import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    faBuilding,
    faClipboardList,
    faPlus,
    faStore,
    faTags,
    faUsers,
} from '@fortawesome/free-solid-svg-icons'
import { useAccountWorkspaces } from './useAccountWorkspaces.js'

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

export function useAccountNav() {
    const { t } = useI18n()
    const page = usePage()
    const {
        merchants,
        merchantContext,
        hasInactiveCustomer,
        hasActiveMerchants,
        goToCustomerHome,
        goToCreateRequest,
        selectMerchant,
        goToStartMerchant,
    } = useAccountWorkspaces()

    const accountSections = computed(() => {
        const customerChildren = hasInactiveCustomer.value
            ? [{
                id: 'customer-inactive',
                label: t('account.workspace.inactiveCustomer'),
                disabled: true,
            }]
            : [
                {
                    id: 'customer-my-requests',
                    label: t('account.nav.myRequests'),
                    icon: faClipboardList,
                    route: namedRoute('customer.home'),
                    onClick: goToCustomerHome,
                },
                {
                    id: 'customer-create-request',
                    label: t('account.nav.createRequest'),
                    icon: faPlus,
                    route: namedRoute('customer.requests.create'),
                    onClick: goToCreateRequest,
                },
            ]

        const merchantChildren = merchants.value.map((merchant) => {
            const current = Boolean(
                merchant.current || merchantContext.value?.public_id === merchant.public_id,
            )

            return {
                id: `merchant-${merchant.public_id}`,
                label: merchant.name,
                icon: faStore,
                current,
                onClick: () => selectMerchant(merchant.public_id),
            }
        })

        merchantChildren.push({
            id: 'merchant-start',
            label: hasActiveMerchants.value
                ? t('account.nav.createAnotherBusiness')
                : t('account.nav.startSelling'),
            icon: faPlus,
            route: namedRoute('account.merchant.start'),
            onClick: goToStartMerchant,
        })

        return [
            {
                id: 'customer-account',
                type: 'section',
                alwaysOpen: true,
                label: t('account.nav.customerAccount'),
                children: customerChildren,
            },
            {
                id: 'merchant-account',
                type: 'section',
                alwaysOpen: true,
                label: t('account.nav.merchantAccount'),
                children: merchantChildren,
            },
        ]
    })

    const merchantToolItems = computed(() => {
        if (!page.props.merchantContext) {
            return []
        }

        const items = [
            {
                id: 'merchant-home',
                label: t('sidebar.merchantHome'),
                icon: faStore,
                route: namedRoute('merchant.home'),
            },
        ]

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

        return items.filter((item) => item.route)
    })

    return {
        accountSections,
        merchantToolItems,
    }
}

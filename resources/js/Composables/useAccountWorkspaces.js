import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

function currentRouteIs(pattern) {
    try {
        return typeof route === 'function' && route().current(pattern)
    } catch {
        return false
    }
}

export function useAccountWorkspaces() {
    const page = usePage()
    const { t } = useI18n()

    const capabilities = computed(() => page.props.auth?.capabilities || {})
    const merchants = computed(() => page.props.availableMerchants || [])
    const merchantContext = computed(() => page.props.merchantContext || null)

    const hasActiveCustomer = computed(() => Boolean(capabilities.value.hasActiveCustomer))
    const hasCustomer = computed(() => Boolean(capabilities.value.hasCustomer))
    const hasInactiveCustomer = computed(() => hasCustomer.value && !hasActiveCustomer.value)
    const hasActiveMerchants = computed(() => merchants.value.length > 0)

    const isCustomerWorkspace = computed(() => currentRouteIs('customer.*'))
    const isMerchantWorkspace = computed(() => currentRouteIs('merchant.*'))

    const currentLabel = computed(() => {
        if (isCustomerWorkspace.value) {
            return t('account.workspace.myRequests')
        }

        if (isMerchantWorkspace.value && merchantContext.value?.name) {
            return merchantContext.value.name
        }

        if (merchantContext.value?.name) {
            return merchantContext.value.name
        }

        return t('account.workspace.usingAs')
    })

    const currentHint = computed(() => {
        if (isCustomerWorkspace.value) {
            return t('account.workspace.myRequests')
        }

        if (merchantContext.value?.name) {
            return t('account.workspace.businessWorkspace')
        }

        return t('account.workspace.usingAs')
    })

    const goToCustomerHome = () => {
        router.visit(route('customer.home'))
    }

    const goToCreateRequest = () => {
        router.visit(route('customer.requests.create'))
    }

    const selectMerchant = (publicId) => {
        if (!publicId) {
            return
        }

        router.post(route('merchant.context.store'), {
            public_id: publicId,
        })
    }

    const goToStartMerchant = () => {
        router.visit(route('account.merchant.start'))
    }

    return {
        capabilities,
        merchants,
        merchantContext,
        hasActiveCustomer,
        hasCustomer,
        hasInactiveCustomer,
        hasActiveMerchants,
        isCustomerWorkspace,
        isMerchantWorkspace,
        currentLabel,
        currentHint,
        goToCustomerHome,
        goToCreateRequest,
        selectMerchant,
        goToStartMerchant,
    }
}

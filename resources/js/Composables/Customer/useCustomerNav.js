import { computed } from 'vue'
import { useAccountNav } from '../useAccountNav.js'

export function useCustomerNav() {
    const { accountSections, merchantToolItems } = useAccountNav()

    const menuItems = computed(() => [
        ...accountSections.value,
        ...merchantToolItems.value,
    ])

    return { menuItems }
}

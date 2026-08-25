import { computed } from 'vue'
import { useAccountNav } from '../useAccountNav.js'

export function useCustomerNav() {
    const { accountSections } = useAccountNav()

    const menuItems = computed(() => [...accountSections.value])

    return { menuItems }
}

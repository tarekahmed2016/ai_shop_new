<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const isAdmin = computed(() => page.props.auth?.isAdmin === true)
const profileHref = computed(() => {
    try {
        if (page.props.auth?.isCustomer && typeof route === 'function' && route().has('customer.profile.edit')) {
            return route('customer.profile.edit')
        }
        return typeof route === 'function' && route().has('profile.edit') ? route('profile.edit') : null
    } catch {
        return null
    }
})
const settingsHref = computed(() => {
    if (!isAdmin.value) {
        return null
    }

    try {
        return typeof route === 'function' && route().has('company-info.index')
            ? route('company-info.index')
            : null
    } catch {
        return null
    }
})

const userInitials = computed(() => {
    const name = page.props.auth?.user?.name?.trim()
    if (!name) return '?'

    const parts = name.split(/\s+/).filter(Boolean)
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase()
    }

    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase()
})

const props = defineProps({
    sidebarCollapsed: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['toggle-sidebar'])

const { locale, t } = useI18n()

// Dropdown states
const isLanguageDropdownOpen = ref(false)
const isUserDropdownOpen = ref(false)

// Current locale state
const currentLocale = computed(() => locale.value)

// Language options
const languages = [
    { code: 'en', name: 'English', },
    { code: 'ar', name: 'العربية', }
]

// Get current language object
const currentLanguage = computed(() => {
    return languages.find(lang => lang.code === currentLocale.value)
})

// Toggle language dropdown
const toggleLanguageDropdown = () => {
    isLanguageDropdownOpen.value = !isLanguageDropdownOpen.value
    isUserDropdownOpen.value = false
}

// Toggle user dropdown
const toggleUserDropdown = () => {
    isUserDropdownOpen.value = !isUserDropdownOpen.value
    isLanguageDropdownOpen.value = false
}

// Change language
const changeLanguage = (newLocale) => {
    locale.value = newLocale

    // Update document direction
    document.dir = newLocale === 'ar' ? 'rtl' : 'ltr'
    document.documentElement.lang = newLocale

    // Persist to localStorage
    localStorage.setItem('locale', newLocale)

    // Close dropdown
    isLanguageDropdownOpen.value = false
}

// Close dropdowns when clicking outside
const handleClickOutside = (event) => {
    const target = event.target
    if (!target.closest('.language-dropdown') && !target.closest('.user-dropdown')) {
        isLanguageDropdownOpen.value = false
        isUserDropdownOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

const handleToggleSidebar = () => {
    emit('toggle-sidebar')
}
</script>

<template>
    <nav :class="[
        'fixed top-0 h-16 bg-gray-900 border-b border-gray-800 flex items-center justify-between px-4 z-30 transition-all duration-300',
        // Mobile: full width (sidebar is overlay)
        'start-0 end-0',
        // Desktop: adjust based on sidebar state
        sidebarCollapsed ? 'md:start-20' : 'md:start-64'
    ]">
        <div class="flex w-full items-center justify-end gap-2 sm:gap-3">
            <!-- User Avatar Dropdown -->
            <div class="relative user-dropdown">
                <button @click="toggleUserDropdown"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-800 cursor-pointer transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :aria-label="t('navbar.userMenu')">
                    <div
                        class="w-8 h-8 rounded-full bg-linear-to-br from-blue-500 to-purple-600 flex items-center justify-center text-button text-white">
                        {{ userInitials }}
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        :class="['w-4 h-4 text-gray-400 transition-transform duration-200', isUserDropdownOpen ? 'rotate-180' : '']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <!-- User Dropdown Menu -->
                <transition enter-active-class="transition ease-out duration-100"
                    enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <div v-if="isUserDropdownOpen"
                        class="absolute end-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-1 z-50">
                        <!-- User Info Header -->
                        <div class="px-4 py-3 border-b border-gray-700">
                            <p class="text-label text-gray-100">{{ page.props.auth.user.name }}</p>
                            <p class="text-muted muted-color truncate">{{ page.props.auth.user.email }}</p>
                        </div>

                        <!-- Menu Items -->
                        <div class="py-1">
                            <Link
                                v-if="profileHref"
                                :href="profileHref"
                                class="flex items-center gap-3 px-4 py-2 text-body text-gray-300 hover:bg-gray-700 hover:text-gray-100 transition-colors"
                                @click="isUserDropdownOpen = false">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                {{ t('navbar.profile') }}
                            </Link>
                            <Link
                                v-if="settingsHref"
                                :href="settingsHref"
                                class="flex items-center gap-3 px-4 py-2 text-body text-gray-300 hover:bg-gray-700 hover:text-gray-100 transition-colors"
                                @click="isUserDropdownOpen = false">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path
                                        d="M12 1v6m0 6v6m7.07-12.07l-4.24 4.24m0 3.54l4.24 4.24M1 12h6m6 0h6m-12.07 7.07l4.24-4.24m3.54 0l4.24 4.24">
                                    </path>
                                </svg>
                                {{ t('navbar.settings') }}
                            </Link>
                        </div>

                        <!-- Logout -->
                        <div class="border-t border-gray-700 py-1">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center w-full cursor-pointer gap-3 px-4 py-2 text-body text-red-400 hover:bg-gray-700 hover:text-red-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                {{ t('navbar.logout') }}
                            </Link>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Language Switcher Dropdown -->
            <div class="relative language-dropdown">
                <button @click="toggleLanguageDropdown"
                    class="flex items-center gap-2 px-3 py-2 text-button text-gray-300 hover:text-gray-100 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                    :aria-label="t('navbar.language')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path
                            d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z">
                        </path>
                    </svg>
                    <span>{{ currentLanguage?.code.toUpperCase() }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg"
                        :class="['w-4 h-4 transition-transform duration-200', isLanguageDropdownOpen ? 'rotate-180' : '']"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <transition enter-active-class="transition ease-out duration-100"
                    enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-75"
                    leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <div v-if="isLanguageDropdownOpen"
                        class="absolute end-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-1 z-50">
                        <button v-for="lang in languages" :key="lang.code" @click="changeLanguage(lang.code)" :class="[
                            'w-full flex items-center gap-3 px-4 py-2 text-body transition-colors cursor-pointer',
                            currentLocale === lang.code
                                ? 'bg-blue-600 text-white'
                                : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100'
                        ]">
                            <span>{{ lang.name }}</span>
                            <svg v-if="currentLocale === lang.code" xmlns="http://www.w3.org/2000/svg"
                                class="w-4 h-4 ms-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </button>
                    </div>
                </transition>
            </div>

            <button
                type="button"
                @click="handleToggleSidebar"
                class="md:hidden text-gray-300 hover:text-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg p-2 cursor-pointer"
                :aria-label="t('navbar.toggleMenu')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </nav>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faSignInAlt, faBars, faSignOutAlt } from '@fortawesome/free-solid-svg-icons'
import { resolveBilingualField } from '../../../Composables/useBilingualContent.js'
import AccountWorkspaceSwitcher from '../../Account/AccountWorkspaceSwitcher.vue'

const { locale, t } = useI18n()
const page = usePage()
const isAuthenticated = computed(() => page.props.auth?.user)
const companyName = computed(() =>
    resolveBilingualField(page.props.companyInfo, 'name', locale.value) || 'ai_shop_new'
)

const isMenuOpen = ref(false)

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value
}

const handleClickOutside = (event) => {
    const target = event.target
    if (!target.closest('.user-menu-dropdown')) {
        isMenuOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>
<template>
    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-700 shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <Link :href="route('home')" class="flex items-center gap-2">
                <span class="text-white text-lg font-semibold">{{ companyName }}</span>
            </Link>
            <div class="flex items-center gap-3">
                <AccountWorkspaceSwitcher v-if="isAuthenticated" />
                <Link v-if="!isAuthenticated" :href="route('login')"
                    class="text-menu text-blue-400 hover:text-blue-300 transition-colors">
                    <font-awesome-icon :icon="faSignInAlt" class="me-1" />
                    {{ t('auth.login.loginButton') }}
                </Link>
                <Link v-if="!isAuthenticated" :href="route('register')"
                    class="text-menu text-blue-400 hover:text-blue-300 transition-colors">
                    {{ t('auth.login.registerLink') }}
                </Link>
                <div v-else class="relative user-menu-dropdown">
                    <button @click="toggleMenu"
                        class="text-blue-400 hover:text-blue-300 transition-colors p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg cursor-pointer">
                        <font-awesome-icon :icon="faBars" class="text-lg" />
                    </button>
                    <Transition enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95">
                        <div v-if="isMenuOpen"
                            class="absolute end-0 mt-2 w-48 bg-slate-800 rounded-lg shadow-lg border border-slate-700 overflow-hidden z-50">
                            <Link :href="route('dashboard')"
                                class="flex items-center gap-3 px-4 py-3 text-menu text-slate-200 hover:bg-slate-700 transition-colors">
                                Dashboard
                            </Link>
                            <Link :href="route('logout')" method="post" as="button"
                                class="w-full flex items-center gap-3 px-4 py-3 text-menu text-slate-200 hover:bg-slate-700 transition-colors cursor-pointer">
                                <font-awesome-icon :icon="faSignOutAlt" class="text-red-400" />
                                Logout
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'
import { usePublicNavLinks } from '../../Composables/usePublicNavLinks.js'
import { faEnvelope, faPhone } from '@fortawesome/free-solid-svg-icons'
import SocialLinks from './SocialLinks.vue'

/** Temporary visibility toggles — set true to restore public top-bar items. */
const SHOW_PUBLIC_STAFF_LOGIN = false
const SHOW_PUBLIC_BUSINESS_CTA = false

const { t, locale } = useI18n()
const page = usePage()
const { navLinks, isHomePage } = usePublicNavLinks()

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const companyInfo = computed(() => page.props.companyInfo || {})
const businessCta = computed(() => page.props.businessCta || null)

const companyName = computed(() =>
  resolveBilingualField(companyInfo.value, 'name', locale.value) || t('public.home.defaultCompanyName')
)
const logo = computed(() => companyInfo.value.logo || companyInfo.value.attachment?.asset_path || null)

const businessCtaLabel = computed(() => {
  const text = businessCta.value ? resolveBilingualField(businessCta.value, 'cta_text', locale.value) : ''
  if (text) return text

  return t('public.home.nav.businessCtaFallback')
})

const businessCtaUrl = computed(() => businessCta.value?.cta_url || '#contact')

const isMenuOpen = ref(false)

const toggleMenu = () => {
  isMenuOpen.value = !isMenuOpen.value
}

const closeMenu = () => {
  isMenuOpen.value = false
}

const setLanguage = (code) => {
  locale.value = code
  document.dir = code === 'ar' ? 'rtl' : 'ltr'
  document.documentElement.lang = code
  localStorage.setItem('locale', code)
}

const homeHref = computed(() => (isHomePage.value ? '#home' : route('home')))
const dashboardHref = computed(() => page.props.auth?.home || (typeof route === 'function' ? route('dashboard') : '/dashboard'))
</script>

<template>
  <header>
    <div class="public-top-bar">
      <div class="public-top-bar-inner">
        <div class="public-top-bar-group">
          <SocialLinks :company-info="companyInfo" variant="top-bar" :include-website="false" />

          <a
            v-if="companyInfo.phone"
            :href="`tel:${companyInfo.phone}`"
            class="public-top-contact-link"
          >
            <font-awesome-icon :icon="faPhone" />
            <span dir="ltr">{{ companyInfo.phone }}</span>
          </a>

          <a
            v-if="companyInfo.email"
            :href="`mailto:${companyInfo.email}`"
            class="public-top-contact-link"
          >
            <font-awesome-icon :icon="faEnvelope" />
            <span dir="ltr">{{ companyInfo.email }}</span>
          </a>
        </div>
      </div>
    </div>

    <nav class="public-nav-bar">
      <div class="public-nav-inner">
        <button
          type="button"
          class="public-nav-toggle"
          :class="{ open: isMenuOpen }"
          @click="toggleMenu"
          :aria-label="t('public.home.nav.toggleMenu')"
          :aria-expanded="isMenuOpen"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>

        <div class="public-nav-brand">
          <a :href="homeHref" class="flex items-center gap-3 min-w-0" @click="closeMenu">
            <img
              v-if="logo"
              :src="logo"
              :alt="companyName"
              class="public-nav-logo"
            />
            <div v-else class="public-nav-logo-fallback">
              {{ companyName.charAt(0).toUpperCase() }}
            </div>
            <span class="public-nav-brand-name hidden md:inline truncate max-w-[12rem] lg:max-w-sm xl:max-w-md">
              {{ companyName }}
            </span>
          </a>
        </div>

        <div class="public-nav-links">
          <a
            v-for="link in navLinks"
            :key="link.key"
            :href="link.href"
          >
            {{ link.label }}
          </a>

          <div class="public-language-flags public-nav-language-flags" role="group" :aria-label="t('public.home.nav.selectLanguage')">
            <button
              type="button"
              class="public-language-flag"
              :class="{ 'is-active': locale === 'ar' }"
              aria-label="العربية"
              title="العربية"
              @click="setLanguage('ar')"
            >
              🇴🇲
            </button>
            <button
              type="button"
              class="public-language-flag"
              :class="{ 'is-active': locale === 'en' }"
              aria-label="English"
              title="English"
              @click="setLanguage('en')"
            >
              🇬🇧
            </button>
          </div>
        </div>

        <div class="public-nav-auth">
          <template v-if="!isAuthenticated">
            <Link :href="route('login')" class="public-nav-login">
              {{ t('auth.login.loginButton') }}
            </Link>
            <Link :href="route('register')" class="public-nav-register">
              {{ t('auth.login.registerLink') }}
            </Link>
          </template>
          <Link v-else :href="dashboardHref" class="public-nav-dashboard">
            {{ t('navbar.returnToDashboard') }}
          </Link>
        </div>
      </div>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-if="isMenuOpen" class="public-mobile-nav lg:hidden">
          <div class="public-container py-3">
            <a
              v-for="link in navLinks"
              :key="link.key"
              :href="link.href"
              @click="closeMenu"
            >
              {{ link.label }}
            </a>
            <div class="public-mobile-auth">
              <template v-if="!isAuthenticated">
                <Link :href="route('login')" class="public-nav-login" @click="closeMenu">
                  {{ t('auth.login.loginButton') }}
                </Link>
                <Link :href="route('register')" class="public-nav-register" @click="closeMenu">
                  {{ t('auth.login.registerLink') }}
                </Link>
              </template>
              <Link v-else :href="dashboardHref" class="public-nav-dashboard" @click="closeMenu">
                {{ t('navbar.returnToDashboard') }}
              </Link>
            </div>
            <a
              v-if="SHOW_PUBLIC_BUSINESS_CTA"
              :href="businessCtaUrl"
              class="public-cta-btn mt-3"
              @click="closeMenu"
            >
              {{ businessCtaLabel }}
            </a>
            <Link
              v-if="SHOW_PUBLIC_STAFF_LOGIN"
              :href="route('login')"
              class="public-staff-login-link mt-3"
              @click="closeMenu"
            >
              {{ t('public.home.nav.staffLogin') }}
            </Link>
            <div class="public-language-flags mt-3">
              <button
                type="button"
                class="public-language-flag"
                :class="{ 'is-active': locale === 'ar' }"
                aria-label="العربية"
                title="العربية"
                @click="setLanguage('ar')"
              >
                🇴🇲
              </button>
              <button
                type="button"
                class="public-language-flag"
                :class="{ 'is-active': locale === 'en' }"
                aria-label="English"
                title="English"
                @click="setLanguage('en')"
              >
                🇬🇧
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </nav>
  </header>
</template>

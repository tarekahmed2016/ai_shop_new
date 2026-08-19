import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { resolveBilingualField } from './useBilingualContent.js'

const BUILTIN_ORDERS = {
  home: 10,
  services: 20,
  feature: 25,
  about: 40,
  projects: 50,
  team: 60,
  clients: 70,
  partners: 75,
  certificates: 80,
  awards: 85,
  contact: 100,
}

export function usePublicNavLinks() {
  const { t, locale } = useI18n()
  const page = usePage()

  const navContext = computed(() => page.props.publicNavContext || {})
  const menuPages = computed(() => page.props.menuPages || [])
  const isHomePage = computed(() => page.url === '/' || page.url === '')

  const homePrefix = computed(() => (isHomePage.value ? '' : route('home')))

  const navLinks = computed(() => {
    const items = [
      {
        key: 'home',
        order: BUILTIN_ORDERS.home,
        href: `${homePrefix.value}#home`,
        label: t('public.home.nav.home'),
      },
      {
        key: 'services',
        order: BUILTIN_ORDERS.services,
        href: `${homePrefix.value}#services`,
        label: t('public.home.nav.services'),
      },
    ]

    if (navContext.value.hasFeatureBand) {
      items.push({
        key: 'feature',
        order: BUILTIN_ORDERS.feature,
        href: `${homePrefix.value}#feature`,
        label: t('public.home.nav.feature'),
      })
    }

    items.push({
      key: 'about',
      order: BUILTIN_ORDERS.about,
      href: `${homePrefix.value}#about`,
      label: t('public.home.nav.about'),
    })

    if (navContext.value.hasProjects || (page.props.projects?.length > 0)) {
      items.push({
        key: 'projects',
        order: BUILTIN_ORDERS.projects,
        href: `${homePrefix.value}#projects`,
        label: t('public.home.nav.projects'),
      })
    }

    if (navContext.value.hasTeamMembers || (page.props.teamMembers?.length > 0)) {
      items.push({
        key: 'team',
        order: BUILTIN_ORDERS.team,
        href: `${homePrefix.value}#team`,
        label: t('public.home.nav.team'),
      })
    }

    if (navContext.value.hasClients || (page.props.clients?.length > 0)) {
      items.push({
        key: 'clients',
        order: BUILTIN_ORDERS.clients,
        href: `${homePrefix.value}#clients`,
        label: t('public.home.nav.clients'),
      })
    }

    if (navContext.value.hasPartners || (page.props.partners?.length > 0)) {
      items.push({
        key: 'partners',
        order: BUILTIN_ORDERS.partners,
        href: `${homePrefix.value}#partners`,
        label: t('public.home.nav.partners'),
      })
    }

    if (navContext.value.hasCertificates || (page.props.certificates?.length > 0)) {
      items.push({
        key: 'certificates',
        order: BUILTIN_ORDERS.certificates,
        href: `${homePrefix.value}#certificates`,
        label: t('public.home.nav.certificates'),
      })
    }

    if (navContext.value.hasAwards || (page.props.awards?.length > 0)) {
      items.push({
        key: 'awards',
        order: BUILTIN_ORDERS.awards,
        href: `${homePrefix.value}#awards`,
        label: t('public.home.nav.awards'),
      })
    }

    menuPages.value.forEach((customPage) => {
      items.push({
        key: `page-${customPage.slug}`,
        order: customPage.menu_order,
        href: route('public.page.show', { slug: customPage.slug }),
        label: resolveBilingualField(customPage, 'menu_title', locale.value),
      })
    })

    items.push({
      key: 'contact',
      order: BUILTIN_ORDERS.contact,
      href: `${homePrefix.value}#contact`,
      label: t('public.home.nav.contact'),
    })

    return items.sort((a, b) => a.order - b.order)
  })

  return {
    navLinks,
    isHomePage,
  }
}

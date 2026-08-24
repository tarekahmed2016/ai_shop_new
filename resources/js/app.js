import { createApp, h } from 'vue'
import { createInertiaApp, Link } from '@inertiajs/vue3'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import i18n from './Plugins/I18n'
import fontawesome from './Plugins/FontAwesome'
import DashboardLayout from './Layouts/DashboardLayout.vue'
import CustomerLayout from './Layouts/CustomerLayout.vue'
import FrontLayout from './Layouts/FrontLayout.vue'
import PublicLayout from './Layouts/PublicLayout.vue'

createInertiaApp({
    title: (title) => `${title ? `${title}` : 'ai_shop_new'}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        const page = pages[`./Pages/${name}.vue`]
        if (!page.default.layout) {
            if (name.startsWith('CustomerPortal/')) {
                page.default.layout = CustomerLayout
            } else if (
                name.startsWith('Dashboard/')
                || name.startsWith('Users/')
                || name.startsWith('Roles/')
                || name.startsWith('Merchants/')
                || name.startsWith('Categories/')
                || name.startsWith('Customers/')
                || name.startsWith('Marketers/')
                || name.startsWith('MarketerPortal/')
                || name.startsWith('CustomerRequests/')
                || name.startsWith('Matching/')
                || name.startsWith('Profile/')
                || name.startsWith('Services/')
                || name.startsWith('Projects/')
                || name.startsWith('TeamMembers/')
                || name.startsWith('ClientsPartners/')
                || name.startsWith('CertificatesAwards/')
                || name.startsWith('ContactMessages/')
                || name.startsWith('HeroSlides/')
                || name.startsWith('HomepagePromos/')
                || name.startsWith('Pages/')
                || name.startsWith('NewsletterSubscribers/')
                || name.startsWith('CompanyInfo/')
                || name.startsWith('ThemeColors/')
                || name.startsWith('CustomAssets/')
            ) {
                page.default.layout = DashboardLayout
            } else if (name.startsWith('Public/')) {
                page.default.layout = PublicLayout
            } else if (name.startsWith('Auth/') || name.startsWith('Account/')) {
                page.default.layout = FrontLayout
            }
        }
        return page
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .use(fontawesome)
            .component('Link', Link)
            .mount(el)
    },
})

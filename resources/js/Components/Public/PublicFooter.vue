<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'
import { plainTextFromHtml } from '../../Composables/useRichText.js'
import SocialLinks from './SocialLinks.vue'

const { t, locale } = useI18n()
const page = usePage()

const companyInfo = computed(() => page.props.companyInfo || {})
const teamMembers = computed(() => page.props.teamMembers || [])
const clients = computed(() => page.props.clients || [])
const partners = computed(() => page.props.partners || [])
const certificates = computed(() => page.props.certificates || [])
const awards = computed(() => page.props.awards || [])

const companyName = computed(() =>
    resolveBilingualField(companyInfo.value, 'name', locale.value) || t('public.home.defaultCompanyName')
)
const heroDescription = computed(() => plainTextFromHtml(resolveBilingualField(companyInfo.value, 'hero_description', locale.value)))
const logo = computed(() => companyInfo.value.logo || companyInfo.value.attachment?.asset_path || null)
const year = new Date().getFullYear()

const footerLinks = computed(() => {
    const links = [
        { href: '#home', label: t('public.home.nav.home') },
        { href: '#about', label: t('public.home.nav.about') },
        { href: '#services', label: t('public.home.nav.services') },
        { href: '#projects', label: t('public.home.nav.projects') },
    ]

    if (teamMembers.value.length) {
        links.push({ href: '#team', label: t('public.home.nav.team') })
    }

    if (clients.value.length) {
        links.push({ href: '#clients', label: t('public.home.nav.clients') })
    }

    if (partners.value.length) {
        links.push({ href: '#partners', label: t('public.home.nav.partners') })
    }

    if (certificates.value.length) {
        links.push({ href: '#certificates', label: t('public.home.nav.certificates') })
    }

    if (awards.value.length) {
        links.push({ href: '#awards', label: t('public.home.nav.awards') })
    }

    links.push({ href: '#contact', label: t('public.home.nav.contact') })

    return links
})
</script>

<template>
    <footer class="public-footer">
        <div class="public-footer-arch">
            <img
                v-if="logo"
                :src="logo"
                :alt="companyName"
            />
            <div v-else class="public-nav-logo-fallback">
                {{ companyName.charAt(0).toUpperCase() }}
            </div>
        </div>

        <div class="public-container">
            <div class="public-footer-grid">
                <div>
                    <h3>{{ companyName }}</h3>
                    <p v-if="heroDescription" class="text-sm leading-relaxed">
                        {{ heroDescription }}
                    </p>
                    <p v-else class="text-sm leading-relaxed">
                        {{ t('public.home.footer.tagline') }}
                    </p>
                    <SocialLinks :company-info="companyInfo" variant="footer" />
                </div>

                <div>
                    <h4>{{ t('public.home.footer.navigation') }}</h4>
                    <a v-for="link in footerLinks" :key="link.href" :href="link.href">
                        {{ link.label }}
                    </a>
                </div>

                <div>
                    <h4>{{ t('public.home.footer.contact') }}</h4>
                    <a v-if="companyInfo.phone" :href="`tel:${companyInfo.phone}`">
                        <span dir="ltr">{{ companyInfo.phone }}</span>
                    </a>
                    <a v-if="companyInfo.email" :href="`mailto:${companyInfo.email}`">
                        {{ companyInfo.email }}
                    </a>
                    <p v-if="!companyInfo.phone && !companyInfo.email" class="text-sm">
                        {{ t('public.home.contact.notAvailable') }}
                    </p>
                </div>
            </div>

            <div class="public-footer-bottom">
                {{ t('public.home.footer.copyright', { year, company: companyName }) }}
            </div>
        </div>
    </footer>
</template>

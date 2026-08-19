<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForm, usePage } from '@inertiajs/vue3'
import { faEnvelope, faPhone, faBriefcase, faFolderOpen, faExternalLinkAlt, faGlobe, faMapMarkerAlt, faBullseye, faFlag, faUserGroup, faHandshake, faBuilding, faCertificate, faAward } from '@fortawesome/free-solid-svg-icons'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'
import HeroSlider from '../../Components/Public/HeroSlider.vue'
import RichTextContent from '../../Components/Common/RichTextContent.vue'
import SocialLinks from '../../Components/Public/SocialLinks.vue'
import { useSocialLinks } from '../../Composables/useSocialLinks.js'

const { t, locale } = useI18n()
const page = usePage()

const companyInfo = computed(() => page.props.companyInfo || {})
const heroSlides = computed(() => page.props.heroSlides || [])
const featureBand = computed(() => page.props.featureBand || null)
const promoStrips = computed(() => page.props.promoStrips || [])
const services = computed(() => page.props.services || [])
const projects = computed(() => page.props.projects || [])
const teamMembers = computed(() => page.props.teamMembers || [])
const clients = computed(() => page.props.clients || [])
const partners = computed(() => page.props.partners || [])
const certificates = computed(() => page.props.certificates || [])
const awards = computed(() => page.props.awards || [])

const previewProjects = computed(() => projects.value.slice(0, 3))
const previewTeamMembers = computed(() => teamMembers.value.slice(0, 4))
const previewCertificates = computed(() => certificates.value.slice(0, 3))
const previewAwards = computed(() => awards.value.slice(0, 3))

const companyName = computed(() =>
    resolveBilingualField(companyInfo.value, 'name', locale.value) || t('public.home.defaultCompanyName')
)
const heroTitle = computed(() =>
    resolveBilingualField(companyInfo.value, 'hero_title', locale.value) || companyName.value
)
const heroDescription = computed(() => {
    const managed = resolveBilingualField(companyInfo.value, 'hero_description', locale.value)
    if (managed) return managed

    return companyInfo.value.name_ar || companyInfo.value.name_en
        ? t('public.home.hero.subheadlineWithCompany', { company: companyName.value })
        : t('public.home.hero.subheadlineFallback')
})
const aboutText = computed(() => resolveBilingualField(companyInfo.value, 'about', locale.value))
const visionText = computed(() => resolveBilingualField(companyInfo.value, 'vision', locale.value))
const missionText = computed(() => resolveBilingualField(companyInfo.value, 'mission', locale.value))
const addressText = computed(() => resolveBilingualField(companyInfo.value, 'address', locale.value))

const hasAboutContent = computed(() =>
    Boolean(aboutText.value || companyInfo.value.name_ar || companyInfo.value.name_en || companyInfo.value.phone || companyInfo.value.email || companyInfo.value.logo)
)
const hasVisionMission = computed(() => Boolean(visionText.value || missionText.value))
const hasContactInfo = computed(() =>
    Boolean(companyInfo.value.phone || companyInfo.value.email || addressText.value || companyInfo.value.website)
)
const { socialLinksWithoutWebsite } = useSocialLinks(companyInfo)

const formatProjectDate = (date) => {
    if (!date) return null

    return new Date(date).toLocaleDateString()
}

const serviceName = (service) => resolveBilingualField(service, 'name', locale.value)
const serviceDescription = (service) =>
    resolveBilingualField(service, 'description', locale.value) || t('public.home.services.noDescription')

const projectName = (project) => resolveBilingualField(project, 'name', locale.value)
const projectClientName = (project) => resolveBilingualField(project, 'client_name', locale.value)
const projectDescription = (project) =>
    resolveBilingualField(project, 'description', locale.value) || t('public.home.projects.noDescription')

const memberName = (member) => resolveBilingualField(member, 'name', locale.value)
const memberPosition = (member) => resolveBilingualField(member, 'position', locale.value)
const memberBio = (member) =>
    resolveBilingualField(member, 'bio', locale.value) || t('public.home.team.noBio')

const clientName = (client) => resolveBilingualField(client, 'name', locale.value)
const partnerName = (partner) => resolveBilingualField(partner, 'name', locale.value)

const certificateTitle = (item) => resolveBilingualField(item, 'title', locale.value)
const certificateIssuer = (item) => resolveBilingualField(item, 'issuer', locale.value)
const certificateDescription = (item) => resolveBilingualField(item, 'description', locale.value)

const awardTitle = (item) => resolveBilingualField(item, 'title', locale.value)
const awardIssuer = (item) => resolveBilingualField(item, 'issuer', locale.value)
const awardDescription = (item) => resolveBilingualField(item, 'description', locale.value)

const formatIssuedDate = (date) => {
    if (!date) return null

    return new Date(date).toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en')
}

const promoField = (block, field) => block ? resolveBilingualField(block, field, locale.value) : ''

const featureBandLayoutClass = computed(() =>
    featureBand.value?.layout_variant === 'content_right' ? 'is-content-right' : 'is-content-left'
)

const promoStripLayoutClass = (strip) =>
    strip?.layout_variant === 'content_right' ? 'is-content-right' : 'is-content-left'

const contactForm = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
})

const contactFormSuccess = ref(false)

const newsletterForm = useForm({
    email: '',
})

const newsletterSuccess = ref(false)
const newsletterAlreadySubscribed = ref(false)

watch(() => page.props.flash, (flash) => {
    if (flash?.success === 'newsletter_subscribed') {
        newsletterSuccess.value = true
        newsletterAlreadySubscribed.value = false
    }

    if (flash?.info === 'newsletter_already_subscribed') {
        newsletterAlreadySubscribed.value = true
        newsletterSuccess.value = false
    }
}, { immediate: true, deep: true })

const submitNewsletter = () => {
    newsletterSuccess.value = false
    newsletterAlreadySubscribed.value = false

    newsletterForm.post(route('newsletter.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newsletterForm.reset()
        },
    })
}

const submitContactForm = () => {
    contactFormSuccess.value = false

    contactForm.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => {
            contactForm.reset()
            contactFormSuccess.value = true
        },
    })
}
</script>

<template>
    <HeroSlider
        :slides="heroSlides"
        :fallback-title="heroTitle"
        :fallback-description="heroDescription"
        :fallback-logo="companyInfo.logo"
    />

    <section v-if="featureBand" id="feature" class="public-feature-band">
        <div class="public-container">
            <div class="public-feature-band-inner" :class="featureBandLayoutClass">
                <div class="public-feature-band-content">
                    <h2 v-if="promoField(featureBand, 'title')" class="public-feature-band-title">
                        {{ promoField(featureBand, 'title') }}
                    </h2>
                    <RichTextContent
                        v-if="promoField(featureBand, 'description')"
                        :content="promoField(featureBand, 'description')"
                        tag="div"
                        class="public-feature-band-text"
                    />
                    <a
                        v-if="promoField(featureBand, 'cta_text') && featureBand.cta_url"
                        :href="featureBand.cta_url"
                        class="public-btn-dark"
                    >
                        {{ promoField(featureBand, 'cta_text') }}
                    </a>
                </div>
                <div class="public-feature-band-visual">
                    <img
                        v-if="featureBand.image"
                        :src="featureBand.image"
                        :alt="promoField(featureBand, 'title') || companyName"
                    />
                    <img
                        v-if="featureBand.badge_image"
                        :src="featureBand.badge_image"
                        :alt="t('public.home.featureBand.badgeAlt')"
                        class="public-feature-band-badge"
                    />
                </div>
            </div>
        </div>
    </section>

    <section id="newsletter" class="public-newsletter-band">
        <div class="public-container">
            <div class="public-newsletter-inner">
                <div>
                    <h2 class="public-section-title" style="margin-bottom: 0.5rem;">
                        {{ t('public.home.newsletter.title') }}
                    </h2>
                    <p style="line-height: 1.7;">
                        {{ t('public.home.newsletter.subtitle') }}
                    </p>
                </div>
                <form class="public-newsletter-form" @submit.prevent="submitNewsletter">
                    <input
                        v-model="newsletterForm.email"
                        type="email"
                        required
                        maxlength="255"
                        class="public-newsletter-input"
                        :placeholder="t('public.home.newsletter.emailPlaceholder')"
                    />
                    <button type="submit" class="public-btn-primary" :disabled="newsletterForm.processing">
                        {{ newsletterForm.processing ? t('public.home.newsletter.submitting') : t('public.home.newsletter.submit') }}
                    </button>
                </form>
            </div>
            <p v-if="newsletterForm.errors.email" class="public-form-error" style="margin-top: 0.75rem;">
                {{ newsletterForm.errors.email }}
            </p>
            <p v-if="newsletterSuccess" class="public-success" style="margin-top: 0.75rem;">
                {{ t('public.home.newsletter.success') }}
            </p>
            <p v-if="newsletterAlreadySubscribed" class="public-info-banner">
                {{ t('public.home.newsletter.alreadySubscribed') }}
            </p>
        </div>
    </section>

    <!-- Services -->
    <section id="services" class="public-section public-section-marble">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.services.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.services.subtitle') }}
                </p>
            </div>

            <div v-if="services.length" class="public-promo-grid">
                <article
                    v-for="(service, index) in services"
                    :key="`${serviceName(service)}-${index}`"
                    class="public-promo-card"
                >
                    <div class="public-promo-card-image">
                        <img
                            v-if="service.image"
                            :src="service.image"
                            :alt="serviceName(service)"
                        />
                        <font-awesome-icon v-else :icon="faBriefcase" style="font-size: 2.5rem; color: var(--sf-muted);" />
                    </div>
                    <div class="public-promo-card-body">
                        <h3 class="public-promo-card-title">{{ serviceName(service) }}</h3>
                        <div class="public-promo-card-divider"></div>
                        <RichTextContent
                            :content="serviceDescription(service)"
                            tag="p"
                            class="public-promo-card-text"
                        />
                        <a href="#contact" class="public-link" style="margin-top: 0.75rem;">
                            {{ t('public.home.services.learnMore') }}
                        </a>
                    </div>
                </article>
            </div>

            <div v-else class="public-empty">
                <p>{{ t('public.home.services.empty') }}</p>
            </div>
        </div>
    </section>

    <section v-if="partners.length" id="partners" class="public-delivery-section">
        <div class="public-container">
            <div class="public-section-header" style="margin-bottom: 2rem;">
                <h2 class="public-section-title">
                    {{ t('public.home.delivery.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.delivery.subtitle') }}
                </p>
            </div>
            <div class="public-delivery-logos">
                <component
                    :is="partner.website ? 'a' : 'div'"
                    v-for="(partner, index) in partners"
                    :key="`${partnerName(partner)}-${index}`"
                    :href="partner.website || undefined"
                    :target="partner.website ? '_blank' : undefined"
                    :rel="partner.website ? 'noopener noreferrer' : undefined"
                    class="public-delivery-logo"
                    :aria-label="partnerName(partner)"
                >
                    <img
                        v-if="partner.logo"
                        :src="partner.logo"
                        :alt="partnerName(partner)"
                    />
                    <span v-else style="font-weight: 700; text-align: center;">{{ partnerName(partner) }}</span>
                </component>
            </div>
        </div>
    </section>

    <section
        v-for="(strip, index) in promoStrips"
        :key="`promo-strip-${index}`"
        class="public-promo-strip"
    >
        <div class="public-promo-strip-inner" :class="promoStripLayoutClass(strip)">
            <div class="public-promo-strip-visual">
                <img
                    v-if="strip.image"
                    :src="strip.image"
                    :alt="promoField(strip, 'title') || companyName"
                />
            </div>
            <div class="public-promo-strip-content">
                <h2 v-if="promoField(strip, 'title')" class="public-promo-strip-title">
                    {{ promoField(strip, 'title') }}
                </h2>
                <RichTextContent
                    v-if="promoField(strip, 'description')"
                    :content="promoField(strip, 'description')"
                    tag="div"
                    class="public-promo-strip-text"
                />
                <a
                    v-if="promoField(strip, 'cta_text') && strip.cta_url"
                    :href="strip.cta_url"
                    class="public-btn-primary"
                    style="align-self: flex-start;"
                >
                    {{ promoField(strip, 'cta_text') }}
                </a>
            </div>
        </div>
    </section>

    <!-- About -->
    <section v-if="hasAboutContent" id="about" class="public-section public-section-white">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.about.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.about.subtitle') }}
                </p>
            </div>

            <div class="public-split">
                <div class="public-about-panel">
                    <h3>{{ companyName }}</h3>
                    <RichTextContent
                        v-if="aboutText"
                        :content="aboutText"
                        tag="div"
                        style="margin-top: 0.75rem;"
                    />
                    <p v-else style="margin-top: 0.75rem;">
                        {{ t('public.home.about.descriptionWithCompany', { company: companyName }) }}
                    </p>

                    <div v-if="hasVisionMission" class="public-vision-grid">
                        <div v-if="visionText" class="public-vision-card">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <font-awesome-icon :icon="faBullseye" />
                                <h4>{{ t('public.home.about.visionTitle') }}</h4>
                            </div>
                            <RichTextContent :content="visionText" tag="div" />
                        </div>
                        <div v-if="missionText" class="public-vision-card">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <font-awesome-icon :icon="faFlag" />
                                <h4>{{ t('public.home.about.missionTitle') }}</h4>
                            </div>
                            <RichTextContent :content="missionText" tag="div" />
                        </div>
                    </div>
                </div>

                <div class="public-about-logo-wrap">
                    <img
                        v-if="companyInfo.logo"
                        :src="companyInfo.logo"
                        :alt="companyName"
                    />
                    <div v-else style="text-align: center; color: var(--sf-muted);">
                        <div class="public-nav-logo-fallback" style="width: 6rem; height: 6rem; font-size: 2.5rem; margin: 0 auto 1rem;">
                            {{ companyName.charAt(0).toUpperCase() }}
                        </div>
                        <p style="font-size: 0.875rem;">{{ t('public.home.about.logoFallback') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Projects -->
    <section v-if="projects.length" id="projects" class="public-section public-section-white">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.projects.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.projects.subtitle') }}
                </p>
            </div>

            <div class="public-promo-grid">
                <article
                    v-for="(project, index) in previewProjects"
                    :key="`${projectName(project)}-${index}`"
                    class="public-promo-card"
                >
                    <div class="public-promo-card-image">
                        <img
                            v-if="project.image"
                            :src="project.image"
                            :alt="projectName(project)"
                        />
                        <font-awesome-icon v-else :icon="faFolderOpen" style="font-size: 2.5rem; color: var(--sf-muted);" />
                    </div>
                    <div class="public-promo-card-body">
                        <h3 class="public-promo-card-title">{{ projectName(project) }}</h3>
                        <div class="public-promo-card-divider"></div>
                        <p v-if="projectClientName(project)" class="public-promo-card-text">
                            {{ t('public.home.projects.clientLabel') }}: {{ projectClientName(project) }}
                        </p>
                        <RichTextContent
                            :content="projectDescription(project)"
                            tag="p"
                            class="public-promo-card-text"
                        />
                        <p v-if="project.project_date" style="font-size: 0.75rem; color: var(--sf-muted); margin-top: 0.25rem;">
                            {{ formatProjectDate(project.project_date) }}
                        </p>
                        <a
                            v-if="project.project_url"
                            :href="project.project_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-link"
                            style="display: inline-flex; align-items: center; gap: 0.375rem; margin-top: 0.5rem;"
                        >
                            {{ t('public.home.projects.viewProject') }}
                            <font-awesome-icon :icon="faExternalLinkAlt" />
                        </a>
                    </div>
                </article>
            </div>

            <p v-if="projects.length > previewProjects.length" style="text-align: center; margin-top: 1.5rem;">
                <a href="#contact" class="public-link">{{ t('public.home.projects.viewAll') }}</a>
            </p>
        </div>
    </section>

    <!-- Team -->
    <section v-if="teamMembers.length" id="team" class="public-section public-section-light">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.team.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.team.subtitle') }}
                </p>
            </div>

            <div class="public-team-grid">
                <article
                    v-for="(member, index) in previewTeamMembers"
                    :key="`${memberName(member)}-${index}`"
                    class="public-team-card"
                >
                    <img
                        v-if="member.image"
                        :src="member.image"
                        :alt="memberName(member)"
                        class="public-team-photo"
                    />
                    <div
                        v-else
                        class="public-team-photo"
                        style="display: flex; align-items: center; justify-content: center; background: var(--sf-light); color: var(--sf-muted);"
                    >
                        <font-awesome-icon :icon="faUserGroup" />
                    </div>
                    <h3 style="font-weight: 700; margin-bottom: 0.25rem;">{{ memberName(member) }}</h3>
                    <p v-if="memberPosition(member)" style="font-size: 0.875rem; font-weight: 700; color: var(--sf-text-heading); margin-bottom: 0.75rem;">
                        {{ memberPosition(member) }}
                    </p>
                    <p style="font-size: 0.875rem; color: var(--sf-gray); line-height: 1.6; margin-bottom: 1rem;">
                        <RichTextContent
                            :content="memberBio(member)"
                            tag="p"
                            style="font-size: 0.875rem; color: var(--sf-gray); line-height: 1.6; margin-bottom: 1rem;"
                        />
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a
                            v-if="member.linkedin_url"
                            :href="member.linkedin_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-link"
                            style="display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem;"
                        >
                            <font-awesome-icon :icon="faExternalLinkAlt" />
                            {{ t('public.home.team.viewLinkedIn') }}
                        </a>
                        <a
                            v-if="member.email"
                            :href="`mailto:${member.email}`"
                            class="public-link"
                            style="display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; text-transform: none; letter-spacing: normal; font-size: 0.8125rem;"
                        >
                            <font-awesome-icon :icon="faEnvelope" />
                            {{ member.email }}
                        </a>
                        <a
                            v-if="member.phone"
                            :href="`tel:${member.phone}`"
                            class="public-link"
                            style="display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; text-transform: none; letter-spacing: normal; font-size: 0.8125rem;"
                        >
                            <font-awesome-icon :icon="faPhone" />
                            {{ member.phone }}
                        </a>
                    </div>
                </article>
            </div>

            <p v-if="teamMembers.length > previewTeamMembers.length" style="text-align: center; margin-top: 1.5rem;">
                <a href="#contact" class="public-link">{{ t('public.home.team.viewAll') }}</a>
            </p>
        </div>
    </section>

    <!-- Clients -->
    <section v-if="clients.length" id="clients" class="public-section public-section-white">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.clients.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.clients.subtitle') }}
                </p>
            </div>

            <div class="public-logo-grid">
                <component
                    :is="client.website ? 'a' : 'div'"
                    v-for="(client, index) in clients"
                    :key="`${clientName(client)}-${index}`"
                    :href="client.website || undefined"
                    :target="client.website ? '_blank' : undefined"
                    :rel="client.website ? 'noopener noreferrer' : undefined"
                    class="public-logo-card"
                >
                    <img
                        v-if="client.logo"
                        :src="client.logo"
                        :alt="clientName(client)"
                    />
                    <font-awesome-icon v-else :icon="faBuilding" style="font-size: 2rem; color: var(--sf-muted);" />
                    <p style="font-size: 0.875rem; font-weight: 700; text-align: center;">{{ clientName(client) }}</p>
                </component>
            </div>
        </div>
    </section>

    <!-- Certificates -->
    <section v-if="certificates.length" id="certificates" class="public-section public-section-white">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.certificates.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.certificates.subtitle') }}
                </p>
            </div>

            <div class="public-cert-grid">
                <article
                    v-for="(certificate, index) in previewCertificates"
                    :key="`${certificateTitle(certificate)}-${index}`"
                    class="public-cert-card"
                >
                    <div class="public-cert-card-image">
                        <img
                            v-if="certificate.image"
                            :src="certificate.image"
                            :alt="certificateTitle(certificate)"
                        />
                        <font-awesome-icon v-else :icon="faCertificate" style="font-size: 2.5rem; color: var(--sf-muted);" />
                    </div>
                    <div class="public-cert-card-body">
                        <h3 style="font-weight: 700; margin-bottom: 0.5rem;">{{ certificateTitle(certificate) }}</h3>
                        <p v-if="certificateIssuer(certificate)" style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.25rem;">
                            {{ certificateIssuer(certificate) }}
                        </p>
                        <p v-if="formatIssuedDate(certificate.issued_date)" style="font-size: 0.75rem; color: var(--sf-muted); margin-bottom: 0.75rem;">
                            {{ formatIssuedDate(certificate.issued_date) }}
                        </p>
                        <RichTextContent
                            v-if="certificateDescription(certificate)"
                            :content="certificateDescription(certificate)"
                            tag="p"
                            style="font-size: 0.875rem; color: var(--sf-gray); line-height: 1.6; margin-bottom: 1rem;"
                        />
                        <a
                            v-if="certificate.external_url"
                            :href="certificate.external_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-link"
                            style="display: inline-flex; align-items: center; gap: 0.375rem;"
                        >
                            <font-awesome-icon :icon="faExternalLinkAlt" />
                            {{ t('public.home.certificates.viewLink') }}
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Awards -->
    <section v-if="awards.length" id="awards" class="public-section public-section-light">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.awards.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.awards.subtitle') }}
                </p>
            </div>

            <div class="public-cert-grid">
                <article
                    v-for="(award, index) in previewAwards"
                    :key="`${awardTitle(award)}-${index}`"
                    class="public-cert-card"
                >
                    <div class="public-cert-card-image">
                        <img
                            v-if="award.image"
                            :src="award.image"
                            :alt="awardTitle(award)"
                        />
                        <font-awesome-icon v-else :icon="faAward" style="font-size: 2.5rem; color: var(--sf-muted);" />
                    </div>
                    <div class="public-cert-card-body">
                        <h3 style="font-weight: 700; margin-bottom: 0.5rem;">{{ awardTitle(award) }}</h3>
                        <p v-if="awardIssuer(award)" style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.25rem;">
                            {{ awardIssuer(award) }}
                        </p>
                        <p v-if="formatIssuedDate(award.issued_date)" style="font-size: 0.75rem; color: var(--sf-muted); margin-bottom: 0.75rem;">
                            {{ formatIssuedDate(award.issued_date) }}
                        </p>
                        <RichTextContent
                            v-if="awardDescription(award)"
                            :content="awardDescription(award)"
                            tag="p"
                            style="font-size: 0.875rem; color: var(--sf-gray); line-height: 1.6; margin-bottom: 1rem;"
                        />
                        <a
                            v-if="award.external_url"
                            :href="award.external_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-link"
                            style="display: inline-flex; align-items: center; gap: 0.375rem;"
                        >
                            <font-awesome-icon :icon="faExternalLinkAlt" />
                            {{ t('public.home.awards.viewLink') }}
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="public-section public-section-marble">
        <div class="public-container">
            <div class="public-section-header">
                <h2 class="public-section-title">
                    {{ t('public.home.contact.title') }}
                </h2>
                <p class="public-section-subtitle">
                    {{ t('public.home.contact.subtitle') }}
                </p>
            </div>

            <div class="public-split">
                <div
                    class="public-contact-shell"
                    :style="!(hasContactInfo || socialLinksWithoutWebsite.length) ? { maxWidth: '42rem', marginInline: 'auto', width: '100%' } : undefined"
                >
                    <form @submit.prevent="submitContactForm">
                        <p style="font-size: 0.875rem; margin-bottom: 1.25rem;">
                            {{ t('public.home.contact.contactMethodHint') }}
                        </p>

                        <div v-if="contactFormSuccess" class="public-success" style="margin-bottom: 1.25rem;">
                            {{ t('public.home.contact.messageSentSuccess') }}
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                {{ t('public.home.contact.formName') }} *
                            </label>
                            <input
                                v-model="contactForm.name"
                                type="text"
                                required
                                class="public-form-input"
                                :placeholder="t('public.home.contact.formNamePlaceholder')"
                            />
                            <p v-if="contactForm.errors.name" class="public-form-error">{{ contactForm.errors.name }}</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                    {{ t('public.home.contact.formEmail') }}
                                </label>
                                <input
                                    v-model="contactForm.email"
                                    type="email"
                                    class="public-form-input"
                                    :placeholder="t('public.home.contact.formEmailPlaceholder')"
                                />
                                <p v-if="contactForm.errors.email" class="public-form-error">{{ contactForm.errors.email }}</p>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                    {{ t('public.home.contact.formPhone') }}
                                </label>
                                <input
                                    v-model="contactForm.phone"
                                    type="text"
                                    class="public-form-input"
                                    :placeholder="t('public.home.contact.formPhonePlaceholder')"
                                />
                                <p v-if="contactForm.errors.phone" class="public-form-error">{{ contactForm.errors.phone }}</p>
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                {{ t('public.home.contact.formSubject') }}
                            </label>
                            <input
                                v-model="contactForm.subject"
                                type="text"
                                class="public-form-input"
                                :placeholder="t('public.home.contact.formSubjectPlaceholder')"
                            />
                            <p v-if="contactForm.errors.subject" class="public-form-error">{{ contactForm.errors.subject }}</p>
                        </div>

                        <div style="margin-bottom: 1.25rem;">
                            <label style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                {{ t('public.home.contact.formMessage') }} *
                            </label>
                            <textarea
                                v-model="contactForm.message"
                                rows="5"
                                required
                                class="public-form-textarea"
                                :placeholder="t('public.home.contact.formMessagePlaceholder')"
                            ></textarea>
                            <p v-if="contactForm.errors.message" class="public-form-error">{{ contactForm.errors.message }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="contactForm.processing"
                            class="public-btn-primary"
                            style="width: 100%;"
                        >
                            {{ contactForm.processing ? t('public.home.contact.formSending') : t('public.home.contact.formSend') }}
                        </button>
                    </form>
                </div>

                <div v-if="hasContactInfo || socialLinksWithoutWebsite.length">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                        <a
                            v-if="companyInfo.phone"
                            :href="`tel:${companyInfo.phone}`"
                            class="public-info-card"
                        >
                            <div class="public-info-icon">
                                <font-awesome-icon :icon="faPhone" />
                            </div>
                            <div>
                                <p style="font-size: 0.875rem; color: var(--sf-muted); margin-bottom: 0.25rem;">{{ t('public.home.contact.phone') }}</p>
                                <p style="font-weight: 700;">{{ companyInfo.phone }}</p>
                            </div>
                        </a>

                        <a
                            v-if="companyInfo.email"
                            :href="`mailto:${companyInfo.email}`"
                            class="public-info-card"
                        >
                            <div class="public-info-icon">
                                <font-awesome-icon :icon="faEnvelope" />
                            </div>
                            <div style="min-width: 0;">
                                <p style="font-size: 0.875rem; color: var(--sf-muted); margin-bottom: 0.25rem;">{{ t('public.home.contact.email') }}</p>
                                <p style="font-weight: 700; word-break: break-all;">{{ companyInfo.email }}</p>
                            </div>
                        </a>

                        <div v-if="addressText" class="public-info-card">
                            <div class="public-info-icon">
                                <font-awesome-icon :icon="faMapMarkerAlt" />
                            </div>
                            <div>
                                <p style="font-size: 0.875rem; color: var(--sf-muted); margin-bottom: 0.25rem;">{{ t('public.home.contact.address') }}</p>
                                <p style="font-weight: 700; white-space: pre-line;">{{ addressText }}</p>
                            </div>
                        </div>

                        <a
                            v-if="companyInfo.website"
                            :href="companyInfo.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-info-card"
                        >
                            <div class="public-info-icon">
                                <font-awesome-icon :icon="faGlobe" />
                            </div>
                            <div style="min-width: 0;">
                                <p style="font-size: 0.875rem; color: var(--sf-muted); margin-bottom: 0.25rem;">{{ t('public.home.contact.website') }}</p>
                                <p style="font-weight: 700; word-break: break-all;">{{ companyInfo.website }}</p>
                            </div>
                        </a>
                    </div>

                    <SocialLinks
                        :company-info="companyInfo"
                        variant="contact"
                        :include-website="false"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

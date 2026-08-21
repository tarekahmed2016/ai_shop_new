<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'
import RichTextContent from '../Common/RichTextContent.vue'

const props = defineProps({
    slides: {
        type: Array,
        default: () => [],
    },
    fallbackTitle: {
        type: String,
        default: '',
    },
    fallbackDescription: {
        type: String,
        default: '',
    },
    fallbackLogo: {
        type: String,
        default: null,
    },
})

const { t, locale } = useI18n()

const activeIndex = ref(0)
const isPaused = ref(false)
let timer = null

const normalizedSlides = computed(() => {
    if (props.slides.length) {
        return props.slides
    }

    return [{
        title_ar: props.fallbackTitle,
        title_en: props.fallbackTitle,
        description_ar: props.fallbackDescription,
        description_en: props.fallbackDescription,
        cta_text_ar: null,
        cta_text_en: null,
        cta_url: null,
        image: null,
    }]
})

const slideCount = computed(() => normalizedSlides.value.length)
const currentSlide = computed(() => normalizedSlides.value[activeIndex.value] || normalizedSlides.value[0])

const slideTitle = (slide) => resolveBilingualField(slide, 'title', locale.value)
const slideDescription = (slide) => resolveBilingualField(slide, 'description', locale.value)
const slideCtaText = (slide) => resolveBilingualField(slide, 'cta_text', locale.value)

const goTo = (index) => {
    if (!slideCount.value) return
    activeIndex.value = (index + slideCount.value) % slideCount.value
}

const goNext = () => goTo(activeIndex.value + 1)
const goPrev = () => goTo(activeIndex.value - 1)

const isRtl = computed(() => locale.value === 'ar')

const chevronLeftPath = 'M15 19l-7-7 7-7'
const chevronRightPath = 'M9 5l7 7-7 7'

const prevIconPath = computed(() => (isRtl.value ? chevronRightPath : chevronLeftPath))
const nextIconPath = computed(() => (isRtl.value ? chevronLeftPath : chevronRightPath))

const clearTimer = () => {
    if (timer) {
        clearInterval(timer)
        timer = null
    }
}

const startTimer = () => {
    clearTimer()
    if (slideCount.value <= 1 || isPaused.value) return

    timer = setInterval(() => {
        goNext()
    }, 6000)
}

const pause = () => {
    isPaused.value = true
    clearTimer()
}

const resume = () => {
    isPaused.value = false
    startTimer()
}

watch(slideCount, () => {
    if (activeIndex.value >= slideCount.value) {
        activeIndex.value = 0
    }
    startTimer()
})

onMounted(startTimer)
onUnmounted(clearTimer)
</script>

<template>
    <section
        id="home"
        class="public-hero-slider"
        aria-roledescription="carousel"
        :aria-label="t('public.home.hero.sliderLabel')"
        @mouseenter="pause"
        @mouseleave="resume"
        @focusin="pause"
        @focusout="resume"
    >
        <div
            v-for="(slide, index) in normalizedSlides"
            :key="`slide-${index}`"
            class="public-hero-slide"
            :class="{ 'is-active': index === activeIndex }"
            :aria-hidden="index !== activeIndex"
        >
            <div
                class="public-hero-slide-bg"
                :style="slide.image ? { backgroundImage: `url(${slide.image})` } : undefined"
            ></div>
            <div class="public-container public-hero-slide-inner">
                <div class="public-hero-content">
                    <img
                        v-if="fallbackLogo && index === 0 && !slide.image"
                        :src="fallbackLogo"
                        :alt="fallbackTitle"
                        class="public-hero-logo"
                    />
                    <p v-if="!slideTitle(slide) && slides.length" class="public-hero-eyebrow">
                        {{ t('public.home.hero.eyebrow') }}
                    </p>
                    <h1 v-if="slideTitle(slide)" class="public-hero-title">
                        {{ slideTitle(slide) }}
                    </h1>
                    <div v-if="slideDescription(slide)" class="public-hero-description">
                        <RichTextContent
                            :content="slideDescription(slide)"
                            tag="div"
                        />
                    </div>
                    <div v-if="slideCtaText(slide) && slide.cta_url" class="public-hero-actions">
                        <a :href="slide.cta_url" class="public-btn-primary">
                            {{ slideCtaText(slide) }}
                        </a>
                    </div>
                    <div v-else-if="!slides.length" class="public-hero-actions">
                        <a href="#services" class="public-btn-primary">
                            {{ t('public.home.hero.ctaServices') }}
                        </a>
                        <a href="#contact" class="public-btn-outline">
                            {{ t('public.home.hero.ctaContact') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <button
            v-if="slideCount > 1"
            type="button"
            class="public-hero-nav public-hero-nav-prev"
            :aria-label="t('public.home.hero.prevSlide')"
            @click="goPrev"
        >
            <svg class="public-hero-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="prevIconPath" />
            </svg>
        </button>
        <button
            v-if="slideCount > 1"
            type="button"
            class="public-hero-nav public-hero-nav-next"
            :aria-label="t('public.home.hero.nextSlide')"
            @click="goNext"
        >
            <svg class="public-hero-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="nextIconPath" />
            </svg>
        </button>

        <div v-if="slideCount > 1" class="public-hero-dots" role="tablist" :aria-label="t('public.home.hero.slideIndicators')">
            <button
                v-for="(_, index) in normalizedSlides"
                :key="`dot-${index}`"
                type="button"
                role="tab"
                class="public-hero-dot"
                :class="{ 'is-active': index === activeIndex }"
                :aria-selected="index === activeIndex"
                :aria-label="t('public.home.hero.goToSlide', { number: index + 1 })"
                @click="goTo(index)"
            ></button>
        </div>
    </section>
</template>

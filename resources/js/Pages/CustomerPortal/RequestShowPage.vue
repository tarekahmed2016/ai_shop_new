<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faWhatsapp } from '@fortawesome/free-brands-svg-icons'
import CategoryTreeSelector from '../../Components/Features/Categories/CategoryTreeSelector.vue'

const { t, locale } = useI18n()
const page = usePage()
const request = computed(() => page.props.request || {})
const offers = computed(() => page.props.offers || [])
const classification = computed(() => page.props.classification || null)
const availableCategories = computed(() => page.props.availableCategories || [])
const showManual = ref(false)
const selectedSuggestion = ref('')
const additionalDetails = ref('')

const confirmForm = useForm({
    category_id: '',
})
const retryForm = useForm({
    additional_details: '',
    image: null,
})
const categoryForm = useForm({
    category_id: '',
})

const isPendingClassification = computed(() => request.value.status_formatted?.name === 'PendingClassification')

const isMobileClient = computed(() => {
    if (typeof navigator === 'undefined') {
        return false
    }

    const ua = navigator.userAgent || ''
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua)) {
        return true
    }

    return navigator.maxTouchPoints > 1 && window.matchMedia('(pointer: coarse)').matches
})

const offerWhatsAppHref = (offer) => (
    isMobileClient.value ? offer.whatsapp_mobile_url : offer.whatsapp_web_url
)

const categoryName = computed(() => {
    const category = request.value.category
    if (!category) return '—'
    return locale.value === 'ar' ? (category.name_ar || category.name_en) : (category.name_en || category.name_ar)
})

const categoryLabel = (row) => {
    if (!row) return ''
    return locale.value === 'ar' ? (row.name_ar || row.name_en) : (row.name_en || row.name_ar)
}

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—'

const formatConfidence = (value) => {
    if (value === null || value === undefined || value === '') return '—'
    return `${Math.round(Number(value) * 100)}%`
}

const confirmSuggestion = (categoryPublicId) => {
    if (!classification.value?.public_id || !categoryPublicId) return
    confirmForm.category_id = categoryPublicId
    confirmForm.post(route('customer.requests.classifications.confirm', classification.value.public_id))
}

const retryAnalysis = () => {
    retryForm.additional_details = additionalDetails.value
    retryForm.post(route('customer.requests.classify.resume', request.value.public_id), {
        forceFormData: true,
        preserveScroll: true,
    })
}

const submitManualCategory = () => {
    categoryForm.post(route('customer.requests.category', request.value.public_id))
}

const processing = computed(() => confirmForm.processing || retryForm.processing || categoryForm.processing)

watch(classification, (value) => {
    selectedSuggestion.value = value?.primary?.category_public_id || value?.suggestions?.[0]?.category_public_id || ''
}, { immediate: true })
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <Link :href="route('customer.requests.index')" class="text-blue-600 text-sm">← {{ t('customerPortal.requests.back') }}</Link>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100 mt-2">{{ t('customerPortal.show.title') }}</h1>
                <p class="text-muted muted-color mt-1 font-mono text-sm">{{ request.public_id }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.status') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ request.status_formatted?.label }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.category') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ categoryName }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.date') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ formatDate(request.created_at) }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.create.requestText') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ request.request_text }}</p>
                </div>
                <div v-if="request.has_image">
                    <p class="text-label text-muted mb-2">{{ t('customerPortal.requests.image') }}</p>
                    <img :src="route('customer.requests.image', request.public_id)" alt="" class="max-w-full rounded-md border border-gray-200 dark:border-gray-700" />
                </div>
            </div>

            <div v-if="isPendingClassification" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.classify.resumeTitle') }}</h2>
                <p v-if="classification?.failed" class="form-error">{{ t('customerPortal.classify.failed') }}</p>
                <p v-if="classification?.suggested_category_inactive" class="form-error">{{ t('customerPortal.classify.inactiveSuggestion') }}</p>
                <p v-if="classification?.needs_more_information && classification?.question" class="text-body font-medium text-amber-700 dark:text-amber-300">
                    {{ classification.question }}
                </p>

                <p class="text-body">{{ t('customerPortal.classify.detectedItem') }}: {{ classification?.detected_item || '—' }}</p>
                <p class="text-body">{{ t('customerPortal.classify.suggestedCategory') }}: {{ categoryLabel(classification?.primary || classification?.suggested_category) }}</p>
                <p class="text-muted text-sm">{{ t('customerPortal.classify.confidence') }}: {{ formatConfidence(classification?.confidence) }}</p>
                <p v-if="classification?.reason" class="text-muted text-sm">{{ t('customerPortal.classify.reason') }}: {{ classification.reason }}</p>

                <div v-if="classification?.suggestions?.length" class="space-y-2">
                    <p class="text-label text-muted">{{ t('customerPortal.classify.alternatives') }}</p>
                    <label v-for="row in classification.suggestions" :key="row.category_public_id" class="flex items-start gap-2 text-body">
                        <input v-model="selectedSuggestion" type="radio" :value="row.category_public_id" class="mt-1" />
                        <span>{{ categoryLabel(row) }} <span class="text-muted text-sm">({{ formatConfidence(row.confidence) }})</span></span>
                    </label>
                </div>

                <textarea
                    v-if="classification?.needs_more_information || classification?.failed || !classification?.can_confirm"
                    v-model="additionalDetails"
                    rows="3"
                    class="form-input text-body"
                    :placeholder="t('customerPortal.classify.moreDetails')"
                />

                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="classification?.can_confirm"
                        type="button"
                        class="btn btn-primary px-4 py-2 disabled:opacity-50"
                        :disabled="processing || !(selectedSuggestion || classification.primary?.category_public_id)"
                        @click="confirmSuggestion(selectedSuggestion || classification.primary?.category_public_id)"
                    >
                        {{ t('customerPortal.classify.confirmSend') }}
                    </button>
                    <button type="button" class="btn btn-secondary px-4 py-2" :disabled="processing" @click="showManual = !showManual">
                        {{ t('customerPortal.classify.changeCategory') }}
                    </button>
                    <button type="button" class="btn btn-secondary px-4 py-2 disabled:opacity-50" :disabled="processing" @click="retryAnalysis">
                        {{ retryForm.processing ? t('customerPortal.classify.analyzing') : t('customerPortal.classify.retryAnalysis') }}
                    </button>
                </div>
                <p v-if="confirmForm.errors.category_id" class="form-error">{{ confirmForm.errors.category_id }}</p>
                <p v-if="retryForm.errors.request_text" class="form-error">{{ retryForm.errors.request_text }}</p>
                <p v-if="retryForm.errors.additional_details" class="form-error">{{ retryForm.errors.additional_details }}</p>

                <div v-if="showManual" class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.category') }} <span class="text-red-500">*</span>
                    </label>
                    <CategoryTreeSelector
                        :categories="availableCategories"
                        :multiple="false"
                        :selectedId="categoryForm.category_id"
                        :emptyText="t('customerPortal.create.selectCategory')"
                        @select="categoryForm.category_id = $event"
                    />
                    <p v-if="categoryForm.errors.category_id" class="form-error">{{ categoryForm.errors.category_id }}</p>
                    <button type="button" class="btn btn-primary px-4 py-2 disabled:opacity-50" :disabled="processing || !categoryForm.category_id" @click="submitManualCategory">
                        {{ t('customerPortal.create.submit') }}
                    </button>
                </div>
            </div>

            <div v-if="!isPendingClassification" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.show.offersTitle') }}</h2>
                <p class="text-muted">{{ t('customerPortal.show.offersCount', { count: offers.length }) }}</p>
                <p v-if="offers.length === 0" class="text-muted">{{ t('customerPortal.show.noOffers') }}</p>
                <div v-for="offer in offers" :key="offer.public_id" class="border border-gray-200 dark:border-gray-700 rounded-md p-4 space-y-2">
                    <p class="text-body font-medium text-gray-900 dark:text-gray-100">{{ offer.merchant_name }}</p>
                    <p class="text-body">{{ offer.price }} {{ offer.currency }}</p>
                    <p class="text-body">{{ offer.availability_status_formatted?.label }}</p>
                    <p v-if="offer.notes" class="text-body whitespace-pre-wrap">{{ offer.notes }}</p>
                    <p class="text-muted text-sm">{{ t('customerPortal.show.validUntil') }}: {{ offer.valid_until || '—' }}</p>
                    <p class="text-muted text-sm">{{ t('customerPortal.show.submittedAt') }}: {{ formatDate(offer.submitted_at) }}</p>
                    <div v-if="offer.images?.length" class="flex flex-wrap gap-3 pt-2">
                        <img v-for="image in offer.images" :key="image.id" :src="image.url" alt="" class="h-24 rounded border border-gray-200 dark:border-gray-700" />
                    </div>
                    <div class="pt-3">
                        <a
                            v-if="offerWhatsAppHref(offer)"
                            :href="offerWhatsAppHref(offer)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-white bg-[#25D366] hover:bg-[#1ebe5d]"
                        >
                            <font-awesome-icon :icon="faWhatsapp" />
                            {{ t('customerPortal.show.contactWhatsApp') }}
                        </a>
                        <span
                            v-else
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-gray-500 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 cursor-not-allowed"
                        >
                            <font-awesome-icon :icon="faWhatsapp" />
                            {{ t('customerPortal.show.whatsappUnavailable') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

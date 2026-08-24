<script setup>
import { computed, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const page = usePage()
const classification = computed(() => page.props.classification || null)
const pendingRequest = computed(() => page.props.pendingRequest || null)
const requestQuota = computed(() => page.props.requestQuota || page.props.customerContext?.request_quota || {})
const isSuspended = computed(() => page.props.customerContext?.is_suspended === true)
const remaining = computed(() => Number(requestQuota.value.remaining ?? 0))
const atLimit = computed(() => remaining.value <= 0)

const selectedSuggestion = ref('')
const additionalDetails = ref('')

const classifyForm = useForm({
    request_text: '',
    additional_details: '',
    pending_request_id: '',
    image: null,
})

const confirmForm = useForm({
    category_id: '',
})

watch(classification, (value) => {
    if (value) {
        selectedSuggestion.value = value.primary?.category_public_id || value.suggestions?.[0]?.category_public_id || ''
        if (pendingRequest.value?.request_text) {
            classifyForm.request_text = pendingRequest.value.request_text
        }
        classifyForm.pending_request_id = pendingRequest.value?.public_id || ''
    }
}, { immediate: true })

const categoryLabel = (row) => {
    if (!row) return ''
    return locale.value === 'ar' ? (row.name_ar || row.name_en) : (row.name_en || row.name_ar)
}

const onFileChange = (event) => {
    classifyForm.image = event.target.files?.[0] || null
}

const analyze = () => {
    classifyForm.additional_details = additionalDetails.value
    classifyForm.pending_request_id = pendingRequest.value?.public_id || ''
    classifyForm.post(route('customer.requests.classify'), {
        forceFormData: true,
        preserveScroll: true,
    })
}

const confirmSuggestion = (categoryPublicId) => {
    if (!classification.value?.public_id || !categoryPublicId) return
    confirmForm.category_id = categoryPublicId
    confirmForm.post(route('customer.requests.classifications.confirm', classification.value.public_id))
}

const processing = computed(() => classifyForm.processing || confirmForm.processing)
const canSubmit = computed(() => !isSuspended.value && !atLimit.value && !!classifyForm.request_text && !processing.value)
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.create.title') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customerPortal.create.subtitle') }}</p>
            </div>

            <div v-if="isSuspended" class="mb-4 rounded-md border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40 p-4">
                <p class="text-body text-red-800 dark:text-red-200">{{ t('customerPortal.suspended.message') }}</p>
            </div>

            <div v-else class="mb-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <p class="text-body text-gray-900 dark:text-gray-100">
                    {{ t('customerPortal.quota.today', { used: requestQuota.used ?? 0, limit: requestQuota.daily_limit ?? 0, remaining: remaining }) }}
                </p>
                <p v-if="atLimit" class="form-error mt-2">{{ t('customerPortal.quota.reached') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-5">
                <div>
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.requestText') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="classifyForm.request_text" rows="6" required class="form-input text-body" :disabled="isSuspended || atLimit" />
                    <p v-if="classifyForm.errors.request_text" class="form-error">{{ classifyForm.errors.request_text }}</p>
                </div>

                <div>
                    <label class="form-label text-label">{{ t('customerPortal.create.image') }}</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" class="form-input text-body" :disabled="isSuspended || atLimit" @change="onFileChange" />
                    <p class="text-muted text-sm mt-1">{{ t('customerPortal.create.imageHint') }}</p>
                    <p v-if="classifyForm.errors.image" class="form-error">{{ classifyForm.errors.image }}</p>
                </div>

                <div v-if="!classification" class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="btn btn-primary px-4 py-2 disabled:opacity-50"
                        :disabled="!canSubmit"
                        @click="analyze"
                    >
                        {{ classifyForm.processing ? t('customerPortal.classify.analyzing') : t('customerPortal.create.submit') }}
                    </button>
                </div>

                <div v-if="classification" class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p v-if="classification.failed" class="form-error">{{ t('customerPortal.classify.failed') }}</p>

                    <template v-else-if="classification.confidence_band === 'high' && classification.primary">
                        <p class="text-muted">{{ t('customerPortal.classify.highIntro') }}</p>
                        <p class="text-body font-medium">{{ t('customerPortal.classify.detectedItem') }}: {{ classification.detected_item || '—' }}</p>
                        <p class="text-body">{{ t('customerPortal.classify.suggestedCategory') }}: {{ categoryLabel(classification.primary) }}</p>
                        <p class="text-muted text-sm">{{ t('customerPortal.classify.bandHigh') }}</p>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="btn btn-primary px-4 py-2 disabled:opacity-50" :disabled="processing" @click="confirmSuggestion(classification.primary.category_public_id)">
                                {{ t('customerPortal.classify.confirmSubmit') }}
                            </button>
                        </div>
                    </template>

                    <template v-else-if="classification.confidence_band === 'medium' && classification.suggestions?.length">
                        <p class="text-muted">{{ t('customerPortal.classify.mediumIntro') }}</p>
                        <p class="text-muted text-sm">{{ t('customerPortal.classify.bandMedium') }}</p>
                        <label v-for="row in classification.suggestions" :key="row.category_public_id" class="flex items-start gap-2 text-body">
                            <input v-model="selectedSuggestion" type="radio" :value="row.category_public_id" class="mt-1" />
                            <span>{{ categoryLabel(row) }}</span>
                        </label>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="btn btn-primary px-4 py-2 disabled:opacity-50" :disabled="processing || !selectedSuggestion" @click="confirmSuggestion(selectedSuggestion)">
                                {{ t('customerPortal.classify.continue') }}
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <p class="text-muted">{{ t('customerPortal.classify.lowIntro') }}</p>
                        <p class="text-muted text-sm">{{ t('customerPortal.classify.bandLow') }}</p>
                        <p v-if="classification.question" class="text-body">{{ classification.question }}</p>
                        <textarea v-model="additionalDetails" rows="3" class="form-input text-body" :placeholder="t('customerPortal.classify.moreDetails')" />
                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="btn btn-primary px-4 py-2 disabled:opacity-50" :disabled="processing" @click="analyze">
                                {{ t('customerPortal.classify.tryAgain') }}
                            </button>
                        </div>
                    </template>
                    <p v-if="confirmForm.errors.category_id" class="form-error">{{ confirmForm.errors.category_id }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

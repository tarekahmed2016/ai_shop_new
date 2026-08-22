<script setup>
import { computed, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CategoryTreeSelector from '../../Components/Features/Categories/CategoryTreeSelector.vue'

const { t, locale } = useI18n()
const page = usePage()
const availableCategories = computed(() => page.props.availableCategories || [])
const classification = computed(() => page.props.classification || null)
const pendingRequest = computed(() => page.props.pendingRequest || null)

const path = ref('choose')
const selectedSuggestion = ref('')
const additionalDetails = ref('')

const createForm = useForm({
    category_id: '',
    request_text: '',
    image: null,
})

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
        path.value = 'result'
        selectedSuggestion.value = value.primary?.category_public_id || value.suggestions?.[0]?.category_public_id || ''
        if (pendingRequest.value?.request_text) {
            createForm.request_text = pendingRequest.value.request_text
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
    const file = event.target.files?.[0] || null
    createForm.image = file
    classifyForm.image = file
}

const submitManual = () => {
    createForm.post(route('customer.requests.store'), {
        forceFormData: true,
    })
}

const analyze = () => {
    classifyForm.request_text = createForm.request_text
    classifyForm.additional_details = additionalDetails.value
    classifyForm.pending_request_id = pendingRequest.value?.public_id || ''
    classifyForm.image = createForm.image
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

const processing = computed(() => createForm.processing || classifyForm.processing || confirmForm.processing)
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.create.title') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customerPortal.create.subtitle') }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-5">
                <div>
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.requestText') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="createForm.request_text" rows="6" required class="form-input text-body" />
                    <p v-if="createForm.errors.request_text" class="form-error">{{ createForm.errors.request_text }}</p>
                    <p v-if="classifyForm.errors.request_text" class="form-error">{{ classifyForm.errors.request_text }}</p>
                </div>

                <div>
                    <label class="form-label text-label">{{ t('customerPortal.create.image') }}</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" class="form-input text-body" @change="onFileChange" />
                    <p class="text-muted text-sm mt-1">{{ t('customerPortal.create.imageHint') }}</p>
                    <p v-if="createForm.errors.image" class="form-error">{{ createForm.errors.image }}</p>
                </div>

                <div class="space-y-3">
                    <p class="form-label text-label">{{ t('customerPortal.classify.howToChoose') }}</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button
                            type="button"
                            class="btn btn-primary px-4 py-2 disabled:opacity-50"
                            :disabled="processing || !createForm.request_text"
                            @click="analyze"
                        >
                            {{ classifyForm.processing ? t('customerPortal.classify.analyzing') : t('customerPortal.classify.helpMe') }}
                        </button>
                        <button type="button" class="btn btn-secondary px-4 py-2" :disabled="processing" @click="path = 'manual'">
                            {{ t('customerPortal.classify.chooseManually') }}
                        </button>
                    </div>
                </div>

                <div v-if="path === 'manual'" class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.category') }} <span class="text-red-500">*</span>
                    </label>
                    <CategoryTreeSelector
                        :categories="availableCategories"
                        :multiple="false"
                        :selectedId="createForm.category_id"
                        :emptyText="t('customerPortal.create.selectCategory')"
                        @select="createForm.category_id = $event"
                    />
                    <p v-if="createForm.errors.category_id" class="form-error">{{ createForm.errors.category_id }}</p>
                    <button type="button" class="btn btn-primary px-4 py-2 disabled:opacity-50" :disabled="processing" @click="submitManual">
                        {{ createForm.processing ? t('customerPortal.create.submitting') : t('customerPortal.create.submit') }}
                    </button>
                </div>

                <div v-if="path === 'result' && classification" class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
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
                            <button type="button" class="btn btn-secondary px-4 py-2" @click="path = 'manual'">
                                {{ t('customerPortal.classify.changeCategory') }}
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
                            <button type="button" class="btn btn-secondary px-4 py-2" @click="path = 'manual'">
                                {{ t('customerPortal.classify.chooseManually') }}
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
                            <button type="button" class="btn btn-secondary px-4 py-2" @click="path = 'manual'">
                                {{ t('customerPortal.classify.chooseManually') }}
                            </button>
                        </div>
                    </template>
                    <p v-if="confirmForm.errors.category_id" class="form-error">{{ confirmForm.errors.category_id }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

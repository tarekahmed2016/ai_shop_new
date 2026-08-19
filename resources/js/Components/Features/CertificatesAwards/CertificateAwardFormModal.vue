<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useCertificatesAwards } from '../../../Composables/useCertificatesAwards.js'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import RichTextEditor from '../../Common/asyncRichTextEditor.js'

const { t } = useI18n()
const { fetchNextOrdering } = useCertificatesAwards()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  certificateAward: {
    type: Object,
    default: null
  },
  nextData: {
    type: Object,
    default: null
  },
  defaultType: {
    type: String,
    default: 'certificate'
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  type: 'certificate',
  title_ar: '',
  title_en: '',
  issuer_ar: '',
  issuer_en: '',
  description_ar: '',
  description_en: '',
  issued_date: '',
  external_url: '',
  ordering: '',
  is_active: true,
  image: null,
})

const imagePreview = ref(null)
const imageInput = ref(null)
const imageFileName = ref(null)

const handleImageChange = (event) => {
  const file = event.target.files[0] || null
  form.image = file
  imageFileName.value = file?.name || null
  imagePreview.value = file ? URL.createObjectURL(file) : null
}

const refreshOrderingForType = async (type) => {
  if (props.certificateAward) return

  try {
    form.ordering = await fetchNextOrdering(type)
  } catch {
    form.ordering = ''
  }
}

watch(() => form.type, (type) => {
  if (props.isOpen && !props.certificateAward) {
    refreshOrderingForType(type)
  }
})

watch(() => props.isOpen, async (isOpen) => {
  if (!isOpen) return

  if (props.certificateAward) {
    form.type = props.certificateAward.type?.value || props.certificateAward.type || 'certificate'
    form.title_ar = props.certificateAward.title_ar || ''
    form.title_en = props.certificateAward.title_en || ''
    form.issuer_ar = props.certificateAward.issuer_ar || ''
    form.issuer_en = props.certificateAward.issuer_en || ''
    form.description_ar = props.certificateAward.description_ar || ''
    form.description_en = props.certificateAward.description_en || ''
    form.issued_date = props.certificateAward.issued_date ? props.certificateAward.issued_date.substring(0, 10) : ''
    form.external_url = props.certificateAward.external_url || ''
    form.ordering = props.certificateAward.ordering ?? ''
    form.is_active = Boolean(props.certificateAward.is_active)
    form.image = null
    imagePreview.value = props.certificateAward.attachment?.asset_path || null
    imageFileName.value = null
  } else {
    form.reset()
    form.is_active = true
    form.type = props.nextData?.type || props.defaultType || 'certificate'
    form.ordering = props.nextData?.ordering ?? ''
    imagePreview.value = null
    imageFileName.value = null
  }

  if (imageInput.value) {
    imageInput.value.value = ''
  }

  form.clearErrors()
}, { immediate: true })

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  }

  props.certificateAward
    ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('certificates-awards.update', props.certificateAward.id), options)
    : form.post(route('certificates-awards.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  imagePreview.value = null
  imageFileName.value = null
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="certificate-award-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="certificate-award-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ certificateAward ? t('certificatesAwards.form.editTitle') : t('certificatesAwards.form.addTitle') }}
        </h2>
        <button
          @click="handleClose"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
        <div>
          <label class="form-label text-label">
            {{ t('certificatesAwards.form.typeLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.type" required class="form-input text-body">
            <option value="certificate">{{ t('certificatesAwards.form.typeCertificate') }}</option>
            <option value="award">{{ t('certificatesAwards.form.typeAward') }}</option>
          </select>
          <p v-if="form.errors.type" class="form-error">{{ form.errors.type }}</p>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
          <div>
            <label class="form-label text-label">
              {{ t('certificatesAwards.form.titleArLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.title_ar" type="text" required class="form-input text-body" :placeholder="t('certificatesAwards.form.titleArPlaceholder')" />
            <p v-if="form.errors.title_ar" class="form-error">{{ form.errors.title_ar }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.issuerArLabel') }}</label>
            <input v-model="form.issuer_ar" type="text" class="form-input text-body" :placeholder="t('certificatesAwards.form.issuerArPlaceholder')" />
            <p v-if="form.errors.issuer_ar" class="form-error">{{ form.errors.issuer_ar }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.descriptionArLabel') }}</label>
            <RichTextEditor
              v-model="form.description_ar"
              :active="isOpen"
              dir="rtl"
              :placeholder="t('certificatesAwards.form.descriptionArPlaceholder')"
            />
            <p v-if="form.errors.description_ar" class="form-error">{{ form.errors.description_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
          <div>
            <label class="form-label text-label">
              {{ t('certificatesAwards.form.titleEnLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.title_en" type="text" required class="form-input text-body" :placeholder="t('certificatesAwards.form.titleEnPlaceholder')" />
            <p v-if="form.errors.title_en" class="form-error">{{ form.errors.title_en }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.issuerEnLabel') }}</label>
            <input v-model="form.issuer_en" type="text" class="form-input text-body" :placeholder="t('certificatesAwards.form.issuerEnPlaceholder')" />
            <p v-if="form.errors.issuer_en" class="form-error">{{ form.errors.issuer_en }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.descriptionEnLabel') }}</label>
            <RichTextEditor
              v-model="form.description_en"
              :active="isOpen"
              dir="ltr"
              :placeholder="t('certificatesAwards.form.descriptionEnPlaceholder')"
            />
            <p v-if="form.errors.description_en" class="form-error">{{ form.errors.description_en }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('certificatesAwards.form.otherInfoTitle') }}</h3>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.issueDateLabel') }}</label>
            <input v-model="form.issued_date" type="date" class="form-input text-body" />
            <p v-if="form.errors.issued_date" class="form-error">{{ form.errors.issued_date }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('certificatesAwards.form.externalUrlLabel') }}</label>
            <input v-model="form.external_url" type="url" class="form-input text-body" :placeholder="t('certificatesAwards.form.externalUrlPlaceholder')" />
            <p v-if="form.errors.external_url" class="form-error">{{ form.errors.external_url }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">
                {{ t('certificatesAwards.form.orderingLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.ordering" type="number" min="0" required class="form-input text-body" :placeholder="t('certificatesAwards.form.orderingPlaceholder')" />
              <p v-if="form.errors.ordering" class="form-error">{{ form.errors.ordering }}</p>
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span class="text-label">{{ t('certificatesAwards.form.activeLabel') }}</span>
              </label>
              <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
            </div>
          </div>
          <div>
            <label class="form-label text-label">
              {{ t('certificatesAwards.form.imageLabel') }} <span v-if="!certificateAward" class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-4">
              <img v-if="imagePreview" :src="imagePreview" alt="Image preview" class="h-16 w-28 rounded-md border border-gray-200 dark:border-gray-700 object-contain bg-white p-1" />
              <div class="flex flex-col gap-1.5 flex-1">
                <button type="button" @click="imageInput.click()" class="btn btn-secondary px-4 py-2 w-full cursor-pointer">
                  {{ t('certificatesAwards.form.chooseFile') }}
                </button>
                <span class="text-sm text-muted muted-color truncate text-center">
                  {{ imageFileName || t('certificatesAwards.form.noFileChosen') }}
                </span>
                <input ref="imageInput" type="file" accept="image/*" :required="!certificateAward" @change="handleImageChange" class="hidden" />
              </div>
            </div>
            <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="handleClose" :disabled="form.processing" class="btn btn-secondary px-4 py-2">
            {{ t('certificatesAwards.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('certificatesAwards.form.saving') : t('certificatesAwards.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

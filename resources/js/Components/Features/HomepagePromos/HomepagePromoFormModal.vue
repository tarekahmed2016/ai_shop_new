<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useHomepagePromos } from '../../../Composables/useHomepagePromos.js'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import RichTextEditor from '../../Common/asyncRichTextEditor.js'

const { t, locale } = useI18n()
const { fetchNextOrdering } = useHomepagePromos()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  homepagePromo: {
    type: Object,
    default: null
  },
  nextData: {
    type: Object,
    default: null
  },
  defaultType: {
    type: String,
    default: 'feature_band'
  },
  promoTypes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  type: 'feature_band',
  title_ar: '',
  title_en: '',
  description_ar: '',
  description_en: '',
  cta_text_ar: '',
  cta_text_en: '',
  cta_url: '',
  layout_variant: 'content_left',
  ordering: '',
  is_active: true,
  image: null,
  badge_image: null,
  remove_badge: false,
})

const imagePreview = ref(null)
const imageInput = ref(null)
const imageFileName = ref(null)

const badgePreview = ref(null)
const badgeInput = ref(null)
const badgeFileName = ref(null)
const hasExistingBadge = ref(false)

const layoutOptions = [
  { value: 'content_left', labelKey: 'homepagePromos.form.layoutContentLeft' },
  { value: 'content_right', labelKey: 'homepagePromos.form.layoutContentRight' },
]

const imageRequired = computed(() => {
  if (props.homepagePromo) return false
  return ['feature_band', 'promo_strip'].includes(form.type)
})

const showMainImage = computed(() => ['feature_band', 'promo_strip'].includes(form.type))
const showBadgeImage = computed(() => form.type === 'feature_band')

const typeLabel = (promoType) => locale.value === 'ar' ? promoType.label : promoType.name

const handleImageChange = (event) => {
  const file = event.target.files[0] || null
  form.image = file
  imageFileName.value = file?.name || null
  imagePreview.value = file ? URL.createObjectURL(file) : null
}

const handleBadgeChange = (event) => {
  const file = event.target.files[0] || null
  form.badge_image = file
  badgeFileName.value = file?.name || null
  badgePreview.value = file ? URL.createObjectURL(file) : null
  if (file) {
    form.remove_badge = false
  }
}

const refreshOrderingForType = async (type) => {
  if (props.homepagePromo) return

  try {
    form.ordering = await fetchNextOrdering(type)
  } catch {
    form.ordering = ''
  }
}

watch(() => form.type, (type) => {
  if (props.isOpen && !props.homepagePromo) {
    refreshOrderingForType(type)
  }
})

watch(() => props.isOpen, async (isOpen) => {
  if (!isOpen) return

  if (props.homepagePromo) {
    form.type = props.homepagePromo.type?.value || props.homepagePromo.type || 'feature_band'
    form.title_ar = props.homepagePromo.title_ar || ''
    form.title_en = props.homepagePromo.title_en || ''
    form.description_ar = props.homepagePromo.description_ar || ''
    form.description_en = props.homepagePromo.description_en || ''
    form.cta_text_ar = props.homepagePromo.cta_text_ar || ''
    form.cta_text_en = props.homepagePromo.cta_text_en || ''
    form.cta_url = props.homepagePromo.cta_url || ''
    form.layout_variant = props.homepagePromo.layout_variant?.value || props.homepagePromo.layout_variant || 'content_left'
    form.ordering = props.homepagePromo.ordering ?? ''
    form.is_active = Boolean(props.homepagePromo.is_active)
    form.image = null
    form.badge_image = null
    form.remove_badge = false
    imagePreview.value = props.homepagePromo.attachment?.asset_path || null
    badgePreview.value = props.homepagePromo.badge_attachment?.asset_path || null
    hasExistingBadge.value = Boolean(props.homepagePromo.badge_attachment?.asset_path)
    imageFileName.value = null
    badgeFileName.value = null
  } else {
    form.reset()
    form.is_active = true
    form.type = props.nextData?.type || props.defaultType || 'feature_band'
    form.layout_variant = 'content_left'
    form.ordering = props.nextData?.ordering ?? ''
    form.remove_badge = false
    imagePreview.value = null
    badgePreview.value = null
    hasExistingBadge.value = false
    imageFileName.value = null
    badgeFileName.value = null
  }

  if (imageInput.value) {
    imageInput.value.value = ''
  }

  if (badgeInput.value) {
    badgeInput.value.value = ''
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

  props.homepagePromo
    ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('homepage-promos.update', props.homepagePromo.id), options)
    : form.post(route('homepage-promos.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  imagePreview.value = null
  badgePreview.value = null
  hasExistingBadge.value = false
  imageFileName.value = null
  badgeFileName.value = null
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="homepage-promo-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="homepage-promo-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ homepagePromo ? t('homepagePromos.form.editTitle') : t('homepagePromos.form.addTitle') }}
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
            {{ t('homepagePromos.form.typeLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.type" required class="form-input text-body">
            <option v-for="promoType in promoTypes" :key="promoType.value" :value="promoType.value">
              {{ typeLabel(promoType) }}
            </option>
          </select>
          <p v-if="form.errors.type" class="form-error">{{ form.errors.type }}</p>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.titleArLabel') }}</label>
            <input
              v-model="form.title_ar"
              type="text"
              class="form-input text-body"
              :placeholder="t('homepagePromos.form.titleArPlaceholder')"
            />
            <p v-if="form.errors.title_ar" class="form-error">{{ form.errors.title_ar }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.descriptionArLabel') }}</label>
            <RichTextEditor
              v-model="form.description_ar"
              :active="isOpen"
              dir="rtl"
              :placeholder="t('homepagePromos.form.descriptionArPlaceholder')"
            />
            <p v-if="form.errors.description_ar" class="form-error">{{ form.errors.description_ar }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.ctaTextArLabel') }}</label>
            <input
              v-model="form.cta_text_ar"
              type="text"
              class="form-input text-body"
              :placeholder="t('homepagePromos.form.ctaTextArPlaceholder')"
            />
            <p v-if="form.errors.cta_text_ar" class="form-error">{{ form.errors.cta_text_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.titleEnLabel') }}</label>
            <input
              v-model="form.title_en"
              type="text"
              class="form-input text-body"
              :placeholder="t('homepagePromos.form.titleEnPlaceholder')"
            />
            <p v-if="form.errors.title_en" class="form-error">{{ form.errors.title_en }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.descriptionEnLabel') }}</label>
            <RichTextEditor
              v-model="form.description_en"
              :active="isOpen"
              dir="ltr"
              :placeholder="t('homepagePromos.form.descriptionEnPlaceholder')"
            />
            <p v-if="form.errors.description_en" class="form-error">{{ form.errors.description_en }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.ctaTextEnLabel') }}</label>
            <input
              v-model="form.cta_text_en"
              type="text"
              class="form-input text-body"
              :placeholder="t('homepagePromos.form.ctaTextEnPlaceholder')"
            />
            <p v-if="form.errors.cta_text_en" class="form-error">{{ form.errors.cta_text_en }}</p>
          </div>
        </div>

        <div>
          <label class="form-label text-label">{{ t('homepagePromos.form.ctaUrlLabel') }}</label>
          <input
            v-model="form.cta_url"
            type="text"
            class="form-input text-body"
            :placeholder="t('homepagePromos.form.ctaUrlPlaceholder')"
          />
          <p v-if="form.errors.cta_url" class="form-error">{{ form.errors.cta_url }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">{{ t('homepagePromos.form.layoutLabel') }}</label>
            <select v-model="form.layout_variant" class="form-input text-body">
              <option v-for="layout in layoutOptions" :key="layout.value" :value="layout.value">
                {{ t(layout.labelKey) }}
              </option>
            </select>
            <p v-if="form.errors.layout_variant" class="form-error">{{ form.errors.layout_variant }}</p>
          </div>

          <div>
            <label class="form-label text-label">
              {{ t('homepagePromos.form.orderingLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.ordering"
              type="number"
              min="0"
              required
              class="form-input text-body"
              :placeholder="t('homepagePromos.form.orderingPlaceholder')"
            />
            <p v-if="form.errors.ordering" class="form-error">{{ form.errors.ordering }}</p>
          </div>
        </div>

        <div class="flex items-end pb-2">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span class="text-label">{{ t('homepagePromos.form.activeLabel') }}</span>
          </label>
          <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
        </div>

        <div v-if="showMainImage">
          <label class="form-label text-label">
            {{ t('homepagePromos.form.imageLabel') }} <span v-if="imageRequired" class="text-red-500">*</span>
          </label>
          <div class="flex items-center gap-4">
            <img
              v-if="imagePreview"
              :src="imagePreview"
              alt="Promo image preview"
              class="h-16 rounded-md border border-gray-200 dark:border-gray-700 object-cover"
            />
            <div class="flex flex-col gap-1.5 flex-1">
              <button
                type="button"
                @click="imageInput.click()"
                class="btn btn-secondary px-4 py-2 w-full cursor-pointer"
              >
                {{ t('homepagePromos.form.chooseFile') }}
              </button>
              <span class="text-sm text-muted muted-color truncate text-center">
                {{ imageFileName || t('homepagePromos.form.noFileChosen') }}
              </span>
              <input
                ref="imageInput"
                type="file"
                accept="image/*"
                :required="imageRequired"
                @change="handleImageChange"
                class="hidden"
              />
            </div>
          </div>
          <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
        </div>

        <div v-if="showBadgeImage">
          <label class="form-label text-label">{{ t('homepagePromos.form.badgeImageLabel') }}</label>
          <div class="flex items-center gap-4">
            <img
              v-if="badgePreview"
              :src="badgePreview"
              alt="Badge image preview"
              class="h-16 rounded-md border border-gray-200 dark:border-gray-700 object-cover"
            />
            <div class="flex flex-col gap-1.5 flex-1">
              <button
                type="button"
                @click="badgeInput.click()"
                class="btn btn-secondary px-4 py-2 w-full cursor-pointer"
              >
                {{ t('homepagePromos.form.chooseFile') }}
              </button>
              <span class="text-sm text-muted muted-color truncate text-center">
                {{ badgeFileName || t('homepagePromos.form.noFileChosen') }}
              </span>
              <input
                ref="badgeInput"
                type="file"
                accept="image/*"
                @change="handleBadgeChange"
                class="hidden"
              />
            </div>
          </div>
          <p v-if="form.errors.badge_image" class="form-error">{{ form.errors.badge_image }}</p>

          <div v-if="homepagePromo && hasExistingBadge" class="mt-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input
                v-model="form.remove_badge"
                type="checkbox"
                class="rounded border-gray-300 text-red-600 focus:ring-red-500"
              />
              <span class="text-label">{{ t('homepagePromos.form.removeBadgeLabel') }}</span>
            </label>
            <p v-if="form.errors.remove_badge" class="form-error">{{ form.errors.remove_badge }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button
            type="button"
            @click="handleClose"
            :disabled="form.processing"
            class="btn btn-secondary px-4 py-2"
          >
            {{ t('homepagePromos.form.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? t('homepagePromos.form.saving') : t('homepagePromos.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

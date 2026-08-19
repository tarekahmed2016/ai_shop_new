<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import RichTextEditor from '../../Common/asyncRichTextEditor.js'

const { t } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  heroSlide: {
    type: Object,
    default: null
  },
  nextOrdering: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  title_ar: '',
  title_en: '',
  description_ar: '',
  description_en: '',
  cta_text_ar: '',
  cta_text_en: '',
  cta_url: '',
  ordering: '',
  is_active: true,
  image: null,
})

const imagePreview = ref(null)
const imageInput = ref(null)
const imageFileName = ref(null)
const editingHeroSlideId = ref(null)

const handleImageChange = (event) => {
  const file = event.target.files[0] || null
  form.image = file
  imageFileName.value = file?.name || null
  imagePreview.value = file ? URL.createObjectURL(file) : null
}

watch([() => props.isOpen, () => props.heroSlide?.id], ([isOpen, heroSlideId]) => {
  if (!isOpen) return

  editingHeroSlideId.value = heroSlideId ?? null

  if (heroSlideId && props.heroSlide) {
    form.title_ar = props.heroSlide.title_ar || ''
    form.title_en = props.heroSlide.title_en || ''
    form.description_ar = props.heroSlide.description_ar || ''
    form.description_en = props.heroSlide.description_en || ''
    form.cta_text_ar = props.heroSlide.cta_text_ar || ''
    form.cta_text_en = props.heroSlide.cta_text_en || ''
    form.cta_url = props.heroSlide.cta_url || ''
    form.ordering = props.heroSlide.ordering ?? ''
    form.is_active = Boolean(props.heroSlide.is_active)
    form.image = null
    imagePreview.value = props.heroSlide.attachment?.asset_path || null
    imageFileName.value = null
  } else {
    form.reset()
    form.is_active = true
    form.ordering = props.nextOrdering ?? ''
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
      editingHeroSlideId.value = null
      emit('close')
    }
  }

  if (editingHeroSlideId.value) {
    form
      .transform((data) => ({ ...data, _method: 'put' }))
      .post(route('hero-slides.update', { hero_slide: editingHeroSlideId.value }), options)

    return
  }

  form.transform((data) => data)
  form.post(route('hero-slides.store'), options)
}

const handleClose = () => {
  form.transform((data) => data)
  form.reset()
  form.clearErrors()
  editingHeroSlideId.value = null
  imagePreview.value = null
  imageFileName.value = null
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="hero-slide-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="hero-slide-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ heroSlide ? t('heroSlides.form.editTitle') : t('heroSlides.form.addTitle') }}
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
        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.titleArLabel') }}</label>
            <input
              v-model="form.title_ar"
              type="text"
              class="form-input text-body"
              :placeholder="t('heroSlides.form.titleArPlaceholder')"
            />
            <p v-if="form.errors.title_ar" class="form-error">{{ form.errors.title_ar }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.descriptionArLabel') }}</label>
            <RichTextEditor
              v-model="form.description_ar"
              :active="isOpen"
              dir="rtl"
              :placeholder="t('heroSlides.form.descriptionArPlaceholder')"
            />
            <p v-if="form.errors.description_ar" class="form-error">{{ form.errors.description_ar }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.ctaTextArLabel') }}</label>
            <input
              v-model="form.cta_text_ar"
              type="text"
              class="form-input text-body"
              :placeholder="t('heroSlides.form.ctaTextArPlaceholder')"
            />
            <p v-if="form.errors.cta_text_ar" class="form-error">{{ form.errors.cta_text_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.titleEnLabel') }}</label>
            <input
              v-model="form.title_en"
              type="text"
              class="form-input text-body"
              :placeholder="t('heroSlides.form.titleEnPlaceholder')"
            />
            <p v-if="form.errors.title_en" class="form-error">{{ form.errors.title_en }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.descriptionEnLabel') }}</label>
            <RichTextEditor
              v-model="form.description_en"
              :active="isOpen"
              dir="ltr"
              :placeholder="t('heroSlides.form.descriptionEnPlaceholder')"
            />
            <p v-if="form.errors.description_en" class="form-error">{{ form.errors.description_en }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('heroSlides.form.ctaTextEnLabel') }}</label>
            <input
              v-model="form.cta_text_en"
              type="text"
              class="form-input text-body"
              :placeholder="t('heroSlides.form.ctaTextEnPlaceholder')"
            />
            <p v-if="form.errors.cta_text_en" class="form-error">{{ form.errors.cta_text_en }}</p>
          </div>
        </div>

        <div>
          <label class="form-label text-label">{{ t('heroSlides.form.ctaUrlLabel') }}</label>
          <input
            v-model="form.cta_url"
            type="url"
            class="form-input text-body"
            :placeholder="t('heroSlides.form.ctaUrlPlaceholder')"
          />
          <p v-if="form.errors.cta_url" class="form-error">{{ form.errors.cta_url }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">
              {{ t('heroSlides.form.orderingLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.ordering"
              type="number"
              min="0"
              required
              class="form-input text-body"
              :placeholder="t('heroSlides.form.orderingPlaceholder')"
            />
            <p v-if="form.errors.ordering" class="form-error">{{ form.errors.ordering }}</p>
          </div>

          <div class="flex items-end pb-2">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span class="text-label">{{ t('heroSlides.form.activeLabel') }}</span>
            </label>
            <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
          </div>
        </div>

        <div>
          <label class="form-label text-label">
            {{ t('heroSlides.form.imageLabel') }} <span v-if="!editingHeroSlideId" class="text-red-500">*</span>
          </label>
          <div class="flex items-center gap-4">
            <img
              v-if="imagePreview"
              :src="imagePreview"
              alt="Hero slide image preview"
              class="h-16 rounded-md border border-gray-200 dark:border-gray-700 object-cover"
            />
            <div class="flex flex-col gap-1.5 flex-1">
              <button
                type="button"
                @click="imageInput.click()"
                class="btn btn-secondary px-4 py-2 w-full cursor-pointer"
              >
                {{ t('heroSlides.form.chooseFile') }}
              </button>
              <span class="text-sm text-muted muted-color truncate text-center">
                {{ imageFileName || t('heroSlides.form.noFileChosen') }}
              </span>
              <input
                ref="imageInput"
                type="file"
                accept="image/*"
                :required="!editingHeroSlideId"
                @change="handleImageChange"
                class="hidden"
              />
            </div>
          </div>
          <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button
            type="button"
            @click="handleClose"
            :disabled="form.processing"
            class="btn btn-secondary px-4 py-2"
          >
            {{ t('heroSlides.form.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? t('heroSlides.form.saving') : t('heroSlides.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

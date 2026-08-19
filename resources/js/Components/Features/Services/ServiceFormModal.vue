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
  service: {
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
  name_ar: '',
  name_en: '',
  description_ar: '',
  description_en: '',
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

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.service) {
    form.name_ar = props.service.name_ar || ''
    form.name_en = props.service.name_en || ''
    form.description_ar = props.service.description_ar || ''
    form.description_en = props.service.description_en || ''
    form.ordering = props.service.ordering ?? ''
    form.is_active = Boolean(props.service.is_active)
    form.image = null
    imagePreview.value = props.service.attachment?.asset_path || null
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
      emit('close')
    }
  }

  props.service
    ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('services.update', props.service.id), options)
    : form.post(route('services.store'), options)
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
    title-id="service-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="service-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ service ? t('services.form.editTitle') : t('services.form.addTitle') }}
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
            <label class="form-label text-label">
              {{ t('services.form.nameArLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name_ar"
              type="text"
              required
              class="form-input text-body"
              :placeholder="t('services.form.nameArPlaceholder')"
            />
            <p v-if="form.errors.name_ar" class="form-error">{{ form.errors.name_ar }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('services.form.descriptionArLabel') }}</label>
            <RichTextEditor
              v-model="form.description_ar"
              :active="isOpen"
              dir="rtl"
              :placeholder="t('services.form.descriptionArPlaceholder')"
            />
            <p v-if="form.errors.description_ar" class="form-error">{{ form.errors.description_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>

          <div>
            <label class="form-label text-label">
              {{ t('services.form.nameEnLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name_en"
              type="text"
              required
              class="form-input text-body"
              :placeholder="t('services.form.nameEnPlaceholder')"
            />
            <p v-if="form.errors.name_en" class="form-error">{{ form.errors.name_en }}</p>
          </div>

          <div>
            <label class="form-label text-label">{{ t('services.form.descriptionEnLabel') }}</label>
            <RichTextEditor
              v-model="form.description_en"
              :active="isOpen"
              dir="ltr"
              :placeholder="t('services.form.descriptionEnPlaceholder')"
            />
            <p v-if="form.errors.description_en" class="form-error">{{ form.errors.description_en }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">
              {{ t('services.form.orderingLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.ordering"
              type="number"
              min="0"
              required
              class="form-input text-body"
              :placeholder="t('services.form.orderingPlaceholder')"
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
              <span class="text-label">{{ t('services.form.activeLabel') }}</span>
            </label>
            <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
          </div>
        </div>

        <div>
          <label class="form-label text-label">
            {{ t('services.form.imageLabel') }} <span v-if="!service" class="text-red-500">*</span>
          </label>
          <div class="flex items-center gap-4">
            <img
              v-if="imagePreview"
              :src="imagePreview"
              alt="Service image preview"
              class="h-16 rounded-md border border-gray-200 dark:border-gray-700 object-cover"
            />
            <div class="flex flex-col gap-1.5 flex-1">
              <button
                type="button"
                @click="imageInput.click()"
                class="btn btn-secondary px-4 py-2 w-full cursor-pointer"
              >
                {{ t('services.form.chooseFile') }}
              </button>
              <span class="text-sm text-muted muted-color truncate text-center">
                {{ imageFileName || t('services.form.noFileChosen') }}
              </span>
              <input
                ref="imageInput"
                type="file"
                accept="image/*"
                :required="!service"
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
            {{ t('services.form.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? t('services.form.saving') : t('services.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

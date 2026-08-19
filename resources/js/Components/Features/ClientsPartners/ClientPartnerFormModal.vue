<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useClientsPartners } from '../../../Composables/useClientsPartners.js'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()
const { fetchNextOrdering } = useClientsPartners()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  clientPartner: {
    type: Object,
    default: null
  },
  nextData: {
    type: Object,
    default: null
  },
  defaultType: {
    type: String,
    default: 'client'
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  type: 'client',
  name_ar: '',
  name_en: '',
  website: '',
  ordering: '',
  is_active: true,
  image: null,
})

const logoPreview = ref(null)
const logoInput = ref(null)
const logoFileName = ref(null)

const handleLogoChange = (event) => {
  const file = event.target.files[0] || null
  form.image = file
  logoFileName.value = file?.name || null
  logoPreview.value = file ? URL.createObjectURL(file) : null
}

const refreshOrderingForType = async (type) => {
  if (props.clientPartner) return

  try {
    form.ordering = await fetchNextOrdering(type)
  } catch {
    form.ordering = ''
  }
}

watch(() => form.type, (type) => {
  if (props.isOpen && !props.clientPartner) {
    refreshOrderingForType(type)
  }
})

watch(() => props.isOpen, async (isOpen) => {
  if (!isOpen) return

  if (props.clientPartner) {
    form.type = props.clientPartner.type?.value || props.clientPartner.type || 'client'
    form.name_ar = props.clientPartner.name_ar || ''
    form.name_en = props.clientPartner.name_en || ''
    form.website = props.clientPartner.website || ''
    form.ordering = props.clientPartner.ordering ?? ''
    form.is_active = Boolean(props.clientPartner.is_active)
    form.image = null
    logoPreview.value = props.clientPartner.attachment?.asset_path || null
    logoFileName.value = null
  } else {
    form.reset()
    form.is_active = true
    form.type = props.nextData?.type || props.defaultType || 'client'
    form.ordering = props.nextData?.ordering ?? ''
    logoPreview.value = null
    logoFileName.value = null
  }

  if (logoInput.value) {
    logoInput.value.value = ''
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

  props.clientPartner
    ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('clients-partners.update', props.clientPartner.id), options)
    : form.post(route('clients-partners.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  logoPreview.value = null
  logoFileName.value = null
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="client-partner-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="client-partner-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ clientPartner ? t('clientsPartners.form.editTitle') : t('clientsPartners.form.addTitle') }}
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
            {{ t('clientsPartners.form.typeLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.type" required class="form-input text-body">
            <option value="client">{{ t('clientsPartners.form.typeClient') }}</option>
            <option value="partner">{{ t('clientsPartners.form.typePartner') }}</option>
          </select>
          <p v-if="form.errors.type" class="form-error">{{ form.errors.type }}</p>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
          <div>
            <label class="form-label text-label">
              {{ t('clientsPartners.form.nameArLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_ar" type="text" required class="form-input text-body" :placeholder="t('clientsPartners.form.nameArPlaceholder')" />
            <p v-if="form.errors.name_ar" class="form-error">{{ form.errors.name_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
          <div>
            <label class="form-label text-label">
              {{ t('clientsPartners.form.nameEnLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_en" type="text" required class="form-input text-body" :placeholder="t('clientsPartners.form.nameEnPlaceholder')" />
            <p v-if="form.errors.name_en" class="form-error">{{ form.errors.name_en }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('clientsPartners.form.otherInfoTitle') }}</h3>
          <div>
            <label class="form-label text-label">{{ t('clientsPartners.form.websiteLabel') }}</label>
            <input v-model="form.website" type="url" class="form-input text-body" :placeholder="t('clientsPartners.form.websitePlaceholder')" />
            <p v-if="form.errors.website" class="form-error">{{ form.errors.website }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">
                {{ t('clientsPartners.form.orderingLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.ordering" type="number" min="0" required class="form-input text-body" :placeholder="t('clientsPartners.form.orderingPlaceholder')" />
              <p v-if="form.errors.ordering" class="form-error">{{ form.errors.ordering }}</p>
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span class="text-label">{{ t('clientsPartners.form.activeLabel') }}</span>
              </label>
              <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
            </div>
          </div>
          <div>
            <label class="form-label text-label">
              {{ t('clientsPartners.form.logoLabel') }} <span v-if="!clientPartner" class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-4">
              <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="h-16 w-28 rounded-md border border-gray-200 dark:border-gray-700 object-contain bg-white p-1" />
              <div class="flex flex-col gap-1.5 flex-1">
                <button type="button" @click="logoInput.click()" class="btn btn-secondary px-4 py-2 w-full cursor-pointer">
                  {{ t('clientsPartners.form.chooseFile') }}
                </button>
                <span class="text-sm text-muted muted-color truncate text-center">
                  {{ logoFileName || t('clientsPartners.form.noFileChosen') }}
                </span>
                <input ref="logoInput" type="file" accept="image/*" :required="!clientPartner" @change="handleLogoChange" class="hidden" />
              </div>
            </div>
            <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="handleClose" :disabled="form.processing" class="btn btn-secondary px-4 py-2">
            {{ t('clientsPartners.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('clientsPartners.form.saving') : t('clientsPartners.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

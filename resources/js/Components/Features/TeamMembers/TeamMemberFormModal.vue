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
  teamMember: {
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
  position_ar: '',
  position_en: '',
  bio_ar: '',
  bio_en: '',
  email: '',
  phone: '',
  linkedin_url: '',
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

  if (props.teamMember) {
    form.name_ar = props.teamMember.name_ar || ''
    form.name_en = props.teamMember.name_en || ''
    form.position_ar = props.teamMember.position_ar || ''
    form.position_en = props.teamMember.position_en || ''
    form.bio_ar = props.teamMember.bio_ar || ''
    form.bio_en = props.teamMember.bio_en || ''
    form.email = props.teamMember.email || ''
    form.phone = props.teamMember.phone || ''
    form.linkedin_url = props.teamMember.linkedin_url || ''
    form.ordering = props.teamMember.ordering ?? ''
    form.is_active = Boolean(props.teamMember.is_active)
    form.image = null
    imagePreview.value = props.teamMember.attachment?.asset_path || null
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

watch(() => props.nextOrdering, (ordering) => {
  if (props.isOpen && !props.teamMember && ordering !== null && ordering !== '') {
    form.ordering = ordering
  }
})

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  }

  props.teamMember
    ? form.transform((data) => ({ ...data, _method: 'put' })).post(route('team-members.update', props.teamMember.id), options)
    : form.post(route('team-members.store'), options)
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
    title-id="team-member-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="team-member-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ teamMember ? t('teamMembers.form.editTitle') : t('teamMembers.form.addTitle') }}
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
              {{ t('teamMembers.form.nameArLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_ar" type="text" required class="form-input text-body" :placeholder="t('teamMembers.form.nameArPlaceholder')" />
            <p v-if="form.errors.name_ar" class="form-error">{{ form.errors.name_ar }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('teamMembers.form.positionArLabel') }}</label>
            <input v-model="form.position_ar" type="text" class="form-input text-body" :placeholder="t('teamMembers.form.positionArPlaceholder')" />
            <p v-if="form.errors.position_ar" class="form-error">{{ form.errors.position_ar }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('teamMembers.form.bioArLabel') }}</label>
            <RichTextEditor
              v-model="form.bio_ar"
              :active="isOpen"
              dir="rtl"
              :placeholder="t('teamMembers.form.bioArPlaceholder')"
            />
            <p v-if="form.errors.bio_ar" class="form-error">{{ form.errors.bio_ar }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
          <div>
            <label class="form-label text-label">
              {{ t('teamMembers.form.nameEnLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_en" type="text" required class="form-input text-body" :placeholder="t('teamMembers.form.nameEnPlaceholder')" />
            <p v-if="form.errors.name_en" class="form-error">{{ form.errors.name_en }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('teamMembers.form.positionEnLabel') }}</label>
            <input v-model="form.position_en" type="text" class="form-input text-body" :placeholder="t('teamMembers.form.positionEnPlaceholder')" />
            <p v-if="form.errors.position_en" class="form-error">{{ form.errors.position_en }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('teamMembers.form.bioEnLabel') }}</label>
            <RichTextEditor
              v-model="form.bio_en"
              :active="isOpen"
              dir="ltr"
              :placeholder="t('teamMembers.form.bioEnPlaceholder')"
            />
            <p v-if="form.errors.bio_en" class="form-error">{{ form.errors.bio_en }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('teamMembers.form.contactTitle') }}</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">{{ t('teamMembers.form.emailLabel') }}</label>
              <input v-model="form.email" type="email" class="form-input text-body" :placeholder="t('teamMembers.form.emailPlaceholder')" />
              <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
            </div>
            <div>
              <label class="form-label text-label">{{ t('teamMembers.form.phoneLabel') }}</label>
              <input v-model="form.phone" type="text" class="form-input text-body" :placeholder="t('teamMembers.form.phonePlaceholder')" />
              <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
            </div>
          </div>
          <div>
            <label class="form-label text-label">{{ t('teamMembers.form.linkedinUrlLabel') }}</label>
            <input v-model="form.linkedin_url" type="url" class="form-input text-body" :placeholder="t('teamMembers.form.linkedinUrlPlaceholder')" />
            <p v-if="form.errors.linkedin_url" class="form-error">{{ form.errors.linkedin_url }}</p>
          </div>
        </div>

        <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('teamMembers.form.otherInfoTitle') }}</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">
                {{ t('teamMembers.form.orderingLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.ordering" type="number" min="0" required class="form-input text-body" :placeholder="t('teamMembers.form.orderingPlaceholder')" />
              <p v-if="form.errors.ordering" class="form-error">{{ form.errors.ordering }}</p>
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span class="text-label">{{ t('teamMembers.form.activeLabel') }}</span>
              </label>
              <p v-if="form.errors.is_active" class="form-error ms-2">{{ form.errors.is_active }}</p>
            </div>
          </div>
          <div>
            <label class="form-label text-label">
              {{ t('teamMembers.form.imageLabel') }} <span v-if="!teamMember" class="text-red-500">*</span>
            </label>
            <div class="flex items-center gap-4">
              <img v-if="imagePreview" :src="imagePreview" alt="Team member photo preview" class="h-16 w-16 rounded-full border border-gray-200 dark:border-gray-700 object-cover" />
              <div class="flex flex-col gap-1.5 flex-1">
                <button type="button" @click="imageInput.click()" class="btn btn-secondary px-4 py-2 w-full cursor-pointer">
                  {{ t('teamMembers.form.chooseFile') }}
                </button>
                <span class="text-sm text-muted muted-color truncate text-center">
                  {{ imageFileName || t('teamMembers.form.noFileChosen') }}
                </span>
                <input ref="imageInput" type="file" accept="image/*" :required="!teamMember" @change="handleImageChange" class="hidden" />
              </div>
            </div>
            <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="handleClose" :disabled="form.processing" class="btn btn-secondary px-4 py-2">
            {{ t('teamMembers.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('teamMembers.form.saving') : t('teamMembers.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

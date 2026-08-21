<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  category: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  parentOptions: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])

const form = useForm({
  name_ar: '',
  name_en: '',
  slug: '',
  parent_id: '',
  status: '',
  sort_order: 0,
})

const selectableParents = computed(() => {
  if (!props.category) {
    return props.parentOptions
  }

  return props.parentOptions.filter((option) => option.public_id !== props.category.public_id)
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.category) {
    form.name_ar = props.category.name_ar || ''
    form.name_en = props.category.name_en || ''
    form.slug = props.category.slug || ''
    form.parent_id = props.category.parent?.public_id || ''
    form.status = props.category.status || ''
    form.sort_order = props.category.sort_order ?? 0
  } else {
    form.reset()
    form.sort_order = 0
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

  props.category
    ? form.put(route('categories.update', props.category.public_id), options)
    : form.post(route('categories.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="category-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="category-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ category ? t('categories.form.editTitle') : t('categories.form.addTitle') }}
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
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">
              {{ t('categories.form.nameArLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_ar" type="text" required class="form-input text-body" />
            <p v-if="form.errors.name_ar" class="form-error">{{ form.errors.name_ar }}</p>
          </div>
          <div>
            <label class="form-label text-label">
              {{ t('categories.form.nameEnLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name_en" type="text" required class="form-input text-body" />
            <p v-if="form.errors.name_en" class="form-error">{{ form.errors.name_en }}</p>
          </div>
        </div>

        <div>
          <label class="form-label text-label">{{ t('categories.form.slugLabel') }}</label>
          <input v-model="form.slug" type="text" dir="ltr" class="form-input text-body" />
          <p v-if="form.errors.slug" class="form-error">{{ form.errors.slug }}</p>
        </div>

        <div>
          <label class="form-label text-label">{{ t('categories.form.parentLabel') }}</label>
          <select v-model="form.parent_id" class="form-input text-body">
            <option value="">{{ t('categories.form.noParent') }}</option>
            <option v-for="option in selectableParents" :key="option.public_id" :value="option.public_id">
              {{ option.name_ar }} / {{ option.name_en }}
            </option>
          </select>
          <p v-if="form.errors.parent_id" class="form-error">{{ form.errors.parent_id }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">
              {{ t('categories.form.statusLabel') }} <span class="text-red-500">*</span>
            </label>
            <select v-model="form.status" required class="form-input text-body">
              <option value="" disabled>{{ t('categories.form.selectStatus') }}</option>
              <option v-for="option in statuses" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('categories.form.sortOrderLabel') }}</label>
            <input v-model="form.sort_order" type="number" min="0" class="form-input text-body" />
            <p v-if="form.errors.sort_order" class="form-error">{{ form.errors.sort_order }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('categories.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('categories.form.saving') : t('categories.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

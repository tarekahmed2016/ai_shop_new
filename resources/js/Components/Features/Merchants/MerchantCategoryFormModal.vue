<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import CategoryTreeSelector from '../Categories/CategoryTreeSelector.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  merchant: { type: Object, default: () => ({}) },
  availableCategories: { type: Array, default: () => [] },
  storeRouteName: { type: String, default: 'merchants.categories.store' }
})

const emit = defineEmits(['close'])

const form = useForm({
  category_id: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  form.reset()
  form.clearErrors()
}, { immediate: true })

const submit = () => {
  const href = props.storeRouteName === 'merchant.activities.store'
    ? route('merchant.activities.store')
    : route('merchants.categories.store', props.merchant.public_id)

  form.post(href, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  })
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
    title-id="merchant-category-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="merchant-category-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ t('merchantCategories.form.addTitle') }}
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

      <form @submit.prevent="submit" class="px-6 py-4 space-y-4">
        <div>
          <label class="form-label text-label">
            {{ t('merchantCategories.form.categoryLabel') }} <span class="text-red-500">*</span>
          </label>
          <CategoryTreeSelector
            :categories="availableCategories"
            :multiple="false"
            :selectedId="form.category_id"
            :emptyText="t('merchantCategories.form.selectCategory')"
            @select="form.category_id = $event"
          />
          <p v-if="form.errors.category_id" class="form-error">{{ form.errors.category_id }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('merchantCategories.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('merchantCategories.form.saving') : t('merchantCategories.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import CategoryTreeSelector from '../Categories/CategoryTreeSelector.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  merchant: { type: Object, default: () => ({}) },
  availableCategories: { type: Array, default: () => [] },
  assignment: { type: Object, default: null },
  storeRouteName: { type: String, default: 'merchants.categories.store' },
  updateRouteName: { type: String, default: 'merchants.categories.update' }
})

const emit = defineEmits(['close'])

const isEdit = computed(() => Boolean(props.assignment?.id))

const addForm = useForm({
  category_id: '',
  whatsapp_phone: '',
})

const editForm = useForm({
  whatsapp_phone: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  addForm.reset()
  addForm.clearErrors()
  editForm.reset()
  editForm.clearErrors()

  if (isEdit.value) {
    editForm.whatsapp_phone = props.assignment?.whatsapp_phone || ''
  }
}, { immediate: true })

const submit = () => {
  if (isEdit.value) {
    const href = props.updateRouteName === 'merchant.activities.update'
      ? route('merchant.activities.update', props.assignment.id)
      : route('merchants.categories.update', {
          merchant: props.merchant.public_id,
          merchantCategory: props.assignment.id
        })

    editForm.transform(() => ({
      whatsapp_phone: editForm.whatsapp_phone,
    })).patch(href, {
      preserveScroll: true,
      onSuccess: () => {
        editForm.reset()
        emit('close')
      }
    })
    return
  }

  const href = props.storeRouteName === 'merchant.activities.store'
    ? route('merchant.activities.store')
    : route('merchants.categories.store', props.merchant.public_id)

  addForm.transform(() => ({
    category_id: addForm.category_id,
    whatsapp_phone: addForm.whatsapp_phone,
  })).post(href, {
    preserveScroll: true,
    onSuccess: () => {
      addForm.reset()
      emit('close')
    }
  })
}

const handleClose = () => {
  addForm.reset()
  addForm.clearErrors()
  editForm.reset()
  editForm.clearErrors()
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
          {{ isEdit ? t('merchantCategories.form.editWhatsAppTitle') : t('merchantCategories.form.addTitle') }}
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
        <div v-if="!isEdit">
          <label class="form-label text-label">
            {{ t('merchantCategories.form.categoryLabel') }} <span class="text-red-500">*</span>
          </label>
          <CategoryTreeSelector
            :categories="availableCategories"
            :multiple="false"
            :selectedId="addForm.category_id"
            :emptyText="t('merchantCategories.form.selectCategory')"
            @select="addForm.category_id = $event"
          />
          <p v-if="addForm.errors.category_id" class="form-error">{{ addForm.errors.category_id }}</p>
        </div>

        <div>
          <label class="form-label text-label">
            {{ t('merchantCategories.form.whatsappLabel') }} <span class="text-red-500">*</span>
          </label>
          <input
            :value="isEdit ? editForm.whatsapp_phone : addForm.whatsapp_phone"
            type="text"
            maxlength="20"
            :placeholder="t('merchantCategories.form.whatsappPlaceholder')"
            class="block w-full ps-3 pe-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-body text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            @input="isEdit ? (editForm.whatsapp_phone = $event.target.value) : (addForm.whatsapp_phone = $event.target.value)"
          />
          <p class="mt-1 text-muted muted-color">{{ t('merchantCategories.form.whatsappHint') }}</p>
          <p v-if="isEdit ? editForm.errors.whatsapp_phone : addForm.errors.whatsapp_phone" class="form-error">
            {{ isEdit ? editForm.errors.whatsapp_phone : addForm.errors.whatsapp_phone }}
          </p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('merchantCategories.form.cancel') }}
          </button>
          <button type="submit" :disabled="isEdit ? editForm.processing : addForm.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ (isEdit ? editForm.processing : addForm.processing) ? t('merchantCategories.form.saving') : t('merchantCategories.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

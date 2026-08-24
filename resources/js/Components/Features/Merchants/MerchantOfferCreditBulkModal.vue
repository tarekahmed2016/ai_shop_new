<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  sources: { type: Array, default: () => [] },
  selectedPublicIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'success'])

const form = useForm({
  merchant_public_ids: [],
  amount: 20,
  source: '',
  reference: '',
  notes: '',
  paid_amount: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) {
    return
  }

  form.reset()
  form.merchant_public_ids = [...props.selectedPublicIds]
  form.amount = 20
  form.source = props.sources[0]?.value ?? ''
  form.paid_amount = ''
  form.clearErrors()
}, { immediate: true })

const submit = () => {
  form.merchant_public_ids = [...props.selectedPublicIds]
  form.post(route('merchants.credits.bulk'), {
    preserveScroll: true,
    onSuccess: () => {
      emit('success')
      emit('close')
    },
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
    title-id="merchant-credit-bulk-modal-title"
    max-width-class="max-w-lg"
    @close="handleClose"
  >
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="merchant-credit-bulk-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ t('merchants.credits.bulkTitle') }}
      </h2>
      <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer" @click="handleClose">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form class="px-6 py-4 space-y-4" @submit.prevent="submit">
      <p class="text-muted muted-color">
        {{ t('merchants.credits.bulkSelected', { count: selectedPublicIds.length }) }}
      </p>
      <p v-if="form.errors.merchant_public_ids" class="form-error">{{ form.errors.merchant_public_ids }}</p>
      <div>
        <label class="form-label text-label">{{ t('merchants.credits.amountLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.amount" type="number" min="1" required class="form-input text-body" />
        <p v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('merchants.credits.sourceLabel') }} <span class="text-red-500">*</span></label>
        <select v-model="form.source" required class="form-input text-body">
          <option value="" disabled>{{ t('merchants.credits.selectSource') }}</option>
          <option v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.source" class="form-error">{{ form.errors.source }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('merchants.credits.paidAmountPerMerchantLabel') }}</label>
        <input v-model="form.paid_amount" type="number" min="0" step="0.001" inputmode="decimal" class="form-input text-body" />
        <p class="text-muted text-sm mt-1">{{ t('merchants.credits.paidAmountPerMerchantHint') }}</p>
        <p v-if="form.errors.paid_amount" class="form-error">{{ form.errors.paid_amount }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('merchants.credits.referenceLabel') }}</label>
        <input v-model="form.reference" type="text" class="form-input text-body" />
        <p v-if="form.errors.reference" class="form-error">{{ form.errors.reference }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('merchants.credits.notesLabel') }}</label>
        <textarea v-model="form.notes" rows="3" class="form-input text-body"></textarea>
        <p v-if="form.errors.notes" class="form-error">{{ form.errors.notes }}</p>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" class="btn btn-secondary px-4 py-2" @click="handleClose">{{ t('merchants.form.cancel') }}</button>
        <button type="submit" :disabled="form.processing || selectedPublicIds.length === 0" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('merchants.form.saving') : t('merchants.credits.bulkSubmit') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

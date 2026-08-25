<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  mode: { type: String, default: 'add' },
  sources: { type: Array, default: () => [] },
  customerPublicId: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const form = useForm({
  amount: 5,
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
  form.amount = 5
  form.source = props.sources[0]?.value ?? ''
  form.paid_amount = ''
  form.clearErrors()
}, { immediate: true })

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
    },
  }

  if (props.mode === 'deduct') {
    form.transform((data) => ({
      amount: data.amount,
      source: data.source,
      reference: data.reference,
      notes: data.notes,
    })).post(route('customers.extra-requests.deduct', props.customerPublicId), options)
    return
  }

  form.post(route('customers.extra-requests.store', props.customerPublicId), options)
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
    title-id="customer-extra-request-form-modal-title"
    max-width-class="max-w-lg"
    @close="handleClose"
  >
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="customer-extra-request-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ mode === 'deduct' ? t('customers.extraRequests.deductTitle') : t('customers.extraRequests.addTitle') }}
      </h2>
      <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer" @click="handleClose">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form class="px-6 py-4 space-y-4" @submit.prevent="submit">
      <div>
        <label class="form-label text-label">{{ t('customers.extraRequests.amountLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.amount" type="number" min="1" required class="form-input text-body" />
        <p v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.extraRequests.sourceLabel') }} <span class="text-red-500">*</span></label>
        <select v-model="form.source" required class="form-input text-body">
          <option value="" disabled>{{ t('customers.extraRequests.selectSource') }}</option>
          <option v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.source" class="form-error">{{ form.errors.source }}</p>
      </div>
      <div v-if="mode !== 'deduct'">
        <label class="form-label text-label">{{ t('customers.extraRequests.paidAmountLabel') }}</label>
        <input v-model="form.paid_amount" type="number" min="0" step="0.001" inputmode="decimal" class="form-input text-body" />
        <p class="text-muted text-sm mt-1">{{ t('customers.extraRequests.paidAmountHint') }}</p>
        <p v-if="form.errors.paid_amount" class="form-error">{{ form.errors.paid_amount }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.extraRequests.referenceLabel') }}</label>
        <input v-model="form.reference" type="text" class="form-input text-body" />
        <p v-if="form.errors.reference" class="form-error">{{ form.errors.reference }}</p>
      </div>
      <div>
        <label class="form-label text-label">
          {{ t('customers.extraRequests.notesLabel') }}
          <span v-if="mode === 'deduct'" class="text-red-500">*</span>
        </label>
        <textarea v-model="form.notes" rows="3" class="form-input text-body" :required="mode === 'deduct'"></textarea>
        <p v-if="form.errors.notes" class="form-error">{{ form.errors.notes }}</p>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" class="btn btn-secondary px-4 py-2" @click="handleClose">{{ t('customers.form.cancel') }}</button>
        <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('customers.form.saving') : t('customers.form.save') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

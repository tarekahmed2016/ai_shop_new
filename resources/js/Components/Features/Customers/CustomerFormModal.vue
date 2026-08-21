<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  customer: { type: Object, default: null },
  statuses: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])

const form = useForm({
  name: '',
  phone: '',
  email: '',
  whatsapp_id: '',
  status: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.customer) {
    form.name = props.customer.name || ''
    form.phone = props.customer.phone || ''
    form.email = props.customer.email || ''
    form.whatsapp_id = props.customer.whatsapp_id || ''
    form.status = props.customer.status || ''
  } else {
    form.reset()
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

  props.customer
    ? form.put(route('customers.update', props.customer.public_id), options)
    : form.post(route('customers.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  emit('close')
}
</script>

<template>
  <DashboardModalShell :isOpen="isOpen" title-id="customer-form-modal-title" @close="handleClose">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="customer-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ customer ? t('customers.form.editTitle') : t('customers.form.addTitle') }}
      </h2>
      <button @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
      <div>
        <label class="form-label text-label">{{ t('customers.form.nameLabel') }}</label>
        <input v-model="form.name" type="text" class="form-input text-body" />
        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label text-label">{{ t('customers.form.phoneLabel') }}</label>
          <input v-model="form.phone" type="text" dir="ltr" class="form-input text-body" />
          <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('customers.form.emailLabel') }}</label>
          <input v-model="form.email" type="email" class="form-input text-body" />
          <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
        </div>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.form.whatsappLabel') }}</label>
        <input v-model="form.whatsapp_id" type="text" dir="ltr" class="form-input text-body" />
        <p v-if="form.errors.whatsapp_id" class="form-error">{{ form.errors.whatsapp_id }}</p>
      </div>
      <div>
        <label class="form-label text-label">
          {{ t('customers.form.statusLabel') }} <span class="text-red-500">*</span>
        </label>
        <select v-model="form.status" required class="form-input text-body">
          <option value="" disabled>{{ t('customers.form.selectStatus') }}</option>
          <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">{{ t('customers.form.cancel') }}</button>
        <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('customers.form.saving') : t('customers.form.save') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  customer: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  form.name = props.customer?.name || ''
  form.email = props.customer?.email || ''
  form.phone = props.customer?.phone || ''
  form.password = ''
  form.password_confirmation = ''
  form.clearErrors()
}, { immediate: true })

const submit = () => {
  if (!props.customer) return

  form.post(route('customers.portal-access', props.customer.public_id), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
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
  <DashboardModalShell :isOpen="isOpen" title-id="customer-enable-portal-modal-title" @close="handleClose">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="customer-enable-portal-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ t('customers.portal.createLoginTitle') }}
      </h2>
      <button type="button" @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4">
      <p class="text-muted text-sm">{{ t('customers.portal.createLoginHint') }}</p>

      <div>
        <label class="form-label text-label">{{ t('customers.form.nameLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.name" type="text" required class="form-input text-body" />
        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.form.emailLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.email" type="email" required class="form-input text-body" />
        <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.form.phoneLabel') }}</label>
        <input v-model="form.phone" type="text" dir="ltr" class="form-input text-body" />
        <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.form.passwordLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.password" type="password" required autocomplete="new-password" class="form-input text-body" />
        <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="form-label text-label">{{ t('customers.form.passwordConfirmationLabel') }} <span class="text-red-500">*</span></label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="form-input text-body" />
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">{{ t('customers.form.cancel') }}</button>
        <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('customers.form.saving') : t('customers.portal.createLoginSubmit') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

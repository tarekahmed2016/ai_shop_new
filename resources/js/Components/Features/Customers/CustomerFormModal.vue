<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  customer: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  globalLimit: { type: Number, default: 5 },
})

const emit = defineEmits(['close'])

const isEdit = computed(() => !!props.customer)
const hasPortalAccess = computed(() => !!props.customer?.has_portal_access || !!props.customer?.user_id)

const form = useForm({
  name: '',
  phone: '',
  email: '',
  whatsapp_id: '',
  status: '',
  daily_request_limit_override: '',
  daily_request_limit_notes: '',
  password: '',
  password_confirmation: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.customer) {
    form.name = props.customer.name || ''
    form.phone = props.customer.phone || ''
    form.email = props.customer.email || ''
    form.whatsapp_id = props.customer.whatsapp_id || ''
    form.status = props.customer.status || ''
    form.daily_request_limit_override = props.customer.daily_limit_override ?? props.customer.daily_request_limit_override ?? ''
    form.daily_request_limit_notes = ''
    form.password = ''
    form.password_confirmation = ''
  } else {
    form.reset()
    form.status = props.statuses?.[0]?.value ?? 1
    form.daily_request_limit_override = ''
  }

  form.clearErrors()
}, { immediate: true })

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    },
    onFinish: () => {
      form.transform((data) => data)
    },
  }

  if (props.customer) {
    form.transform((data) => ({
      name: data.name,
      phone: data.phone,
      email: data.email,
      whatsapp_id: data.whatsapp_id,
      status: data.status,
      daily_request_limit_override: data.daily_request_limit_override === '' || data.daily_request_limit_override === null
        ? null
        : data.daily_request_limit_override,
      daily_request_limit_notes: data.daily_request_limit_notes || null,
    })).put(route('customers.update', props.customer.public_id), options)
    return
  }

  form.post(route('customers.store'), options)
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
        {{ isEdit ? t('customers.form.editTitle') : t('customers.form.addTitle') }}
      </h2>
      <button type="button" @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
      <div v-if="isEdit" class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
        <p class="text-body font-medium text-gray-900 dark:text-gray-100">
          {{ hasPortalAccess ? t('customers.portal.enabled') : t('customers.portal.disabled') }}
        </p>
        <p class="text-muted text-sm mt-1">
          {{ hasPortalAccess ? t('customers.portal.enabledHint') : t('customers.portal.disabledHint') }}
        </p>
      </div>

      <div v-else class="rounded-md border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/30 p-3">
        <p class="text-body text-gray-900 dark:text-gray-100">{{ t('customers.form.portalAlwaysEnabled') }}</p>
      </div>

      <div>
        <label class="form-label text-label">
          {{ t('customers.form.nameLabel') }}
          <span v-if="!isEdit" class="text-red-500">*</span>
        </label>
        <input v-model="form.name" type="text" :required="!isEdit" class="form-input text-body" />
        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label text-label">
            {{ t('customers.form.phoneLabel') }}
            <span v-if="!isEdit" class="text-red-500">*</span>
          </label>
          <input v-model="form.phone" type="text" dir="ltr" :required="!isEdit" class="form-input text-body" />
          <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
        </div>
        <div>
          <label class="form-label text-label">
            {{ t('customers.form.emailLabel') }}
            <span v-if="!isEdit" class="text-red-500">*</span>
          </label>
          <input v-model="form.email" type="email" :required="!isEdit" class="form-input text-body" />
          <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
        </div>
      </div>

      <div>
        <label class="form-label text-label">{{ t('customers.form.whatsappLabel') }}</label>
        <input v-model="form.whatsapp_id" type="text" dir="ltr" class="form-input text-body" />
        <p v-if="form.errors.whatsapp_id" class="form-error">{{ form.errors.whatsapp_id }}</p>
      </div>

      <template v-if="!isEdit">
        <div>
          <label class="form-label text-label">
            {{ t('customers.form.passwordLabel') }} <span class="text-red-500">*</span>
          </label>
          <input v-model="form.password" type="password" required autocomplete="new-password" class="form-input text-body" />
          <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
        </div>
        <div>
          <label class="form-label text-label">
            {{ t('customers.form.passwordConfirmationLabel') }} <span class="text-red-500">*</span>
          </label>
          <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="form-input text-body" />
        </div>
      </template>

      <div>
        <label class="form-label text-label">{{ t('customers.form.overrideLabel') }}</label>
        <input v-model="form.daily_request_limit_override" type="number" min="1" max="100" class="form-input text-body" :placeholder="t('customers.form.overridePlaceholder')" />
        <p class="text-muted text-sm mt-1">
          {{ t('customers.form.overrideHint', {
            global: props.globalLimit,
            override: form.daily_request_limit_override || t('customers.form.overrideNone'),
            effective: form.daily_request_limit_override || props.globalLimit,
          }) }}
        </p>
        <p v-if="form.errors.daily_request_limit_override" class="form-error">{{ form.errors.daily_request_limit_override }}</p>
      </div>

      <div>
        <label class="form-label text-label">{{ t('customers.form.overrideNotesLabel') }}</label>
        <input v-model="form.daily_request_limit_notes" type="text" maxlength="500" class="form-input text-body" :placeholder="t('customers.form.overrideNotesPlaceholder')" />
        <p v-if="form.errors.daily_request_limit_notes" class="form-error">{{ form.errors.daily_request_limit_notes }}</p>
      </div>

      <div v-if="isEdit && props.customer?.status === 3" class="rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/30 p-3 space-y-1">
        <p class="text-body font-medium text-red-800 dark:text-red-200">{{ t('customers.suspension.title') }}</p>
        <p class="text-muted text-sm">{{ t('customers.suspension.reason') }}: {{ props.customer.suspension_reason || '—' }}</p>
        <p class="text-muted text-sm">{{ t('customers.suspension.date') }}: {{ props.customer.suspended_at || '—' }}</p>
        <p class="text-muted text-sm">{{ t('customers.suspension.types') }}: {{ (props.customer.suspension_types || []).join(', ') || '—' }}</p>
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

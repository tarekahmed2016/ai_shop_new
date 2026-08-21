<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  merchant: { type: Object, required: true },
  membership: { type: Object, default: null },
  users: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])

const form = useForm({
  user_id: '',
  role: '',
  status: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.membership) {
    form.user_id = props.membership.user_id || ''
    form.role = props.membership.role || ''
    form.status = props.membership.status || ''
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

  props.membership
    ? form.put(route('merchants.memberships.update', [props.merchant.public_id, props.membership.id]), options)
    : form.post(route('merchants.memberships.store', props.merchant.public_id), options)
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
    title-id="merchant-membership-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="merchant-membership-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ membership ? t('merchantMemberships.form.editTitle') : t('merchantMemberships.form.addTitle') }}
        </h2>
        <button @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
        <div v-if="!membership">
          <label class="form-label text-label">
            {{ t('merchantMemberships.form.userLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.user_id" required class="form-input text-body">
            <option value="" disabled>{{ t('merchantMemberships.form.selectUser') }}</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
          <p v-if="form.errors.user_id" class="form-error">{{ form.errors.user_id }}</p>
        </div>

        <div>
          <label class="form-label text-label">
            {{ t('merchantMemberships.form.roleLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.role" required class="form-input text-body">
            <option value="" disabled>{{ t('merchantMemberships.form.selectRole') }}</option>
            <option v-for="option in roles" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <p v-if="form.errors.role" class="form-error">{{ form.errors.role }}</p>
        </div>

        <div>
          <label class="form-label text-label">
            {{ t('merchantMemberships.form.statusLabel') }} <span class="text-red-500">*</span>
          </label>
          <select v-model="form.status" required class="form-input text-body">
            <option value="" disabled>{{ t('merchantMemberships.form.selectStatus') }}</option>
            <option v-for="option in statuses" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
          <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('merchantMemberships.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
            {{ form.processing ? t('merchantMemberships.form.saving') : t('merchantMemberships.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

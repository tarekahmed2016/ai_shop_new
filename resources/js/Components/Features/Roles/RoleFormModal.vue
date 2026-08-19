<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  role: {
    type: Object,
    default: null
  },
  permissions: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  name: '',
  permissions: [],
})

// Populate the form each time the modal opens, since re-opening with the
// same role value wouldn't otherwise re-trigger a watcher
watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.role) {
    form.name = props.role.name || ''
    form.permissions = (props.role.permissions || []).map(permission => permission.id)
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

  props.role
    ? form.put(route('roles.update', props.role.id), options)
    : form.post(route('roles.store'), options)
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
    title-id="role-form-modal-title"
    @close="handleClose"
  >
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="role-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ role ? t('roles.form.editTitle') : t('roles.form.addTitle') }}
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

      <!-- Form -->
      <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
        <!-- Name Field -->
        <div>
          <label class="form-label text-label">
            {{ t('roles.form.nameLabel') }} <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            class="form-input text-body"
          />
          <p v-if="form.errors.name" class="form-error">
            {{ form.errors.name }}
          </p>
        </div>

        <!-- Permissions Field -->
        <div>
          <label class="form-label text-label">
            {{ t('roles.form.permissionsLabel') }}
          </label>
          <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-3">
            <label
              v-for="permission in permissions"
              :key="permission.id"
              class="flex items-center gap-2 text-body text-gray-700 dark:text-gray-300"
            >
              <input
                type="checkbox"
                :value="permission.id"
                v-model="form.permissions"
              />
              {{ permission.name }}
            </label>
            <p v-if="permissions.length === 0" class="text-muted muted-color col-span-2">
              {{ t('roles.form.noPermissionsAvailable') }}
            </p>
          </div>
          <p v-if="form.errors.permissions" class="form-error">
            {{ form.errors.permissions }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            @click="handleClose"
            class="btn btn-secondary px-4 py-2"
          >
            {{ t('roles.form.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? t('roles.form.saving') : t('roles.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

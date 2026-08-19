<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  user: {
    type: Object,
    default: null
  },
  statuses: {
    type: Array,
    default: () => []
  },
  roles: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close'])

const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  status: '',
  role: '',
})

const showPassword = ref(false)

// Populate the form each time the modal opens, since re-opening with the
// same user value wouldn't otherwise re-trigger a watcher
watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.user) {
    form.name = props.user.name || ''
    form.email = props.user.email || ''
    form.phone = props.user.phone || ''
    form.status = props.user.status || ''
    form.role = props.user.roles?.[0]?.name || ''
    form.password = ''
  } else {
    form.reset()
  }

  showPassword.value = false
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

  props.user
    ? form.put(route('users.update', props.user.id), options)
    : form.post(route('users.store'), options)
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
    title-id="user-form-modal-title"
    @close="handleClose"
  >
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="user-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ user ? t('users.form.editTitle') : t('users.form.addTitle') }}
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
        <div class="grid grid-cols-2 gap-4">
          <!-- Name Field -->
          <div>
            <label class="form-label text-label">
              {{ t('users.form.nameLabel') }} <span class="text-red-500">*</span>
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

          <!-- Email Field -->
          <div>
            <label class="form-label text-label">
              {{ t('users.form.emailLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.email"
              type="email"
              required
              class="form-input text-body"
            />
            <p v-if="form.errors.email" class="form-error">
              {{ form.errors.email }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Phone Field -->
          <div>
            <label class="form-label text-label">
              {{ t('users.form.phoneLabel') }} <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.phone"
              type="text"
              required
              class="form-input text-body"
            />
            <p v-if="form.errors.phone" class="form-error">
              {{ form.errors.phone }}
            </p>
          </div>

          <!-- Status Field -->
          <div>
            <label class="form-label text-label">
              {{ t('users.form.statusLabel') }} <span class="text-red-500">*</span>
            </label>
            <select v-model="form.status" required class="form-input text-body">
              <option value="" disabled>{{ t('users.form.selectStatus') }}</option>
              <option v-for="option in statuses" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <p v-if="form.errors.status" class="form-error">
              {{ form.errors.status }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Role Field -->
          <div>
            <label class="form-label text-label">
              {{ t('users.form.roleLabel') }} <span class="text-red-500">*</span>
            </label>
            <select v-model="form.role" required class="form-input text-body">
              <option value="" disabled>{{ t('users.form.selectRole') }}</option>
              <option v-for="option in roles" :key="option.id" :value="option.name">
                {{ option.name }}
              </option>
            </select>
            <p v-if="form.errors.role" class="form-error">
              {{ form.errors.role }}
            </p>
          </div>
        </div>

        <!-- Password Field -->
        <div>
          <label class="form-label text-label">
            {{ t('users.form.passwordLabel') }}
            <span v-if="!user" class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              :required="!user"
              :placeholder="user ? t('users.form.passwordEditPlaceholder') : ''"
              class="form-input text-body pe-16"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 end-0 px-3 text-muted muted-color text-sm cursor-pointer"
            >
              {{ showPassword ? t('users.form.hide') : t('users.form.show') }}
            </button>
          </div>
          <p v-if="form.errors.password" class="form-error">
            {{ form.errors.password }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            @click="handleClose"
            class="btn btn-secondary px-4 py-2"
          >
            {{ t('users.form.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ form.processing ? t('users.form.saving') : t('users.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

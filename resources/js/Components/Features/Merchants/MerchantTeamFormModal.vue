<script setup>
import { computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t, locale } = useI18n()
const page = usePage()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  membership: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  permissionCatalog: { type: Array, default: () => [] },
  assignablePermissions: { type: Object, default: () => ({}) },
  canCustomizePermissions: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const form = useForm({
  email: '',
  name: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role: '',
  status: '',
  permissions: [],
})

const isEdit = computed(() => !!props.membership)
const isOwnerTarget = computed(() => props.membership?.role === 'merchant-owner' || props.membership?.is_full_access)

const assignableForRole = computed(() => {
  const role = form.role || props.membership?.role
  return props.assignablePermissions?.[role] || []
})

const visibleGroups = computed(() => {
  const allowed = new Set(assignableForRole.value)
  return (props.permissionCatalog || [])
    .map((group) => ({
      ...group,
      permissions: (group.permissions || []).filter((permission) => allowed.has(permission.key)),
    }))
    .filter((group) => group.permissions.length > 0)
})

const showPermissionEditor = computed(() => {
  if (!props.canCustomizePermissions) return false
  if (isOwnerTarget.value) return false
  if (!form.role) return false
  return assignableForRole.value.length > 0
})

const groupLabel = (group) => {
  return locale.value === 'ar' ? group.group_label_ar : group.group_label_en
}

const permissionLabel = (permission) => {
  return locale.value === 'ar' ? permission.name_ar : permission.name_en
}

const defaultPermissionsForRole = (role) => {
  const defaults = page.props.rolePermissionDefaults || {}
  return defaults[role] || assignableForRole.value.slice()
}

watch(() => props.isOpen, async (isOpen) => {
  if (!isOpen) return

  if (props.membership) {
    form.email = props.membership.user?.email || ''
    form.name = props.membership.user?.name || ''
    form.phone = props.membership.user?.phone || ''
    form.password = ''
    form.password_confirmation = ''
    form.role = props.membership.role || ''
    form.status = props.membership.status || ''
    form.permissions = [...(props.membership.permission_keys || [])]
  } else {
    form.reset()
    form.permissions = []
    form.status = props.statuses?.[0]?.value ?? 1
  }

  form.clearErrors()
}, { immediate: true })

watch(() => form.role, (role, previous) => {
  if (!role || isOwnerTarget.value) return
  if (isEdit.value && previous && previous !== role) {
    form.permissions = defaultPermissionsForRole(role)
  }
  if (!isEdit.value && role) {
    form.permissions = defaultPermissionsForRole(role)
  }
})

const togglePermission = (key) => {
  if (!assignableForRole.value.includes(key)) return
  if (form.permissions.includes(key)) {
    form.permissions = form.permissions.filter((item) => item !== key)
  } else {
    form.permissions = [...form.permissions, key]
  }
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  }

  if (props.membership) {
    const payload = {
      role: form.role,
      status: form.status,
    }
    if (showPermissionEditor.value) {
      payload.permissions = form.permissions.filter((key) => assignableForRole.value.includes(key))
    }
    useForm(payload).patch(route('merchant.team.update', props.membership.id), {
      ...options,
      onError: (errors) => form.setError(errors),
    })
    return
  }

  const payload = {
    email: form.email,
    name: form.name,
    phone: form.phone,
    password: form.password,
    password_confirmation: form.password_confirmation,
    role: form.role,
    status: form.status,
  }

  if (showPermissionEditor.value) {
    payload.permissions = form.permissions.filter((key) => assignableForRole.value.includes(key))
  }

  useForm(payload).post(route('merchant.team.store'), {
    ...options,
    onError: (errors) => form.setError(errors),
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
    title-id="merchant-team-form-modal-title"
    @close="handleClose"
  >
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="merchant-team-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ isEdit ? t('merchantTeam.form.editTitle') : t('merchantTeam.form.addTitle') }}
      </h2>
      <button type="button" @click="handleClose" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
      <div>
        <label class="form-label text-label">
          {{ t('merchantTeam.form.emailLabel') }} <span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.email"
          type="email"
          required
          :disabled="isEdit"
          class="form-input text-body disabled:opacity-60"
        />
        <p v-if="!isEdit" class="text-muted text-sm mt-1">{{ t('merchantTeam.form.addHint') }}</p>
        <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
      </div>

      <div v-if="!isEdit">
        <label class="form-label text-label">
          {{ t('merchantTeam.form.nameLabel') }}
          <span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.name"
          type="text"
          required
          class="form-input text-body"
        />
        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
      </div>

      <div v-if="!isEdit">
        <label class="form-label text-label">{{ t('merchantTeam.form.phoneLabel') }}</label>
        <input v-model="form.phone" type="text" class="form-input text-body" />
        <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
      </div>

      <div v-if="!isEdit">
        <label class="form-label text-label">
          {{ t('merchantTeam.form.passwordLabel') }} <span class="text-red-500">*</span>
        </label>
        <input v-model="form.password" type="password" required autocomplete="new-password" class="form-input text-body" />
        <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
      </div>

      <div v-if="!isEdit">
        <label class="form-label text-label">
          {{ t('merchantTeam.form.passwordConfirmationLabel') }} <span class="text-red-500">*</span>
        </label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="form-input text-body" />
      </div>

      <div>
        <label class="form-label text-label">
          {{ t('merchantTeam.form.roleLabel') }} <span class="text-red-500">*</span>
        </label>
        <select v-model="form.role" required class="form-input text-body">
          <option value="" disabled>{{ t('merchantTeam.form.selectRole') }}</option>
          <option v-for="option in roles" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <p v-if="form.errors.role" class="form-error">{{ form.errors.role }}</p>
      </div>

      <div>
        <label class="form-label text-label">
          {{ t('merchantTeam.form.statusLabel') }} <span class="text-red-500">*</span>
        </label>
        <select v-model="form.status" required class="form-input text-body">
          <option value="" disabled>{{ t('merchantTeam.form.selectStatus') }}</option>
          <option v-for="option in statuses" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
        <p v-if="form.errors.membership" class="form-error">{{ form.errors.membership }}</p>
      </div>

      <div v-if="isOwnerTarget" class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
        <p class="text-body font-medium text-gray-900 dark:text-gray-100">{{ t('merchantTeam.form.fullAccess') }}</p>
        <p class="text-muted text-sm mt-1">{{ t('merchantTeam.form.fullAccessHint') }}</p>
      </div>

      <div v-else-if="showPermissionEditor" class="space-y-4">
        <h3 class="text-body font-medium text-gray-900 dark:text-gray-100">{{ t('merchantTeam.form.permissionsTitle') }}</h3>
        <div v-for="group in visibleGroups" :key="group.group_key" class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
          <p class="text-label mb-2">{{ groupLabel(group) }}</p>
          <label
            v-for="permission in group.permissions"
            :key="permission.key"
            class="flex items-center gap-2 py-1 text-body text-gray-800 dark:text-gray-200"
          >
            <input
              type="checkbox"
              class="rounded border-gray-300"
              :checked="form.permissions.includes(permission.key)"
              @change="togglePermission(permission.key)"
            />
            <span>{{ permissionLabel(permission) }}</span>
          </label>
        </div>
        <p v-if="form.errors.permissions" class="form-error">{{ form.errors.permissions }}</p>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
          {{ t('merchantTeam.form.cancel') }}
        </button>
        <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('merchantTeam.form.saving') : t('merchantTeam.form.save') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

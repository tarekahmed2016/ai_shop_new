<script setup>
import { watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import CategoryTreeSelector from '../Categories/CategoryTreeSelector.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  merchant: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  availableCategories: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])

const form = useForm({
  name: '',
  email: '',
  phone: '',
  status: '',
  category_ids: [],
  owner_name: '',
  owner_email: '',
  owner_phone: '',
  password: '',
  password_confirmation: '',
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.merchant) {
    form.name = props.merchant.name || ''
    form.email = props.merchant.email || ''
    form.phone = props.merchant.phone || ''
    form.status = props.merchant.status || ''
    form.category_ids = []
    form.owner_name = ''
    form.owner_email = ''
    form.owner_phone = ''
    form.password = ''
    form.password_confirmation = ''
  } else {
    form.reset()
    form.category_ids = []
  }

  form.clearErrors()
}, { immediate: true })

const toggleCategory = (publicId) => {
  const current = Array.isArray(form.category_ids) ? [...form.category_ids] : []
  const index = current.indexOf(publicId)

  if (index === -1) {
    current.push(publicId)
  } else {
    current.splice(index, 1)
  }

  form.category_ids = current
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  }

  if (props.merchant) {
    router.put(route('merchants.update', props.merchant.public_id), {
      name: form.name,
      email: form.email,
      phone: form.phone,
      status: form.status,
    }, options)
    return
  }

  form.post(route('merchants.store'), options)
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
    title-id="merchant-form-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="merchant-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ merchant ? t('merchants.form.editTitle') : t('merchants.form.addTitle') }}
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

      <form @submit.prevent="submit" class="px-6 py-4 space-y-6 max-h-[75vh] overflow-y-auto">
        <section class="space-y-4">
          <h3 class="text-label text-gray-900 dark:text-gray-100">{{ t('merchants.form.merchantSection') }}</h3>

          <div>
            <label class="form-label text-label">
              {{ t('merchants.form.nameLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.name" type="text" required class="form-input text-body" />
            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">{{ t('merchants.form.emailLabel') }}</label>
              <input v-model="form.email" type="email" class="form-input text-body" />
              <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
            </div>
            <div>
              <label class="form-label text-label">{{ t('merchants.form.phoneLabel') }}</label>
              <input v-model="form.phone" type="text" dir="ltr" class="form-input text-body" />
              <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
            </div>
          </div>

          <div>
            <label class="form-label text-label">
              {{ t('merchants.form.statusLabel') }} <span class="text-red-500">*</span>
            </label>
            <select v-model="form.status" required class="form-input text-body">
              <option value="" disabled>{{ t('merchants.form.selectStatus') }}</option>
              <option v-for="option in statuses" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
          </div>
        </section>

        <section v-if="!merchant" class="space-y-4">
          <h3 class="text-label text-gray-900 dark:text-gray-100">
            {{ t('merchants.form.categoriesSection') }} <span class="text-red-500">*</span>
          </h3>
          <p class="text-muted muted-color">{{ t('merchants.form.categoriesHint') }}</p>
          <CategoryTreeSelector
            :categories="availableCategories"
            :multiple="true"
            :selectedIds="form.category_ids"
            :emptyText="t('merchants.form.noActiveCategories')"
            @toggle="toggleCategory"
          />
          <p v-if="form.errors.category_ids" class="form-error">{{ form.errors.category_ids }}</p>
          <p v-if="form.errors['category_ids.0']" class="form-error">{{ form.errors['category_ids.0'] }}</p>
        </section>

        <section v-if="!merchant" class="space-y-4">
          <h3 class="text-label text-gray-900 dark:text-gray-100">{{ t('merchants.form.ownerSection') }}</h3>

          <div>
            <label class="form-label text-label">
              {{ t('merchants.form.ownerNameLabel') }} <span class="text-red-500">*</span>
            </label>
            <input v-model="form.owner_name" type="text" required class="form-input text-body" />
            <p v-if="form.errors.owner_name" class="form-error">{{ form.errors.owner_name }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">
                {{ t('merchants.form.ownerEmailLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.owner_email" type="email" required class="form-input text-body" />
              <p v-if="form.errors.owner_email" class="form-error">{{ form.errors.owner_email }}</p>
            </div>
            <div>
              <label class="form-label text-label">{{ t('merchants.form.ownerPhoneLabel') }}</label>
              <input v-model="form.owner_phone" type="text" dir="ltr" class="form-input text-body" />
              <p v-if="form.errors.owner_phone" class="form-error">{{ form.errors.owner_phone }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label text-label">
                {{ t('merchants.form.passwordLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.password" type="password" required autocomplete="new-password" class="form-input text-body" />
              <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
            </div>
            <div>
              <label class="form-label text-label">
                {{ t('merchants.form.passwordConfirmationLabel') }} <span class="text-red-500">*</span>
              </label>
              <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="form-input text-body" />
            </div>
          </div>
        </section>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('merchants.form.cancel') }}
          </button>
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ form.processing ? t('merchants.form.saving') : t('merchants.form.save') }}
          </button>
        </div>
      </form>
  </DashboardModalShell>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import CategoryTreeSelector from '../Categories/CategoryTreeSelector.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  customerRequest: { type: Object, default: null },
  customers: { type: Array, default: () => [] },
  availableCategories: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] }
})

const emit = defineEmits(['close'])
const imageInput = ref(null)

const form = useForm({
  customer_id: '',
  request_text: '',
  status: '',
  category_id: '',
  image: null,
  remove_image: false,
})

watch(() => props.isOpen, (isOpen) => {
  if (!isOpen) return

  if (props.customerRequest) {
    form.customer_id = props.customerRequest.customer?.public_id || ''
    form.request_text = props.customerRequest.request_text || ''
    form.status = props.customerRequest.status || ''
    form.category_id = props.customerRequest.category?.public_id || ''
    form.image = null
    form.remove_image = false
  } else {
    form.reset()
    form.category_id = ''
    form.remove_image = false
  }

  if (imageInput.value) {
    imageInput.value.value = ''
  }

  form.clearErrors()
}, { immediate: true })

const submit = () => {
  const options = {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      form.reset()
      emit('close')
    }
  }

  props.customerRequest
    ? form.put(route('customer-requests.update', props.customerRequest.public_id), options)
    : form.post(route('customer-requests.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  emit('close')
}
</script>

<template>
  <DashboardModalShell :isOpen="isOpen" title-id="customer-request-form-modal-title" @close="handleClose">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="customer-request-form-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ customerRequest ? t('customerRequests.form.editTitle') : t('customerRequests.form.addTitle') }}
      </h2>
      <button @click="handleClose" class="text-gray-400 hover:text-gray-600 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
      <div>
        <label class="form-label text-label">
          {{ t('customerRequests.form.customerLabel') }} <span class="text-red-500">*</span>
        </label>
        <select v-model="form.customer_id" required class="form-input text-body">
          <option value="" disabled>{{ t('customerRequests.form.selectCustomer') }}</option>
          <option v-for="customer in customers" :key="customer.public_id" :value="customer.public_id">
            {{ customer.name || customer.phone || customer.email }}
          </option>
        </select>
        <p v-if="form.errors.customer_id" class="form-error">{{ form.errors.customer_id }}</p>
      </div>

      <div>
        <label class="form-label text-label">
          {{ t('customerRequests.form.textLabel') }} <span class="text-red-500">*</span>
        </label>
        <textarea v-model="form.request_text" rows="4" required class="form-input text-body"></textarea>
        <p v-if="form.errors.request_text" class="form-error">{{ form.errors.request_text }}</p>
      </div>

      <div>
        <label class="form-label text-label">
          {{ t('customerRequests.form.statusLabel') }} <span class="text-red-500">*</span>
        </label>
        <select v-model="form.status" required class="form-input text-body">
          <option value="" disabled>{{ t('customerRequests.form.selectStatus') }}</option>
          <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="form.errors.status" class="form-error">{{ form.errors.status }}</p>
      </div>

      <div>
        <label class="form-label text-label">{{ t('customerRequests.form.categoryLabel') }}</label>
        <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="form.category_id = ''">
          {{ t('customerRequests.form.noCategory') }}
        </button>
        <CategoryTreeSelector
          :categories="availableCategories"
          :multiple="false"
          :selectedId="form.category_id"
          :inputRequired="false"
          :emptyText="t('customerRequests.form.noCategory')"
          @select="form.category_id = $event"
        />
        <p v-if="form.errors.category_id" class="form-error">{{ form.errors.category_id }}</p>
      </div>

      <div>
        <label class="form-label text-label">{{ t('customerRequests.form.imageLabel') }}</label>
        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="form-input text-body"
          @change="form.image = $event.target.files[0] || null" />
        <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
        <label v-if="customerRequest?.has_image" class="flex items-center gap-2 mt-2 text-body">
          <input v-model="form.remove_image" type="checkbox" />
          {{ t('customerRequests.form.removeImage') }}
        </label>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">{{ t('customerRequests.form.cancel') }}</button>
        <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
          {{ form.processing ? t('customerRequests.form.saving') : t('customerRequests.form.save') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

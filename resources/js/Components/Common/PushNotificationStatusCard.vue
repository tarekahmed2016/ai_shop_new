<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  i18nPrefix: { type: String, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  processing: { type: Boolean, default: false },
})

const emit = defineEmits(['enable'])
const { t } = useI18n()

const message = computed(() => {
  switch (props.status) {
    case 'unsupported':
      return t(`${props.i18nPrefix}.unsupported`)
    case 'ios_install':
      return t(`${props.i18nPrefix}.iosInstall`)
    case 'denied':
      return t(`${props.i18nPrefix}.denied`)
    case 'prompt':
      return t(`${props.i18nPrefix}.prompt`)
    case 'subscribed':
      return t(`${props.i18nPrefix}.subscribed`)
    case 'missing_subscription':
      return t(`${props.i18nPrefix}.missingSubscription`)
    case 'missing_vapid':
      return t(`${props.i18nPrefix}.missingVapid`)
    default:
      return ''
  }
})

const canEnable = computed(() => (
  ['prompt', 'missing_subscription'].includes(props.status) && !props.processing
))
</script>

<template>
    <div v-if="status !== 'inactive' && status !== 'checking'" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-3">
        <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t(`${i18nPrefix}.title`) }}</h2>
        <p class="text-body text-gray-700 dark:text-gray-300">{{ message }}</p>
        <p v-if="errorMessage" class="form-error">{{ t(`${i18nPrefix}.error`) }}</p>
        <button
            v-if="canEnable"
            type="button"
            class="btn btn-primary px-4 py-2 disabled:opacity-50"
            :disabled="processing"
            @click="emit('enable')"
        >
            {{ processing ? t(`${i18nPrefix}.enabling`) : t(`${i18nPrefix}.enable`) }}
        </button>
    </div>
</template>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="homepage-promo-delete-modal-title"
    @close="handleClose"
  >
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
          <div class="shrink-0 w-12 h-12 flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div>
            <h2 id="homepage-promo-delete-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
              {{ t('homepagePromos.deleteModal.title') }}
            </h2>
          </div>
        </div>
      </div>

      <div class="px-6 py-4">
        <p class="text-body text-gray-700 dark:text-gray-300">
          {{ t('homepagePromos.deleteModal.confirmMessage', { name: displayTitle }) }}
        </p>
      </div>

      <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
        <button
          type="button"
          @click="handleClose"
          :disabled="loading"
          class="btn btn-secondary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ t('homepagePromos.deleteModal.cancel') }}
        </button>
        <button
          type="button"
          @click="handleConfirm"
          :disabled="loading"
          class="btn btn-danger px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loading ? t('homepagePromos.deleteModal.deleting') : t('homepagePromos.deleteModal.deleteButton') }}
        </button>
      </div>
  </DashboardModalShell>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import { resolveBilingualField } from '../../../Composables/useBilingualContent.js'

const { t, locale } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  homepagePromo: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const displayTitle = computed(() =>
  resolveBilingualField(props.homepagePromo, 'title', locale.value) || `#${props.homepagePromo?.id || ''}`
)

const emit = defineEmits(['close', 'confirm'])

const handleClose = () => emit('close')
const handleConfirm = () => emit('confirm')
</script>

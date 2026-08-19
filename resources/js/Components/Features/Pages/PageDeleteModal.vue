<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="page-delete-modal-title"
    @close="handleClose"
  >
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="page-delete-modal-title" class="text-modal-title text-gray-900 dark:text-gray-100">
        {{ t('pages.deleteModal.title') }}
      </h2>
    </div>
    <div class="px-6 py-4">
      <p class="text-body text-gray-700 dark:text-gray-300">
        {{ t('pages.deleteModal.confirmMessage', { name: displayTitle }) }}
      </p>
    </div>
    <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-900 rounded-b-lg">
      <button type="button" class="btn btn-secondary" :disabled="loading" @click="handleClose">
        {{ t('pages.deleteModal.cancel') }}
      </button>
      <button type="button" class="btn btn-danger" :disabled="loading" @click="handleConfirm">
        {{ loading ? t('pages.deleteModal.deleting') : t('pages.deleteModal.deleteButton') }}
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
  isOpen: { type: Boolean, default: false },
  page: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'confirm'])

const displayTitle = computed(() => resolveBilingualField(props.page, 'title', locale.value))

const handleClose = () => emit('close')
const handleConfirm = () => emit('confirm')
</script>

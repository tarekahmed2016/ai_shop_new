<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="contact-message-detail-modal-title"
    @close="handleClose"
  >
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 id="contact-message-detail-modal-title" class="text-card-title text-gray-900 dark:text-gray-100">
          {{ t('contactMessages.detail.title') }}
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

      <div v-if="contactMessage" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.name') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100">{{ contactMessage.name }}</p>
          </div>
          <div>
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.status') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100">{{ statusLabel }}</p>
          </div>
          <div>
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.email') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100 break-all">{{ contactMessage.email || '—' }}</p>
          </div>
          <div>
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.phone') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100">{{ contactMessage.phone || '—' }}</p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.subject') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100">{{ contactMessage.subject || '—' }}</p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-label text-muted muted-color">{{ t('contactMessages.detail.receivedAt') }}</p>
            <p class="text-body text-gray-900 dark:text-gray-100">{{ formattedReceivedAt }}</p>
          </div>
        </div>

        <div>
          <p class="text-label text-muted muted-color mb-2">{{ t('contactMessages.detail.message') }}</p>
          <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-4">
            <p class="text-body text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">{{ contactMessage.message }}</p>
          </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="handleClose" class="btn btn-secondary px-4 py-2">
            {{ t('contactMessages.detail.close') }}
          </button>
          <button
            v-if="!contactMessage.is_read"
            type="button"
            :disabled="readLoading"
            @click="$emit('mark-read', contactMessage)"
            class="btn btn-primary px-4 py-2 disabled:opacity-50"
          >
            {{ t('contactMessages.table.markRead') }}
          </button>
          <button
            v-else
            type="button"
            :disabled="unreadLoading"
            @click="$emit('mark-unread', contactMessage)"
            class="btn btn-secondary px-4 py-2 disabled:opacity-50"
          >
            {{ t('contactMessages.table.markUnread') }}
          </button>
        </div>
      </div>
  </DashboardModalShell>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t, locale } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  contactMessage: {
    type: Object,
    default: null
  },
  readLoading: {
    type: Boolean,
    default: false
  },
  unreadLoading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'mark-read', 'mark-unread'])

const statusLabel = computed(() => {
  if (!props.contactMessage) return '—'

  if (locale.value === 'ar') {
    return props.contactMessage.is_read_formatted?.label || (props.contactMessage.is_read ? 'مقروء' : 'غير مقروء')
  }

  return props.contactMessage.is_read_formatted?.name || (props.contactMessage.is_read ? 'Read' : 'Unread')
})

const formattedReceivedAt = computed(() => {
  if (!props.contactMessage?.created_at) return '—'

  return new Date(props.contactMessage.created_at).toLocaleString(locale.value === 'ar' ? 'ar' : 'en')
})

const handleClose = () => emit('close')
</script>

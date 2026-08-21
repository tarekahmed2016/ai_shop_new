<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'

const { t } = useI18n()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  customerRequest: { type: Object, default: null }
})

const emit = defineEmits(['close', 'match'])

const matchesCount = computed(() => props.customerRequest?.matches_count ?? 0)
const matchesSummary = computed(() => {
  if (matchesCount.value > 0) {
    return t('customerRequests.details.matchedMerchants', { count: matchesCount.value })
  }

  return t('customerRequests.details.noMerchantsMatched')
})
</script>

<template>
  <DashboardModalShell :isOpen="isOpen" title-id="customer-request-details-title" @close="emit('close')">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="customer-request-details-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ t('customerRequests.details.title') }}
      </h2>
    </div>
    <div v-if="customerRequest" class="px-6 py-4 space-y-3 text-body text-gray-800 dark:text-gray-200">
      <p><strong>{{ t('customerRequests.table.customer') }}:</strong> {{ customerRequest.customer?.display_name || customerRequest.customer?.name }}</p>
      <p><strong>{{ t('customerRequests.table.status') }}:</strong> {{ customerRequest.status_formatted?.label }}</p>
      <p><strong>{{ t('customerRequests.table.source') }}:</strong> {{ customerRequest.source_formatted?.label }}</p>
      <p><strong>{{ t('customerRequests.table.category') }}:</strong>
        {{ customerRequest.category ? `${customerRequest.category.name_ar} / ${customerRequest.category.name_en}` : '—' }}
      </p>
      <p><strong>{{ t('customerRequests.table.matches') }}:</strong> {{ matchesSummary }}</p>
      <p class="whitespace-pre-wrap">{{ customerRequest.request_text }}</p>
      <div v-if="customerRequest.image_url">
        <p class="mb-2">{{ t('customerRequests.details.image') }}</p>
        <img :src="customerRequest.image_url" alt="" class="max-h-64 rounded border border-gray-200 dark:border-gray-700" />
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4">
      <button type="button" class="btn btn-secondary px-4 py-2" @click="emit('close')">
        {{ t('customerRequests.details.close') }}
      </button>
      <button
        type="button"
        class="btn btn-primary px-4 py-2"
        :disabled="!customerRequest?.category"
        @click="emit('match', customerRequest)">
        {{ t('customerRequests.matchMerchants') }}
      </button>
    </div>
  </DashboardModalShell>
</template>

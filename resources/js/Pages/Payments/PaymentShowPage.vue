<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const payment = computed(() => page.props.payment || {})

const formatRow = (row) => {
  const name = row.customer?.name || ''
  const amount = row.amount > 0 ? `+${row.amount}` : String(row.amount)
  return `${amount} · ${row.type?.label || ''} · ${name}`
}

const formatMerchantRow = (row) => {
  const name = row.merchant?.name || ''
  const amount = row.amount > 0 ? `+${row.amount}` : String(row.amount)
  return `${amount} · ${row.paid_amount || '—'} OMR · ${name}`
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-5xl mx-auto space-y-6">
      <div>
        <button type="button" class="text-body text-blue-600 mb-2 cursor-pointer" @click="router.visit(route('payments.index'))">
          ← {{ t('payments.backToPayments') }}
        </button>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('payments.showTitle') }}</h1>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <p><span class="text-muted">{{ t('payments.table.date') }}:</span> {{ String(payment.paid_at || payment.created_at || '').replace('T', ' ').slice(0, 16) }}</p>
        <p><span class="text-muted">{{ t('payments.table.payer') }}:</span> {{ payment.payer?.name || payment.payer?.email || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.type') }}:</span> {{ payment.type?.label || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.amount') }}:</span> {{ payment.amount || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.method') }}:</span> {{ payment.payment_method?.label || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.status') }}:</span> {{ payment.status?.label || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.reference') }}:</span> {{ payment.reference || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.createdBy') }}:</span> {{ payment.created_by?.name || '—' }}</p>
        <p class="md:col-span-2"><span class="text-muted">{{ t('payments.table.notes') }}:</span> {{ payment.notes || '—' }}</p>
        <p><span class="text-muted">{{ t('payments.table.context') }}:</span> {{ payment.customer?.name || payment.merchant?.name || '—' }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
        <h2 class="text-card-title">{{ t('payments.ledgerTitle') }}</h2>
        <div>
          <h3 class="font-medium mb-2">{{ t('payments.extraLedger') }}</h3>
          <p v-if="!(payment.extra_request_ledger || []).length" class="text-muted">{{ t('payments.noLedger') }}</p>
          <ul v-else class="space-y-1">
            <li v-for="row in payment.extra_request_ledger" :key="'e-' + row.id">
              {{ formatRow(row) }}
            </li>
          </ul>
        </div>
        <div>
          <h3 class="font-medium mb-2">{{ t('payments.merchantLedger') }}</h3>
          <p v-if="!(payment.merchant_credit_ledger || []).length" class="text-muted">{{ t('payments.noLedger') }}</p>
          <ul v-else class="space-y-1">
            <li v-for="row in payment.merchant_credit_ledger" :key="'m-' + row.id">
              {{ formatMerchantRow(row) }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

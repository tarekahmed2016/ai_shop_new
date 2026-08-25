<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const marketer = computed(() => page.props.marketer || {})
const summary = computed(() => page.props.summary || {})
const globalRates = computed(() => page.props.globalRates || {})
const effectiveRates = computed(() => page.props.effectiveRates || {})
const methods = computed(() => page.props.methods || [])

const rateForm = useForm({
  customer_commission_rate: marketer.value.customer_commission_rate ?? '',
  merchant_commission_rate: marketer.value.merchant_commission_rate ?? '',
})

const payoutForm = useForm({
  amount: '',
  payment_method: 1,
  reference: '',
  notes: '',
  paid_at: new Date().toISOString().slice(0, 10),
})

const submitRates = () => {
  rateForm.put(route('marketers.commission-rates.update', marketer.value.public_id), {
    preserveScroll: true,
  })
}

const submitPayout = () => {
  payoutForm.post(route('marketers.payouts.store', marketer.value.public_id), {
    preserveScroll: true,
    onSuccess: () => {
      payoutForm.reset('amount', 'reference', 'notes')
      payoutForm.payment_method = 1
      payoutForm.paid_at = new Date().toISOString().slice(0, 10)
    },
  })
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
      <div>
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ marketer.name }}</h1>
        <p class="mt-2 text-muted muted-color">{{ marketer.email }}</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.referralPayments') }}</p>
          <p class="text-2xl font-semibold">{{ summary.referral_payments }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.approvedCommission') }}</p>
          <p class="text-2xl font-semibold">{{ summary.approved_commission }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.pendingCommission') }}</p>
          <p class="text-2xl font-semibold">{{ summary.pending_commission }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.totalPaid') }}</p>
          <p class="text-2xl font-semibold">{{ summary.paid }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-muted text-sm">{{ t('marketerFinance.outstanding') }}</p>
          <p class="text-2xl font-semibold">{{ summary.outstanding }}</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <Link :href="route('marketers.commissions', marketer.public_id)" class="btn btn-secondary">
          {{ t('marketerFinance.viewCommissions') }}
        </Link>
        <Link :href="route('marketers.payouts', marketer.public_id)" class="btn btn-secondary">
          {{ t('marketerFinance.viewPayouts') }}
        </Link>
        <Link :href="route('marketers.index')" class="btn btn-secondary">{{ t('marketerFinance.back') }}</Link>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-4">
          <h2 class="text-card-title">{{ t('marketerFinance.recordPayout') }}</h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <p>{{ t('marketerFinance.approvedCommission') }}: {{ summary.approved_commission }}</p>
            <p>{{ t('marketerFinance.alreadyPaid') }}: {{ summary.paid }}</p>
            <p>{{ t('marketerFinance.outstanding') }}: {{ summary.outstanding }}</p>
          </div>
          <form class="space-y-3" @submit.prevent="submitPayout">
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.amount') }}</label>
              <input v-model="payoutForm.amount" type="number" step="0.001" min="0.001" class="form-input w-full" />
              <p v-if="payoutForm.errors.amount" class="text-red-600 text-sm mt-1">{{ payoutForm.errors.amount }}</p>
            </div>
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.method') }}</label>
              <select v-model="payoutForm.payment_method" class="form-input w-full">
                <option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option>
              </select>
            </div>
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.reference') }}</label>
              <input v-model="payoutForm.reference" type="text" class="form-input w-full" />
            </div>
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.notes') }}</label>
              <textarea v-model="payoutForm.notes" class="form-input w-full" rows="3"></textarea>
            </div>
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.paidAt') }}</label>
              <input v-model="payoutForm.paid_at" type="date" class="form-input w-full" />
            </div>
            <button type="submit" class="btn btn-primary" :disabled="payoutForm.processing">
              {{ t('marketerFinance.recordPayout') }}
            </button>
          </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 space-y-4">
          <h2 class="text-card-title">{{ t('marketerFinance.overrides') }}</h2>
          <p class="text-sm text-muted">
            {{ t('marketerFinance.globalCustomerRate') }}: {{ globalRates.customer_extra_requests }}%
            · {{ t('marketerFinance.globalMerchantRate') }}: {{ globalRates.merchant_offer_credits }}%
          </p>
          <p class="text-sm">
            {{ t('marketerFinance.effectiveCustomerRate') }}: {{ effectiveRates.customer_extra_requests }}%
            · {{ t('marketerFinance.effectiveMerchantRate') }}: {{ effectiveRates.merchant_offer_credits }}%
          </p>
          <form class="space-y-3" @submit.prevent="submitRates">
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.customerRate') }}</label>
              <input v-model="rateForm.customer_commission_rate" type="number" step="0.001" min="0" max="100" class="form-input w-full" />
            </div>
            <div>
              <label class="form-label text-label">{{ t('marketerFinance.merchantRate') }}</label>
              <input v-model="rateForm.merchant_commission_rate" type="number" step="0.001" min="0" max="100" class="form-input w-full" />
            </div>
            <p class="text-sm text-muted">{{ t('marketerFinance.overrideHint') }}</p>
            <button type="submit" class="btn btn-secondary" :disabled="rateForm.processing">
              {{ t('marketerFinance.saveRates') }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useMerchantRequests } from '../../Composables/useMerchantRequests.js'

const { t } = useI18n()
const page = usePage()
const requestItem = computed(() => page.props.request || {})
const offer = computed(() => page.props.offer || null)
const permissions = computed(() => page.props.offerPermissions || {})
const offerCredits = computed(() => page.props.offerCredits || {})
const availabilityStatuses = computed(() => page.props.availabilityStatuses || [])
const { dismissRequest } = useMerchantRequests()

const editing = ref(!page.props.offer)
const removeImageIds = ref([])

const form = useForm({
  price: page.props.offer?.price || '',
  availability_status: page.props.offer?.availability_status || '',
  notes: page.props.offer?.notes || '',
  valid_until: page.props.offer?.valid_until || '',
  images: [],
  remove_image_ids: [],
})

const categoryLabel = computed(() => {
  const category = requestItem.value.category
  if (!category) {
    return '—'
  }

  return `${category.name_ar} / ${category.name_en}`
})

const createdAt = computed(() => {
  const value = requestItem.value.created_at
  if (!value) {
    return '—'
  }

  return String(value).replace('T', ' ').slice(0, 16)
})

const isSubmitted = computed(() => offer.value?.status_formatted?.name === 'Submitted')
const hasSubmitCredits = computed(() => offerCredits.value.can_consume_new !== false)
const canSubmit = computed(() => permissions.value.create && !offer.value && hasSubmitCredits.value)
const canResubmit = computed(() => permissions.value.create && offer.value && !isSubmitted.value)
const canEdit = computed(() => permissions.value.update && isSubmitted.value)
const canWithdraw = computed(() => permissions.value.withdraw && isSubmitted.value)

const handleDismiss = () => {
  if (!requestItem.value.public_id) {
    return
  }

  dismissRequest(requestItem.value.public_id)
}

const onFilesChange = (event) => {
  form.images = Array.from(event.target.files || [])
}

const toggleRemoveImage = (id) => {
  if (removeImageIds.value.includes(id)) {
    removeImageIds.value = removeImageIds.value.filter((item) => item !== id)
  } else {
    removeImageIds.value = [...removeImageIds.value, id]
  }
}

const submitOffer = () => {
  form.remove_image_ids = removeImageIds.value
  const options = {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      editing.value = false
      form.images = []
      removeImageIds.value = []
    },
  }

  if (isSubmitted.value) {
    form.post(route('merchant.requests.offers.update', requestItem.value.public_id), options)
    return
  }

  form.post(route('merchant.requests.offers.store', requestItem.value.public_id), options)
}

const withdrawOffer = () => {
  router.post(route('merchant.requests.offers.withdraw', requestItem.value.public_id), {}, { preserveScroll: true })
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-3xl mx-auto space-y-6">
      <div>
        <Link :href="route('merchant.requests.index')" class="text-body text-blue-600">
          ← {{ t('merchantRequests.backToList') }}
        </Link>
        <h1 class="mt-3 text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantRequests.detailsTitle') }}</h1>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4 text-body text-gray-800 dark:text-gray-200">
        <p><strong>{{ t('merchantRequests.table.category') }}:</strong> {{ categoryLabel }}</p>
        <p><strong>{{ t('merchantRequests.table.date') }}:</strong> {{ createdAt }}</p>
        <p><strong>{{ t('merchantRequests.table.matchStatus') }}:</strong> {{ requestItem.match_status?.label || '—' }}</p>
        <p class="whitespace-pre-wrap">{{ requestItem.request_text }}</p>
        <div v-if="requestItem.image_url">
          <p class="mb-2">{{ t('merchantRequests.image') }}</p>
          <img :src="requestItem.image_url" alt="" class="max-h-80 rounded border border-gray-200 dark:border-gray-700" />
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
          <button type="button" class="btn btn-secondary px-4 py-2" @click="router.visit(route('merchant.requests.index'))">
            {{ t('merchantRequests.backToList') }}
          </button>
          <button type="button" class="btn btn-primary px-4 py-2" @click="handleDismiss">
            {{ t('merchantRequests.dismiss') }}
          </button>
        </div>
      </div>

      <div v-if="permissions.view" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4 text-body text-gray-800 dark:text-gray-200">
        <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('merchantOffers.sectionTitle') }}</h2>
        <p class="text-muted muted-color">
          {{ offerCredits.enforcement_enabled ? t('merchantOffers.remainingCredits', { count: offerCredits.balance ?? 0 }) : t('merchantOffers.freeSubmission') }}
        </p>
        <p v-if="offerCredits.enforcement_enabled && !hasSubmitCredits && !offer" class="form-error">
          {{ t('merchantOffers.insufficientCredits') }}
        </p>

        <div v-if="offer && !editing">
          <p><strong>{{ t('merchantOffers.status') }}:</strong> {{ offer.status_formatted?.label }}</p>
          <p><strong>{{ t('merchantOffers.price') }}:</strong> {{ offer.price }} {{ offer.currency }}</p>
          <p><strong>{{ t('merchantOffers.availability') }}:</strong> {{ offer.availability_status_formatted?.label }}</p>
          <p v-if="offer.notes"><strong>{{ t('merchantOffers.notes') }}:</strong> {{ offer.notes }}</p>
          <p><strong>{{ t('merchantOffers.validUntil') }}:</strong> {{ offer.valid_until || '—' }}</p>
          <div v-if="offer.images?.length" class="flex flex-wrap gap-3">
            <img v-for="image in offer.images" :key="image.id" :src="image.url" alt="" class="h-24 rounded border border-gray-200 dark:border-gray-700" />
          </div>
          <div class="flex flex-wrap gap-3 pt-2">
            <button v-if="canEdit" type="button" class="btn btn-primary px-4 py-2" @click="editing = true">
              {{ t('merchantOffers.edit') }}
            </button>
            <button v-if="canWithdraw" type="button" class="btn btn-secondary px-4 py-2" @click="withdrawOffer">
              {{ t('merchantOffers.withdraw') }}
            </button>
            <button v-if="canResubmit" type="button" class="btn btn-primary px-4 py-2" @click="editing = true">
              {{ t('merchantOffers.resubmit') }}
            </button>
          </div>
        </div>

        <p v-else-if="!offer && !canSubmit && hasSubmitCredits" class="text-muted">{{ t('merchantOffers.none') }}</p>

        <form v-if="canSubmit || (editing && (canEdit || canResubmit))" class="space-y-4" @submit.prevent="submitOffer">
          <p v-if="form.errors.credits" class="form-error">{{ form.errors.credits }}</p>
          <div>
            <label class="form-label text-label">{{ t('merchantOffers.price') }} <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-2">
              <input v-model="form.price" type="text" inputmode="decimal" required class="form-input text-body" />
              <span>OMR</span>
            </div>
            <p v-if="form.errors.price" class="form-error">{{ form.errors.price }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchantOffers.availability') }} <span class="text-red-500">*</span></label>
            <select v-model="form.availability_status" required class="form-input text-body">
              <option value="" disabled>{{ t('merchantOffers.selectAvailability') }}</option>
              <option v-for="option in availabilityStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="form.errors.availability_status" class="form-error">{{ form.errors.availability_status }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchantOffers.notes') }}</label>
            <textarea v-model="form.notes" rows="3" class="form-input text-body"></textarea>
            <p v-if="form.errors.notes" class="form-error">{{ form.errors.notes }}</p>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchantOffers.validUntil') }}</label>
            <input v-model="form.valid_until" type="date" class="form-input text-body" />
            <p v-if="form.errors.valid_until" class="form-error">{{ form.errors.valid_until }}</p>
          </div>
          <div v-if="offer?.images?.length">
            <p class="form-label text-label">{{ t('merchantOffers.existingImages') }}</p>
            <label v-for="image in offer.images" :key="image.id" class="flex items-center gap-2 mt-2">
              <input type="checkbox" :checked="removeImageIds.includes(image.id)" @change="toggleRemoveImage(image.id)" />
              <img :src="image.url" alt="" class="h-16 rounded" />
              <span>{{ t('merchantOffers.removeImage') }}</span>
            </label>
          </div>
          <div>
            <label class="form-label text-label">{{ t('merchantOffers.images') }}</label>
            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="form-input text-body" @change="onFilesChange" />
            <p class="text-muted text-sm">{{ t('merchantOffers.imagesHint') }}</p>
            <p v-if="form.errors.images" class="form-error">{{ form.errors.images }}</p>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
              {{ form.processing ? t('merchantOffers.saving') : t('merchantOffers.submit') }}
            </button>
            <button v-if="offer && editing" type="button" class="btn btn-secondary px-4 py-2" @click="editing = false">
              {{ t('merchantOffers.cancel') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

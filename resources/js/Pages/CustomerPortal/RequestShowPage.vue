<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faWhatsapp } from '@fortawesome/free-brands-svg-icons'

const { t, locale } = useI18n()
const page = usePage()
const request = computed(() => page.props.request || {})
const offers = computed(() => page.props.offers || [])

const categoryName = computed(() => {
    const category = request.value.category
    if (!category) return '—'
    return locale.value === 'ar' ? (category.name_ar || category.name_en) : (category.name_en || category.name_ar)
})

const formatDate = (value) => value ? new Date(value).toLocaleString() : '—'
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <Link :href="route('customer.requests.index')" class="text-blue-600 text-sm">← {{ t('customerPortal.requests.back') }}</Link>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100 mt-2">{{ t('customerPortal.show.title') }}</h1>
                <p class="text-muted muted-color mt-1 font-mono text-sm">{{ request.public_id }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.status') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ request.status_formatted?.label }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.category') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ categoryName }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.requests.date') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100">{{ formatDate(request.created_at) }}</p>
                </div>
                <div>
                    <p class="text-label text-muted">{{ t('customerPortal.create.requestText') }}</p>
                    <p class="text-body text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ request.request_text }}</p>
                </div>
                <div v-if="request.has_image">
                    <p class="text-label text-muted mb-2">{{ t('customerPortal.requests.image') }}</p>
                    <img :src="route('customer.requests.image', request.public_id)" alt="" class="max-w-full rounded-md border border-gray-200 dark:border-gray-700" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.show.offersTitle') }}</h2>
                <p class="text-muted">{{ t('customerPortal.show.offersCount', { count: offers.length }) }}</p>
                <p v-if="offers.length === 0" class="text-muted">{{ t('customerPortal.show.noOffers') }}</p>
                <div v-for="offer in offers" :key="offer.public_id" class="border border-gray-200 dark:border-gray-700 rounded-md p-4 space-y-2">
                    <p class="text-body font-medium text-gray-900 dark:text-gray-100">{{ offer.merchant_name }}</p>
                    <p class="text-body">{{ offer.price }} {{ offer.currency }}</p>
                    <p class="text-body">{{ offer.availability_status_formatted?.label }}</p>
                    <p v-if="offer.notes" class="text-body whitespace-pre-wrap">{{ offer.notes }}</p>
                    <p class="text-muted text-sm">{{ t('customerPortal.show.validUntil') }}: {{ offer.valid_until || '—' }}</p>
                    <p class="text-muted text-sm">{{ t('customerPortal.show.submittedAt') }}: {{ formatDate(offer.submitted_at) }}</p>
                    <div v-if="offer.images?.length" class="flex flex-wrap gap-3 pt-2">
                        <img v-for="image in offer.images" :key="image.id" :src="image.url" alt="" class="h-24 rounded border border-gray-200 dark:border-gray-700" />
                    </div>
                    <div class="pt-3">
                        <a
                            v-if="offer.whatsapp_url"
                            :href="offer.whatsapp_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-white bg-[#25D366] hover:bg-[#1ebe5d]"
                        >
                            <font-awesome-icon :icon="faWhatsapp" />
                            {{ t('customerPortal.show.contactWhatsApp') }}
                        </a>
                        <span
                            v-else
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-gray-500 bg-gray-100 dark:bg-gray-700 dark:text-gray-300 cursor-not-allowed"
                        >
                            <font-awesome-icon :icon="faWhatsapp" />
                            {{ t('customerPortal.show.whatsappUnavailable') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

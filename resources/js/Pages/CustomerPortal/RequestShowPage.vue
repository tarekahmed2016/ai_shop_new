<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const page = usePage()
const request = computed(() => page.props.request || {})

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

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6">
                <h2 class="text-card-title text-gray-900 dark:text-gray-100 mb-2">{{ t('customerPortal.show.offersTitle') }}</h2>
                <p class="text-muted">{{ t('customerPortal.show.noOffers') }}</p>
            </div>
        </div>
    </div>
</template>

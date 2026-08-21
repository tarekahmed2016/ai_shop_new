<script setup>
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useMerchantRequests } from '../../Composables/useMerchantRequests.js'

const { t } = useI18n()
const page = usePage()
const requestItem = computed(() => page.props.request || {})
const { dismissRequest } = useMerchantRequests()

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

const handleDismiss = () => {
    if (!requestItem.value.public_id) {
        return
    }

    dismissRequest(requestItem.value.public_id)
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
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const merchants = computed(() => page.props.availableMerchants || [])
const form = useForm({ public_id: '' })

const selectMerchant = (publicId) => {
    form.public_id = publicId
    form.post(route('merchant.context.store'), { preserveScroll: true })
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6 md:mb-8">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantSelect.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchantSelect.pageSubtitle') }}</p>
            </div>

            <div v-if="merchants.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <p class="text-body text-gray-700 dark:text-gray-300">{{ t('merchantSelect.empty') }}</p>
            </div>

            <div v-else class="space-y-3">
                <button
                    v-for="merchant in merchants"
                    :key="merchant.public_id"
                    type="button"
                    class="w-full text-start bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:border-blue-300 border border-gray-200 dark:border-gray-700 cursor-pointer"
                    :disabled="form.processing"
                    @click="selectMerchant(merchant.public_id)"
                >
                    <p class="text-card-title text-gray-900 dark:text-gray-100">{{ merchant.name }}</p>
                    <p class="text-muted muted-color mt-1">{{ merchant.role }}</p>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const merchant = computed(() => page.props.merchant || page.props.merchantContext || {})
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantHome.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchantHome.pageSubtitle', { name: merchant.name }) }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <p class="text-body text-gray-700 dark:text-gray-300">{{ t('merchantHome.contextRole') }}: {{ merchant.role }}</p>
                <div class="mt-4 flex flex-wrap gap-4">
                    <Link :href="route('merchant.requests.index')" class="text-blue-600">
                        {{ t('merchantHome.availableRequests') }}
                    </Link>
                    <Link :href="route('merchant.activities.index')" class="text-blue-600">
                        {{ t('merchantHome.businessActivities') }}
                    </Link>
                    <Link :href="route('merchant.team.index')" class="text-blue-600">
                        {{ t('merchantHome.teamMembers') }}
                    </Link>
                    <Link :href="route('merchant.select')" class="text-blue-600">
                        {{ t('merchantHome.switchMerchant') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

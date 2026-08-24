<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import MerchantPushStatusCard from '../../Components/Features/Merchants/MerchantPushStatusCard.vue'
import {
    faClipboardList,
    faPaperPlane,
} from '@fortawesome/free-solid-svg-icons'

const { t } = useI18n()
const page = usePage()
const merchant = computed(() => page.props.merchant || page.props.merchantContext || {})
const usage = computed(() => page.props.usage || {})

const usageCards = computed(() => [
    {
        label: t('merchantHome.requestsReceived'),
        value: usage.value.requests_received ?? 0,
        icon: faClipboardList,
    },
    {
        label: t('merchantHome.offersSubmitted'),
        value: usage.value.offers_submitted ?? 0,
        icon: faPaperPlane,
    },
])
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantHome.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('merchantHome.pageSubtitle', { name: merchant.name }) }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                    v-for="card in usageCards"
                    :key="card.label"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <font-awesome-icon :icon="card.icon" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-muted muted-color">{{ card.label }}</p>
                            <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ card.value }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <MerchantPushStatusCard />

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
                    <Link :href="route('merchant.business-profile.edit')" class="text-blue-600">
                        {{ t('merchantHome.businessProfile') }}
                    </Link>
                    <Link :href="route('merchant.select')" class="text-blue-600">
                        {{ t('merchantHome.switchMerchant') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

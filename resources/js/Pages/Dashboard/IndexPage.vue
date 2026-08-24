<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import {
    faArrowUpRightFromSquare,
    faBriefcase,
    faBuilding,
    faClipboardList,
    faEnvelope,
    faEye,
    faPaperPlane,
    faStore,
    faTags,
} from '@fortawesome/free-solid-svg-icons'

const { t } = useI18n()
const page = usePage()

const isAdmin = computed(() => page.props.isAdmin === true || page.props.auth?.isAdmin === true)
const merchantWorkspace = computed(() => page.props.merchantWorkspace || null)
const hasMerchantMemberships = computed(() => page.props.hasMerchantMemberships === true)

const adminQuickLinks = computed(() => [
    {
        label: t('dashboard.manageCompanyInfo'),
        route: route('company-info.index'),
        icon: faBuilding,
    },
    {
        label: t('dashboard.manageServices'),
        route: route('services.index'),
        icon: faBriefcase,
    },
    {
        label: t('dashboard.viewContactMessages'),
        route: route('contact-messages.index'),
        icon: faEnvelope,
    },
    {
        label: t('dashboard.viewPublicSite'),
        route: route('home'),
        icon: faArrowUpRightFromSquare,
        external: true,
    },
])

const merchantStats = computed(() => {
    if (!merchantWorkspace.value) {
        return []
    }

    return [
        {
            label: t('dashboard.merchant.requestsReceived'),
            value: merchantWorkspace.value.requests_received ?? 0,
            icon: faClipboardList,
            prominent: true,
        },
        {
            label: t('dashboard.merchant.offersSubmitted'),
            value: merchantWorkspace.value.offers_submitted ?? 0,
            icon: faPaperPlane,
            prominent: true,
        },
        {
            label: t('dashboard.merchant.categoriesCount'),
            value: merchantWorkspace.value.categories_count ?? 0,
            icon: faTags,
        },
        {
            label: t('dashboard.merchant.availableRequestsCount'),
            value: merchantWorkspace.value.available_requests_count ?? 0,
            icon: faClipboardList,
        },
        {
            label: t('dashboard.merchant.viewedRequestsCount'),
            value: merchantWorkspace.value.viewed_requests_count ?? 0,
            icon: faEye,
        },
    ]
})

const prominentStats = computed(() => merchantStats.value.filter((stat) => stat.prominent))
const operationalStats = computed(() => merchantStats.value.filter((stat) => !stat.prominent))
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-8">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('dashboard.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('dashboard.pageSubtitle') }}</p>
            </div>

            <section v-if="isAdmin">
                <h2 class="text-section-title text-gray-900 dark:text-gray-100 mb-4">{{ t('dashboard.quickLinksTitle') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <component
                        v-for="link in adminQuickLinks"
                        :key="link.label"
                        :is="link.external ? 'a' : Link"
                        :href="link.route"
                        :target="link.external ? '_blank' : undefined"
                        :rel="link.external ? 'noopener noreferrer' : undefined"
                        class="group flex items-start gap-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <font-awesome-icon :icon="link.icon" class="h-4 w-4" />
                        </span>
                        <span class="text-body text-gray-900 dark:text-gray-100 group-hover:text-blue-700 dark:group-hover:text-blue-300">
                            {{ link.label }}
                        </span>
                    </component>
                </div>
            </section>

            <section v-else-if="merchantWorkspace" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <font-awesome-icon :icon="faStore" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-section-title text-gray-900 dark:text-gray-100">
                                {{ t('dashboard.merchant.workspaceTitle') }}
                            </h2>
                            <p class="mt-2 text-body text-gray-700 dark:text-gray-300">
                                {{ t('dashboard.merchant.workingAs', { name: merchantWorkspace.name }) }}
                            </p>
                            <p class="mt-1 text-muted muted-color">
                                {{ t('dashboard.merchant.roleLabel') }}: {{ merchantWorkspace.role }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div
                        v-for="stat in prominentStats"
                        :key="stat.label"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                                <font-awesome-icon :icon="stat.icon" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-muted muted-color">{{ stat.label }}</p>
                                <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ stat.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div
                        v-for="stat in operationalStats"
                        :key="stat.label"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                                <font-awesome-icon :icon="stat.icon" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="text-muted muted-color">{{ stat.label }}</p>
                                <p class="text-card-title text-gray-900 dark:text-gray-100">{{ stat.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <Link :href="route('merchant.requests.index')" class="text-blue-600 hover:text-blue-700">
                        {{ t('dashboard.merchant.viewAvailableRequests') }}
                    </Link>
                    <Link :href="route('merchant.home')" class="text-blue-600 hover:text-blue-700">
                        {{ t('dashboard.merchant.openWorkspace') }}
                    </Link>
                    <Link :href="route('merchant.select')" class="text-blue-600 hover:text-blue-700">
                        {{ t('dashboard.merchant.switchMerchant') }}
                    </Link>
                </div>
            </section>

            <section v-else-if="hasMerchantMemberships" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-section-title text-gray-900 dark:text-gray-100">
                    {{ t('dashboard.merchant.selectTitle') }}
                </h2>
                <p class="mt-2 text-body text-gray-700 dark:text-gray-300">
                    {{ t('dashboard.merchant.selectSubtitle') }}
                </p>
                <Link :href="route('merchant.select')" class="inline-block mt-4 text-blue-600 hover:text-blue-700">
                    {{ t('dashboard.merchant.selectAction') }}
                </Link>
            </section>

            <section v-else class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <p class="text-body text-gray-700 dark:text-gray-300">{{ t('dashboard.merchant.emptyAccess') }}</p>
            </section>
        </div>
    </div>
</template>

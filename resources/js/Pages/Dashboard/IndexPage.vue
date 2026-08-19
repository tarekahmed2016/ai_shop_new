<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { faArrowUpRightFromSquare, faBuilding, faBriefcase, faEnvelope } from '@fortawesome/free-solid-svg-icons'

const { t } = useI18n()

const quickLinks = computed(() => [
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
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-7xl mx-auto space-y-8">
            <div>
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('dashboard.pageTitle') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('dashboard.pageSubtitle') }}</p>
            </div>

            <section>
                <h2 class="text-section-title text-gray-900 dark:text-gray-100 mb-4">{{ t('dashboard.quickLinksTitle') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <component
                        v-for="link in quickLinks"
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
        </div>
    </div>
</template>

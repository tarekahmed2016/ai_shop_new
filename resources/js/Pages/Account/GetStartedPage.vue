<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const form = useForm({})

const capabilities = computed(() => page.props.auth?.capabilities || page.props.capabilities || {})
const hasActiveCustomer = computed(() => Boolean(capabilities.value.hasActiveCustomer))
const hasCustomer = computed(() => Boolean(capabilities.value.hasCustomer))
const hasActiveMerchantMemberships = computed(() => Boolean(capabilities.value.hasActiveMerchantMemberships))
const hasActiveMarketer = computed(() => Boolean(capabilities.value.hasActiveMarketer))
const marketerStatus = computed(() => capabilities.value.marketerStatus || null)
const inactiveCustomer = computed(() => hasCustomer.value && !hasActiveCustomer.value)

const enableCustomer = () => {
    form.post(route('account.customer.enable.store'))
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-3xl">
            <div class="text-center mb-8">
                <h1 class="text-hero text-white">{{ t('account.getStarted.title') }}</h1>
                <p class="text-small text-lighter mt-3">{{ t('account.getStarted.subtitle') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="glass-card p-6 shadow-xl flex flex-col">
                    <h2 class="text-lg font-semibold text-white mb-2">{{ t('account.getStarted.requestTitle') }}</h2>
                    <p class="text-small text-lighter mb-6 flex-1">{{ t('account.getStarted.requestDescription') }}</p>
                    <p v-if="hasActiveCustomer" class="text-sm text-emerald-400 mb-3">{{ t('account.getStarted.requestEnabled') }}</p>
                    <p v-else-if="inactiveCustomer" class="text-sm text-amber-400 mb-3">{{ t('account.getStarted.inactiveCustomer') }}</p>
                    <Link
                        v-if="hasActiveCustomer"
                        :href="route('customer.requests.create')"
                        class="w-full btn-primary text-center"
                    >
                        {{ t('account.getStarted.requestCta') }}
                    </Link>
                    <button
                        v-else-if="!inactiveCustomer"
                        type="button"
                        class="w-full btn-primary"
                        :disabled="form.processing"
                        @click="enableCustomer"
                    >
                        {{ t('account.getStarted.requestCta') }}
                    </button>
                </div>

                <div class="glass-card p-6 shadow-xl flex flex-col">
                    <h2 class="text-lg font-semibold text-white mb-2">{{ t('account.getStarted.sellTitle') }}</h2>
                    <p class="text-small text-lighter mb-6 flex-1">{{ t('account.getStarted.sellDescription') }}</p>
                    <p v-if="hasActiveMerchantMemberships" class="text-sm text-emerald-400 mb-3">{{ t('account.getStarted.sellEnabled') }}</p>
                    <Link :href="route('account.merchant.start')" class="w-full btn-primary text-center">
                        {{ t('account.getStarted.sellCta') }}
                    </Link>
                </div>

                <div class="glass-card p-6 shadow-xl flex flex-col">
                    <h2 class="text-lg font-semibold text-white mb-2">{{ t('account.getStarted.marketerTitle') }}</h2>
                    <p class="text-small text-lighter mb-6 flex-1">{{ t('account.getStarted.marketerDescription') }}</p>
                    <p v-if="hasActiveMarketer" class="text-sm text-emerald-400 mb-3">{{ t('account.getStarted.marketerEnabled') }}</p>
                    <p v-else-if="marketerStatus === 'Pending'" class="text-sm text-amber-400 mb-3">{{ t('account.getStarted.marketerPending') }}</p>
                    <Link
                        v-if="hasActiveMarketer"
                        :href="route('marketer.home')"
                        class="w-full btn-primary text-center"
                    >
                        {{ t('account.getStarted.marketerOpen') }}
                    </Link>
                    <Link
                        v-else-if="marketerStatus === 'Pending' || marketerStatus === 'Inactive'"
                        :href="route('marketer.application.status')"
                        class="w-full btn-primary text-center"
                    >
                        {{ t('account.getStarted.marketerStatusCta') }}
                    </Link>
                    <Link
                        v-else
                        :href="route('marketer.application.create')"
                        class="w-full btn-primary text-center"
                    >
                        {{ t('account.getStarted.marketerCta') }}
                    </Link>
                </div>
            </div>

            <div v-if="hasActiveCustomer || hasActiveMerchantMemberships" class="mt-6 flex flex-wrap justify-center gap-4">
                <Link
                    v-if="hasActiveCustomer"
                    :href="route('customer.home')"
                    class="text-blue-400 hover:text-blue-300"
                >
                    {{ t('account.getStarted.myRequests') }}
                </Link>
                <Link
                    v-if="hasActiveMerchantMemberships"
                    :href="route('merchant.select')"
                    class="text-blue-400 hover:text-blue-300"
                >
                    {{ t('account.getStarted.myBusinesses') }}
                </Link>
            </div>
        </div>
    </div>
</template>

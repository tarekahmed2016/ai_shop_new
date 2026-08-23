<script setup>
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import CategoryTreeSelector from '../../Components/Features/Categories/CategoryTreeSelector.vue'

defineProps({
    availableCategories: { type: Array, default: () => [] },
})

const { t } = useI18n()

const form = useForm({
    name: '',
    email: '',
    phone: '',
    category_ids: [],
})

const toggleCategory = (publicId) => {
    const current = Array.isArray(form.category_ids) ? [...form.category_ids] : []
    const index = current.indexOf(publicId)

    if (index === -1) {
        current.push(publicId)
    } else {
        current.splice(index, 1)
    }

    form.category_ids = current
}

const submit = () => {
    form.post(route('account.merchant.start.store'))
}
</script>

<template>
    <div class="flex items-center justify-center p-3 sm:p-4 min-h-[calc(100vh-153px)]">
        <div class="w-full max-w-xl">
            <div class="glass-card p-6 sm:p-8 shadow-xl">
                <div class="text-center mb-6">
                    <h1 class="text-hero text-white">{{ t('account.merchantStart.title') }}</h1>
                    <p class="text-small text-lighter mt-2">{{ t('account.merchantStart.subtitle') }}</p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-body text-white mb-2">{{ t('account.merchantStart.name') }}</label>
                        <input v-model="form.name" type="text" required class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">{{ t('account.merchantStart.email') }}</label>
                        <input v-model="form.email" type="email" class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.email" class="text-red-400 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">{{ t('account.merchantStart.phone') }}</label>
                        <input v-model="form.phone" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-600 bg-slate-800/50 text-white" />
                        <p v-if="form.errors.phone" class="text-red-400 text-sm mt-1">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-body text-white mb-2">{{ t('account.merchantStart.categories') }}</label>
                        <CategoryTreeSelector
                            :categories="availableCategories"
                            :selectedIds="form.category_ids"
                            @toggle="toggleCategory"
                        />
                        <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
                        <p v-if="form.errors['category_ids.0']" class="text-red-400 text-sm mt-1">{{ form.errors['category_ids.0'] }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full btn bg-blue-600 hover:bg-blue-700 text-white py-4 px-6 disabled:opacity-50">
                        {{ form.processing ? t('account.merchantStart.submitting') : t('account.merchantStart.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

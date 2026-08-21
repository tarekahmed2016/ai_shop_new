<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CategoryTreeSelector from '../../Components/Features/Categories/CategoryTreeSelector.vue'

const { t } = useI18n()
const page = usePage()
const availableCategories = computed(() => page.props.availableCategories || [])

const form = useForm({
    category_id: '',
    request_text: '',
    image: null,
})

const onFileChange = (event) => {
    form.image = event.target.files?.[0] || null
}

const submit = () => {
    form.post(route('customer.requests.store'), {
        forceFormData: true,
    })
}
</script>

<template>
    <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customerPortal.create.title') }}</h1>
                <p class="mt-2 text-muted muted-color">{{ t('customerPortal.create.subtitle') }}</p>
            </div>

            <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4">
                <div>
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.category') }} <span class="text-red-500">*</span>
                    </label>
                    <CategoryTreeSelector
                        :categories="availableCategories"
                        :multiple="false"
                        :selectedId="form.category_id"
                        :emptyText="t('customerPortal.create.selectCategory')"
                        @select="form.category_id = $event"
                    />
                    <p v-if="form.errors.category_id" class="form-error">{{ form.errors.category_id }}</p>
                </div>

                <div>
                    <label class="form-label text-label">
                        {{ t('customerPortal.create.requestText') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="form.request_text" rows="6" required class="form-input text-body" />
                    <p v-if="form.errors.request_text" class="form-error">{{ form.errors.request_text }}</p>
                </div>

                <div>
                    <label class="form-label text-label">{{ t('customerPortal.create.image') }}</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" class="form-input text-body" @change="onFileChange" />
                    <p class="text-muted text-sm mt-1">{{ t('customerPortal.create.imageHint') }}</p>
                    <p v-if="form.errors.image" class="form-error">{{ form.errors.image }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
                        {{ form.processing ? t('customerPortal.create.submitting') : t('customerPortal.create.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

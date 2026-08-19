<script setup>
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useCustomAssets } from '../../Composables/useCustomAssets.js'

const { t } = useI18n()
const page = usePage()
const customAssets = page.props.customAssets

const { form, updateCustomAssets } = useCustomAssets(customAssets)

const submit = () => updateCustomAssets()
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('customAssets.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('customAssets.pageSubtitle') }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6">
        <form @submit.prevent="submit" class="space-y-8">
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('customAssets.sections.css') }}
            </h2>
            <p class="text-muted muted-color">{{ t('customAssets.form.cssHelp') }}</p>
            <div>
              <label class="form-label text-label">{{ t('customAssets.form.cssLabel') }}</label>
              <textarea
                v-model="form.custom_css"
                rows="14"
                dir="ltr"
                class="form-input text-body font-mono text-sm"
                placeholder=".public-layout .my-class { color: red; }"
              />
              <p v-if="form.errors.custom_css" class="form-error">{{ form.errors.custom_css }}</p>
            </div>
          </section>

          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('customAssets.sections.js') }}
            </h2>
            <p class="text-muted muted-color">{{ t('customAssets.form.jsHelp') }}</p>
            <div>
              <label class="form-label text-label">{{ t('customAssets.form.jsLabel') }}</label>
              <textarea
                v-model="form.custom_js"
                rows="14"
                dir="ltr"
                class="form-input text-body font-mono text-sm"
                placeholder="console.log('Hello from custom JS');"
              />
              <p v-if="form.errors.custom_js" class="form-error">{{ form.errors.custom_js }}</p>
            </div>
          </section>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary px-4 py-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ form.processing ? t('customAssets.form.saving') : t('customAssets.form.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

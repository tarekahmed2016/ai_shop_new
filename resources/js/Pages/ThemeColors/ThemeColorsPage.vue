<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useThemeColors } from '../../Composables/useThemeColors.js'

const { t } = useI18n()
const page = usePage()
const themeColors = page.props.themeColors

const { form, updateThemeColors } = useThemeColors(themeColors)

const themeGroups = computed(() => [
  {
    title: t('themeColors.sections.backgrounds'),
    fields: [
      { key: 'theme_primary_color', label: t('themeColors.form.primaryLabel'), placeholder: t('themeColors.form.primaryPlaceholder') },
      { key: 'theme_dark_color', label: t('themeColors.form.darkLabel'), placeholder: t('themeColors.form.darkPlaceholder') },
    ],
  },
  {
    title: t('themeColors.sections.lightText'),
    fields: [
      { key: 'theme_heading_text_color', label: t('themeColors.form.headingTextLabel'), placeholder: t('themeColors.form.headingTextPlaceholder') },
      { key: 'theme_body_text_color', label: t('themeColors.form.bodyTextLabel'), placeholder: t('themeColors.form.bodyTextPlaceholder') },
      { key: 'theme_muted_text_color', label: t('themeColors.form.mutedTextLabel'), placeholder: t('themeColors.form.mutedTextPlaceholder') },
    ],
  },
  {
    title: t('themeColors.sections.navigation'),
    fields: [
      { key: 'theme_nav_text_color', label: t('themeColors.form.navTextLabel'), placeholder: t('themeColors.form.navTextPlaceholder') },
      { key: 'theme_nav_hover_text_color', label: t('themeColors.form.navHoverTextLabel'), placeholder: t('themeColors.form.navHoverTextPlaceholder') },
    ],
  },
  {
    title: t('themeColors.sections.heroDarkText'),
    fields: [
      { key: 'theme_hero_text_color', label: t('themeColors.form.heroTextLabel'), placeholder: t('themeColors.form.heroTextPlaceholder') },
      { key: 'theme_on_dark_text_color', label: t('themeColors.form.onDarkTextLabel'), placeholder: t('themeColors.form.onDarkTextPlaceholder') },
    ],
  },
])

const submit = () => updateThemeColors()
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('themeColors.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('themeColors.pageSubtitle') }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6">
        <form @submit.prevent="submit" class="space-y-8">
          <p class="text-muted muted-color">{{ t('themeColors.form.help') }}</p>

          <div
            v-for="group in themeGroups"
            :key="group.title"
            class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
          >
            <h2 class="text-card-title text-gray-900 dark:text-gray-100">{{ group.title }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="field in group.fields" :key="field.key">
                <label class="form-label text-label">{{ field.label }}</label>
                <div class="flex items-center gap-3">
                  <input
                    v-model="form[field.key]"
                    type="color"
                    class="h-10 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-1"
                  />
                  <input
                    v-model="form[field.key]"
                    type="text"
                    dir="ltr"
                    maxlength="7"
                    class="form-input text-body font-mono uppercase"
                    :placeholder="field.placeholder"
                  />
                </div>
                <p v-if="form.errors[field.key]" class="form-error">{{ form.errors[field.key] }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div
              class="px-4 py-2 text-button font-semibold"
              :style="{ backgroundColor: form.theme_primary_color, color: form.theme_dark_color }"
            >
              {{ t('themeColors.form.previewTopBar') }}
            </div>
            <div
              class="px-4 py-3 text-body flex gap-4"
              :style="{ backgroundColor: form.theme_dark_color, color: form.theme_nav_text_color }"
            >
              <span>{{ t('themeColors.form.previewNavbar') }}</span>
              <span :style="{ color: form.theme_nav_hover_text_color }">{{ t('themeColors.form.previewNavHover') }}</span>
            </div>
            <div class="px-4 py-4 bg-white dark:bg-gray-900 space-y-2">
              <h4 class="text-section-title" :style="{ color: form.theme_heading_text_color }">
                {{ t('themeColors.form.previewHeading') }}
              </h4>
              <p class="text-body" :style="{ color: form.theme_body_text_color }">
                {{ t('themeColors.form.previewBody') }}
              </p>
              <p class="text-muted" :style="{ color: form.theme_muted_text_color }">
                {{ t('themeColors.form.previewMuted') }}
              </p>
            </div>
            <div
              class="px-4 py-4 space-y-2"
              :style="{ backgroundColor: form.theme_dark_color, color: form.theme_on_dark_text_color }"
            >
              <h4 class="text-section-title" :style="{ color: form.theme_hero_text_color }">
                {{ t('themeColors.form.previewHero') }}
              </h4>
              <span
                class="inline-flex px-4 py-2 rounded text-button font-semibold uppercase"
                :style="{ backgroundColor: form.theme_primary_color, color: form.theme_dark_color }"
              >
                CTA
              </span>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary px-4 py-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ form.processing ? t('themeColors.form.saving') : t('themeColors.form.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useCompanyInfo } from '../../Composables/useCompanyInfo.js'
import RichTextEditor from '../../Components/Common/asyncRichTextEditor.js'

const { t } = useI18n()
const page = usePage()
const companyInfo = page.props.companyInfo

const {
  form,
  logoInput,
  logoFileName,
  logoPreview,
  handleLogoChange,
  updateCompanyInfo
} = useCompanyInfo(companyInfo)

const submit = () => updateCompanyInfo()
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 md:mb-8">
        <h1 class="text-page-title text-gray-900 dark:text-gray-100">{{ t('companyInfo.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('companyInfo.pageSubtitle') }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6">
        <form @submit.prevent="submit" class="space-y-8">
          <!-- A. Basic Information -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.basic') }}
            </h2>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.nameArLabel') }}</label>
                <input v-model="form.name_ar" type="text" class="form-input text-body" :placeholder="t('companyInfo.form.nameArPlaceholder')" />
                <p v-if="form.errors.name_ar" class="form-error">{{ form.errors.name_ar }}</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.nameEnLabel') }}</label>
                <input v-model="form.name_en" type="text" class="form-input text-body" :placeholder="t('companyInfo.form.nameEnPlaceholder')" />
                <p v-if="form.errors.name_en" class="form-error">{{ form.errors.name_en }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.phoneLabel') }}</label>
                <input v-model="form.phone" type="text" class="form-input text-body" :placeholder="t('companyInfo.form.phonePlaceholder')" />
                <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.emailLabel') }}</label>
                <input v-model="form.email" type="email" class="form-input text-body" :placeholder="t('companyInfo.form.emailPlaceholder')" />
                <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
              </div>
            </div>

            <div>
              <label class="form-label text-label">{{ t('companyInfo.form.logoLabel') }}</label>
              <div class="flex items-center gap-4">
                <img v-if="logoPreview" :src="logoPreview" alt="Company logo preview" class="h-16 rounded-md border border-gray-200 dark:border-gray-700" />
                <div class="flex flex-col gap-1.5 flex-1">
                  <button type="button" @click="logoInput.click()" class="btn btn-secondary px-4 py-2 w-full cursor-pointer">
                    {{ t('companyInfo.form.chooseFile') }}
                  </button>
                  <span class="text-sm text-muted muted-color truncate text-center">
                    {{ logoFileName || t('companyInfo.form.noFileChosen') }}
                  </span>
                  <input ref="logoInput" type="file" accept="image/*" @change="handleLogoChange" class="hidden" />
                </div>
              </div>
              <p v-if="form.errors.logo" class="form-error">{{ form.errors.logo }}</p>
            </div>
          </section>

          <!-- B. Hero -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.hero') }}
            </h2>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.heroTitleArLabel') }}</label>
                <input v-model="form.hero_title_ar" type="text" class="form-input text-body" :placeholder="t('companyInfo.form.heroTitleArPlaceholder')" />
                <p v-if="form.errors.hero_title_ar" class="form-error">{{ form.errors.hero_title_ar }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.heroDescriptionArLabel') }}</label>
                <RichTextEditor
                  v-model="form.hero_description_ar"
                  :active="true"
                  dir="rtl"
                  :placeholder="t('companyInfo.form.heroDescriptionArPlaceholder')"
                />
                <p v-if="form.errors.hero_description_ar" class="form-error">{{ form.errors.hero_description_ar }}</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.heroTitleEnLabel') }}</label>
                <input v-model="form.hero_title_en" type="text" class="form-input text-body" :placeholder="t('companyInfo.form.heroTitleEnPlaceholder')" />
                <p v-if="form.errors.hero_title_en" class="form-error">{{ form.errors.hero_title_en }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.heroDescriptionEnLabel') }}</label>
                <RichTextEditor
                  v-model="form.hero_description_en"
                  :active="true"
                  dir="ltr"
                  :placeholder="t('companyInfo.form.heroDescriptionEnPlaceholder')"
                />
                <p v-if="form.errors.hero_description_en" class="form-error">{{ form.errors.hero_description_en }}</p>
              </div>
            </div>
          </section>

          <!-- C. About -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.about') }}
            </h2>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.aboutArLabel') }}</label>
                <RichTextEditor
                  v-model="form.about_ar"
                  :active="true"
                  dir="rtl"
                  :placeholder="t('companyInfo.form.aboutArPlaceholder')"
                />
                <p v-if="form.errors.about_ar" class="form-error">{{ form.errors.about_ar }}</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.aboutEnLabel') }}</label>
                <RichTextEditor
                  v-model="form.about_en"
                  :active="true"
                  dir="ltr"
                  :placeholder="t('companyInfo.form.aboutEnPlaceholder')"
                />
                <p v-if="form.errors.about_en" class="form-error">{{ form.errors.about_en }}</p>
              </div>
            </div>
          </section>

          <!-- D. Vision & Mission -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.visionMission') }}
            </h2>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.visionArLabel') }}</label>
                <RichTextEditor
                  v-model="form.vision_ar"
                  :active="true"
                  dir="rtl"
                  :placeholder="t('companyInfo.form.visionArPlaceholder')"
                />
                <p v-if="form.errors.vision_ar" class="form-error">{{ form.errors.vision_ar }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.missionArLabel') }}</label>
                <RichTextEditor
                  v-model="form.mission_ar"
                  :active="true"
                  dir="rtl"
                  :placeholder="t('companyInfo.form.missionArPlaceholder')"
                />
                <p v-if="form.errors.mission_ar" class="form-error">{{ form.errors.mission_ar }}</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.visionEnLabel') }}</label>
                <RichTextEditor
                  v-model="form.vision_en"
                  :active="true"
                  dir="ltr"
                  :placeholder="t('companyInfo.form.visionEnPlaceholder')"
                />
                <p v-if="form.errors.vision_en" class="form-error">{{ form.errors.vision_en }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.missionEnLabel') }}</label>
                <RichTextEditor
                  v-model="form.mission_en"
                  :active="true"
                  dir="ltr"
                  :placeholder="t('companyInfo.form.missionEnPlaceholder')"
                />
                <p v-if="form.errors.mission_en" class="form-error">{{ form.errors.mission_en }}</p>
              </div>
            </div>
          </section>

          <!-- E. Address -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.address') }}
            </h2>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.addressArLabel') }}</label>
                <textarea v-model="form.address_ar" rows="3" class="form-input text-body" :placeholder="t('companyInfo.form.addressArPlaceholder')" />
                <p v-if="form.errors.address_ar" class="form-error">{{ form.errors.address_ar }}</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
              <h3 class="text-label font-medium text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.addressEnLabel') }}</label>
                <textarea v-model="form.address_en" rows="3" class="form-input text-body" :placeholder="t('companyInfo.form.addressEnPlaceholder')" />
                <p v-if="form.errors.address_en" class="form-error">{{ form.errors.address_en }}</p>
              </div>
            </div>
          </section>

          <!-- F. Website & Social Media -->
          <section class="space-y-4">
            <h2 class="text-card-title text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
              {{ t('companyInfo.sections.social') }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.websiteLabel') }}</label>
                <input v-model="form.website" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.websitePlaceholder')" />
                <p v-if="form.errors.website" class="form-error">{{ form.errors.website }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.facebookLabel') }}</label>
                <input v-model="form.facebook" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.facebookPlaceholder')" />
                <p v-if="form.errors.facebook" class="form-error">{{ form.errors.facebook }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.instagramLabel') }}</label>
                <input v-model="form.instagram" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.instagramPlaceholder')" />
                <p v-if="form.errors.instagram" class="form-error">{{ form.errors.instagram }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.linkedinLabel') }}</label>
                <input v-model="form.linkedin" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.linkedinPlaceholder')" />
                <p v-if="form.errors.linkedin" class="form-error">{{ form.errors.linkedin }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.xTwitterLabel') }}</label>
                <input v-model="form.x_twitter" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.xTwitterPlaceholder')" />
                <p v-if="form.errors.x_twitter" class="form-error">{{ form.errors.x_twitter }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.youtubeLabel') }}</label>
                <input v-model="form.youtube" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.youtubePlaceholder')" />
                <p v-if="form.errors.youtube" class="form-error">{{ form.errors.youtube }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.tiktokLabel') }}</label>
                <input v-model="form.tiktok" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.tiktokPlaceholder')" />
                <p v-if="form.errors.tiktok" class="form-error">{{ form.errors.tiktok }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.snapchatLabel') }}</label>
                <input v-model="form.snapchat" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.snapchatPlaceholder')" />
                <p v-if="form.errors.snapchat" class="form-error">{{ form.errors.snapchat }}</p>
              </div>
              <div>
                <label class="form-label text-label">{{ t('companyInfo.form.whatsappLabel') }}</label>
                <input v-model="form.whatsapp" type="url" class="form-input text-body" :placeholder="t('companyInfo.form.whatsappPlaceholder')" />
                <p v-if="form.errors.whatsapp" class="form-error">{{ form.errors.whatsapp }}</p>
              </div>
            </div>
          </section>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              :disabled="form.processing"
              class="btn btn-primary px-4 py-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ form.processing ? t('companyInfo.form.saving') : t('companyInfo.form.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

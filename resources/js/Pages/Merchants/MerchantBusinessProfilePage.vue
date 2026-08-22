<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const merchant = computed(() => page.props.merchant || {})
const canUpdate = computed(() => page.props.canUpdate === true)

const form = useForm({
  name: merchant.value.name || '',
  email: merchant.value.email || '',
  phone: merchant.value.phone || '',
})

const submit = () => {
  if (!canUpdate.value) {
    return
  }

  form.patch(route('merchant.business-profile.update'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-3 md:p-6">
    <div class="max-w-3xl mx-auto space-y-6">
      <div>
        <Link :href="route('merchant.home')" class="text-body text-blue-600">
          ← {{ t('merchantBusinessProfile.backToHome') }}
        </Link>
        <h1 class="mt-3 text-page-title text-gray-900 dark:text-gray-100">{{ t('merchantBusinessProfile.pageTitle') }}</h1>
        <p class="mt-2 text-muted muted-color">{{ t('merchantBusinessProfile.pageSubtitle') }}</p>
      </div>

      <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900 rounded-lg p-4 space-y-2 text-body text-gray-800 dark:text-gray-200">
        <p><strong>{{ t('merchantBusinessProfile.pageTitle') }}:</strong> {{ t('merchantBusinessProfile.entityHint') }}</p>
        <p>
          <strong>{{ t('merchantBusinessProfile.myProfile') }}:</strong> {{ t('merchantBusinessProfile.userHint') }}
          <Link :href="route('profile.edit')" class="text-blue-600 ms-1">{{ t('merchantBusinessProfile.openMyProfile') }}</Link>
        </p>
      </div>

      <form class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 md:p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="form-label text-label">{{ t('merchantBusinessProfile.name') }} <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required :disabled="!canUpdate" class="form-input text-body disabled:opacity-60" />
          <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('merchantBusinessProfile.email') }}</label>
          <input v-model="form.email" type="email" :disabled="!canUpdate" class="form-input text-body disabled:opacity-60" />
          <p class="text-muted text-sm mt-1">{{ t('merchantBusinessProfile.emailHint') }}</p>
          <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('merchantBusinessProfile.phone') }} <span class="text-red-500">*</span></label>
          <input v-model="form.phone" type="text" required dir="ltr" :disabled="!canUpdate" class="form-input text-body disabled:opacity-60" />
          <p class="text-muted text-sm mt-1">{{ t('merchantBusinessProfile.phoneHint') }}</p>
          <p v-if="form.errors.phone" class="form-error">{{ form.errors.phone }}</p>
        </div>
        <div v-if="canUpdate" class="pt-2">
          <button type="submit" :disabled="form.processing" class="btn btn-primary px-4 py-2 disabled:opacity-50">
            {{ form.processing ? t('merchantBusinessProfile.saving') : t('merchantBusinessProfile.save') }}
          </button>
        </div>
        <p v-else class="text-muted">{{ t('merchantBusinessProfile.readOnly') }}</p>
      </form>
    </div>
  </div>
</template>

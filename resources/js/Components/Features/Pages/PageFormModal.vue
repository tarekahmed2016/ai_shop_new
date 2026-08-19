<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import DashboardModalShell from '../../Common/DashboardModalShell.vue'
import RichTextEditor from '../../Common/asyncRichTextEditor.js'

const { t } = useI18n()

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  page: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])

const form = useForm({
  title_ar: '',
  title_en: '',
  menu_title_ar: '',
  menu_title_en: '',
  slug: '',
  content_ar: '',
  content_en: '',
  show_in_main_menu: false,
  menu_order: 100,
  is_active: true,
})

const editingPageId = ref(null)

watch([() => props.isOpen, () => props.page?.id], ([isOpen, pageId]) => {
  if (!isOpen) return

  editingPageId.value = pageId ?? null

  if (pageId && props.page) {
    form.title_ar = props.page.title_ar || ''
    form.title_en = props.page.title_en || ''
    form.menu_title_ar = props.page.menu_title_ar || ''
    form.menu_title_en = props.page.menu_title_en || ''
    form.slug = props.page.slug || ''
    form.content_ar = props.page.content_ar || ''
    form.content_en = props.page.content_en || ''
    form.show_in_main_menu = Boolean(props.page.show_in_main_menu)
    form.menu_order = props.page.menu_order ?? 100
    form.is_active = Boolean(props.page.is_active)
  } else {
    form.reset()
    form.show_in_main_menu = false
    form.menu_order = 100
    form.is_active = true
  }

  form.clearErrors()
}, { immediate: true })

const modalTitle = computed(() =>
  editingPageId.value ? t('pages.form.editTitle') : t('pages.form.createTitle')
)

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      editingPageId.value = null
      emit('close')
    },
  }

  if (editingPageId.value) {
    form.put(route('pages.update', { page: editingPageId.value }), options)

    return
  }

  form.post(route('pages.store'), options)
}

const handleClose = () => {
  form.reset()
  form.clearErrors()
  editingPageId.value = null
  emit('close')
}
</script>

<template>
  <DashboardModalShell
    :isOpen="isOpen"
    title-id="page-form-title"
    @close="handleClose"
  >
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <h2 id="page-form-title" class="text-card-title text-gray-900 dark:text-gray-100">
        {{ modalTitle }}
      </h2>
      <button
        type="button"
        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer transition-colors"
        @click="handleClose"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <form @submit.prevent="submit" class="px-6 py-4 space-y-4 max-h-[75vh] overflow-y-auto">
      <section class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-section-title text-gray-900 dark:text-gray-100">{{ t('bilingual.arabic') }}</h3>
        <div>
          <label class="form-label text-label">{{ t('pages.form.titleArLabel') }}</label>
          <input v-model="form.title_ar" type="text" class="form-input text-body" :placeholder="t('pages.form.titleArPlaceholder')" />
          <p v-if="form.errors.title_ar" class="form-error">{{ form.errors.title_ar }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('pages.form.menuTitleArLabel') }}</label>
          <input v-model="form.menu_title_ar" type="text" class="form-input text-body" :placeholder="t('pages.form.menuTitleArPlaceholder')" />
          <p v-if="form.errors.menu_title_ar" class="form-error">{{ form.errors.menu_title_ar }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('pages.form.contentArLabel') }}</label>
          <RichTextEditor v-model="form.content_ar" dir="rtl" :placeholder="t('pages.form.contentArPlaceholder')" />
          <p v-if="form.errors.content_ar" class="form-error">{{ form.errors.content_ar }}</p>
        </div>
      </section>

      <section class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-section-title text-gray-900 dark:text-gray-100">{{ t('bilingual.english') }}</h3>
        <div>
          <label class="form-label text-label">{{ t('pages.form.titleEnLabel') }}</label>
          <input v-model="form.title_en" type="text" class="form-input text-body" :placeholder="t('pages.form.titleEnPlaceholder')" />
          <p v-if="form.errors.title_en" class="form-error">{{ form.errors.title_en }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('pages.form.menuTitleEnLabel') }}</label>
          <input v-model="form.menu_title_en" type="text" class="form-input text-body" :placeholder="t('pages.form.menuTitleEnPlaceholder')" />
          <p v-if="form.errors.menu_title_en" class="form-error">{{ form.errors.menu_title_en }}</p>
        </div>
        <div>
          <label class="form-label text-label">{{ t('pages.form.contentEnLabel') }}</label>
          <RichTextEditor v-model="form.content_en" dir="ltr" :placeholder="t('pages.form.contentEnPlaceholder')" />
          <p v-if="form.errors.content_en" class="form-error">{{ form.errors.content_en }}</p>
        </div>
      </section>

      <section class="space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-section-title text-gray-900 dark:text-gray-100">{{ t('pages.form.publishingSection') }}</h3>
        <div>
          <label class="form-label text-label">{{ t('pages.form.slugLabel') }}</label>
          <input v-model="form.slug" type="text" class="form-input text-body" dir="ltr" :placeholder="t('pages.form.slugPlaceholder')" />
          <p class="text-muted muted-color mt-1">{{ t('pages.form.slugHelp') }}</p>
          <p v-if="form.errors.slug" class="form-error">{{ form.errors.slug }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="form-label text-label">{{ t('pages.form.menuOrderLabel') }}</label>
            <input v-model.number="form.menu_order" type="number" min="0" class="form-input text-body" />
            <p class="text-muted muted-color mt-1">{{ t('pages.form.menuOrderHelp') }}</p>
            <p v-if="form.errors.menu_order" class="form-error">{{ form.errors.menu_order }}</p>
          </div>
          <div class="space-y-3">
            <label class="inline-flex items-center gap-2 text-body text-gray-700 dark:text-gray-300">
              <input v-model="form.show_in_main_menu" type="checkbox" class="rounded border-gray-300" />
              {{ t('pages.form.showInMainMenuLabel') }}
            </label>
            <label class="inline-flex items-center gap-2 text-body text-gray-700 dark:text-gray-300">
              <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
              {{ t('pages.form.activeLabel') }}
            </label>
          </div>
        </div>
      </section>

      <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <button type="button" class="btn btn-secondary px-4 py-2" :disabled="form.processing" @click="handleClose">
          {{ t('pages.form.cancel') }}
        </button>
        <button type="submit" class="btn btn-primary px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="form.processing">
          {{ form.processing ? t('pages.form.saving') : t('pages.form.save') }}
        </button>
      </div>
    </form>
  </DashboardModalShell>
</template>

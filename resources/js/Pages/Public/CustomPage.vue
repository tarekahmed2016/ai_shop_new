<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue'
import RichTextContent from '../../Components/Common/RichTextContent.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePage } from '@inertiajs/vue3'
import { resolveBilingualField } from '../../Composables/useBilingualContent.js'

defineOptions({ layout: PublicLayout })

const { locale } = useI18n()
const page = usePage()

const pageData = computed(() => page.props.page || {})

const pageTitle = computed(() =>
  resolveBilingualField(pageData.value, 'title', locale.value)
)

const pageContent = computed(() =>
  resolveBilingualField(pageData.value, 'content', locale.value)
)
</script>

<template>
  <section class="public-custom-page">
    <div class="public-container py-12 md:py-16">
      <header class="mb-8 md:mb-10">
        <h1 class="public-custom-page-title">{{ pageTitle }}</h1>
      </header>
        <RichTextContent
          v-if="pageContent"
          :content="pageContent"
          tag="div"
        />
    </div>
  </section>
</template>

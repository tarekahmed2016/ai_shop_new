<script setup>
import { computed, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import PublicNavbar from '../Components/Public/PublicNavbar.vue'
import PublicFooter from '../Components/Public/PublicFooter.vue'
import FlashMessage from '../Components/Common/FlashMessage.vue'
import { publicThemeStyle } from '../Composables/usePublicTheme.js'

const page = usePage()
const themeStyle = computed(() => publicThemeStyle(page.props.companyInfo))
const customCss = computed(() => page.props.companyInfo?.custom_css || '')
const customJs = computed(() => page.props.companyInfo?.custom_js || '')

function injectCustomJs(code) {
  const existing = document.getElementById('public-custom-js')

  if (existing) {
    existing.remove()
  }

  if (!code?.trim()) {
    return
  }

  const script = document.createElement('script')
  script.id = 'public-custom-js'
  script.textContent = code
  document.body.appendChild(script)
}

onMounted(() => injectCustomJs(customJs.value))
watch(customJs, injectCustomJs)
</script>

<template>
    <div class="min-h-screen flex flex-col public-layout" :style="themeStyle">
        <component :is="'style'" v-if="customCss">{{ customCss }}</component>
        <FlashMessage />
        <PublicNavbar />
        <main class="flex-1">
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>

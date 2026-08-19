<script setup>
import { computed, toRef } from 'vue'
import { useSocialLinks } from '../../Composables/useSocialLinks.js'

const props = defineProps({
  companyInfo: {
    type: Object,
    default: () => ({}),
  },
  variant: {
    type: String,
    default: 'footer',
    validator: (value) => ['top-bar', 'footer', 'contact'].includes(value),
  },
  includeWebsite: {
    type: Boolean,
    default: true,
  },
})

const { socialLinks, socialLinksWithoutWebsite } = useSocialLinks(toRef(props, 'companyInfo'))

const links = computed(() => (props.includeWebsite ? socialLinks.value : socialLinksWithoutWebsite.value))
</script>

<template>
  <div
    v-if="links.length"
    :class="{
      'public-top-social': variant === 'top-bar',
      'flex flex-wrap gap-2 mt-4': variant === 'footer',
      'public-social-links-contact': variant === 'contact',
    }"
  >
    <a
      v-for="link in links"
      :key="link.key"
      :href="link.url"
      target="_blank"
      rel="noopener noreferrer"
      :aria-label="link.label"
      :class="{
        'public-chip': variant === 'footer' || variant === 'contact',
      }"
    >
      <font-awesome-icon :icon="link.icon" />
      <span v-if="variant !== 'top-bar'" class="ms-2">{{ link.label }}</span>
    </a>
  </div>
</template>

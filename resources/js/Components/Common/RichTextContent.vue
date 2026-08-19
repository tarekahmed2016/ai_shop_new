<script setup>
import { computed } from 'vue'
import { sanitizeRichText } from '../../Composables/useRichText.js'

const props = defineProps({
  content: {
    type: String,
    default: '',
  },
  tag: {
    type: String,
    default: 'div',
  },
})

const sanitizedContent = computed(() => sanitizeRichText(props.content))
const hasContent = computed(() => Boolean(sanitizedContent.value))
</script>

<template>
  <component
    :is="tag"
    v-if="hasContent"
    class="rich-text-content public-rich-content"
    v-html="sanitizedContent"
  />
</template>

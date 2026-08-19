<script setup>
import { toRef } from 'vue'
import { useDialogAccessibility } from '../../Composables/General/useDialogAccessibility.js'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  titleId: {
    type: String,
    required: true,
  },
  maxWidthClass: {
    type: String,
    default: 'max-w-7xl',
  },
  scrollable: {
    type: Boolean,
    default: true,
  },
  closeOnBackdropClick: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])

const handleBackdropClick = () => {
  if (props.closeOnBackdropClick) {
    emit('close')
  }
}

useDialogAccessibility(toRef(props, 'isOpen'), () => emit('close'))
</script>

<template>
  <div
    v-if="isOpen"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="titleId"
    :class="[
      'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60',
      scrollable ? 'overflow-y-auto' : ''
    ]"
    @click.self="handleBackdropClick"
  >
    <div
      :class="[
        'bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full my-auto',
        maxWidthClass,
        $attrs.class?.includes('mx-4') ? '' : ''
      ]"
    >
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
    <div
      class="mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 text-gray-400 dark:text-gray-500"
      aria-hidden="true"
    >
      <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2h-3l-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2h6M8 11h8M8 15h5" />
      </svg>
    </div>
    <p class="text-body text-gray-700 dark:text-gray-300 font-medium">{{ title }}</p>
    <p v-if="description" class="mt-1 text-sm text-muted muted-color max-w-md">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>

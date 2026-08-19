<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false
    },
    message: {
        type: String,
        default: 'Loading...'
    }
})
</script>

<template>
    <div v-if="show" class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm z-10 flex items-center justify-center">
        <div class="flex flex-col items-center gap-3">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-200 dark:border-blue-800 border-t-blue-600 dark:border-t-blue-400"></div>
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ message }}</span>
        </div>
    </div>
</template>

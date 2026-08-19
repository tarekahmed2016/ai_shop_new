<script setup>
import { ref, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const page = usePage()
const show = ref(false)
const message = ref('')
const type = ref('success')
const messageCount = ref(0)
let timeoutId = null

const bgColors = {
    success: 'bg-green-50 dark:bg-green-900/20 border-green-500',
    error: 'bg-red-50 dark:bg-red-900/20 border-red-500',
    warning: 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-500',
    info: 'bg-blue-50 dark:bg-blue-900/20 border-blue-500'
}

const textColors = {
    success: 'text-green-800 dark:text-green-300',
    error: 'text-red-800 dark:text-red-300',
    warning: 'text-yellow-800 dark:text-yellow-300',
    info: 'text-blue-800 dark:text-blue-300'
}

const iconPaths = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
}

const showFlashMessage = (msg, msgType) => {
    // Clear any existing timeout
    if (timeoutId) {
        clearTimeout(timeoutId)
    }

    message.value = msg
    type.value = msgType
    messageCount.value++
    show.value = true

    timeoutId = setTimeout(() => {
        show.value = false
        timeoutId = null
    }, 5000)
}

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        showFlashMessage(flash.success, 'success')
    } else if (flash?.error) {
        showFlashMessage(flash.error, 'error')
    } else if (flash?.warning) {
        showFlashMessage(flash.warning, 'warning')
    } else if (flash?.info) {
        showFlashMessage(flash.info, 'info')
    }
}, { deep: true, immediate: true })

// Watch for validation errors
watch(() => page.props.errors, (errors) => {
    if (errors && Object.keys(errors).length > 0) {
        const errorMessages = Object.values(errors)
        const firstError = errorMessages[0]
        const errorCount = errorMessages.length

        if (errorCount > 1) {
            showFlashMessage(`${firstError} (${t('common.validationErrorsExtra', { count: errorCount - 1 })})`, 'error')
        } else {
            showFlashMessage(firstError, 'error')
        }
    }
}, { deep: true, immediate: false })

const close = () => {
    if (timeoutId) {
        clearTimeout(timeoutId)
        timeoutId = null
    }
    show.value = false
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div
            v-if="show"
            :key="messageCount"
            :class="[
                'fixed top-4 end-4 z-50 w-full max-w-sm p-4 rounded-lg border-s-4 shadow-lg',
                bgColors[type]
            ]"
        >
            <div class="flex items-start">
                <div class="shrink-0">
                    <svg
                        :class="['h-6 w-6', textColors[type]]"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="iconPaths[type]"
                        />
                    </svg>
                </div>
                <div class="ms-3 flex-1">
                    <p :class="['text-sm font-medium', textColors[type]]">
                        {{ message }}
                    </p>
                </div>
                <div class="ms-4 shrink-0">
                    <button
                        @click="close"
                        :class="[
                            'inline-flex cursor-pointer rounded-md p-1.5 hover:bg-black/5 dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-offset-2',
                            textColors[type]
                        ]"
                    >
                        <span class="sr-only">{{ t('common.close') }}</span>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

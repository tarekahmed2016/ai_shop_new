<script setup>
import { useAccountNav } from '../../Composables/useAccountNav.js'

const { accountSections } = useAccountNav()

const emit = defineEmits(['navigate'])

const handleClick = (item) => {
    if (item.disabled || typeof item.onClick !== 'function') {
        return
    }

    item.onClick()
    emit('navigate')
}
</script>

<template>
    <div class="py-1 max-h-[70vh] overflow-y-auto">
        <section
            v-for="section in accountSections"
            :key="section.id"
            class="py-2"
        >
            <p class="px-3 py-2.5 mx-2 mt-2 mb-2 text-base font-bold tracking-wide text-white pointer-events-none rounded-lg bg-gray-800 ring-1 ring-gray-600">
                {{ section.label }}
            </p>
            <div class="ms-2 ps-2 border-s border-gray-700">
                <button
                    v-for="child in section.children"
                    :key="child.id"
                    type="button"
                    :disabled="child.disabled"
                    class="w-full text-start px-3 py-2 text-sm transition-colors min-h-11"
                    :class="child.disabled
                        ? 'text-amber-400 cursor-default'
                        : child.current
                            ? 'bg-blue-600 text-white cursor-pointer'
                            : 'text-gray-300 hover:bg-gray-700 hover:text-gray-100 cursor-pointer'"
                    @click="handleClick(child)"
                >
                    <span v-if="child.current">✓ </span>{{ child.label }}
                </button>
            </div>
        </section>
    </div>
</template>

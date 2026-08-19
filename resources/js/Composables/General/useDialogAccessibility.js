import { onUnmounted, watch } from 'vue'

/**
 * Shared dialog behavior: body scroll lock and Escape-to-close.
 *
 * @param {import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>} isOpen
 * @param {() => void} onClose
 */
export function useDialogAccessibility(isOpen, onClose) {
  const handleEscape = (event) => {
    if (event.key === 'Escape' && isOpen.value) {
      event.preventDefault()
      onClose()
    }
  }

  watch(isOpen, (open) => {
    if (open) {
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', handleEscape)
    } else {
      document.body.style.overflow = ''
      document.removeEventListener('keydown', handleEscape)
    }
  }, { immediate: true })

  onUnmounted(() => {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', handleEscape)
  })
}

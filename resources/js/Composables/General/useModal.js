import { ref } from 'vue'

/**
 * Generic modal state manager (open/close + selected item), reusable across pages.
 * @param {Object} [options]
 * @param {(item: any) => Promise<any>|any} [options.onOpen] - Optional hook run before opening, its return value is exposed as `extraData`.
 */
export function useModal(options = {}) {
  const isOpen = ref(false)
  const selectedItem = ref(null)
  const extraData = ref(null)

  const open = async (item = null) => {
    selectedItem.value = item

    if (options.onOpen) {
      try {
        extraData.value = await options.onOpen(item)
      } catch {
        extraData.value = null
      }
    } else {
      extraData.value = null
    }

    isOpen.value = true
  }

  const close = () => {
    isOpen.value = false
    selectedItem.value = null
    extraData.value = null
  }

  return {
    isOpen,
    selectedItem,
    extraData,
    open,
    close
  }
}

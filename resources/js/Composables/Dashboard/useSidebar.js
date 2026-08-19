import { ref, onMounted, onUnmounted } from 'vue'

export function useSidebar() {
  // Check if screen is mobile on initial load
  const isMobile = () => window.innerWidth < 768 // md breakpoint
  const isCollapsed = ref(isMobile())

  const toggle = () => {
    isCollapsed.value = !isCollapsed.value
  }

  const collapse = () => {
    isCollapsed.value = true
  }

  const expand = () => {
    isCollapsed.value = false
  }

  // Handle window resize to auto-collapse on mobile
  const handleResize = () => {
    if (isMobile()) {
      isCollapsed.value = true
    }
  }

  onMounted(() => {
    window.addEventListener('resize', handleResize)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
  })

  return {
    isCollapsed,
    toggle,
    collapse,
    expand
  }
}

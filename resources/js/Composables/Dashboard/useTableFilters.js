import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

/**
 * Generic table filters composable for server-side filtering, sorting, and pagination
 *
 * @param {string} routeName - The route name for the index page (e.g., 'admin.users.index')
 * @param {string} dataKey - The key for the data in Inertia props (e.g., 'users', 'roles')
 * @returns {object} Filter state and handlers
 */
export function useTableFilters(routeName, dataKey) {
  const page = usePage()

  // Initialize from server props
  const searchQuery = ref(page.props.filters?.search || '')
  const sortColumn = ref(page.props.filters?.sort_column || 'id')
  const sortDirection = ref(page.props.filters?.sort_direction || 'desc')
  const isPaginating = ref(false)

  // Perform server request with current filters
  const performServerRequest = () => {
    // Get current URL parameters to preserve additional filters
    const currentParams = new URLSearchParams(window.location.search)
    const additionalParams = {}

    // Preserve all existing query parameters except the ones we're managing
    for (const [key, value] of currentParams.entries()) {
      if (!['search', 'sort_column', 'sort_direction', 'page'].includes(key)) {
        additionalParams[key] = value
      }
    }

    router.get(route(routeName), {
      search: searchQuery.value || undefined,
      sort_column: sortColumn.value,
      sort_direction: sortDirection.value,
      ...additionalParams
    }, {
      preserveState: true,
      preserveScroll: true,
      only: [dataKey],
      onStart: () => {
        isPaginating.value = true
      },
      onFinish: () => {
        isPaginating.value = false
      }
    })
  }

  // Watch for search query and sort changes
  watch([searchQuery, sortColumn, sortDirection], () => {
    performServerRequest()
  })

  // Handle sort from table
  const handleSort = (sortData) => {
    sortColumn.value = sortData.column
    sortDirection.value = sortData.direction
    // Watchers will automatically trigger server request
  }

  // Handle pagination state
  const handlePaginating = (status) => {
    isPaginating.value = status
  }

  return {
    searchQuery,
    sortColumn,
    sortDirection,
    isPaginating,
    handleSort,
    handlePaginating
  }
}

import { router } from '@inertiajs/vue3'

export function useRequestMatches() {
  const recalculateMatches = (publicId, options = {}) => {
    return router.post(route('customer-requests.match', publicId), {}, {
      preserveScroll: true,
      ...options
    })
  }

  return {
    recalculateMatches
  }
}

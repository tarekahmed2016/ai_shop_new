import { router } from '@inertiajs/vue3'

export function useMerchantRequests() {
  const dismissRequest = (publicId, options = {}) => {
    return router.post(route('merchant.requests.dismiss', publicId), {}, {
      preserveScroll: true,
      ...options
    })
  }

  return {
    dismissRequest
  }
}

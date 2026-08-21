import { router, useForm } from '@inertiajs/vue3'

export function useMerchants() {
  const deleteForm = useForm({})

  const createMerchant = (data, options = {}) => {
    return router.post(route('merchants.store'), data, {
      preserveScroll: true,
      ...options
    })
  }

  const updateMerchant = (publicId, data, options = {}) => {
    return router.put(route('merchants.update', publicId), data, {
      preserveScroll: true,
      ...options
    })
  }

  return {
    deleteForm,
    createMerchant,
    updateMerchant
  }
}

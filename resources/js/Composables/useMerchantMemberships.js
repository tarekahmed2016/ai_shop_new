import { router, useForm } from '@inertiajs/vue3'

export function useMerchantMemberships(merchantPublicId) {
  const deleteForm = useForm({})

  const createMembership = (data, options = {}) => {
    return router.post(route('merchants.memberships.store', merchantPublicId), data, {
      preserveScroll: true,
      ...options
    })
  }

  const updateMembership = (membershipId, data, options = {}) => {
    return router.put(route('merchants.memberships.update', [merchantPublicId, membershipId]), data, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteMembership = (membershipId, callbacks = {}) => {
    return deleteForm.delete(route('merchants.memberships.destroy', [merchantPublicId, membershipId]), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    createMembership,
    updateMembership,
    deleteMembership
  }
}

import { useForm } from '@inertiajs/vue3'

export function useMerchantCategories(merchantPublicId) {
  const deleteForm = useForm({})

  const detachCategory = (assignmentId, options = {}) => {
    return deleteForm.delete(route('merchants.categories.destroy', {
      merchant: merchantPublicId,
      merchantCategory: assignmentId
    }), {
      preserveScroll: true,
      ...options
    })
  }

  return {
    deleteForm,
    detachCategory
  }
}

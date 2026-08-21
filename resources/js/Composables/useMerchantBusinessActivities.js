import { useForm } from '@inertiajs/vue3'

export function useMerchantBusinessActivities() {
  const deleteForm = useForm({})

  const detachCategory = (assignmentId, options = {}) => {
    return deleteForm.delete(route('merchant.activities.destroy', assignmentId), {
      preserveScroll: true,
      ...options
    })
  }

  return {
    deleteForm,
    detachCategory
  }
}

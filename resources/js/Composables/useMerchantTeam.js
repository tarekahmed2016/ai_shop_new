import { useForm } from '@inertiajs/vue3'

export function useMerchantTeam() {
  const deleteForm = useForm({})

  const deleteMembership = (membershipId, callbacks = {}) => {
    return deleteForm.delete(route('merchant.team.destroy', membershipId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    deleteMembership
  }
}

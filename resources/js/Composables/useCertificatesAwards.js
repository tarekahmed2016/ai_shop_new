import { router, useForm } from '@inertiajs/vue3'

export function useCertificatesAwards() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async (type) => {
    const response = await fetch(route('certificates-awards.next-ordering', { type }), {
      headers: { Accept: 'application/json' }
    })

    if (!response.ok) {
      throw new Error('Failed to fetch next ordering')
    }

    const data = await response.json()

    return data.ordering
  }

  const deleteCertificateAward = (id, callbacks = {}) => {
    return deleteForm.delete(route('certificates-awards.destroy', id), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    deleteCertificateAward
  }
}

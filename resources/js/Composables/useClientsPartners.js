import { router, useForm } from '@inertiajs/vue3'

export function useClientsPartners() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async (type) => {
    const response = await fetch(route('clients-partners.next-ordering', { type }), {
      headers: { Accept: 'application/json' }
    })

    if (!response.ok) {
      throw new Error('Failed to fetch next ordering')
    }

    const data = await response.json()

    return data.ordering
  }

  const createClientPartner = (data, options = {}) => {
    return router.post(route('clients-partners.store'), data, {
      preserveScroll: true,
      ...options
    })
  }

  const updateClientPartner = (id, data, options = {}) => {
    return router.put(route('clients-partners.update', id), data, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteClientPartner = (id, callbacks = {}) => {
    return deleteForm.delete(route('clients-partners.destroy', id), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createClientPartner,
    updateClientPartner,
    deleteClientPartner
  }
}

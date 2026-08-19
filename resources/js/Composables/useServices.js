import { router, useForm } from '@inertiajs/vue3'

export function useServices() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async () => {
    const response = await fetch(route('services.next-ordering'), {
      headers: { Accept: 'application/json' }
    })
    const data = await response.json()

    return data.ordering
  }

  const createService = (serviceData, options = {}) => {
    return router.post(route('services.store'), serviceData, {
      preserveScroll: true,
      ...options
    })
  }

  const updateService = (id, serviceData, options = {}) => {
    return router.put(route('services.update', id), serviceData, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteService = (serviceId, callbacks = {}) => {
    return deleteForm.delete(route('services.destroy', serviceId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createService,
    updateService,
    deleteService
  }
}

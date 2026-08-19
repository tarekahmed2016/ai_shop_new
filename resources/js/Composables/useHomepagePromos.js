import { router, useForm } from '@inertiajs/vue3'

export function useHomepagePromos() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async (type) => {
    const response = await fetch(route('homepage-promos.next-ordering', { type }), {
      headers: { Accept: 'application/json' }
    })

    if (!response.ok) {
      throw new Error('Failed to fetch next ordering')
    }

    const data = await response.json()

    return data.ordering
  }

  const createHomepagePromo = (promoData, options = {}) => {
    return router.post(route('homepage-promos.store'), promoData, {
      preserveScroll: true,
      ...options
    })
  }

  const updateHomepagePromo = (id, promoData, options = {}) => {
    return router.put(route('homepage-promos.update', id), promoData, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteHomepagePromo = (promoId, callbacks = {}) => {
    return deleteForm.delete(route('homepage-promos.destroy', promoId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createHomepagePromo,
    updateHomepagePromo,
    deleteHomepagePromo
  }
}

import { router, useForm } from '@inertiajs/vue3'

export function useHeroSlides() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async () => {
    const response = await fetch(route('hero-slides.next-ordering'), {
      headers: { Accept: 'application/json' }
    })

    if (!response.ok) {
      throw new Error('Failed to fetch next ordering')
    }

    const data = await response.json()

    return data.ordering
  }

  const createHeroSlide = (heroSlideData, options = {}) => {
    return router.post(route('hero-slides.store'), heroSlideData, {
      preserveScroll: true,
      ...options
    })
  }

  const updateHeroSlide = (id, heroSlideData, options = {}) => {
    return router.put(route('hero-slides.update', id), heroSlideData, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteHeroSlide = (heroSlideId, callbacks = {}) => {
    return deleteForm.delete(route('hero-slides.destroy', heroSlideId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createHeroSlide,
    updateHeroSlide,
    deleteHeroSlide
  }
}

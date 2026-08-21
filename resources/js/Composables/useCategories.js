import { router, useForm } from '@inertiajs/vue3'

export function useCategories() {
  const deleteForm = useForm({})

  const createCategory = (data, options = {}) => {
    return router.post(route('categories.store'), data, {
      preserveScroll: true,
      ...options
    })
  }

  const updateCategory = (publicId, data, options = {}) => {
    return router.put(route('categories.update', publicId), data, {
      preserveScroll: true,
      ...options
    })
  }

  return {
    deleteForm,
    createCategory,
    updateCategory
  }
}

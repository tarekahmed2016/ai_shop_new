import { useForm } from '@inertiajs/vue3'

export function usePages() {
  const deleteForm = useForm({})

  const deletePage = (pageId, options = {}) => {
    deleteForm.delete(route('pages.destroy', { page: pageId }), options)
  }

  return {
    deleteForm,
    deletePage,
  }
}

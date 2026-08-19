import { router, useForm } from '@inertiajs/vue3'

export function useNewsletterSubscribers() {
  const deleteForm = useForm({})
  const unsubscribeForm = useForm({})

  const deleteSubscriber = (id, callbacks = {}) => {
    return deleteForm.delete(route('newsletter-subscribers.destroy', id), {
      preserveScroll: true,
      ...callbacks
    })
  }

  const unsubscribeSubscriber = (id, callbacks = {}) => {
    return unsubscribeForm.put(route('newsletter-subscribers.unsubscribe', id), {
      preserveScroll: true,
      ...callbacks
    })
  }

  const refreshList = (params = {}) => {
    return router.get(route('newsletter-subscribers.index'), params, {
      preserveState: true,
      preserveScroll: true,
      only: ['newsletterSubscribers', 'filters'],
    })
  }

  return {
    deleteForm,
    unsubscribeForm,
    deleteSubscriber,
    unsubscribeSubscriber,
    refreshList,
  }
}

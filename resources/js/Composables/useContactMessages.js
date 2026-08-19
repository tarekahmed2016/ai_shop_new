import { router, useForm } from '@inertiajs/vue3'

export function useContactMessages() {
  const deleteForm = useForm({})
  const readForm = useForm({})
  const unreadForm = useForm({})

  const markAsRead = (id, callbacks = {}) => {
    return readForm.put(route('contact-messages.read', id), {
      preserveScroll: true,
      ...callbacks,
    })
  }

  const markAsUnread = (id, callbacks = {}) => {
    return unreadForm.put(route('contact-messages.unread', id), {
      preserveScroll: true,
      ...callbacks,
    })
  }

  const deleteContactMessage = (id, callbacks = {}) => {
    return deleteForm.delete(route('contact-messages.destroy', id), {
      preserveScroll: true,
      ...callbacks,
    })
  }

  const refreshList = (params = {}) => {
    return router.get(route('contact-messages.index'), params, {
      preserveState: true,
      preserveScroll: true,
      only: ['contactMessages', 'filters'],
    })
  }

  return {
    deleteForm,
    readForm,
    unreadForm,
    markAsRead,
    markAsUnread,
    deleteContactMessage,
    refreshList,
  }
}

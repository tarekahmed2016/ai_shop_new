import { router, useForm } from '@inertiajs/vue3'

export function useUsers() {
  // Create delete form
  const deleteForm = useForm({})

  // Create new user
  const createUser = (userData, options = {}) => {
    return router.post(route('users.store'), userData, {
      preserveScroll: true,
      ...options
    })
  }

  // Update existing user
  const updateUser = (id, userData, options = {}) => {
    return router.put(route('users.update', id), userData, {
      preserveScroll: true,
      ...options
    })
  }

  // Delete user
  const deleteUser = (userId, callbacks = {}) => {
    return deleteForm.delete(route('users.destroy', userId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    createUser,
    updateUser,
    deleteUser
  }
}

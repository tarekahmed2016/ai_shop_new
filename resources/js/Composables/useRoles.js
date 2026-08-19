import { router, useForm } from '@inertiajs/vue3'

export function useRoles() {
  // Create delete form
  const deleteForm = useForm({})

  // Create new role
  const createRole = (roleData, options = {}) => {
    return router.post(route('roles.store'), roleData, {
      preserveScroll: true,
      ...options
    })
  }

  // Update existing role
  const updateRole = (id, roleData, options = {}) => {
    return router.put(route('roles.update', id), roleData, {
      preserveScroll: true,
      ...options
    })
  }

  // Delete role
  const deleteRole = (roleId, callbacks = {}) => {
    return deleteForm.delete(route('roles.destroy', roleId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    createRole,
    updateRole,
    deleteRole
  }
}

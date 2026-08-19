import { router, useForm } from '@inertiajs/vue3'

export function useProjects() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async () => {
    const response = await fetch(route('projects.next-ordering'), {
      headers: { Accept: 'application/json' }
    })
    const data = await response.json()

    return data.ordering
  }

  const createProject = (projectData, options = {}) => {
    return router.post(route('projects.store'), projectData, {
      preserveScroll: true,
      ...options
    })
  }

  const updateProject = (id, projectData, options = {}) => {
    return router.put(route('projects.update', id), projectData, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteProject = (projectId, callbacks = {}) => {
    return deleteForm.delete(route('projects.destroy', projectId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createProject,
    updateProject,
    deleteProject
  }
}

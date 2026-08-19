import { router, useForm } from '@inertiajs/vue3'

export function useTeamMembers() {
  const deleteForm = useForm({})

  const fetchNextOrdering = async () => {
    const response = await fetch(route('team-members.next-ordering'), {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      return null
    }

    try {
      const data = await response.json()

      return data.ordering ?? null
    } catch {
      return null
    }
  }

  const createTeamMember = (teamMemberData, options = {}) => {
    return router.post(route('team-members.store'), teamMemberData, {
      preserveScroll: true,
      ...options
    })
  }

  const updateTeamMember = (id, teamMemberData, options = {}) => {
    return router.put(route('team-members.update', id), teamMemberData, {
      preserveScroll: true,
      ...options
    })
  }

  const deleteTeamMember = (teamMemberId, callbacks = {}) => {
    return deleteForm.delete(route('team-members.destroy', teamMemberId), {
      preserveScroll: true,
      ...callbacks
    })
  }

  return {
    deleteForm,
    fetchNextOrdering,
    createTeamMember,
    updateTeamMember,
    deleteTeamMember
  }
}

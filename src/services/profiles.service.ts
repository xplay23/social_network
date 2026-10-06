import { apiRequest } from '../lib/api'

export interface ProfilePatch {
  display_name?: string
  username?: string
  avatar_url?: string | null
}

export const profilesService = {
  getById: (id: string) => apiRequest(`/profiles/${encodeURIComponent(id)}`),
  getByUsername: (username: string) =>
    apiRequest(`/profiles/by-username/${encodeURIComponent(username)}`),
  search: (term: string) => apiRequest<unknown[]>(`/profiles?search=${encodeURIComponent(term)}`),
  updateShared: (id: string, patch: ProfilePatch) =>
    apiRequest(`/profiles/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify(patch),
    }),
}

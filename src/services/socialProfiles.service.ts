import { apiRequest } from '../lib/api'

export interface SocialProfilePatch {
  bio?: string | null
  location?: string | null
  cover_url?: string | null
}

export const socialProfilesService = {
  get: (userId: string) => apiRequest(`/profiles/${encodeURIComponent(userId)}/details`),
  upsert: (userId: string, patch: SocialProfilePatch) =>
    apiRequest(`/profiles/${encodeURIComponent(userId)}/details`, {
      method: 'PUT',
      body: JSON.stringify(patch),
    }),
}

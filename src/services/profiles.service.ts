import { supabase } from '../lib/supabase'

export interface ProfilePatch {
  display_name?: string
  username?: string
  avatar_url?: string | null
}

export const profilesService = {
  getById: (id: string) => supabase.from('profiles').select('*').eq('id', id).single(),
  getByUsername: (username: string) =>
    supabase.from('profiles').select('*').ilike('username', username).single(),
  updateShared: (id: string, patch: ProfilePatch) =>
    supabase.from('profiles').update(patch).eq('id', id).select().single(),
}

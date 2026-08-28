import { supabase } from '../lib/supabase'

export interface SocialProfilePatch {
  bio?: string | null
  location?: string | null
  cover_url?: string | null
}

export const socialProfilesService = {
  get: (userId: string) =>
    supabase.from('social_profiles').select('*').eq('user_id', userId).maybeSingle(),
  upsert: (userId: string, patch: SocialProfilePatch) =>
    supabase
      .from('social_profiles')
      .upsert({ user_id: userId, ...patch })
      .select()
      .single(),
}

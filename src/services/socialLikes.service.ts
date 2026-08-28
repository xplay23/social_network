import { supabase } from '../lib/supabase'

export const socialLikesService = {
  add: (postId: string, userId: string) =>
    supabase.from('social_likes').insert({ post_id: postId, user_id: userId }),
  remove: (postId: string, userId: string) =>
    supabase.from('social_likes').delete().eq('post_id', postId).eq('user_id', userId),
}

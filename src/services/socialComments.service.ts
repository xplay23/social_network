import { supabase } from '../lib/supabase'

export const socialCommentsService = {
  listForPost: (postId: string) =>
    supabase.from('social_comments').select('*').eq('post_id', postId).order('created_at'),
  create: (postId: string, authorId: string, content: string) =>
    supabase
      .from('social_comments')
      .insert({ post_id: postId, author_id: authorId, content })
      .select()
      .single(),
  remove: (id: string) => supabase.from('social_comments').delete().eq('id', id),
}

import { supabase } from '../lib/supabase'

export const socialPostsService = {
  list: () => supabase.from('social_posts').select('*').order('created_at', { ascending: false }),
  create: (authorId: string, content: string, imageUrl?: string) =>
    supabase
      .from('social_posts')
      .insert({ author_id: authorId, content, image_url: imageUrl ?? null })
      .select()
      .single(),
  remove: (id: string) => supabase.from('social_posts').delete().eq('id', id),
}

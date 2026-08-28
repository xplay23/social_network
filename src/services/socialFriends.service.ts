import { supabase } from '../lib/supabase'

export type FriendshipStatus = 'pending' | 'accepted' | 'declined'

export const socialFriendsService = {
  listForUser: (userId: string) =>
    supabase
      .from('social_friendships')
      .select('*')
      .or(`sender_id.eq.${userId},receiver_id.eq.${userId}`),
  request: (senderId: string, receiverId: string) =>
    supabase
      .from('social_friendships')
      .insert({ sender_id: senderId, receiver_id: receiverId, status: 'pending' })
      .select()
      .single(),
  setStatus: (id: string, status: FriendshipStatus) =>
    supabase.from('social_friendships').update({ status }).eq('id', id).select().single(),
  remove: (id: string) => supabase.from('social_friendships').delete().eq('id', id),
}

import { apiRequest } from '../lib/api'

export type FriendshipStatus = 'pending' | 'accepted' | 'declined'

export const socialFriendsService = {
  listForUser: (_userId: string) => apiRequest<unknown[]>('/friendships'),
  request: (_senderId: string, receiverId: string) =>
    apiRequest('/friendships', { method: 'POST', body: JSON.stringify({ receiverId }) }),
  setStatus: (id: string, status: FriendshipStatus) =>
    apiRequest(`/friendships/${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    }),
  remove: (id: string) =>
    apiRequest(`/friendships/${encodeURIComponent(id)}`, { method: 'DELETE' }),
}

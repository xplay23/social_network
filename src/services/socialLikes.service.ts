import { apiRequest } from '../lib/api'

export const socialLikesService = {
  add: (postId: string, _userId: string) =>
    apiRequest(`/posts/${encodeURIComponent(postId)}/likes`, { method: 'PUT' }),
  remove: (postId: string, _userId: string) =>
    apiRequest(`/posts/${encodeURIComponent(postId)}/likes`, { method: 'DELETE' }),
}

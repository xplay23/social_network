import { apiRequest } from '../lib/api'

export const socialCommentsService = {
  listForPost: (postId: string) =>
    apiRequest<unknown[]>(`/posts/${encodeURIComponent(postId)}/comments`),
  create: (postId: string, _authorId: string, content: string) =>
    apiRequest(`/posts/${encodeURIComponent(postId)}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    }),
  remove: (id: string) => apiRequest(`/comments/${encodeURIComponent(id)}`, { method: 'DELETE' }),
}

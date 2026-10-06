import { apiRequest } from '../lib/api'

export const socialPostsService = {
  list: () => apiRequest<unknown[]>('/posts'),
  create: (_authorId: string, content: string, imageUrl?: string) =>
    apiRequest('/posts', {
      method: 'POST',
      body: JSON.stringify({ content, imageUrl: imageUrl ?? null }),
    }),
  remove: (id: string) => apiRequest(`/posts/${encodeURIComponent(id)}`, { method: 'DELETE' }),
}

import { apiRequest, getAccessToken, setAccessToken } from '../lib/api'

export interface User {
  id: string
  email: string
}
export interface Session {
  accessToken: string
  user: User
}
interface AuthResponse {
  token: string
  user: User
}

export const authService = {
  async getSession() {
    if (!getAccessToken()) return { data: { session: null }, error: null }
    const result = await apiRequest<User>('/auth/me')
    if (result.error || !result.data) {
      setAccessToken(null)
      return { data: { session: null }, error: result.error }
    }
    return {
      data: { session: { accessToken: getAccessToken()!, user: result.data } as Session },
      error: null,
    }
  },
  async signIn(email: string, password: string) {
    const result = await apiRequest<AuthResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    if (result.data) setAccessToken(result.data.token)
    return {
      data: {
        session: result.data
          ? ({ accessToken: result.data.token, user: result.data.user } as Session)
          : null,
      },
      error: result.error,
    }
  },
  async signUp(email: string, password: string, metadata: { name: string; username: string }) {
    const result = await apiRequest<AuthResponse>('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ email, password, ...metadata }),
    })
    if (result.data) setAccessToken(result.data.token)
    return {
      data: {
        session: result.data
          ? ({ accessToken: result.data.token, user: result.data.user } as Session)
          : null,
      },
      error: result.error,
    }
  },
  async signOut() {
    setAccessToken(null)
  },
}

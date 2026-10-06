export interface ApiError {
  message: string
}

export interface ApiResult<T> {
  data: T | null
  error: ApiError | null
}

const API_URL = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') || '/api'
const TOKEN_KEY = 'circle_access_token'

export const getAccessToken = () => localStorage.getItem(TOKEN_KEY)

export function setAccessToken(token: string | null) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<ApiResult<T>> {
  const token = getAccessToken()
  try {
    const response = await fetch(`${API_URL}${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) {
      return { data: null, error: { message: payload.message || 'Ошибка запроса к серверу' } }
    }
    return { data: payload as T, error: null }
  } catch {
    return { data: null, error: { message: 'Сервер недоступен. Проверьте API и подключение.' } }
  }
}

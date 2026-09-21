import axios from 'axios'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    Accept: 'application/json',
  },
  timeout: 10_000,
  withCredentials: true,
  withXSRFToken: true,
})

const csrfClient = axios.create({
  baseURL: '/',
  headers: { Accept: 'application/json' },
  withCredentials: true,
  withXSRFToken: true,
})

export async function initializeCsrf(): Promise<void> {
  await csrfClient.get('/sanctum/csrf-cookie')
}

export function validationMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'Something went wrong. Please try again.'

  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  const firstError = errors ? Object.values(errors).flat()[0] : undefined

  return firstError ?? error.response?.data?.message ?? 'Something went wrong. Please try again.'
}

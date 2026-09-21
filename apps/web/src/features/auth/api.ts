import { useQuery } from '@tanstack/react-query'
import axios from 'axios'

import { apiClient, initializeCsrf } from '../../api/client'

export type OrganizationSummary = {
  id: number
  name: string
  slug: string
  description: string | null
  role: string
  member_count?: number
}

export type Membership = {
  id: number
  role: string
  role_label: string
  organization: OrganizationSummary
}

export type User = {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  is_platform_admin: boolean
  memberships: Membership[]
}

type Resource<T> = { data: T }

export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await apiClient.get<Resource<User>>('/auth/user')
    return response.data.data
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 401) return null
    throw error
  }
}

export function useAuthQuery() {
  return useQuery({ queryKey: ['auth', 'user'], queryFn: getCurrentUser, retry: false })
}

export async function login(input: {
  email: string
  password: string
  remember: boolean
}): Promise<User> {
  await initializeCsrf()
  const response = await apiClient.post<Resource<User>>('/auth/login', input)
  return response.data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function forgotPassword(email: string): Promise<string> {
  await initializeCsrf()
  const response = await apiClient.post<{ message: string }>('/auth/forgot-password', { email })
  return response.data.message
}

export async function resetPassword(input: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<string> {
  await initializeCsrf()
  const response = await apiClient.post<{ message: string }>('/auth/reset-password', input)
  return response.data.message
}

export async function resendVerification(): Promise<string> {
  const response = await apiClient.post<{ message: string }>('/auth/email/verification-notification')
  return response.data.message
}

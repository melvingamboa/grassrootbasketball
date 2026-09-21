import { apiClient, initializeCsrf } from '../../api/client'
import type { OrganizationSummary, User } from '../auth/api'

type Resource<T> = { data: T }

export type OrganizationMember = {
  id: number
  role: string
  role_label: string
  joined_at: string
  user: {
    id: number
    name: string
    email: string
  }
}

export type InvitationPreview = {
  email: string
  role: string
  role_label: string
  expires_at: string
  organization: {
    name: string
    slug: string
  }
}

export async function listOrganizations(): Promise<OrganizationSummary[]> {
  const response = await apiClient.get<Resource<OrganizationSummary[]>>('/organizations')
  return response.data.data
}

export async function createOrganization(input: {
  name: string
  description: string
}): Promise<OrganizationSummary> {
  const response = await apiClient.post<Resource<OrganizationSummary>>('/organizations', input)
  return response.data.data
}

export async function listMembers(slug: string): Promise<OrganizationMember[]> {
  const response = await apiClient.get<Resource<OrganizationMember[]>>(`/organizations/${slug}/members`)
  return response.data.data
}

export async function inviteMember(
  slug: string,
  input: { email: string; role: string },
): Promise<void> {
  await apiClient.post(`/organizations/${slug}/invitations`, input)
}

export async function updateMemberRole(
  slug: string,
  membershipId: number,
  role: string,
): Promise<void> {
  await apiClient.patch(`/organizations/${slug}/members/${membershipId}`, { role })
}

export async function removeMember(slug: string, membershipId: number): Promise<void> {
  await apiClient.delete(`/organizations/${slug}/members/${membershipId}`)
}

export async function acceptInvitation(token: string): Promise<OrganizationSummary> {
  const response = await apiClient.post<Resource<OrganizationSummary>>('/invitations/accept', { token })
  return response.data.data
}

export async function getInvitation(token: string): Promise<InvitationPreview> {
  const response = await apiClient.get<Resource<InvitationPreview>>(`/invitations/${token}`)
  return response.data.data
}

export async function registerFromInvitation(
  token: string,
  input: { name: string; password: string; password_confirmation: string },
): Promise<User> {
  await initializeCsrf()
  const response = await apiClient.post<Resource<User>>(`/invitations/${token}/register`, input)
  return response.data.data
}

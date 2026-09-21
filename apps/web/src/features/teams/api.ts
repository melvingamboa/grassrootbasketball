import { apiClient } from '../../api/client'
import type { AdministrativeArea, Competition, Division } from '../competitions/api'

type Resource<T> = { data: T }

export type Team = { id: number; name: string; slug: string; short_name: string | null; primary_color: string; secondary_color: string; logo_url: string | null; status: string; status_label: string; administrative_area?: AdministrativeArea | null }
export type Player = { id: number; display_name: string; first_name: string; middle_name: string | null; last_name: string; suffix: string | null; date_of_birth: string | null; contact_email: string | null; contact_phone: string | null; photo_url: string | null; is_active: boolean }
export type PlayerRegistration = { id: number; jersey_number: number; position: string; position_label: string; status: string; status_label: string; player: Player }
export type TeamRegistration = { id: number; status: string; status_label: string; team: Team; division: Division; players: PlayerRegistration[]; roster_count: number }
export type PublicRosterPlayer = { id: number; name: string; photo_url: string | null; jersey_number: number; position: string; position_label: string }
export type PublicTeam = { name: string; slug: string; short_name: string | null; primary_color: string; secondary_color: string; logo_url: string | null; location: string | null; organization: { name: string; slug: string }; participations: { id: number; competition: string; season: string; division: string; roster: PublicRosterPlayer[] }[] }

export type TeamInput = { name: string; short_name: string; administrative_area_id: number | null; primary_color: string; secondary_color: string; status: string }
export type PlayerInput = { first_name: string; middle_name: string; last_name: string; suffix: string; date_of_birth: string | null; contact_email: string; contact_phone: string; is_active: boolean }
export type QuickPlayerInput = Omit<PlayerInput, 'is_active'> & { jersey_number: number; position: string; status: string }

export async function listTeams(organization: string): Promise<Team[]> { return (await apiClient.get<Resource<Team[]>>(`/organizations/${organization}/teams`)).data.data }
export async function createTeam(organization: string, input: TeamInput): Promise<Team> { return (await apiClient.post<Resource<Team>>(`/organizations/${organization}/teams`, input)).data.data }
export async function updateTeam(organization: string, team: string, input: Partial<TeamInput>): Promise<Team> { return (await apiClient.patch<Resource<Team>>(`/organizations/${organization}/teams/${team}`, input)).data.data }
export async function uploadTeamLogo(organization: string, team: string, image: File): Promise<Team> { const data = new FormData(); data.append('image', image); return (await apiClient.post<Resource<Team>>(`/organizations/${organization}/teams/${team}/logo`, data)).data.data }
export async function listPlayers(organization: string): Promise<Player[]> { return (await apiClient.get<Resource<Player[]>>(`/organizations/${organization}/players`)).data.data }
export async function createPlayer(organization: string, input: PlayerInput): Promise<Player> { return (await apiClient.post<Resource<Player>>(`/organizations/${organization}/players`, input)).data.data }
export async function updatePlayer(organization: string, player: number, input: Partial<PlayerInput>): Promise<Player> { return (await apiClient.patch<Resource<Player>>(`/organizations/${organization}/players/${player}`, input)).data.data }
export async function uploadPlayerPhoto(organization: string, player: number, image: File): Promise<Player> { const data = new FormData(); data.append('image', image); return (await apiClient.post<Resource<Player>>(`/organizations/${organization}/players/${player}/photo`, data)).data.data }

function seasonPath(organization: string, competition: string, season: string) { return `/organizations/${organization}/competitions/${competition}/seasons/${season}` }
export async function listTeamRegistrations(organization: string, competition: string, season: string): Promise<TeamRegistration[]> { return (await apiClient.get<Resource<TeamRegistration[]>>(`${seasonPath(organization, competition, season)}/team-registrations`)).data.data }
export async function registerTeam(organization: string, competition: string, season: string, input: { team_id: number; division_id: number; status: string }): Promise<TeamRegistration> { return (await apiClient.post<Resource<TeamRegistration>>(`${seasonPath(organization, competition, season)}/team-registrations`, input)).data.data }
export async function updateTeamRegistration(organization: string, competition: string, season: string, registration: number, input: { division_id?: number; status?: string }): Promise<TeamRegistration> { return (await apiClient.patch<Resource<TeamRegistration>>(`${seasonPath(organization, competition, season)}/team-registrations/${registration}`, input)).data.data }
export async function removeTeamRegistration(organization: string, competition: string, season: string, registration: number): Promise<void> { await apiClient.delete(`${seasonPath(organization, competition, season)}/team-registrations/${registration}`) }
export async function registerPlayer(organization: string, competition: string, season: string, registration: number, input: { player_id: number; jersey_number: number; position: string; status: string }): Promise<PlayerRegistration> { return (await apiClient.post<Resource<PlayerRegistration>>(`${seasonPath(organization, competition, season)}/team-registrations/${registration}/players`, input)).data.data }
export async function createAndRegisterPlayer(organization: string, competition: string, season: string, registration: number, input: QuickPlayerInput): Promise<PlayerRegistration> { return (await apiClient.post<Resource<PlayerRegistration>>(`${seasonPath(organization, competition, season)}/team-registrations/${registration}/players/create`, input)).data.data }
export async function updatePlayerRegistration(organization: string, competition: string, season: string, registration: number, playerRegistration: number, input: { jersey_number?: number; position?: string; status?: string }): Promise<PlayerRegistration> { return (await apiClient.patch<Resource<PlayerRegistration>>(`${seasonPath(organization, competition, season)}/team-registrations/${registration}/players/${playerRegistration}`, input)).data.data }
export async function removePlayerRegistration(organization: string, competition: string, season: string, registration: number, playerRegistration: number): Promise<void> { await apiClient.delete(`${seasonPath(organization, competition, season)}/team-registrations/${registration}/players/${playerRegistration}`) }
export async function getPublicTeam(organization: string, team: string): Promise<PublicTeam> { return (await apiClient.get<Resource<PublicTeam>>(`/public/organizations/${organization}/teams/${team}`)).data.data }

export type { Competition }

import { apiClient } from '../../api/client'

type Resource<T> = { data: T }

export type AdministrativeArea = { id: number; parent_id: number | null; type: string; type_label: string; name: string; code: string | null; path: string }
export type Venue = { id: number; name: string; slug: string; address: string | null; latitude: string | null; longitude: string | null; status: string; status_label: string; administrative_area?: AdministrativeArea | null }
export type Division = { id: number; name: string; slug: string; category: string; category_label: string; gender: string; minimum_age: number | null; maximum_age: number | null; is_active: boolean }
export type Season = { id: number; name: string; slug: string; starts_on: string | null; ends_on: string | null; timezone: string; format: string; format_label: string; status: string; status_label: string; max_roster_size: number; period_count: number; period_minutes: number; overtime_minutes: number; rules_notes: string | null; primary_venue?: Venue | null; divisions: Division[] }
export type Competition = { id: number; name: string; slug: string; type: string; type_label: string; status: string; status_label: string; description: string | null; administrative_area?: AdministrativeArea | null; seasons?: Season[]; season_count?: number }

export type VenueInput = { name: string; administrative_area_id: number | null; address: string; latitude: number | null; longitude: number | null; status: string }
export type CompetitionInput = { name: string; administrative_area_id: number | null; type: string; status: string; description: string }
export type SeasonInput = { name: string; primary_venue_id: number | null; starts_on: string | null; ends_on: string | null; timezone: string; format: string; status: string; max_roster_size: number; period_count: number; period_minutes: number; overtime_minutes: number; rules_notes: string }
export type DivisionInput = { name: string; category: string; gender: string; minimum_age: number | null; maximum_age: number | null; is_active: boolean }

export async function listAdministrativeAreas(): Promise<AdministrativeArea[]> { return (await apiClient.get<Resource<AdministrativeArea[]>>('/administrative-areas')).data.data }
export async function listVenues(organization: string): Promise<Venue[]> { return (await apiClient.get<Resource<Venue[]>>(`/organizations/${organization}/venues`)).data.data }
export async function createVenue(organization: string, input: VenueInput): Promise<Venue> { return (await apiClient.post<Resource<Venue>>(`/organizations/${organization}/venues`, input)).data.data }
export async function updateVenue(organization: string, venue: string, input: Partial<VenueInput>): Promise<Venue> { return (await apiClient.patch<Resource<Venue>>(`/organizations/${organization}/venues/${venue}`, input)).data.data }
export async function listCompetitions(organization: string): Promise<Competition[]> { return (await apiClient.get<Resource<Competition[]>>(`/organizations/${organization}/competitions`)).data.data }
export async function getCompetition(organization: string, competition: string): Promise<Competition> { return (await apiClient.get<Resource<Competition>>(`/organizations/${organization}/competitions/${competition}`)).data.data }
export async function createCompetition(organization: string, input: CompetitionInput): Promise<Competition> { return (await apiClient.post<Resource<Competition>>(`/organizations/${organization}/competitions`, input)).data.data }
export async function updateCompetition(organization: string, competition: string, input: Partial<CompetitionInput>): Promise<Competition> { return (await apiClient.patch<Resource<Competition>>(`/organizations/${organization}/competitions/${competition}`, input)).data.data }
export async function createSeason(organization: string, competition: string, input: SeasonInput): Promise<Season> { return (await apiClient.post<Resource<Season>>(`/organizations/${organization}/competitions/${competition}/seasons`, input)).data.data }
export async function updateSeason(organization: string, competition: string, season: string, input: Partial<SeasonInput>): Promise<Season> { return (await apiClient.patch<Resource<Season>>(`/organizations/${organization}/competitions/${competition}/seasons/${season}`, input)).data.data }
export async function createDivision(organization: string, competition: string, season: string, input: DivisionInput): Promise<Division> { return (await apiClient.post<Resource<Division>>(`/organizations/${organization}/competitions/${competition}/seasons/${season}/divisions`, input)).data.data }
export async function updateDivision(organization: string, competition: string, season: string, division: number, input: Partial<DivisionInput>): Promise<Division> { return (await apiClient.patch<Resource<Division>>(`/organizations/${organization}/competitions/${competition}/seasons/${season}/divisions/${division}`, input)).data.data }
export async function deleteDivision(organization: string, competition: string, season: string, division: number): Promise<void> { await apiClient.delete(`/organizations/${organization}/competitions/${competition}/seasons/${season}/divisions/${division}`) }

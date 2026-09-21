import { apiClient } from '../../api/client'
import type { Division } from '../competitions/api'

type Resource<T> = { data: T }

export type CompetitionTeam = {
  registration_id: number
  name: string
  slug: string
  short_name: string | null
  primary_color: string
  secondary_color: string
  logo_url: string | null
}

export type Standing = {
  id: number
  rank: number
  played: number
  wins: number
  losses: number
  points_for: number
  points_against: number
  point_difference: number
  qualification_status: string
  qualification_status_label: string
  notes: string | null
  team_registration_id: number
  team: Omit<CompetitionTeam, 'registration_id'>
  division: Division
  updated_at: string | null
  updated_by?: { id: number; name: string } | null
}

export type StandingInput = {
  team_registration_id: number
  qualification_status: string
  notes: string | null
}

export type BracketMatch = {
  id: number
  round_number: number
  match_number: number
  round_label: string
  home_team: CompetitionTeam | null
  away_team: CompetitionTeam | null
  winner_team: CompetitionTeam | null
  game_id: number | null
}

export type Bracket = {
  id: number
  name: string
  status: string
  status_label: string
  division: Division
  matches: BracketMatch[]
  updated_at: string | null
  updated_by?: { id: number; name: string } | null
}

export type BracketMatchInput = {
  round_number: number
  match_number: number
  round_label: string
  home_team_registration_id: number | null
  away_team_registration_id: number | null
  winner_team_registration_id: number | null
  game_id: number | null
}

function seasonPath(organization: string, competition: string, season: string) {
  return `/organizations/${organization}/competitions/${competition}/seasons/${season}`
}

export async function listStandings(
  organization: string,
  competition: string,
  season: string,
  division?: number,
): Promise<Standing[]> {
  return (
    await apiClient.get<Resource<Standing[]>>(
      `${seasonPath(organization, competition, season)}/standings`,
      { params: division ? { division_id: division } : {} },
    )
  ).data.data
}

export async function saveStandings(
  organization: string,
  competition: string,
  season: string,
  division: number,
  rows: StandingInput[],
): Promise<Standing[]> {
  return (
    await apiClient.put<Resource<Standing[]>>(
      `${seasonPath(organization, competition, season)}/standings`,
      { division_id: division, rows },
    )
  ).data.data
}

export async function recalculateStandings(
  organization: string,
  competition: string,
  season: string,
  division: number,
): Promise<Standing[]> {
  return (
    await apiClient.post<Resource<Standing[]>>(
      `${seasonPath(organization, competition, season)}/standings/recalculate`,
      { division_id: division },
    )
  ).data.data
}

export async function listBrackets(
  organization: string,
  competition: string,
  season: string,
): Promise<Bracket[]> {
  return (
    await apiClient.get<Resource<Bracket[]>>(
      `${seasonPath(organization, competition, season)}/brackets`,
    )
  ).data.data
}

export async function createBracket(
  organization: string,
  competition: string,
  season: string,
  input: {
    division_id: number
    name: string
    status: string
    template?: 'empty' | 'single_elimination_4' | 'single_elimination_8'
  },
): Promise<Bracket> {
  return (
    await apiClient.post<Resource<Bracket>>(
      `${seasonPath(organization, competition, season)}/brackets`,
      input,
    )
  ).data.data
}

export async function initializeBracket(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
  template: 'single_elimination_4' | 'single_elimination_8',
): Promise<Bracket> {
  return (
    await apiClient.post<Resource<Bracket>>(
      `${seasonPath(organization, competition, season)}/brackets/${bracket}/initialize`,
      { template },
    )
  ).data.data
}

export async function updateBracket(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
  input: Partial<{ name: string; status: string }>,
): Promise<Bracket> {
  return (
    await apiClient.patch<Resource<Bracket>>(
      `${seasonPath(organization, competition, season)}/brackets/${bracket}`,
      input,
    )
  ).data.data
}

export async function deleteBracket(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
): Promise<void> {
  await apiClient.delete(
    `${seasonPath(organization, competition, season)}/brackets/${bracket}`,
  )
}

export async function createBracketMatch(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
  input: BracketMatchInput,
): Promise<BracketMatch> {
  return (
    await apiClient.post<Resource<BracketMatch>>(
      `${seasonPath(organization, competition, season)}/brackets/${bracket}/matches`,
      input,
    )
  ).data.data
}

export async function updateBracketMatch(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
  match: number,
  input: Partial<BracketMatchInput>,
): Promise<BracketMatch> {
  return (
    await apiClient.patch<Resource<BracketMatch>>(
      `${seasonPath(organization, competition, season)}/brackets/${bracket}/matches/${match}`,
      input,
    )
  ).data.data
}

export async function deleteBracketMatch(
  organization: string,
  competition: string,
  season: string,
  bracket: number,
  match: number,
): Promise<void> {
  await apiClient.delete(
    `${seasonPath(organization, competition, season)}/brackets/${bracket}/matches/${match}`,
  )
}

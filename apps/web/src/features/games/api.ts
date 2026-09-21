import { apiClient } from '../../api/client'
import type { Bracket, Standing } from '../competition-control/api'
import type { Division, Venue } from '../competitions/api'

type Resource<T> = { data: T }

export type GameTeam = {
  registration_id: number
  name: string
  slug: string
  short_name: string | null
  primary_color: string
  secondary_color: string
  logo_url: string | null
}

export type GameChange = {
  id?: number
  change_type: string
  from_status: string | null
  to_status: string | null
  old_scheduled_at: string | null
  new_scheduled_at: string | null
  reason: string
  created_at: string | null
  changed_by?: { id: number; name: string } | null
}

export type GameLiveEvent = {
  id: number
  event_type: string
  team_registration_id: number | null
  period_number: number
  points_delta: number | null
  home_score_after: number
  away_score_after: number
  clock_seconds_remaining: number
  details: {
    reason?: string
    player_registration_id?: number
    player_out_registration_id?: number
    player_in_registration_id?: number
    court_slot?: number
    stat?: 'rebounds' | 'assists' | 'steals' | 'blocks' | 'turnovers' | 'fouls'
    delta?: number
    value_after?: number
    clock_seconds_before?: number
    clock_seconds_after?: number
  } | null
  recorded_by?: { id: number; name: string } | null
  created_at: string | null
}

export type GameRosterPlayer = {
  id: number
  jersey_number: number
  position: string
  position_label: string
  status: string
  status_label: string
  player: { id: number; display_name: string }
}

export type GamePlayerStat = {
  id: number
  team_registration_id: number
  is_starter: boolean
  is_on_court: boolean
  court_slot: number | null
  player_registration: Omit<GameRosterPlayer, 'status' | 'status_label'>
  points: number
  rebounds: number
  assists: number
  steals: number
  blocks: number
  turnovers: number
  fouls: number
}

export type GameStatEvent = {
  id: number
  player_registration_id: number
  stat: string
  delta: number
  value_after: number
  reason: string | null
  recorded_by?: { id: number; name: string } | null
  created_at: string | null
}

export type Game = {
  id: number
  scheduled_at: string
  estimated_duration_minutes: number
  round: string | null
  status: string
  status_label: string
  status_reason: string | null
  livestream: {
    provider: 'youtube' | 'facebook'
    provider_label: string
    status: 'unavailable' | 'scheduled' | 'live' | 'ended'
    status_label: string
    watch_url: string
    embed_url: string | null
    can_embed: boolean
  } | null
  home_score: number
  away_score: number
  current_period: number
  period_label: string
  clock_seconds_remaining: number
  clock_running: boolean
  started_at: string | null
  finalized_at: string | null
  division: Division
  venue: Venue | null
  home_team: GameTeam
  away_team: GameTeam
  changes?: GameChange[]
  schedule_history?: GameChange[]
  live_events?: GameLiveEvent[]
  period_scores: { period: number; home: number; away: number }[]
  available_rosters?: { home: GameRosterPlayer[]; away: GameRosterPlayer[] }
  box_score: GamePlayerStat[]
  unassigned_points?: { home: number; away: number }
  stat_events?: GameStatEvent[]
}

export type Announcement = {
  id: number
  title: string
  body: string
  status: string
  status_label: string
  published_at: string | null
  created_at: string
  updated_at: string
}

export type GameInput = {
  division_id: number
  home_team_registration_id: number
  away_team_registration_id: number
  venue_id: number | null
  scheduled_at: string
  estimated_duration_minutes: number
  round: string
  allow_conflicts?: boolean
}

export type GameUpdate = Partial<GameInput> & {
  status?: string
  home_score?: number
  away_score?: number
  status_reason?: string
  livestream_url?: string | null
  livestream_status?: 'unavailable' | 'scheduled' | 'live' | 'ended'
  change_reason: string
}

export type LiveGameAction = {
  action: 'start' | 'score' | 'correct' | 'clock_start' | 'clock_pause' | 'clock_adjust' | 'next_period' | 'finalize'
  team?: 'home' | 'away'
  points?: 1 | 2 | 3
  player_registration_id?: number
  clock_seconds?: number
  reason?: string
}

export type PublicCompetition = {
  organization: { name: string; slug: string }
  competition: { name: string; slug: string; description: string | null }
  season: { name: string; slug: string; timezone: string }
  live_games: Game[]
  upcoming_games: Game[]
  recent_results: Game[]
  announcements: Announcement[]
  standings: Standing[]
  brackets: Bracket[]
}

export type GameFilters = {
  date?: string
  team_registration_id?: number
  status?: string
}

function seasonPath(organization: string, competition: string, season: string) {
  return `/organizations/${organization}/competitions/${competition}/seasons/${season}`
}

export async function listGames(
  organization: string,
  competition: string,
  season: string,
  filters: GameFilters = {},
): Promise<Game[]> {
  return (
    await apiClient.get<Resource<Game[]>>(
      `${seasonPath(organization, competition, season)}/games`,
      { params: filters },
    )
  ).data.data
}

export async function createGame(
  organization: string,
  competition: string,
  season: string,
  input: GameInput,
): Promise<Game> {
  return (
    await apiClient.post<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games`,
      input,
    )
  ).data.data
}

export async function updateGame(
  organization: string,
  competition: string,
  season: string,
  game: number,
  input: GameUpdate,
): Promise<Game> {
  return (
    await apiClient.patch<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games/${game}`,
      input,
    )
  ).data.data
}

export async function getGame(
  organization: string,
  competition: string,
  season: string,
  game: number,
): Promise<Game> {
  return (
    await apiClient.get<Resource<Game>>(`${seasonPath(organization, competition, season)}/games/${game}`)
  ).data.data
}

export async function applyLiveGameAction(
  organization: string,
  competition: string,
  season: string,
  game: number,
  input: LiveGameAction,
): Promise<Game> {
  return (
    await apiClient.post<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games/${game}/live-actions`,
      input,
    )
  ).data.data
}

export async function updateGameLineup(
  organization: string,
  competition: string,
  season: string,
  game: number,
  input: {
    home_player_registration_ids: number[]
    away_player_registration_ids: number[]
    home_starter_player_registration_ids?: number[]
    away_starter_player_registration_ids?: number[]
  },
): Promise<Game> {
  return (
    await apiClient.put<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games/${game}/lineup`,
      input,
    )
  ).data.data
}

export async function substituteGamePlayer(
  organization: string,
  competition: string,
  season: string,
  game: number,
  input: { team: 'home' | 'away'; player_out_registration_id: number; player_in_registration_id: number },
): Promise<Game> {
  return (
    await apiClient.post<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games/${game}/substitutions`,
      input,
    )
  ).data.data
}

export async function updateGamePlayerStat(
  organization: string,
  competition: string,
  season: string,
  game: number,
  playerStat: number,
  input: { stat: 'rebounds' | 'assists' | 'steals' | 'blocks' | 'turnovers' | 'fouls'; delta: -1 | 1; reason?: string },
): Promise<Game> {
  return (
    await apiClient.patch<Resource<Game>>(
      `${seasonPath(organization, competition, season)}/games/${game}/player-stats/${playerStat}`,
      input,
    )
  ).data.data
}

export async function listAnnouncements(
  organization: string,
  competition: string,
  season: string,
): Promise<Announcement[]> {
  return (
    await apiClient.get<Resource<Announcement[]>>(
      `${seasonPath(organization, competition, season)}/announcements`,
    )
  ).data.data
}

export async function createAnnouncement(
  organization: string,
  competition: string,
  season: string,
  input: { title: string; body: string; status: string },
): Promise<Announcement> {
  return (
    await apiClient.post<Resource<Announcement>>(
      `${seasonPath(organization, competition, season)}/announcements`,
      input,
    )
  ).data.data
}

export async function updateAnnouncement(
  organization: string,
  competition: string,
  season: string,
  announcement: number,
  input: Partial<{ title: string; body: string; status: string }>,
): Promise<Announcement> {
  return (
    await apiClient.patch<Resource<Announcement>>(
      `${seasonPath(organization, competition, season)}/announcements/${announcement}`,
      input,
    )
  ).data.data
}

export async function deleteAnnouncement(
  organization: string,
  competition: string,
  season: string,
  announcement: number,
): Promise<void> {
  await apiClient.delete(
    `${seasonPath(organization, competition, season)}/announcements/${announcement}`,
  )
}

export async function getPublicCompetition(
  organization: string,
  competition: string,
  season: string,
): Promise<PublicCompetition> {
  return (
    await apiClient.get<Resource<PublicCompetition>>(
      `/public${seasonPath(organization, competition, season)}`,
    )
  ).data.data
}

export async function listPublicGames(
  organization: string,
  competition: string,
  season: string,
  filters: GameFilters = {},
): Promise<Game[]> {
  return (
    await apiClient.get<Resource<Game[]>>(
      `/public${seasonPath(organization, competition, season)}/games`,
      { params: filters },
    )
  ).data.data
}

export async function getPublicGame(
  organization: string,
  competition: string,
  season: string,
  game: number,
): Promise<Game> {
  return (
    await apiClient.get<Resource<Game>>(
      `/public${seasonPath(organization, competition, season)}/games/${game}`,
    )
  ).data.data
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router'

import { validationMessage } from '../api/client'
import { useAuthQuery } from '../features/auth/api'
import { getCompetition, listCompetitions, listVenues } from '../features/competitions/api'
import {
  createAnnouncement,
  createGame,
  deleteAnnouncement,
  listAnnouncements,
  listGames,
  updateAnnouncement,
  updateGame,
} from '../features/games/api'
import type { GameInput, GameUpdate } from '../features/games/api'
import { listOrganizations } from '../features/organizations/api'
import { listTeamRegistrations } from '../features/teams/api'

const gameStatuses = ['scheduled', 'delayed', 'live', 'suspended', 'postponed', 'cancelled', 'final']
const livestreamStatuses = ['scheduled', 'live', 'ended', 'unavailable'] as const

function nullableNumber(value: FormDataEntryValue | null): number | null {
  return value ? Number(value) : null
}

function localDateTimeValue(iso: string): string {
  const date = new Date(iso)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}

function defaultGameTime(): string {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  date.setHours(18, 0, 0, 0)
  return localDateTimeValue(date.toISOString())
}

export function ScheduleManagementPage() {
  const { organization = '' } = useParams()
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const organizations = useQuery({ queryKey: ['organizations'], queryFn: listOrganizations })
  const competitions = useQuery({ queryKey: ['organizations', organization, 'competitions'], queryFn: () => listCompetitions(organization) })
  const venues = useQuery({ queryKey: ['organizations', organization, 'venues'], queryFn: () => listVenues(organization) })
  const [selectedCompetition, setSelectedCompetition] = useState('')
  const competitionSlug = selectedCompetition || competitions.data?.[0]?.slug || ''
  const competition = useQuery({ queryKey: ['organizations', organization, 'competitions', competitionSlug], queryFn: () => getCompetition(organization, competitionSlug), enabled: Boolean(competitionSlug) })
  const [selectedSeason, setSelectedSeason] = useState('')
  const season = competition.data?.seasons?.find((item) => item.slug === selectedSeason) ?? competition.data?.seasons?.[0]
  const registrations = useQuery({ queryKey: ['team-registrations', organization, competitionSlug, season?.slug], queryFn: () => listTeamRegistrations(organization, competitionSlug, season!.slug), enabled: Boolean(season) })
  const [dateFilter, setDateFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const filters = useMemo(() => ({ ...(dateFilter ? { date: dateFilter } : {}), ...(statusFilter ? { status: statusFilter } : {}) }), [dateFilter, statusFilter])
  const games = useQuery({ queryKey: ['games', organization, competitionSlug, season?.slug, filters], queryFn: () => listGames(organization, competitionSlug, season!.slug, filters), enabled: Boolean(season) })
  const announcements = useQuery({ queryKey: ['announcements', organization, competitionSlug, season?.slug], queryFn: () => listAnnouncements(organization, competitionSlug, season!.slug), enabled: Boolean(season) })
  const membership = organizations.data?.find((item) => item.slug === organization)
  const canManage = Boolean(auth.data?.is_platform_admin || membership && ['owner', 'admin', 'league_manager'].includes(membership.role))
  const canScore = Boolean(auth.data?.is_platform_admin || membership && ['owner', 'admin', 'league_manager', 'scorer'].includes(membership.role))

  const refreshGames = () => queryClient.invalidateQueries({ queryKey: ['games', organization, competitionSlug, season?.slug] })
  const refreshAnnouncements = () => queryClient.invalidateQueries({ queryKey: ['announcements', organization, competitionSlug, season?.slug] })
  const addGame = useMutation({ mutationFn: (input: GameInput) => createGame(organization, competitionSlug, season!.slug, input), onSuccess: refreshGames })
  const editGame = useMutation({ mutationFn: ({ id, input }: { id: number; input: GameUpdate }) => updateGame(organization, competitionSlug, season!.slug, id, input), onSuccess: refreshGames })
  const editLivestream = useMutation({ mutationFn: ({ id, input }: { id: number; input: GameUpdate }) => updateGame(organization, competitionSlug, season!.slug, id, input), onSuccess: refreshGames })
  const addAnnouncement = useMutation({ mutationFn: (input: { title: string; body: string; status: string }) => createAnnouncement(organization, competitionSlug, season!.slug, input), onSuccess: refreshAnnouncements })
  const editAnnouncement = useMutation({ mutationFn: ({ id, input }: { id: number; input: Partial<{ title: string; body: string; status: string }> }) => updateAnnouncement(organization, competitionSlug, season!.slug, id, input), onSuccess: refreshAnnouncements })
  const removeAnnouncement = useMutation({ mutationFn: (id: number) => deleteAnnouncement(organization, competitionSlug, season!.slug, id), onSuccess: refreshAnnouncements })
  const approvedRegistrations = registrations.data?.filter((item) => item.status === 'approved') ?? []

  function submitGame(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    addGame.mutate({
      division_id: Number(data.get('division_id')),
      home_team_registration_id: Number(data.get('home_team_registration_id')),
      away_team_registration_id: Number(data.get('away_team_registration_id')),
      venue_id: nullableNumber(data.get('venue_id')),
      scheduled_at: new Date(String(data.get('scheduled_at'))).toISOString(),
      estimated_duration_minutes: Number(data.get('estimated_duration_minutes')),
      round: String(data.get('round') ?? ''),
      allow_conflicts: data.get('allow_conflicts') === 'on',
    }, { onSuccess: () => form.reset() })
  }

  function submitGameUpdate(event: FormEvent<HTMLFormElement>, gameId: number) {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    editGame.mutate({ id: gameId, input: {
      scheduled_at: new Date(String(data.get('scheduled_at'))).toISOString(),
      venue_id: nullableNumber(data.get('venue_id')),
      status: String(data.get('status')),
      home_score: Number(data.get('home_score')),
      away_score: Number(data.get('away_score')),
      status_reason: String(data.get('status_reason') ?? ''),
      change_reason: String(data.get('change_reason')),
      allow_conflicts: data.get('allow_conflicts') === 'on',
    } })
  }

  function submitLivestreamUpdate(event: FormEvent<HTMLFormElement>, gameId: number) {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const url = String(data.get('livestream_url') ?? '').trim()
    editLivestream.mutate({ id: gameId, input: {
      livestream_url: url || null,
      livestream_status: url ? String(data.get('livestream_status')) as GameUpdate['livestream_status'] : 'unavailable',
      change_reason: String(data.get('change_reason')),
    } })
  }

  if (competitions.isPending) return <main className="grid min-h-[70vh] place-items-center text-slate-300">Loading schedule workspace…</main>

  return <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><Link className="text-sm font-bold text-amber-300" to="/app">← Dashboard</Link><p className="eyebrow mt-5">P5 operations</p><h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Schedule, results, and notices</h1><p className="mt-2 max-w-2xl text-slate-400">Publish fixtures, record official results, and keep viewers informed when plans change.</p></div>{season && <Link className="button button-secondary" to={`/organizations/${organization}/competitions/${competitionSlug}/seasons/${season.slug}`}>Open public league page</Link>}</div>

    <section className="panel mt-8 grid gap-4 md:grid-cols-2">
      <label className="field">Competition<select value={competitionSlug} onChange={(event) => { setSelectedCompetition(event.target.value); setSelectedSeason('') }}>{competitions.data?.map((item) => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label>
      <label className="field">Season<select value={season?.slug ?? ''} onChange={(event) => setSelectedSeason(event.target.value)}>{competition.data?.seasons?.map((item) => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label>
    </section>

    {!season ? <section className="panel mt-6"><p className="empty-state">Create a competition season before scheduling games.</p></section> : <div className="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
      <div className="space-y-6">
        {canManage && <section className="panel"><div className="panel-heading"><div><p className="panel-kicker">New fixture</p><h2 className="panel-title">Schedule a game</h2></div><span className="count-pill">{season.timezone}</span></div>
          <form className="form-stack mt-5" onSubmit={submitGame}>
            <div className="grid gap-4 sm:grid-cols-2"><label className="field">Home team<select name="home_team_registration_id" required defaultValue=""><option value="" disabled>Select team</option>{approvedRegistrations.map((item) => <option key={item.id} value={item.id}>{item.team.name}</option>)}</select></label><label className="field">Away team<select name="away_team_registration_id" required defaultValue=""><option value="" disabled>Select team</option>{approvedRegistrations.map((item) => <option key={item.id} value={item.id}>{item.team.name}</option>)}</select></label></div>
            <div className="grid gap-4 sm:grid-cols-2"><label className="field">Division<select name="division_id" required defaultValue={season.divisions[0]?.id}>{season.divisions.filter((item) => item.is_active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label className="field">Venue<select name="venue_id" defaultValue={season.primary_venue?.id ?? ''}><option value="">To be announced</option>{venues.data?.filter((item) => item.status === 'active').map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label></div>
            <div className="grid gap-4 sm:grid-cols-3"><label className="field sm:col-span-2">Date and time<input name="scheduled_at" type="datetime-local" defaultValue={defaultGameTime()} required /></label><label className="field">Estimated minutes<input name="estimated_duration_minutes" type="number" min="30" max="240" defaultValue="90" required /></label></div>
            <label className="field">Round / game label<input name="round" placeholder="Round 1" /></label>
            <label className="flex items-start gap-3 rounded-xl border border-white/10 p-3 text-sm text-slate-300"><input className="mt-1" name="allow_conflicts" type="checkbox" /><span><strong className="block text-white">Allow an intentional conflict</strong>Use only after reviewing the warning for teams or venues.</span></label>
            {addGame.isError && <p className="form-error">{validationMessage(addGame.error)}</p>}
            {addGame.isSuccess && <p className="form-success">Game published to the public schedule.</p>}
            <button className="button button-primary" disabled={addGame.isPending || approvedRegistrations.length < 2}>{addGame.isPending ? 'Publishing…' : 'Publish game'}</button>
            {approvedRegistrations.length < 2 && <p className="text-xs text-amber-300">Approve at least two teams in the same division first.</p>}
          </form>
        </section>}

        <section className="panel"><div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="panel-kicker">Game operations</p><h2 className="panel-title">Published games</h2></div><div className="flex flex-col gap-2 sm:flex-row"><label className="field"><span className="sr-only">Date filter</span><input type="date" value={dateFilter} onChange={(event) => setDateFilter(event.target.value)} /></label><label className="field"><span className="sr-only">Status filter</span><select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}><option value="">All statuses</option>{gameStatuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label></div></div>
          <div className="mt-6 space-y-4">{games.isPending ? <p className="empty-state">Loading games…</p> : games.data?.length ? games.data.map((game) => <article className="rounded-2xl border border-white/10 bg-white/[.03] p-4" key={game.id}>
            <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">{game.round || game.division.name}</p><h3 className="mt-1 text-lg font-black">{game.home_team.name} <span className="text-slate-600">vs</span> {game.away_team.name}</h3><p className="mt-1 text-sm text-slate-400">{new Date(game.scheduled_at).toLocaleString('en-PH')} · {game.venue?.name ?? 'TBA'}</p></div><div className="flex flex-wrap items-center gap-2"><span className="role-pill capitalize">{game.status_label}</span>{canScore && !['cancelled', 'postponed'].includes(game.status) && <Link className="button button-primary px-3 py-2 text-xs" to={`/app/organizations/${organization}/competitions/${competitionSlug}/seasons/${season.slug}/games/${game.id}/live`}>Open scorer</Link>}</div></div>
            {canManage && <details className="mt-4 border-t border-white/10 pt-4"><summary className="cursor-pointer text-sm font-black text-amber-300">Update, reschedule, or finalize</summary><form className="form-stack mt-4" onSubmit={(event) => submitGameUpdate(event, game.id)}>
              <div className="grid gap-3 sm:grid-cols-2"><label className="field">Date and time<input name="scheduled_at" type="datetime-local" defaultValue={localDateTimeValue(game.scheduled_at)} required /></label><label className="field">Venue<select name="venue_id" defaultValue={game.venue?.id ?? ''}><option value="">To be announced</option>{venues.data?.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label></div>
              <div className="grid gap-3 sm:grid-cols-3"><label className="field">Status<select name="status" defaultValue={game.status}>{gameStatuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></label><label className="field">Home score<input name="home_score" type="number" min="0" max="999" defaultValue={game.home_score} /></label><label className="field">Away score<input name="away_score" type="number" min="0" max="999" defaultValue={game.away_score} /></label></div>
              <label className="field">Viewer-facing status explanation<textarea name="status_reason" rows={2} defaultValue={game.status_reason ?? ''} placeholder="Required for delays, suspensions, postponements, and cancellations" /></label>
              <label className="field">Audit reason<textarea name="change_reason" rows={2} required placeholder="Why is this official record changing?" /></label>
              <label className="flex items-center gap-3 text-sm text-slate-300"><input name="allow_conflicts" type="checkbox" />Override a reviewed schedule conflict</label>
              <button className="button button-secondary" disabled={editGame.isPending}>Save official update</button>
            </form></details>}
            {canManage && <details className="mt-4 border-t border-white/10 pt-4"><summary className="cursor-pointer text-sm font-black text-amber-300">Manage livestream</summary><form className="form-stack mt-4" onSubmit={(event) => submitLivestreamUpdate(event, game.id)}>
              <label className="field">Authorized YouTube or Facebook URL<input name="livestream_url" type="url" defaultValue={game.livestream?.watch_url ?? ''} placeholder="https://www.youtube.com/watch?v=…" /></label>
              <div className="grid gap-3 sm:grid-cols-2"><label className="field">Stream status<select name="livestream_status" defaultValue={game.livestream?.status ?? 'scheduled'}>{livestreamStatuses.map((status) => <option key={status} value={status}>{status === 'unavailable' ? 'Hide from viewers' : status}</option>)}</select></label><div className="rounded-xl border border-white/10 p-3 text-xs leading-5 text-slate-400"><strong className="block text-slate-200">Authorized streams only</strong>YouTube plays inside GameCast. Facebook opens on its official page.</div></div>
              <label className="field">Audit reason<textarea name="change_reason" rows={2} required placeholder="Example: Official barangay media stream added." /></label>
              {game.livestream && <a className="text-sm font-bold text-amber-300 hover:text-amber-200" href={game.livestream.watch_url} rel="noreferrer" target="_blank">Open current {game.livestream.provider_label} stream ↗</a>}
              <p className="text-xs text-slate-500">Clear the URL and save to remove the livestream completely.</p>
              <button className="button button-secondary" disabled={editLivestream.isPending}>{editLivestream.isPending ? 'Saving…' : 'Save livestream'}</button>
            </form></details>}
            {game.changes?.length ? <p className="mt-3 text-xs text-slate-500">{game.changes.length} audited change{game.changes.length === 1 ? '' : 's'}</p> : null}
          </article>) : <p className="empty-state">No games match these filters.</p>}</div>
          {(editGame.isError || editLivestream.isError) && <p className="form-error mt-4">{validationMessage(editGame.error ?? editLivestream.error)}</p>}
        </section>
      </div>

      <aside className="space-y-6"><section className="panel"><div className="panel-heading"><div><p className="panel-kicker">Public bulletin</p><h2 className="panel-title">Announcements</h2></div><span className="count-pill">{announcements.data?.length ?? 0}</span></div>
        {canManage && <form className="form-stack mt-5" onSubmit={(event) => { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); addAnnouncement.mutate({ title: String(data.get('title')), body: String(data.get('body')), status: String(data.get('status')) }, { onSuccess: () => form.reset() }) }}><label className="field">Title<input name="title" required placeholder="Schedule update" /></label><label className="field">Message<textarea name="body" rows={4} required /></label><label className="field">Visibility<select name="status" defaultValue="published"><option value="published">Publish now</option><option value="draft">Save draft</option></select></label>{addAnnouncement.isError && <p className="form-error">{validationMessage(addAnnouncement.error)}</p>}<button className="button button-primary" disabled={addAnnouncement.isPending}>Save announcement</button></form>}
        <div className="mt-6 space-y-3 border-t border-white/10 pt-5">{announcements.data?.length ? announcements.data.map((announcement) => <article className="rounded-xl border border-white/10 p-4" key={announcement.id}><div className="flex items-start justify-between gap-3"><div><h3 className="font-black">{announcement.title}</h3><p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-300">{announcement.body}</p></div><span className="role-pill">{announcement.status_label}</span></div>{canManage && <div className="mt-3 flex flex-wrap gap-2"><button className="role-pill" onClick={() => editAnnouncement.mutate({ id: announcement.id, input: { status: announcement.status === 'published' ? 'draft' : 'published' } })}>{announcement.status === 'published' ? 'Unpublish' : 'Publish'}</button><button className="danger-button" onClick={() => removeAnnouncement.mutate(announcement.id)}>Delete</button></div>}</article>) : <p className="empty-state">No announcements yet.</p>}</div>
        {(editAnnouncement.isError || removeAnnouncement.isError) && <p className="form-error mt-4">{validationMessage(editAnnouncement.error ?? removeAnnouncement.error)}</p>}
      </section></aside>
    </div>}
  </main>
}

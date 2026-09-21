import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'

import { validationMessage } from '../api/client'
import { useAuthQuery } from '../features/auth/api'
import {
  createCompetition, createDivision, createSeason, createVenue, deleteDivision,
  getCompetition, listAdministrativeAreas, listCompetitions, listVenues,
  updateCompetition, updateDivision, updateSeason, updateVenue,
  type CompetitionInput, type DivisionInput, type SeasonInput, type VenueInput,
} from '../features/competitions/api'
import { listOrganizations } from '../features/organizations/api'

const competitionStatuses = ['draft', 'active', 'completed', 'archived']
const seasonStatuses = ['registration', 'scheduled', 'active', 'completed', 'archived']
const formats = [['round_robin', 'Round robin'], ['single_elimination', 'Single elimination'], ['double_elimination', 'Double elimination'], ['custom', 'Custom']]

function nullableNumber(value: FormDataEntryValue | null) {
  return value === null || value === '' ? null : Number(value)
}

export function CompetitionManagementPage() {
  const { organization = '' } = useParams()
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const organizations = useQuery({ queryKey: ['organizations'], queryFn: listOrganizations })
  const currentOrganization = organizations.data?.find((item) => item.slug === organization)
  const canManage = Boolean(auth.data?.is_platform_admin || ['owner', 'admin', 'league_manager'].includes(currentOrganization?.role ?? ''))
  const areas = useQuery({ queryKey: ['administrative-areas'], queryFn: listAdministrativeAreas })
  const venues = useQuery({ queryKey: ['venues', organization], queryFn: () => listVenues(organization), enabled: Boolean(organization) })
  const competitions = useQuery({ queryKey: ['competitions', organization], queryFn: () => listCompetitions(organization), enabled: Boolean(organization) })
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null)
  const activeSlug = selectedSlug ?? competitions.data?.[0]?.slug ?? null
  const competition = useQuery({
    queryKey: ['competition', organization, activeSlug],
    queryFn: () => getCompetition(organization, activeSlug!),
    enabled: Boolean(activeSlug),
  })
  const [selectedSeasonSlug, setSelectedSeasonSlug] = useState<string | null>(null)
  const activeSeason = competition.data?.seasons?.find((item) => item.slug === selectedSeasonSlug) ?? competition.data?.seasons?.[0]

  const refreshCompetitions = async () => {
    await queryClient.invalidateQueries({ queryKey: ['competitions', organization] })
    await queryClient.invalidateQueries({ queryKey: ['competition', organization] })
  }
  const refreshVenues = () => queryClient.invalidateQueries({ queryKey: ['venues', organization] })

  const addVenue = useMutation({ mutationFn: (input: VenueInput) => createVenue(organization, input), onSuccess: refreshVenues })
  const editVenue = useMutation({ mutationFn: ({ slug, input }: { slug: string; input: Partial<VenueInput> }) => updateVenue(organization, slug, input), onSuccess: refreshVenues })
  const addCompetition = useMutation({
    mutationFn: (input: CompetitionInput) => createCompetition(organization, input),
    onSuccess: async (created) => { setSelectedSlug(created.slug); setSelectedSeasonSlug(null); await refreshCompetitions() },
  })
  const editCompetition = useMutation({ mutationFn: (input: Partial<CompetitionInput>) => updateCompetition(organization, activeSlug!, input), onSuccess: refreshCompetitions })
  const addSeason = useMutation({ mutationFn: (input: SeasonInput) => createSeason(organization, activeSlug!, input), onSuccess: refreshCompetitions })
  const editSeason = useMutation({ mutationFn: (input: Partial<SeasonInput>) => updateSeason(organization, activeSlug!, activeSeason!.slug, input), onSuccess: refreshCompetitions })
  const addDivision = useMutation({ mutationFn: (input: DivisionInput) => createDivision(organization, activeSlug!, activeSeason!.slug, input), onSuccess: refreshCompetitions })
  const editDivision = useMutation({ mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) => updateDivision(organization, activeSlug!, activeSeason!.slug, id, { is_active }), onSuccess: refreshCompetitions })
  const removeDivision = useMutation({ mutationFn: (id: number) => deleteDivision(organization, activeSlug!, activeSeason!.slug, id), onSuccess: refreshCompetitions })

  function venueInput(form: HTMLFormElement): VenueInput {
    const data = new FormData(form)
    return { name: String(data.get('name')), administrative_area_id: nullableNumber(data.get('administrative_area_id')), address: String(data.get('address')), latitude: nullableNumber(data.get('latitude')), longitude: nullableNumber(data.get('longitude')), status: String(data.get('status')) }
  }
  function competitionInput(form: HTMLFormElement): CompetitionInput {
    const data = new FormData(form)
    return { name: String(data.get('name')), administrative_area_id: nullableNumber(data.get('administrative_area_id')), type: String(data.get('type')), status: String(data.get('status')), description: String(data.get('description')) }
  }
  function seasonInput(form: HTMLFormElement): SeasonInput {
    const data = new FormData(form)
    return { name: String(data.get('name')), primary_venue_id: nullableNumber(data.get('primary_venue_id')), starts_on: String(data.get('starts_on')) || null, ends_on: String(data.get('ends_on')) || null, timezone: String(data.get('timezone')), format: String(data.get('format')), status: String(data.get('status')), max_roster_size: Number(data.get('max_roster_size')), period_count: Number(data.get('period_count')), period_minutes: Number(data.get('period_minutes')), overtime_minutes: Number(data.get('overtime_minutes')), rules_notes: String(data.get('rules_notes')) }
  }

  const error = addVenue.error ?? editVenue.error ?? addCompetition.error ?? editCompetition.error ?? addSeason.error ?? editSeason.error ?? addDivision.error ?? editDivision.error ?? removeDivision.error

  return (
    <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p className="eyebrow">P3 · Competition setup</p><h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{currentOrganization?.name ?? 'Organization'}</h1><p className="mt-2 text-slate-400">Configure where, when, and under which rules your competition runs.</p></div>
        <Link className="button button-secondary" to="/app">← Back to access dashboard</Link>
      </div>
      {error && <p className="form-error mt-6">{validationMessage(error)}</p>}

      <section className="mt-8 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div className="space-y-6">
          <div className="panel">
            <div className="panel-heading"><div><p className="panel-kicker">Competition directory</p><h2 className="panel-title">Leagues and tournaments</h2></div><span className="count-pill">{competitions.data?.length ?? 0}</span></div>
            <div className="mt-5 space-y-2">
              {competitions.isPending && <p className="text-sm text-slate-400">Loading competitions…</p>}
              {competitions.data?.map((item) => <button className={`organization-row ${activeSlug === item.slug ? 'organization-row-active' : ''}`} key={item.id} onClick={() => { setSelectedSlug(item.slug); setSelectedSeasonSlug(null) }}><span><strong className="block text-left">{item.name}</strong><small className="capitalize text-slate-400">{item.type_label} · {item.status_label} · {item.season_count ?? 0} seasons</small></span><span>›</span></button>)}
              {!competitions.isPending && !competitions.data?.length && <p className="empty-state">No competitions yet. Create the pilot competition below.</p>}
            </div>
          </div>

          {canManage && <div className="panel"><p className="panel-kicker">New competition</p><h2 className="panel-title">Create league or tournament</h2>
            <form className="form-stack mt-5" onSubmit={(event) => { event.preventDefault(); const form = event.currentTarget; addCompetition.mutate(competitionInput(form), { onSuccess: () => form.reset() }) }}>
              <label className="field">Name<input name="name" placeholder="Jaen Inter-Purok Basketball" required /></label>
              <div className="grid gap-4 sm:grid-cols-2"><label className="field">Type<select name="type" defaultValue="league"><option value="league">League</option><option value="tournament">Tournament</option></select></label><label className="field">Status<select name="status" defaultValue="draft">{competitionStatuses.map((item) => <option key={item} value={item}>{item}</option>)}</select></label></div>
              <label className="field">Coverage area<select name="administrative_area_id" defaultValue=""><option value="">Not specified</option>{areas.data?.map((area) => <option key={area.id} value={area.id}>{area.path}</option>)}</select></label>
              <label className="field">Description<textarea name="description" rows={3} /></label>
              <button className="button button-primary" disabled={addCompetition.isPending}>{addCompetition.isPending ? 'Creating…' : 'Create competition'}</button>
            </form>
          </div>}

          <div className="panel"><div className="panel-heading"><div><p className="panel-kicker">Venue directory</p><h2 className="panel-title">Courts and gyms</h2></div><span className="count-pill">{venues.data?.length ?? 0}</span></div>
            <div className="mt-5 space-y-3">{venues.data?.map((venue) => <div className="rounded-2xl border border-white/10 p-4" key={venue.id}><div className="flex items-start justify-between gap-3"><div><strong>{venue.name}</strong><p className="mt-1 text-xs text-slate-400">{venue.address || venue.administrative_area?.path || 'Address pending'}</p></div><button className="role-pill capitalize" disabled={!canManage || editVenue.isPending} onClick={() => editVenue.mutate({ slug: venue.slug, input: { status: venue.status === 'active' ? 'inactive' : 'active' } })}>{venue.status}</button></div>{venue.latitude && <a className="mt-2 inline-block text-xs text-amber-400" href={`https://www.google.com/maps?q=${venue.latitude},${venue.longitude}`} target="_blank" rel="noreferrer">Open map coordinates ↗</a>}{canManage && <details className="mt-3 border-t border-white/10 pt-3"><summary className="cursor-pointer text-xs font-bold text-amber-300">Edit venue details</summary><form className="form-stack mt-4" onSubmit={(event) => { event.preventDefault(); editVenue.mutate({ slug: venue.slug, input: venueInput(event.currentTarget) }) }}><label className="field">Venue name<input name="name" defaultValue={venue.name} required /></label><label className="field">Administrative area<select name="administrative_area_id" defaultValue={venue.administrative_area?.id ?? ''}><option value="">Not specified</option>{areas.data?.map((area) => <option key={area.id} value={area.id}>{area.path}</option>)}</select></label><label className="field">Address<input name="address" defaultValue={venue.address ?? ''} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="field">Latitude<input name="latitude" type="number" step="any" defaultValue={venue.latitude ?? ''} /></label><label className="field">Longitude<input name="longitude" type="number" step="any" defaultValue={venue.longitude ?? ''} /></label></div><label className="field">Status<select name="status" defaultValue={venue.status}><option value="active">Active</option><option value="inactive">Inactive</option></select></label><button className="button button-secondary">Save venue</button></form></details>}</div>)}</div>
            {canManage && <form className="form-stack mt-5 border-t border-white/10 pt-5" onSubmit={(event) => { event.preventDefault(); const form = event.currentTarget; addVenue.mutate(venueInput(form), { onSuccess: () => form.reset() }) }}>
              <label className="field">Venue name<input name="name" placeholder="Municipal gym" required /></label><label className="field">Administrative area<select name="administrative_area_id" defaultValue=""><option value="">Not specified</option>{areas.data?.map((area) => <option key={area.id} value={area.id}>{area.path}</option>)}</select></label><label className="field">Address<input name="address" /></label>
              <div className="grid gap-4 sm:grid-cols-2"><label className="field">Latitude<input name="latitude" type="number" step="any" /></label><label className="field">Longitude<input name="longitude" type="number" step="any" /></label></div><input name="status" type="hidden" value="active" /><button className="button button-secondary" disabled={addVenue.isPending}>Add venue</button>
            </form>}
          </div>
        </div>

        <div className="space-y-6">
          {!competition.data ? <section className="panel grid min-h-80 place-items-center text-center"><div><p className="text-5xl">🏆</p><h2 className="mt-4 text-xl font-black">Select a competition</h2><p className="mt-2 text-sm text-slate-400">Season settings and divisions will appear here.</p></div></section> : <>
            <section className="panel"><div className="panel-heading"><div><p className="panel-kicker">Competition identity</p><h2 className="panel-title">{competition.data.name}</h2></div><span className="role-pill capitalize">{competition.data.status_label}</span></div>
              {canManage ? <form className="form-stack mt-5" key={competition.data.slug} onSubmit={(event) => { event.preventDefault(); editCompetition.mutate(competitionInput(event.currentTarget)) }}>
                <label className="field">Name<input name="name" defaultValue={competition.data.name} required /></label><div className="grid gap-4 sm:grid-cols-2"><label className="field">Type<select name="type" defaultValue={competition.data.type}><option value="league">League</option><option value="tournament">Tournament</option></select></label><label className="field">Lifecycle<select name="status" defaultValue={competition.data.status}>{competitionStatuses.map((item) => <option key={item} value={item}>{item}</option>)}</select></label></div><label className="field">Coverage area<select name="administrative_area_id" defaultValue={competition.data.administrative_area?.id ?? ''}><option value="">Not specified</option>{areas.data?.map((area) => <option key={area.id} value={area.id}>{area.path}</option>)}</select></label><label className="field">Description<textarea name="description" rows={3} defaultValue={competition.data.description ?? ''} /></label><button className="button button-secondary" disabled={editCompetition.isPending}>Save competition</button>
              </form> : <p className="mt-5 text-sm text-slate-400">You have read-only access to this configuration.</p>}
            </section>

            <section className="panel"><div className="panel-heading"><div><p className="panel-kicker">Season editions</p><h2 className="panel-title">Rules and calendar</h2></div><span className="count-pill">{competition.data.seasons?.length ?? 0}</span></div>
              <div className="mt-4 flex flex-wrap gap-2">{competition.data.seasons?.map((season) => <button className={`role-pill ${activeSeason?.id === season.id ? 'ring-1 ring-amber-400' : ''}`} key={season.id} onClick={() => setSelectedSeasonSlug(season.slug)}>{season.name}</button>)}</div>
              {activeSeason && canManage && <form className="form-stack mt-5" key={activeSeason.slug} onSubmit={(event) => { event.preventDefault(); editSeason.mutate(seasonInput(event.currentTarget)) }}>
                <label className="field">Season name<input name="name" defaultValue={activeSeason.name} required /></label><div className="grid gap-4 sm:grid-cols-2"><label className="field">Starts<input name="starts_on" type="date" defaultValue={activeSeason.starts_on ?? ''} /></label><label className="field">Ends<input name="ends_on" type="date" defaultValue={activeSeason.ends_on ?? ''} /></label></div>
                <div className="grid gap-4 sm:grid-cols-3"><label className="field">Timezone<input name="timezone" defaultValue={activeSeason.timezone} required /></label><label className="field">Format<select name="format" defaultValue={activeSeason.format}>{formats.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label className="field">Lifecycle<select name="status" defaultValue={activeSeason.status}>{seasonStatuses.map((item) => <option key={item} value={item}>{item}</option>)}</select></label></div>
                <label className="field">Primary venue<select name="primary_venue_id" defaultValue={activeSeason.primary_venue?.id ?? ''}><option value="">Multiple / not set</option>{venues.data?.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}</select></label>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4"><label className="field">Roster max<input name="max_roster_size" type="number" min="1" max="20" defaultValue={activeSeason.max_roster_size} /></label><label className="field">Periods<input name="period_count" type="number" min="1" max="8" defaultValue={activeSeason.period_count} /></label><label className="field">Minutes<input name="period_minutes" type="number" min="1" max="20" defaultValue={activeSeason.period_minutes} /></label><label className="field">Overtime<input name="overtime_minutes" type="number" min="1" max="10" defaultValue={activeSeason.overtime_minutes} /></label></div>
                <label className="field">Rules notes<textarea name="rules_notes" rows={4} defaultValue={activeSeason.rules_notes ?? ''} /></label><button className="button button-secondary" disabled={editSeason.isPending}>Save season settings</button>
              </form>}
              {!activeSeason && canManage && <form className="form-stack mt-5" onSubmit={(event) => { event.preventDefault(); addSeason.mutate(seasonInput(event.currentTarget)) }}><SeasonFields venues={venues.data ?? []} /><button className="button button-primary" disabled={addSeason.isPending}>Create first season</button></form>}
              {activeSeason && canManage && <details className="mt-6 border-t border-white/10 pt-5"><summary className="cursor-pointer text-sm font-black text-amber-300">Add another season edition</summary><form className="form-stack mt-5" onSubmit={(event) => { event.preventDefault(); const form = event.currentTarget; addSeason.mutate(seasonInput(form), { onSuccess: () => form.reset() }) }}><SeasonFields venues={venues.data ?? []} /><button className="button button-primary">Create season</button></form></details>}
            </section>

            {activeSeason && <section className="panel"><div className="panel-heading"><div><p className="panel-kicker">Divisions</p><h2 className="panel-title">Categories for {activeSeason.name}</h2></div><span className="count-pill">{activeSeason.divisions.length}</span></div><div className="mt-5 divide-y divide-white/10">{activeSeason.divisions.map((division) => <div className="flex items-center justify-between gap-4 py-4" key={division.id}><div><strong>{division.name}</strong><p className="mt-1 text-xs capitalize text-slate-400">{division.category_label} · {division.gender}{division.minimum_age || division.maximum_age ? ` · ages ${division.minimum_age ?? 'any'}–${division.maximum_age ?? 'any'}` : ''}</p></div>{canManage && <div className="flex gap-2"><button className="role-pill" onClick={() => editDivision.mutate({ id: division.id, is_active: !division.is_active })}>{division.is_active ? 'Active' : 'Inactive'}</button><button className="danger-button" disabled={activeSeason.divisions.length <= 1} onClick={() => removeDivision.mutate(division.id)}>Remove</button></div>}</div>)}</div>
              {canManage && <form className="mt-5 grid gap-3 border-t border-white/10 pt-5 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); addDivision.mutate({ name: String(data.get('name')), category: String(data.get('category')), gender: String(data.get('gender')), minimum_age: nullableNumber(data.get('minimum_age')), maximum_age: nullableNumber(data.get('maximum_age')), is_active: true }, { onSuccess: () => form.reset() }) }}><label className="field">Division name<input name="name" placeholder="Juniors U18" required /></label><label className="field">Category<select name="category" defaultValue="open"><option value="open">Open</option><option value="seniors">Seniors</option><option value="juniors">Juniors</option><option value="custom">Custom</option></select></label><label className="field">Gender<select name="gender" defaultValue="open"><option value="open">Open</option><option value="male">Male</option><option value="female">Female</option><option value="mixed">Mixed</option></select></label><div className="grid grid-cols-2 gap-3"><label className="field">Min age<input name="minimum_age" type="number" /></label><label className="field">Max age<input name="maximum_age" type="number" /></label></div><button className="button button-secondary sm:col-span-2">Add division</button></form>}
            </section>}
          </>}
        </div>
      </section>
    </main>
  )
}

function SeasonFields({ venues }: { venues: { id: number; name: string }[] }) {
  return <><label className="field">Season name<input name="name" placeholder="2026 Pilot Season" required /></label><div className="grid gap-4 sm:grid-cols-2"><label className="field">Starts<input name="starts_on" type="date" /></label><label className="field">Ends<input name="ends_on" type="date" /></label></div><div className="grid gap-4 sm:grid-cols-3"><label className="field">Timezone<input name="timezone" defaultValue="Asia/Manila" required /></label><label className="field">Format<select name="format" defaultValue="round_robin">{formats.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label className="field">Lifecycle<select name="status" defaultValue="registration">{seasonStatuses.map((item) => <option key={item} value={item}>{item}</option>)}</select></label></div><label className="field">Primary venue<select name="primary_venue_id" defaultValue=""><option value="">Multiple / not set</option>{venues.map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}</select></label><div className="grid grid-cols-2 gap-4 sm:grid-cols-4"><label className="field">Roster max<input name="max_roster_size" type="number" defaultValue="20" min="1" max="20" /></label><label className="field">Periods<input name="period_count" type="number" defaultValue="4" /></label><label className="field">Minutes<input name="period_minutes" type="number" defaultValue="10" /></label><label className="field">Overtime<input name="overtime_minutes" type="number" defaultValue="5" /></label></div><label className="field">Rules notes<textarea name="rules_notes" rows={3} /></label></>
}

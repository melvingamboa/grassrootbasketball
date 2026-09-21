import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { useState } from 'react'
import { Link, useParams } from 'react-router'

import { validationMessage } from '../api/client'
import { useAuthQuery } from '../features/auth/api'
import {
  createBracket,
  createBracketMatch,
  deleteBracket,
  deleteBracketMatch,
  initializeBracket,
  listBrackets,
  listStandings,
  recalculateStandings,
  saveStandings,
  updateBracket,
  updateBracketMatch,
  type BracketMatchInput,
  type BracketMatch,
  type Standing,
  type StandingInput,
} from '../features/competition-control/api'
import { getCompetition, listCompetitions } from '../features/competitions/api'
import { listOrganizations } from '../features/organizations/api'
import {
  listTeamRegistrations,
  type TeamRegistration,
} from '../features/teams/api'

function nullableNumber(value: FormDataEntryValue | null): number | null {
  return value ? Number(value) : null
}

function groupBracketMatches(
  matches: BracketMatch[],
): Record<string, BracketMatch[]> {
  return matches.reduce<Record<string, BracketMatch[]>>(
    (rounds, match) => ({
      ...rounds,
      [String(match.round_number)]: [
        ...(rounds[String(match.round_number)] ?? []),
        match,
      ],
    }),
    {},
  )
}

export function CompetitionControlPage() {
  const { organization = '' } = useParams()
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const organizations = useQuery({
    queryKey: ['organizations'],
    queryFn: listOrganizations,
  })
  const competitions = useQuery({
    queryKey: ['organizations', organization, 'competitions'],
    queryFn: () => listCompetitions(organization),
  })
  const [selectedCompetition, setSelectedCompetition] = useState('')
  const competitionSlug =
    selectedCompetition || competitions.data?.[0]?.slug || ''
  const competition = useQuery({
    queryKey: ['organizations', organization, 'competitions', competitionSlug],
    queryFn: () => getCompetition(organization, competitionSlug),
    enabled: Boolean(competitionSlug),
  })
  const [selectedSeason, setSelectedSeason] = useState('')
  const season =
    competition.data?.seasons?.find((item) => item.slug === selectedSeason) ??
    competition.data?.seasons?.[0]
  const [selectedDivision, setSelectedDivision] = useState<number | null>(null)
  const division =
    season?.divisions.find((item) => item.id === selectedDivision) ??
    season?.divisions[0]
  const registrations = useQuery({
    queryKey: [
      'team-registrations',
      organization,
      competitionSlug,
      season?.slug,
    ],
    queryFn: () =>
      listTeamRegistrations(organization, competitionSlug, season!.slug),
    enabled: Boolean(season),
  })
  const standings = useQuery({
    queryKey: [
      'standings',
      organization,
      competitionSlug,
      season?.slug,
      division?.id,
    ],
    queryFn: () =>
      listStandings(organization, competitionSlug, season!.slug, division!.id),
    enabled: Boolean(season && division),
  })
  const brackets = useQuery({
    queryKey: ['brackets', organization, competitionSlug, season?.slug],
    queryFn: () => listBrackets(organization, competitionSlug, season!.slug),
    enabled: Boolean(season),
  })
  const membership = organizations.data?.find(
    (item) => item.slug === organization,
  )
  const canManage = Boolean(
    auth.data?.is_platform_admin ||
    (membership &&
      ['owner', 'admin', 'league_manager'].includes(membership.role)),
  )
  const divisionTeams =
    registrations.data?.filter(
      (item) => item.status === 'approved' && item.division.id === division?.id,
    ) ?? []

  const refreshStandings = () =>
    queryClient.invalidateQueries({
      queryKey: ['standings', organization, competitionSlug, season?.slug],
    })
  const refreshBrackets = () =>
    queryClient.invalidateQueries({
      queryKey: ['brackets', organization, competitionSlug, season?.slug],
    })
  const saveTable = useMutation({
    mutationFn: (rows: StandingInput[]) =>
      saveStandings(
        organization,
        competitionSlug,
        season!.slug,
        division!.id,
        rows,
      ),
    onSuccess: refreshStandings,
  })
  const recalculateTable = useMutation({
    mutationFn: () =>
      recalculateStandings(
        organization,
        competitionSlug,
        season!.slug,
        division!.id,
      ),
    onSuccess: refreshStandings,
  })
  const addBracket = useMutation({
    mutationFn: (input: {
      division_id: number
      name: string
      status: string
      template: 'empty' | 'single_elimination_4' | 'single_elimination_8'
    }) => createBracket(organization, competitionSlug, season!.slug, input),
    onSuccess: refreshBrackets,
  })
  const initializeBracketLayout = useMutation({
    mutationFn: ({
      bracketId,
      template,
    }: {
      bracketId: number
      template: 'single_elimination_4' | 'single_elimination_8'
    }) =>
      initializeBracket(
        organization,
        competitionSlug,
        season!.slug,
        bracketId,
        template,
      ),
    onSuccess: refreshBrackets,
  })
  const editBracket = useMutation({
    mutationFn: ({
      id,
      input,
    }: {
      id: number
      input: Partial<{ name: string; status: string }>
    }) => updateBracket(organization, competitionSlug, season!.slug, id, input),
    onSuccess: refreshBrackets,
  })
  const removeBracket = useMutation({
    mutationFn: (id: number) =>
      deleteBracket(organization, competitionSlug, season!.slug, id),
    onSuccess: refreshBrackets,
  })
  const addMatch = useMutation({
    mutationFn: ({
      bracketId,
      input,
    }: {
      bracketId: number
      input: BracketMatchInput
    }) =>
      createBracketMatch(
        organization,
        competitionSlug,
        season!.slug,
        bracketId,
        input,
      ),
    onSuccess: refreshBrackets,
  })
  const editMatch = useMutation({
    mutationFn: ({
      bracketId,
      matchId,
      input,
    }: {
      bracketId: number
      matchId: number
      input: Partial<BracketMatchInput>
    }) =>
      updateBracketMatch(
        organization,
        competitionSlug,
        season!.slug,
        bracketId,
        matchId,
        input,
      ),
    onSuccess: refreshBrackets,
  })
  const removeMatch = useMutation({
    mutationFn: ({
      bracketId,
      matchId,
    }: {
      bracketId: number
      matchId: number
    }) =>
      deleteBracketMatch(
        organization,
        competitionSlug,
        season!.slug,
        bracketId,
        matchId,
      ),
    onSuccess: refreshBrackets,
  })
  const mutationError =
    saveTable.error ??
    recalculateTable.error ??
    addBracket.error ??
    initializeBracketLayout.error ??
    editBracket.error ??
    removeBracket.error ??
    addMatch.error ??
    editMatch.error ??
    removeMatch.error

  function bracketMatchInput(form: HTMLFormElement): BracketMatchInput {
    const data = new FormData(form)
    return {
      round_number: Number(data.get('round_number')),
      match_number: Number(data.get('match_number')),
      round_label: String(data.get('round_label')),
      home_team_registration_id: nullableNumber(
        data.get('home_team_registration_id'),
      ),
      away_team_registration_id: nullableNumber(
        data.get('away_team_registration_id'),
      ),
      winner_team_registration_id: nullableNumber(
        data.get('winner_team_registration_id'),
      ),
      game_id: null,
    }
  }

  if (competitions.isPending)
    return (
      <main className="grid min-h-[70vh] place-items-center text-slate-300">
        Loading competition control…
      </main>
    )

  return (
    <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <Link className="text-sm font-bold text-amber-300" to="/app">
            ← Dashboard
          </Link>
          <p className="eyebrow mt-5">P6 · Competition control</p>
          <h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
            Standings and brackets
          </h1>
          <p className="mt-2 max-w-2xl text-slate-400">
            Review automatically calculated standings and publish the playoff
            path. Bracket advancement remains organizer-managed.
          </p>
        </div>
        {season && (
          <Link
            className="button button-secondary"
            to={`/organizations/${organization}/competitions/${competitionSlug}/seasons/${season.slug}/standings`}
          >
            View public standings
          </Link>
        )}
      </div>

      <section className="panel mt-8 grid gap-4 md:grid-cols-3">
        <label className="field">
          Competition
          <select
            value={competitionSlug}
            onChange={(event) => {
              setSelectedCompetition(event.target.value)
              setSelectedSeason('')
              setSelectedDivision(null)
            }}
          >
            {competitions.data?.map((item) => (
              <option key={item.id} value={item.slug}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          Season
          <select
            value={season?.slug ?? ''}
            onChange={(event) => {
              setSelectedSeason(event.target.value)
              setSelectedDivision(null)
            }}
          >
            {competition.data?.seasons?.map((item) => (
              <option key={item.id} value={item.slug}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          Division
          <select
            value={division?.id ?? ''}
            onChange={(event) =>
              setSelectedDivision(Number(event.target.value))
            }
          >
            {season?.divisions.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
      </section>

      {!season || !division ? (
        <section className="panel mt-6">
          <p className="empty-state">
            Create a season and division before managing standings.
          </p>
        </section>
      ) : (
        <>
          <section className="panel mt-6">
            <div className="panel-heading">
              <div>
                <p className="panel-kicker">Official table · Automatic</p>
                <h2 className="panel-title">{division.name} standings</h2>
              </div>
              <span className="count-pill">{divisionTeams.length} teams</span>
            </div>
            <p className="mt-3 rounded-xl border border-blue-400/20 bg-blue-400/10 p-3 text-sm leading-6 text-blue-100">
              Final game results automatically calculate GP, W, L, PF, PA, and
              rank. Ties are ordered by win percentage, games played, point
              difference, points scored, then team name.
            </p>
            {standings.isPending || registrations.isPending ? (
              <p className="empty-state mt-5">Loading standings…</p>
            ) : divisionTeams.length ? (
              <StandingsForm
                key={`${division.id}-${standings.dataUpdatedAt}-${divisionTeams.map((team) => team.id).join('-')}`}
                canManage={canManage}
                teams={divisionTeams}
                standings={standings.data ?? []}
                pending={saveTable.isPending || recalculateTable.isPending}
                onSubmit={(rows) => saveTable.mutate(rows)}
                onRecalculate={() => recalculateTable.mutate()}
              />
            ) : (
              <p className="empty-state mt-5">
                Approve teams in this division before creating its standings.
              </p>
            )}
            {saveTable.isSuccess && (
              <p className="form-success mt-4">
                Qualification status and notes saved.
              </p>
            )}
            {recalculateTable.isSuccess && (
              <p className="form-success mt-4">
                Standings recalculated from final game results.
              </p>
            )}
          </section>

          <section className="panel mt-6">
            <div className="panel-heading">
              <div>
                <p className="panel-kicker">Playoff path · Manual</p>
                <h2 className="panel-title">Tournament brackets</h2>
              </div>
              <span className="count-pill">
                {brackets.data?.filter(
                  (item) => item.division.id === division.id,
                ).length ?? 0}
              </span>
            </div>
            {canManage && (
              <form
                className="mt-5 grid gap-3 md:grid-cols-2 lg:grid-cols-[1fr_220px_160px_auto]"
                onSubmit={(event) => {
                  event.preventDefault()
                  const form = event.currentTarget
                  const data = new FormData(form)
                  addBracket.mutate(
                    {
                      division_id: division.id,
                      name: String(data.get('name')),
                      status: String(data.get('status')),
                      template: String(data.get('template')) as
                        | 'empty'
                        | 'single_elimination_4'
                        | 'single_elimination_8',
                    },
                    { onSuccess: () => form.reset() },
                  )
                }}
              >
                <label className="field">
                  <span className="sr-only">Bracket name</span>
                  <input
                    name="name"
                    required
                    placeholder="Championship playoffs"
                  />
                </label>
                <label className="field">
                  <span className="sr-only">Bracket layout</span>
                  <select name="template" defaultValue="single_elimination_8">
                    <option value="single_elimination_4">
                      Final Four · 4-team elimination
                    </option>
                    <option value="single_elimination_8">
                      8-team single elimination
                    </option>
                    <option value="empty">Empty bracket</option>
                  </select>
                </label>
                <label className="field">
                  <span className="sr-only">Visibility</span>
                  <select name="status" defaultValue="draft">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </select>
                </label>
                <button
                  className="button button-primary"
                  disabled={addBracket.isPending}
                >
                  Create bracket
                </button>
              </form>
            )}

            <div className="mt-6 space-y-5">
              {brackets.isPending ? (
                <p className="empty-state">Loading brackets…</p>
              ) : (
                brackets.data
                  ?.filter((item) => item.division.id === division.id)
                  .map((bracket) => (
                    <article
                      className="rounded-2xl border border-white/10 bg-white/[.025] p-4"
                      key={bracket.id}
                    >
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                          <h3 className="text-lg font-black">{bracket.name}</h3>
                          <p className="text-xs text-slate-400">
                            {bracket.matches.length} match slots ·{' '}
                            {bracket.status_label}
                          </p>
                        </div>
                        {canManage && (
                          <div className="flex flex-wrap gap-2">
                            {bracket.matches.length === 0 && (
                              <button
                                className="button button-secondary"
                                type="button"
                                disabled={initializeBracketLayout.isPending}
                                onClick={() =>
                                  initializeBracketLayout.mutate({
                                    bracketId: bracket.id,
                                    template: 'single_elimination_4',
                                  })
                                }
                              >
                                Initialize Final Four
                              </button>
                            )}
                            {bracket.matches.length < 7 && (
                              <button
                                className="button button-secondary"
                                type="button"
                                disabled={initializeBracketLayout.isPending}
                                onClick={() =>
                                  initializeBracketLayout.mutate({
                                    bracketId: bracket.id,
                                    template: 'single_elimination_8',
                                  })
                                }
                              >
                                Complete 8-team layout
                              </button>
                            )}
                            <select
                              className="compact-select"
                              value={bracket.status}
                              onChange={(event) =>
                                editBracket.mutate({
                                  id: bracket.id,
                                  input: { status: event.target.value },
                                })
                              }
                            >
                              <option value="draft">Draft</option>
                              <option value="published">Published</option>
                              <option value="archived">Archived</option>
                            </select>
                            <button
                              className="danger-button"
                              onClick={() => {
                                if (
                                  window.confirm(
                                    `Delete ${bracket.name} and all its match slots?`,
                                  )
                                )
                                  removeBracket.mutate(bracket.id)
                              }}
                            >
                              Delete
                            </button>
                          </div>
                        )}
                      </div>
                      {bracket.matches.length ? (
                        <div className="mt-4 overflow-x-auto">
                          <div className="flex min-w-max gap-4">
                            {Object.entries(
                              groupBracketMatches(bracket.matches),
                            ).map(([round, matches]) => (
                              <div className="w-72 space-y-3" key={round}>
                                <p className="text-xs font-black uppercase tracking-wider text-amber-300">
                                  {matches[0]?.round_label}
                                </p>
                                {matches.map((match) => (
                                  <div
                                    className="rounded-xl border border-white/10 bg-slate-950/60 p-3"
                                    key={match.id}
                                  >
                                    <p className="text-[11px] font-bold uppercase text-slate-500">
                                      Match {match.match_number}
                                    </p>
                                    <p
                                      className={`mt-2 text-sm font-bold ${match.winner_team?.registration_id === match.home_team?.registration_id ? 'text-emerald-300' : ''}`}
                                    >
                                      {match.home_team?.name ?? 'To be decided'}
                                    </p>
                                    <p
                                      className={`mt-1 text-sm font-bold ${match.winner_team?.registration_id === match.away_team?.registration_id ? 'text-emerald-300' : ''}`}
                                    >
                                      {match.away_team?.name ?? 'To be decided'}
                                    </p>
                                    {canManage && (
                                      <details className="mt-3 border-t border-white/10 pt-3">
                                        <summary className="cursor-pointer text-xs font-bold text-amber-300">
                                          Edit match
                                        </summary>
                                        <MatchForm
                                          teams={divisionTeams}
                                          defaults={match}
                                          submitLabel="Save match"
                                          onSubmit={(event) => {
                                            const form = event.currentTarget
                                            editMatch.mutate({
                                              bracketId: bracket.id,
                                              matchId: match.id,
                                              input: bracketMatchInput(form),
                                            })
                                          }}
                                        />
                                        <button
                                          className="danger-button mt-3"
                                          onClick={() =>
                                            removeMatch.mutate({
                                              bracketId: bracket.id,
                                              matchId: match.id,
                                            })
                                          }
                                        >
                                          Remove slot
                                        </button>
                                      </details>
                                    )}
                                  </div>
                                ))}
                              </div>
                            ))}
                          </div>
                        </div>
                      ) : (
                        <p className="empty-state mt-4">
                          Add semifinal, final, or other match slots below.
                        </p>
                      )}
                      {canManage && (
                        <details className="mt-4 border-t border-white/10 pt-4">
                          <summary className="cursor-pointer text-sm font-black text-amber-300">
                            Add match slot
                          </summary>
                          <MatchForm
                            teams={divisionTeams}
                            submitLabel="Add match"
                            onSubmit={(event) => {
                              const form = event.currentTarget
                              addMatch.mutate(
                                {
                                  bracketId: bracket.id,
                                  input: bracketMatchInput(form),
                                },
                                { onSuccess: () => form.reset() },
                              )
                            }}
                          />
                        </details>
                      )}
                    </article>
                  ))
              )}
            </div>
          </section>
        </>
      )}
      {mutationError && (
        <p className="form-error mt-6">{validationMessage(mutationError)}</p>
      )}
    </main>
  )
}

function StandingsForm({
  canManage,
  teams,
  standings,
  pending,
  onSubmit,
  onRecalculate,
}: {
  canManage: boolean
  teams: TeamRegistration[]
  standings: Standing[]
  pending: boolean
  onSubmit: (rows: StandingInput[]) => void
  onRecalculate: () => void
}) {
  const existing = new Map(
    standings.map((row) => [row.team_registration_id, row]),
  )
  const rankedTeams = [...teams].sort((first, second) => {
    const firstRank = existing.get(first.id)?.rank ?? Number.MAX_SAFE_INTEGER
    const secondRank = existing.get(second.id)?.rank ?? Number.MAX_SAFE_INTEGER

    return (
      firstRank - secondRank || first.team.name.localeCompare(second.team.name)
    )
  })

  return (
    <form
      className="mt-5"
      onSubmit={(event) => {
        event.preventDefault()
        const data = new FormData(event.currentTarget)
        onSubmit(
          rankedTeams.map((team) => ({
            team_registration_id: team.id,
            qualification_status: String(data.get(`status-${team.id}`)),
            notes: String(data.get(`notes-${team.id}`)) || null,
          })),
        )
      }}
    >
      <div className="overflow-x-auto">
        <table className="w-full min-w-[920px] text-left text-sm">
          <thead className="border-b border-white/10 text-xs uppercase tracking-wider text-slate-500">
            <tr>
              <th className="p-3">Rank</th>
              <th className="p-3">Team</th>
              <th className="p-3">GP</th>
              <th className="p-3">W</th>
              <th className="p-3">L</th>
              <th className="p-3">PF</th>
              <th className="p-3">PA</th>
              <th className="p-3">Diff</th>
              <th className="p-3">Status</th>
              <th className="p-3">Note</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/10">
            {rankedTeams.map((team, index) => {
              const row = existing.get(team.id)
              return (
                <tr key={team.id}>
                  <td className="p-2">
                    <strong className="block w-7 text-center text-lg text-amber-300">
                      {row?.rank ?? index + 1}
                    </strong>
                  </td>
                  <td className="p-3 font-black">{team.team.name}</td>
                  <td className="p-3 font-bold">{row?.played ?? 0}</td>
                  <td className="p-3 font-bold">{row?.wins ?? 0}</td>
                  <td className="p-3 font-bold">{row?.losses ?? 0}</td>
                  <td className="p-3 font-bold">{row?.points_for ?? 0}</td>
                  <td className="p-3 font-bold">{row?.points_against ?? 0}</td>
                  <td className="p-3 font-bold">
                    {row?.point_difference ?? 0}
                  </td>
                  <td className="p-2">
                    <select
                      className="compact-select"
                      aria-label={`${team.team.name} qualification status`}
                      name={`status-${team.id}`}
                      defaultValue={row?.qualification_status ?? 'pending'}
                      disabled={!canManage}
                    >
                      <option value="pending">Pending</option>
                      <option value="qualified">Qualified</option>
                      <option value="eliminated">Eliminated</option>
                    </select>
                  </td>
                  <td className="p-2">
                    <input
                      className="compact-select w-40"
                      aria-label={`${team.team.name} note`}
                      name={`notes-${team.id}`}
                      defaultValue={row?.notes ?? ''}
                      disabled={!canManage}
                      placeholder="Optional"
                    />
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
      {canManage && (
        <div className="mt-5 flex flex-wrap gap-3">
          <button className="button button-primary" disabled={pending}>
            {pending ? 'Saving…' : 'Save qualification and notes'}
          </button>
          <button
            className="button button-secondary"
            type="button"
            disabled={pending}
            onClick={onRecalculate}
          >
            Recalculate standings
          </button>
        </div>
      )}
    </form>
  )
}

function MatchForm({
  teams,
  defaults,
  submitLabel,
  onSubmit,
}: {
  teams: TeamRegistration[]
  defaults?: {
    round_number: number
    match_number: number
    round_label: string
    home_team: { registration_id: number } | null
    away_team: { registration_id: number } | null
    winner_team: { registration_id: number } | null
  }
  submitLabel: string
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}) {
  return (
    <form
      className="form-stack mt-4"
      onSubmit={(event) => {
        event.preventDefault()
        onSubmit(event)
      }}
    >
      <div className="grid gap-3 sm:grid-cols-3">
        <label className="field">
          Round #
          <input
            name="round_number"
            type="number"
            min="1"
            defaultValue={defaults?.round_number ?? 1}
            required
          />
        </label>
        <label className="field">
          Match #
          <input
            name="match_number"
            type="number"
            min="1"
            defaultValue={defaults?.match_number ?? 1}
            required
          />
        </label>
        <label className="field">
          Round label
          <input
            name="round_label"
            defaultValue={defaults?.round_label ?? 'Semifinal'}
            required
          />
        </label>
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <TeamSelect
          label="Team one"
          name="home_team_registration_id"
          teams={teams}
          value={defaults?.home_team?.registration_id}
        />
        <TeamSelect
          label="Team two"
          name="away_team_registration_id"
          teams={teams}
          value={defaults?.away_team?.registration_id}
        />
        <TeamSelect
          label="Winner"
          name="winner_team_registration_id"
          teams={teams}
          value={defaults?.winner_team?.registration_id}
        />
      </div>
      <button className="button button-secondary">{submitLabel}</button>
    </form>
  )
}

function TeamSelect({
  label,
  name,
  teams,
  value,
}: {
  label: string
  name: string
  teams: TeamRegistration[]
  value?: number
}) {
  return (
    <label className="field">
      {label}
      <select name={name} defaultValue={value ?? ''}>
        <option value="">To be decided</option>
        {teams.map((team) => (
          <option key={team.id} value={team.id}>
            {team.team.name}
          </option>
        ))}
      </select>
    </label>
  )
}

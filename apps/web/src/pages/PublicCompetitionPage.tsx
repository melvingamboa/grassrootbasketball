import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router'

import type {
  Bracket,
  BracketMatch,
  Standing,
} from '../features/competition-control/api'
import type { Game, PublicCompetition } from '../features/games/api'
import { getPublicCompetition, listPublicGames } from '../features/games/api'
import { realtime } from '../realtime/echo'

type PublicView =
  'overview' | 'today' | 'schedule' | 'results' | 'standings' | 'bracket'

const statusStyles: Record<string, string> = {
  live: 'border-red-400/40 bg-red-500/15 text-red-200',
  final: 'border-slate-400/30 bg-slate-500/10 text-slate-200',
  scheduled: 'border-blue-400/30 bg-blue-500/10 text-blue-200',
  delayed: 'border-amber-400/30 bg-amber-500/10 text-amber-200',
  suspended: 'border-orange-400/30 bg-orange-500/10 text-orange-200',
  postponed: 'border-violet-400/30 bg-violet-500/10 text-violet-200',
  cancelled: 'border-red-400/30 bg-red-500/10 text-red-200',
}

function leagueDate(timezone: string): string {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
    .formatToParts(new Date())
    .reduce<Record<string, string>>(
      (result, part) => ({ ...result, [part.type]: part.value }),
      {},
    )
  return `${parts.year}-${parts.month}-${parts.day}`
}

function gameTime(game: Game, timezone: string): string {
  return new Intl.DateTimeFormat('en-PH', {
    timeZone: timezone,
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(game.scheduled_at))
}

function GameCard({
  game,
  timezone,
  href,
}: {
  game: Game
  timezone: string
  href: string
}) {
  const showScore =
    game.status === 'final' ||
    game.status === 'live' ||
    game.home_score > 0 ||
    game.away_score > 0

  return (
    <Link
      className="block rounded-2xl border border-white/10 bg-white/[.035] p-4 transition hover:border-amber-400/30 hover:bg-white/[.06]"
      to={href}
    >
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs font-bold uppercase tracking-[.14em] text-slate-400">
          {game.round || game.division.name}
        </p>
        <span
          className={`rounded-full border px-2.5 py-1 text-[11px] font-black uppercase ${statusStyles[game.status] ?? statusStyles.scheduled}`}
        >
          {game.status_label}
        </span>
      </div>
      <div className="mt-4 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
        <div>
          <span
            className="mx-auto grid h-11 w-11 place-items-center rounded-xl text-xs font-black"
            style={{
              background: game.home_team.primary_color,
              color: game.home_team.secondary_color,
            }}
          >
            {(game.home_team.short_name || game.home_team.name).slice(0, 3)}
          </span>
          <strong className="mt-2 block text-sm">{game.home_team.name}</strong>
        </div>
        <div>
          {showScore ? (
            <p className="text-3xl font-black tabular-nums">
              {game.home_score}
              <span className="px-2 text-slate-600">–</span>
              {game.away_score}
            </p>
          ) : (
            <p className="text-xs font-black uppercase tracking-wider text-slate-500">
              vs
            </p>
          )}
        </div>
        <div>
          <span
            className="mx-auto grid h-11 w-11 place-items-center rounded-xl text-xs font-black"
            style={{
              background: game.away_team.primary_color,
              color: game.away_team.secondary_color,
            }}
          >
            {(game.away_team.short_name || game.away_team.name).slice(0, 3)}
          </span>
          <strong className="mt-2 block text-sm">{game.away_team.name}</strong>
        </div>
      </div>
      <div className="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-white/10 pt-3 text-xs text-slate-400">
        <span>{gameTime(game, timezone)}</span>
        <span>{game.venue?.name ?? 'Venue to be announced'}</span>
      </div>
      {game.status_reason && (
        <p className="mt-3 rounded-xl bg-amber-400/10 px-3 py-2 text-xs leading-5 text-amber-100">
          {game.status_reason}
        </p>
      )}
    </Link>
  )
}

function StandingTable({ rows }: { rows: Standing[] }) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-white/10">
      <table className="w-full min-w-[650px] text-left text-sm">
        <thead className="bg-white/[.04] text-xs uppercase tracking-wider text-slate-500">
          <tr>
            <th className="p-3">Rank</th>
            <th className="p-3">Team</th>
            <th className="p-3 text-center">GP</th>
            <th className="p-3 text-center">W</th>
            <th className="p-3 text-center">L</th>
            <th className="p-3 text-center">PF</th>
            <th className="p-3 text-center">PA</th>
            <th className="p-3 text-center">Diff</th>
            <th className="p-3">Status</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-white/10">
          {rows.map((row) => (
            <tr className="bg-white/[.02]" key={row.id}>
              <td className="p-3 text-lg font-black text-amber-300">
                {row.rank}
              </td>
              <td className="p-3">
                <div className="flex items-center gap-3">
                  <span
                    className="grid h-9 w-9 place-items-center rounded-lg text-xs font-black"
                    style={{
                      background: row.team.primary_color,
                      color: row.team.secondary_color,
                    }}
                  >
                    {(row.team.short_name || row.team.name).slice(0, 3)}
                  </span>
                  <div>
                    <strong>{row.team.name}</strong>
                    {row.notes && (
                      <p className="text-xs text-slate-500">{row.notes}</p>
                    )}
                  </div>
                </div>
              </td>
              <td className="p-3 text-center tabular-nums">{row.played}</td>
              <td className="p-3 text-center font-bold tabular-nums">
                {row.wins}
              </td>
              <td className="p-3 text-center tabular-nums">{row.losses}</td>
              <td className="p-3 text-center tabular-nums">{row.points_for}</td>
              <td className="p-3 text-center tabular-nums">
                {row.points_against}
              </td>
              <td
                className={`p-3 text-center font-bold tabular-nums ${row.point_difference > 0 ? 'text-emerald-300' : row.point_difference < 0 ? 'text-red-300' : ''}`}
              >
                {row.point_difference > 0 ? '+' : ''}
                {row.point_difference}
              </td>
              <td className="p-3">
                <span className="role-pill">
                  {row.qualification_status_label}
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function groupMatches(matches: BracketMatch[]): Record<string, BracketMatch[]> {
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

function BracketBoard({
  bracket,
  standings,
}: {
  bracket: Bracket
  standings: Standing[]
}) {
  const rounds = Object.entries(groupMatches(bracket.matches)).sort(
    ([first], [second]) => Number(first) - Number(second),
  )
  const ranks = new Map(
    standings
      .filter((standing) => standing.division.id === bracket.division.id)
      .map((standing) => [standing.team_registration_id, standing.rank]),
  )

  return (
    <article className="rounded-2xl border border-white/10 bg-white/[.025] p-4 sm:p-5">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 className="text-xl font-black">{bracket.name}</h3>
          <p className="mt-1 text-sm text-slate-400">{bracket.division.name}</p>
        </div>
        <span className="role-pill">Official bracket</span>
      </div>
      {bracket.matches.length ? (
        <div className="mt-5 overflow-x-auto pb-3">
          <div className="bracket-tree">
            {rounds.map(([round, matches], roundIndex) => (
              <section className="bracket-round" key={round}>
                <h4 className="text-center text-xs font-black uppercase tracking-[.18em] text-amber-300">
                  {matches[0]?.round_label}
                </h4>
                <p className="mt-1 text-center text-[10px] font-bold uppercase tracking-wider text-slate-600">
                  {roundIndex === rounds.length - 1
                    ? 'Championship game'
                    : `${matches.length} match${matches.length === 1 ? '' : 'es'}`}
                </p>
                <div className="bracket-round-matches">
                  {matches.map((match) => (
                    <div
                      className={`bracket-match-slot ${roundIndex > 0 ? 'has-previous' : ''} ${roundIndex < rounds.length - 1 ? 'has-next' : ''}`}
                      key={match.id}
                    >
                      <div className="w-full overflow-hidden rounded-xl border border-white/15 bg-slate-950/90 shadow-lg shadow-black/20">
                        <p className="border-b border-white/10 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                          Match {match.match_number}
                        </p>
                        {[match.home_team, match.away_team].map(
                          (team, index) => {
                            const isWinner =
                              team?.registration_id ===
                              match.winner_team?.registration_id
                            const rank = team
                              ? ranks.get(team.registration_id)
                              : null

                            return (
                              <div
                                className={`flex min-h-12 items-center gap-3 border-white/10 px-3 py-2 ${index === 1 ? 'border-t' : ''} ${isWinner ? 'bg-emerald-400/15 text-emerald-200' : 'bg-white/[.035]'}`}
                                key={team?.registration_id ?? index}
                              >
                                <span
                                  className={`w-7 shrink-0 text-center text-sm font-black ${rank ? 'text-amber-300' : 'text-slate-700'}`}
                                  aria-label={
                                    rank ? `Standing rank ${rank}` : undefined
                                  }
                                >
                                  {rank ? `#${rank}` : '—'}
                                </span>
                                {team ? (
                                  team.logo_url ? (
                                    <img
                                      alt=""
                                      className="h-8 w-8 rounded-lg object-cover"
                                      src={team.logo_url}
                                    />
                                  ) : (
                                    <span
                                      className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[10px] font-black"
                                      style={{
                                        background: team.primary_color,
                                        color: team.secondary_color,
                                      }}
                                    >
                                      {(team.short_name || team.name).slice(
                                        0,
                                        3,
                                      )}
                                    </span>
                                  )
                                ) : (
                                  <span className="grid h-8 w-8 place-items-center rounded-lg border border-dashed border-white/15 text-xs text-slate-600">
                                    ?
                                  </span>
                                )}
                                <strong className="min-w-0 flex-1 truncate text-sm">
                                  {team?.name ?? 'To be decided'}
                                </strong>
                                {isWinner && (
                                  <span
                                    aria-label="Winner"
                                    className="text-emerald-300"
                                  >
                                    ✓
                                  </span>
                                )}
                              </div>
                            )
                          },
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </section>
            ))}
          </div>
        </div>
      ) : (
        <p className="empty-state mt-5">
          Matchups will appear after the organizer publishes them.
        </p>
      )}
    </article>
  )
}

export function PublicCompetitionPage({
  view = 'overview',
}: {
  view?: PublicView
}) {
  const { organization = '', competition = '', season = '' } = useParams()
  const queryClient = useQueryClient()
  const overview = useQuery({
    queryKey: ['public-competition', organization, competition, season],
    queryFn: () => getPublicCompetition(organization, competition, season),
    retry: false,
    refetchInterval: 5_000,
  })
  const [date, setDate] = useState('')
  const timezone = overview.data?.season.timezone ?? 'Asia/Manila'
  const filters =
    view === 'today'
      ? { date: leagueDate(timezone) }
      : view === 'results'
        ? { status: 'final' }
        : date
          ? { date }
          : {}
  const isGamesView =
    view === 'today' || view === 'schedule' || view === 'results'
  const games = useQuery({
    queryKey: [
      'public-games',
      organization,
      competition,
      season,
      view,
      filters,
    ],
    queryFn: () => listPublicGames(organization, competition, season, filters),
    enabled: isGamesView && Boolean(overview.data),
    retry: false,
    refetchInterval: 5_000,
  })
  const basePath = `/organizations/${organization}/competitions/${competition}/seasons/${season}`
  const groupedGames = useMemo(
    () =>
      (games.data ?? []).reduce<Record<string, Game[]>>((groups, game) => {
        const key = new Intl.DateTimeFormat('en-PH', {
          timeZone: timezone,
          weekday: 'long',
          month: 'long',
          day: 'numeric',
        }).format(new Date(game.scheduled_at))
        return { ...groups, [key]: [...(groups[key] ?? []), game] }
      }, {}),
    [games.data, timezone],
  )

  useEffect(() => {
    const echo = realtime()
    if (!echo) return

    const visibleGames = isGamesView
      ? games.data ?? []
      : [
          ...(overview.data?.live_games ?? []),
          ...(overview.data?.upcoming_games ?? []),
          ...(overview.data?.recent_results ?? []),
        ]
    const liveGameIds = [...new Set(
      visibleGames
        .filter((item) => ['live', 'delayed', 'suspended'].includes(item.status))
        .map((item) => item.id),
    )]

    liveGameIds.forEach((gameId) => {
      echo.channel(`games.${gameId}`).listen('.game.updated', (payload: { game: Game }) => {
        queryClient.setQueriesData<Game[]>(
          { queryKey: ['public-games', organization, competition, season] },
          (current) => current?.map((item) => item.id === payload.game.id ? payload.game : item),
        )
        queryClient.setQueryData<PublicCompetition>(
          ['public-competition', organization, competition, season],
          (current) => current ? {
            ...current,
            live_games: current.live_games.map((item) => item.id === payload.game.id ? payload.game : item),
            upcoming_games: current.upcoming_games.map((item) => item.id === payload.game.id ? payload.game : item),
            recent_results: current.recent_results.map((item) => item.id === payload.game.id ? payload.game : item),
          } : current,
        )
      })
    })

    return () => liveGameIds.forEach((gameId) => echo.leave(`games.${gameId}`))
  }, [competition, games.data, isGamesView, organization, overview.data, queryClient, season])

  if (overview.isPending)
    return (
      <main className="grid min-h-[70vh] place-items-center text-slate-300">
        Loading league…
      </main>
    )
  if (!overview.data)
    return (
      <main className="grid min-h-[70vh] place-items-center px-4 text-center">
        <div>
          <h1 className="text-3xl font-black">League unavailable</h1>
          <Link className="button button-secondary mt-5" to="/">
            Return home
          </Link>
        </div>
      </main>
    )

  const data = overview.data
  const tabs: [PublicView, string][] = [
    ['overview', 'Home'],
    ['today', "Today's games"],
    ['schedule', 'Schedule'],
    ['results', 'Results'],
    ['standings', 'Standings'],
    ['bracket', 'Bracket'],
  ]

  return (
    <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
      <section className="overflow-hidden rounded-3xl border border-white/10 bg-slate-900/80">
        <div className="bg-gradient-to-r from-amber-400 via-orange-500 to-red-500 px-6 py-8 text-slate-950 sm:px-10">
          <p className="text-xs font-black uppercase tracking-[.2em]">
            {data.organization.name}
          </p>
          <h1 className="mt-2 text-3xl font-black tracking-tight sm:text-5xl">
            {data.competition.name}
          </h1>
          <p className="mt-2 font-bold">{data.season.name}</p>
        </div>
        <nav
          className="flex gap-1 overflow-x-auto p-2"
          aria-label="Competition pages"
        >
          {tabs.map(([key, label]) => (
            <Link
              className={`whitespace-nowrap rounded-xl px-4 py-3 text-sm font-black ${view === key ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/5'}`}
              key={key}
              to={key === 'overview' ? basePath : `${basePath}/${key}`}
            >
              {label}
            </Link>
          ))}
        </nav>
      </section>

      {view === 'overview' ? (
        <div className="mt-8 grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
          <div className="space-y-6">
            <section className="panel">
              <div className="panel-heading">
                <div>
                  <p className="panel-kicker">Live now</p>
                  <h2 className="panel-title">On the court</h2>
                </div>
                <span className="count-pill">{data.live_games.length}</span>
              </div>
              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                {data.live_games.length ? (
                  data.live_games.map((game) => (
                    <GameCard
                      game={game}
                      timezone={timezone}
                      href={`${basePath}/games/${game.id}`}
                      key={game.id}
                    />
                  ))
                ) : (
                  <p className="empty-state sm:col-span-2">
                    No game is live right now.
                  </p>
                )}
              </div>
            </section>
            <section className="panel">
              <div className="panel-heading">
                <div>
                  <p className="panel-kicker">Coming up</p>
                  <h2 className="panel-title">Next games</h2>
                </div>
                <Link className="role-pill" to={`${basePath}/schedule`}>
                  Full schedule
                </Link>
              </div>
              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                {data.upcoming_games.length ? (
                  data.upcoming_games.map((game) => (
                    <GameCard
                      game={game}
                      timezone={timezone}
                      href={`${basePath}/games/${game.id}`}
                      key={game.id}
                    />
                  ))
                ) : (
                  <p className="empty-state sm:col-span-2">
                    The next fixtures will appear here.
                  </p>
                )}
              </div>
            </section>
            <section className="panel">
              <div className="panel-heading">
                <div>
                  <p className="panel-kicker">Official</p>
                  <h2 className="panel-title">Recent results</h2>
                </div>
                <Link className="role-pill" to={`${basePath}/results`}>
                  All results
                </Link>
              </div>
              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                {data.recent_results.length ? (
                  data.recent_results.map((game) => (
                    <GameCard
                      game={game}
                      timezone={timezone}
                      href={`${basePath}/games/${game.id}`}
                      key={game.id}
                    />
                  ))
                ) : (
                  <p className="empty-state sm:col-span-2">
                    Final scores will appear after games are verified.
                  </p>
                )}
              </div>
            </section>
          </div>
          <aside className="panel self-start">
            <div className="panel-heading">
              <div>
                <p className="panel-kicker">Bulletin</p>
                <h2 className="panel-title">Announcements</h2>
              </div>
              <span className="count-pill">{data.announcements.length}</span>
            </div>
            <div className="mt-5 space-y-4">
              {data.announcements.length ? (
                data.announcements.map((announcement) => (
                  <article
                    className="rounded-2xl border border-white/10 bg-white/[.03] p-4"
                    key={announcement.id}
                  >
                    <h3 className="font-black">{announcement.title}</h3>
                    <p className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-300">
                      {announcement.body}
                    </p>
                    <p className="mt-3 text-xs text-slate-500">
                      {announcement.published_at
                        ? new Date(
                            announcement.published_at,
                          ).toLocaleDateString('en-PH')
                        : ''}
                    </p>
                  </article>
                ))
              ) : (
                <p className="empty-state">No published notices.</p>
              )}
            </div>
          </aside>
        </div>
      ) : view === 'standings' ? (
        <section className="panel mt-8">
          <div className="panel-heading">
            <div>
              <p className="panel-kicker">Official table</p>
              <h2 className="panel-title">League standings</h2>
            </div>
            <span className="count-pill">Organizer verified</span>
          </div>
          <p className="mt-3 text-sm text-slate-400">
            These records and rankings are maintained by the league organizer.
          </p>
          <div className="mt-6 space-y-7">
            {data.standings.length ? (
              Object.entries(
                data.standings.reduce<Record<string, Standing[]>>(
                  (groups, row) => ({
                    ...groups,
                    [row.division.name]: [
                      ...(groups[row.division.name] ?? []),
                      row,
                    ],
                  }),
                  {},
                ),
              ).map(([divisionName, rows]) => (
                <section key={divisionName}>
                  <h3 className="mb-3 text-sm font-black uppercase tracking-wider text-slate-400">
                    {divisionName}
                  </h3>
                  <StandingTable rows={rows} />
                </section>
              ))
            ) : (
              <p className="empty-state">
                The organizer has not published standings yet.
              </p>
            )}
          </div>
        </section>
      ) : view === 'bracket' ? (
        <section className="panel mt-8">
          <div className="panel-heading">
            <div>
              <p className="panel-kicker">Road to the championship</p>
              <h2 className="panel-title">Tournament bracket</h2>
            </div>
            <span className="count-pill">{data.brackets.length}</span>
          </div>
          <div className="mt-6 space-y-5">
            {data.brackets.length ? (
              data.brackets.map((bracket) => (
                <BracketBoard
                  bracket={bracket}
                  standings={data.standings}
                  key={bracket.id}
                />
              ))
            ) : (
              <p className="empty-state">
                The playoff bracket has not been published yet.
              </p>
            )}
          </div>
        </section>
      ) : (
        <section className="panel mt-8">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="panel-kicker">
                {view === 'today'
                  ? 'Game day'
                  : view === 'results'
                    ? 'Official scores'
                    : 'Fixtures'}
              </p>
              <h2 className="panel-title">
                {view === 'today'
                  ? "Today's games"
                  : view === 'results'
                    ? 'Completed games'
                    : 'Full schedule'}
              </h2>
            </div>
            {view === 'schedule' && (
              <label className="field sm:w-56">
                Filter by date
                <input
                  type="date"
                  value={date}
                  onChange={(event) => setDate(event.target.value)}
                />
              </label>
            )}
          </div>
          {games.isPending ? (
            <p className="empty-state mt-6">Loading games…</p>
          ) : Object.keys(groupedGames).length ? (
            <div className="mt-6 space-y-7">
              {Object.entries(groupedGames).map(([day, dayGames]) => (
                <div key={day}>
                  <h3 className="mb-3 text-sm font-black uppercase tracking-[.13em] text-slate-400">
                    {day}
                  </h3>
                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {dayGames.map((game) => (
                      <GameCard
                        game={game}
                        timezone={timezone}
                        href={`${basePath}/games/${game.id}`}
                        key={game.id}
                      />
                    ))}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="empty-state mt-6">No games match this view.</p>
          )}
        </section>
      )}
    </main>
  )
}

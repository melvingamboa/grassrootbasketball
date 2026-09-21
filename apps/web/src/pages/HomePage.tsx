import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'

import { getPublicCompetition } from '../features/games/api'
import type { Game, PublicCompetition } from '../features/games/api'
import { realtime } from '../realtime/echo'

const pilot = {
  organization: 'jaen-community-basketball',
  competition: 'jaen-inter-purok-basketball',
  season: '2026-pilot-season',
}

const pilotPath = `/organizations/${pilot.organization}/competitions/${pilot.competition}/seasons/${pilot.season}`

const features = [
  ['Live game center', 'Scores, game clock, play-by-play, and official updates in one mobile view.'],
  ['League operations', 'Schedules, rosters, standings, brackets, and staff access managed centrally.'],
  ['Local sports network', 'A discoverable home for purok, barangay, town, open, and invitational leagues.'],
]

function clock(value: number) {
  return `${Math.floor(value / 60)}:${String(value % 60).padStart(2, '0')}`
}

function LiveClock({ seconds, running }: { seconds: number; running: boolean }) {
  const [remaining, setRemaining] = useState(seconds)
  useEffect(() => {
    if (!running) return
    const timer = window.setInterval(() => setRemaining((value) => Math.max(0, value - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [running])

  return <>{clock(remaining)}</>
}

function teamMark(game: Game, side: 'home' | 'away') {
  const team = side === 'home' ? game.home_team : game.away_team
  return <div>
    {team.logo_url ? <img alt="" className="team-mark object-cover" src={team.logo_url} /> : <div className="team-mark" style={{ background: team.primary_color, color: team.secondary_color }}>{(team.short_name || team.name).slice(0, 3)}</div>}
    <p className="mt-3 font-bold">{team.name}</p>
  </div>
}

function previewLabel(game: Game) {
  if (game.status === 'live') return { heading: 'Live game', status: '● Live now', style: 'text-red-300' }
  if (['delayed', 'suspended'].includes(game.status)) return { heading: 'Game update', status: game.status_label, style: 'text-amber-300' }
  if (game.status === 'final') return { heading: 'Latest result', status: 'Final', style: 'text-slate-300' }
  return { heading: 'Next scheduled game', status: 'Up next', style: 'text-emerald-300' }
}

export function HomePage() {
  const queryClient = useQueryClient()
  const preview = useQuery({
    queryKey: ['public-competition', pilot.organization, pilot.competition, pilot.season],
    queryFn: () => getPublicCompetition(pilot.organization, pilot.competition, pilot.season),
    retry: false,
    refetchInterval: 5_000,
  })
  const game = preview.data?.live_games[0] ?? preview.data?.upcoming_games[0] ?? preview.data?.recent_results[0]

  useEffect(() => {
    const echo = realtime()
    if (!echo || !game || !['live', 'delayed', 'suspended'].includes(game.status)) return

    echo.channel(`games.${game.id}`).listen('.game.updated', (payload: { game: Game }) => {
      queryClient.setQueryData<PublicCompetition>(
        ['public-competition', pilot.organization, pilot.competition, pilot.season],
        (current) => current ? {
          ...current,
          live_games: current.live_games.map((item) => item.id === payload.game.id ? payload.game : item),
          upcoming_games: current.upcoming_games.map((item) => item.id === payload.game.id ? payload.game : item),
          recent_results: current.recent_results.map((item) => item.id === payload.game.id ? payload.game : item),
        } : current,
      )
    })

    return () => { echo.leave(`games.${game.id}`) }
  }, [game, queryClient])

  const gameLabel = game ? previewLabel(game) : null
  const showScore = game && (['live', 'final'].includes(game.status) || game.home_score > 0 || game.away_score > 0)

  return (
    <main>
      <section className="relative overflow-hidden border-b border-white/10">
        <div className="hero-glow" />
        <div className="relative mx-auto grid max-w-7xl gap-12 px-5 py-20 sm:px-6 sm:py-28 lg:grid-cols-[1.15fr_.85fr] lg:items-center">
          <div>
            <p className="eyebrow">Built for every local court</p>
            <h1 className="mt-5 max-w-4xl text-5xl font-black leading-[.98] tracking-[-0.045em] sm:text-7xl">
              Your league.<br /><span className="text-amber-400">One digital court.</span>
            </h1>
            <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-300 sm:text-xl">
              Follow grassroots basketball live, discover upcoming games, and give organizers the tools to run every competition properly.
            </p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Link className="button button-primary" to={pilotPath}>View pilot league</Link>
              <Link className="button button-secondary" to="/login">Organizer sign in</Link>
            </div>
          </div>

          <div className="scoreboard" aria-label="Pilot league game preview">
            {preview.isPending ? <div className="grid min-h-64 place-items-center text-sm font-bold text-slate-400">Loading the latest pilot game…</div> : game && gameLabel ? <>
              <div className="flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-[.18em] text-slate-400">
                <span>{gameLabel.heading}</span><span className={gameLabel.style}>{gameLabel.status}</span>
              </div>
              <div className="mt-8 grid grid-cols-[1fr_auto_1fr] items-center gap-4 text-center">
                {teamMark(game, 'home')}
                <div><p className="text-4xl font-black tabular-nums sm:text-5xl">{showScore ? <>{game.home_score}<span className="px-1 text-slate-600">—</span>{game.away_score}</> : <span className="text-2xl text-slate-500">VS</span>}</p>{game.status === 'live' ? <p className="mt-2 text-xs font-bold text-amber-300">{game.period_label} · <LiveClock key={`${preview.dataUpdatedAt}-${game.clock_seconds_remaining}`} seconds={game.clock_seconds_remaining} running={game.clock_running} /></p> : <p className="mt-2 text-xs font-bold text-amber-300">{new Date(game.scheduled_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</p>}</div>
                {teamMark(game, 'away')}
              </div>
              <div className="mt-8 flex flex-col gap-3 rounded-2xl bg-white/5 p-4 text-sm text-slate-300 sm:flex-row sm:items-center sm:justify-between"><span>{game.venue?.name ?? 'Venue to be announced'} · {game.round || game.division.name}</span><Link className="font-black text-amber-300 hover:text-amber-200" to={`${pilotPath}/games/${game.id}`}>View game →</Link></div>
            </> : <div className="grid min-h-64 place-items-center text-center"><div><p className="text-lg font-black">No pilot games available</p><p className="mt-2 text-sm text-slate-400">New schedules will appear here after the organizer publishes them.</p><Link className="mt-5 inline-block font-black text-amber-300" to={pilotPath}>Open pilot league →</Link></div></div>}
          </div>
        </div>
      </section>

      <section className="mx-auto grid max-w-7xl gap-4 px-5 py-12 sm:px-6 md:grid-cols-3" id="discover">
        {features.map(([title, detail], index) => (
          <article className="card" key={title}>
            <p className="text-sm font-black text-amber-400">0{index + 1}</p>
            <h2 className="mt-5 text-xl font-black">{title}</h2>
            <p className="mt-3 text-sm leading-6 text-slate-400">{detail}</p>
          </article>
        ))}
      </section>
    </main>
  )
}

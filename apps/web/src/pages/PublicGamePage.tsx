import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'

import { getPublicGame } from '../features/games/api'
import type { Game, GameLiveEvent, GamePlayerStat } from '../features/games/api'
import { realtime } from '../realtime/echo'

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

function useDesktopLayout() {
  const [isDesktop, setIsDesktop] = useState(() => window.matchMedia('(min-width: 1024px)').matches)

  useEffect(() => {
    const media = window.matchMedia('(min-width: 1024px)')
    const updateLayout = () => setIsDesktop(media.matches)
    updateLayout()
    media.addEventListener('change', updateLayout)
    return () => media.removeEventListener('change', updateLayout)
  }, [])

  return isDesktop
}

function PlayerCard({ row, slot }: { row: GamePlayerStat; slot: number }) {
  return <article className="min-w-0 rounded-2xl border border-white/10 bg-white/[.025] p-2 text-center sm:p-3">
    <span className="text-[10px] font-black text-amber-400">COURT {slot}</span>
    <span className="mx-auto mt-1 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-xs font-black">#{row.player_registration.jersey_number}</span>
    <strong className="mt-2 block truncate text-[10px] sm:text-sm">{row.player_registration.player.display_name}</strong>
    <span className="mt-1 block text-sm font-black text-amber-400">{row.points} PTS</span>
    <span className="block text-[9px] text-slate-500">{row.rebounds} REB · {row.assists} AST</span>
  </article>
}

function BoxScore({ game }: { game: Game }) {
  const [activeTeamId, setActiveTeamId] = useState(game.home_team.registration_id)
  const [benchOpen, setBenchOpen] = useState(false)
  const teams = [game.home_team, game.away_team]
  const team = teams.find((item) => item.registration_id === activeTeamId) ?? teams[0]
  const rows = game.box_score.filter((row) => row.team_registration_id === team.registration_id)
  const markedOnCourt = rows.filter((row) => row.is_on_court).sort((a, b) => (a.court_slot ?? 99) - (b.court_slot ?? 99))
  const onCourt = markedOnCourt.length ? markedOnCourt : rows.filter((row) => row.is_starter).slice(0, 5)
  const onCourtIds = new Set(onCourt.map((row) => row.id))
  const bench = rows.filter((row) => !onCourtIds.has(row.id))

  return <section className="panel">
    <div className="panel-heading"><div><p className="panel-kicker">Official statistics</p><h2 className="panel-title">Live player box score</h2></div><span className="count-pill">{game.box_score.length} players</span></div>
    {game.box_score.length ? <>
      <div className="mt-5 grid grid-cols-2 gap-2 rounded-2xl bg-white/5 p-1.5" role="tablist" aria-label="Choose a team box score">
        {teams.map((item) => <button aria-selected={item.registration_id === team.registration_id} className={`rounded-xl px-3 py-3 text-sm font-black transition ${item.registration_id === team.registration_id ? 'bg-amber-400 text-slate-950 shadow-lg' : 'text-slate-300 hover:bg-white/5'}`} key={item.registration_id} onClick={() => { setActiveTeamId(item.registration_id); setBenchOpen(false) }} role="tab">{item.name}</button>)}
      </div>
      <div className="mt-5 flex items-center justify-between"><div><h3 className="font-black">Players on the court</h3><p className="text-xs text-slate-500">Updates automatically when the scorer substitutes.</p></div><span className="count-pill">{onCourt.length}/5</span></div>
      {onCourt.length ? <div className="mt-3 grid grid-cols-5 gap-2">{onCourt.map((row, index) => <PlayerCard key={row.id} row={row} slot={row.court_slot ?? index + 1} />)}</div> : <p className="empty-state mt-3">The starting five has not been published yet.</p>}
      <button className="button button-secondary mt-4 w-full justify-between" onClick={() => setBenchOpen((open) => !open)} type="button"><span>Bench players</span><span>{bench.length} players {benchOpen ? '↑' : '↓'}</span></button>
      {benchOpen && <div className="mt-3 overflow-x-auto rounded-2xl border border-white/10"><table className="w-full min-w-[650px] text-sm"><thead className="bg-white/[.04] text-xs uppercase text-slate-500"><tr><th className="p-3 text-left">Player</th><th className="p-3">PTS</th><th className="p-3">REB</th><th className="p-3">AST</th><th className="p-3">STL</th><th className="p-3">BLK</th><th className="p-3">TO</th><th className="p-3">PF</th></tr></thead><tbody className="divide-y divide-white/10">{bench.map((row) => <tr key={row.id}><td className="p-3 font-bold">#{row.player_registration.jersey_number} {row.player_registration.player.display_name}</td><td className="p-3 text-center font-black text-amber-400">{row.points}</td><td className="p-3 text-center">{row.rebounds}</td><td className="p-3 text-center">{row.assists}</td><td className="p-3 text-center">{row.steals}</td><td className="p-3 text-center">{row.blocks}</td><td className="p-3 text-center">{row.turnovers}</td><td className="p-3 text-center">{row.fouls}</td></tr>)}</tbody></table></div>}
    </> : <p className="empty-state mt-5">The official player lineup and box score have not been published for this game.</p>}
  </section>
}

const publicEventTypes = new Set(['start', 'score', 'correction', 'player_stat', 'substitution', 'next_period', 'clock_adjustment', 'finalize'])
const statLabels: Record<string, string> = {
  rebounds: 'rebound', assists: 'assist', steals: 'steal', blocks: 'block', turnovers: 'turnover', fouls: 'foul',
}

function periodLabel(period: number) {
  return period > 4 ? `OT${period > 5 ? period - 4 : ''}` : `Q${period}`
}

function playerName(game: Game, registrationId?: number) {
  if (!registrationId) return null
  const row = game.box_score.find((item) => item.player_registration.id === registrationId)
  return row ? `#${row.player_registration.jersey_number} ${row.player_registration.player.display_name}` : null
}

function playDescription(event: GameLiveEvent, game: Game) {
  const team = event.team_registration_id === game.home_team.registration_id ? game.home_team : game.away_team
  const actor = playerName(game, event.details?.player_registration_id) ?? team.name
  if (event.event_type === 'start') return 'The game started'
  if (event.event_type === 'score') {
    const shot = event.points_delta === 1 ? 'made a free throw' : event.points_delta === 2 ? 'made a 2-point field goal' : 'made a 3-pointer'
    return `${actor} ${shot}`
  }
  if (event.event_type === 'correction') return `${actor} score corrected by ${Math.abs(event.points_delta ?? 0)} point${Math.abs(event.points_delta ?? 0) === 1 ? '' : 's'}`
  if (event.event_type === 'player_stat') {
    const label = statLabels[event.details?.stat ?? ''] ?? 'stat'
    if ((event.details?.delta ?? 1) < 0) return `${actor} ${label} corrected`
    if (event.details?.stat === 'fouls') return `${actor} committed a foul`
    return `${actor} recorded a ${label}`
  }
  if (event.event_type === 'substitution') return `${playerName(game, event.details?.player_in_registration_id) ?? 'Player'} entered for ${playerName(game, event.details?.player_out_registration_id) ?? 'player'}`
  if (event.event_type === 'next_period') return `${periodLabel(event.period_number)} started`
  if (event.event_type === 'clock_adjustment') return `Game clock corrected from ${clock(event.details?.clock_seconds_before ?? 0)} to ${clock(event.details?.clock_seconds_after ?? event.clock_seconds_remaining)}`
  if (event.event_type === 'finalize') return `Final score: ${game.home_team.name} ${event.home_score_after}, ${game.away_team.name} ${event.away_score_after}`
  return event.event_type.replaceAll('_', ' ')
}

function PlayByPlay({ game }: { game: Game }) {
  const [period, setPeriod] = useState<number | 'all'>('all')
  const [expanded, setExpanded] = useState(false)
  const events = (game.live_events ?? []).filter((event) => publicEventTypes.has(event.event_type))
  const periods = [...new Set(events.map((event) => event.period_number))].sort((a, b) => a - b)
  const filtered = period === 'all' ? events : events.filter((event) => event.period_number === period)
  const visible = expanded ? filtered : filtered.slice(0, 12)

  return <section className="panel">
    <div className="panel-heading"><div><p className="panel-kicker">Live game timeline</p><h2 className="panel-title">Play-by-play</h2></div><span className="count-pill">{events.length} events</span></div>
    {events.length ? <>
      <div className="mt-4 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Filter play-by-play by period">
        <button aria-selected={period === 'all'} className={`shrink-0 rounded-full px-4 py-2 text-xs font-black ${period === 'all' ? 'bg-amber-400 text-slate-950' : 'bg-white/5 text-slate-300'}`} onClick={() => setPeriod('all')} role="tab">All</button>
        {periods.map((item) => <button aria-selected={period === item} className={`shrink-0 rounded-full px-4 py-2 text-xs font-black ${period === item ? 'bg-amber-400 text-slate-950' : 'bg-white/5 text-slate-300'}`} key={item} onClick={() => setPeriod(item)} role="tab">{periodLabel(item)}</button>)}
      </div>
      <ol className="mt-4 space-y-2">{visible.map((event) => { const corrected = event.event_type === 'correction' || event.event_type === 'clock_adjustment' || (event.event_type === 'player_stat' && (event.details?.delta ?? 1) < 0); return <li className={`grid grid-cols-[3.8rem_1fr_auto] items-start gap-3 rounded-2xl border p-3 sm:grid-cols-[5rem_1fr_auto] ${corrected ? 'border-rose-400/25 bg-rose-500/[.04]' : 'border-white/10 bg-white/[.025]'}`} key={event.id}><div className="text-center"><strong className="block text-xs text-amber-400">{periodLabel(event.period_number)}</strong><span className="text-xs font-black tabular-nums text-slate-400">{clock(event.clock_seconds_remaining)}</span></div><div><p className="text-sm font-bold leading-5">{playDescription(event, game)}</p>{event.details?.reason && <p className="mt-1 text-xs text-slate-500">Reason: {event.details.reason}</p>}</div><span className="whitespace-nowrap text-xs font-black tabular-nums">{event.home_score_after}–{event.away_score_after}</span></li> })}</ol>
      {filtered.length > 12 && <button className="button button-secondary mt-4 w-full" onClick={() => setExpanded((value) => !value)}>{expanded ? 'Show latest 12' : `Show all ${filtered.length} events`}</button>}
    </> : <p className="empty-state mt-5">Play-by-play will appear here when the game begins.</p>}
  </section>
}

type GameCenterView = 'gamecast' | 'box-score' | 'play-by-play'

function playersOnCourt(game: Game, teamRegistrationId: number) {
  const rows = game.box_score.filter((row) => row.team_registration_id === teamRegistrationId)
  const active = rows.filter((row) => row.is_on_court).sort((a, b) => (a.court_slot ?? 99) - (b.court_slot ?? 99))
  return active.length ? active.slice(0, 5) : rows.filter((row) => row.is_starter).slice(0, 5)
}

function CompactLineup({ game, team }: { game: Game; team: Game['home_team'] }) {
  const players = playersOnCourt(game, team.registration_id)

  return <article className="min-w-0 rounded-2xl border border-white/10 bg-white/[.025] p-3 sm:p-4">
    <div className="flex items-center gap-2 border-b border-white/10 pb-3">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-[10px] font-black" style={{ background: team.primary_color, color: team.secondary_color }}>{(team.short_name || team.name).slice(0, 3)}</span>
      <div className="min-w-0"><strong className="block truncate text-sm">{team.name}</strong><span className="text-[10px] font-black uppercase tracking-wider text-slate-500">On the court</span></div>
    </div>
    {players.length ? <ol className="mt-2 divide-y divide-white/10">{players.map((row, index) => <li className="flex items-center gap-2 py-2 text-xs" key={row.id}><span className="w-5 shrink-0 text-center font-black text-amber-400">{row.court_slot ?? index + 1}</span><span className="min-w-0 flex-1 truncate font-bold">#{row.player_registration.jersey_number} {row.player_registration.player.display_name}</span><span className="shrink-0 font-black tabular-nums">{row.points} PTS</span></li>)}</ol> : <p className="py-6 text-center text-xs text-slate-500">Lineup not published yet.</p>}
  </article>
}

function LivestreamPanel({ game, compact, onToggleSize, showSizeControl }: { game: Game; compact: boolean; onToggleSize: () => void; showSizeControl: boolean }) {
  const stream = game.livestream
  if (!stream) return <div className="rounded-2xl border border-dashed border-white/15 p-5 text-center"><strong className="block text-sm">No official livestream published</strong><p className="mt-1 text-xs text-slate-500">GameCast scores and updates remain available here.</p></div>

  return <div className="overflow-hidden rounded-2xl border border-white/10 bg-black/20 shadow-xl">
    <div className={`flex items-center justify-between gap-2 border-b border-white/10 ${compact ? 'px-3 py-2' : 'px-4 py-3'}`}><div className="flex min-w-0 items-center gap-2"><span className={`h-2.5 w-2.5 shrink-0 rounded-full ${stream.status === 'live' ? 'animate-pulse bg-rose-500' : stream.status === 'ended' ? 'bg-slate-500' : 'bg-amber-400'}`} /><strong className="truncate text-sm">Official {stream.provider_label} stream</strong><span className="role-pill hidden sm:inline-flex">{stream.status_label}</span></div>{showSizeControl && <button className="shrink-0 rounded-lg border border-white/10 px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-300 transition hover:bg-white/10 hover:text-white" onClick={onToggleSize} type="button">{compact ? 'Expand' : 'Minimize'}</button>}</div>
    {stream.can_embed && stream.embed_url ? <div className="aspect-video bg-black"><iframe allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowFullScreen className="h-full w-full" loading="lazy" referrerPolicy="strict-origin-when-cross-origin" src={stream.embed_url} title={`${game.home_team.name} versus ${game.away_team.name} official livestream`} /></div> : <div className={compact ? 'p-4 text-center' : 'p-6 text-center'}><p className="text-sm text-slate-300">Watch this authorized stream on {stream.provider_label}.</p><a className="button button-primary mt-4" href={stream.watch_url} rel="noreferrer" target="_blank">Watch livestream ↗</a></div>}
    {stream.can_embed && !compact && <div className="flex justify-end px-4 py-3"><a className="text-xs font-bold text-amber-300 hover:text-amber-200" href={stream.watch_url} rel="noreferrer" target="_blank">Open on {stream.provider_label} ↗</a></div>}
  </div>
}

function GameCast({ game, openPlayByPlay }: { game: Game; openPlayByPlay: () => void }) {
  const events = (game.live_events ?? []).filter((event) => publicEventTypes.has(event.event_type))
  const latest = events[0]

  return <section className="panel">
    <div className="panel-heading"><div><p className="panel-kicker">Live game center</p><h2 className="panel-title">GameCast</h2></div><span className={`count-pill ${game.status === 'live' ? '!bg-rose-500 !text-white' : ''}`}>{game.status_label}</span></div>
    {latest ? <button className="mt-5 flex w-full items-start gap-3 rounded-2xl border border-amber-400/20 bg-amber-400/[.06] p-4 text-left transition hover:border-amber-400/40" onClick={openPlayByPlay} type="button">
      <span className="shrink-0 rounded-xl bg-amber-400 px-2.5 py-2 text-center text-xs font-black text-slate-950"><span className="block">{periodLabel(latest.period_number)}</span><span className="block tabular-nums">{clock(latest.clock_seconds_remaining)}</span></span>
      <span className="min-w-0 flex-1"><span className="block text-[10px] font-black uppercase tracking-[.18em] text-amber-400">Latest play</span><strong className="mt-1 block text-sm leading-5">{playDescription(latest, game)}</strong></span>
      <span className="shrink-0 pt-3 text-sm font-black tabular-nums">{latest.home_score_after}–{latest.away_score_after}</span>
    </button> : <p className="empty-state mt-5">GameCast will begin when the scorer starts the game.</p>}
    <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
      <CompactLineup game={game} team={game.home_team} />
      <CompactLineup game={game} team={game.away_team} />
    </div>
    <p className="mt-4 text-center text-xs text-slate-500">Scores, lineups, and the latest play update automatically.</p>
  </section>
}

function GameCenter({ game }: { game: Game }) {
  const isDesktop = useDesktopLayout()
  const [view, setView] = useState<GameCenterView>('gamecast')
  const [playerExpanded, setPlayerExpanded] = useState(false)
  const [playerMinimized, setPlayerMinimized] = useState(false)
  const tabs: { id: GameCenterView; label: string }[] = [
    { id: 'gamecast', label: 'GameCast' },
    { id: 'box-score', label: 'Box Score' },
    { id: 'play-by-play', label: 'Play-by-Play' },
  ]
  const compactPlayer = !isDesktop && (playerMinimized || (view !== 'gamecast' && !playerExpanded))
  const showPlayer = view === 'gamecast' || (!isDesktop && Boolean(game.livestream))

  function selectView(nextView: GameCenterView) {
    setView(nextView)
    setPlayerExpanded(false)
    setPlayerMinimized(false)
  }

  function togglePlayerSize() {
    if (compactPlayer) {
      setPlayerMinimized(false)
      setPlayerExpanded(true)
    } else {
      setPlayerExpanded(false)
      setPlayerMinimized(true)
    }
  }

  return <section className="mt-6" aria-label="Game center">
    <div className="sticky top-20 z-20 grid grid-cols-3 gap-1 rounded-2xl border border-white/10 bg-slate-950/85 p-1.5 shadow-xl backdrop-blur" role="tablist" aria-label="Choose game coverage">
      {tabs.map((tab) => <button aria-controls={`game-center-${tab.id}`} aria-selected={view === tab.id} className={`min-w-0 rounded-xl px-2 py-3 text-xs font-black transition sm:text-sm ${view === tab.id ? 'bg-amber-400 text-slate-950 shadow-lg' : 'text-slate-300 hover:bg-white/5 hover:text-white'}`} id={`game-center-tab-${tab.id}`} key={tab.id} onClick={() => selectView(tab.id)} role="tab" type="button">{tab.label}</button>)}
    </div>
    <div className="mt-3">
      {showPlayer && <div className={compactPlayer && game.livestream ? 'sticky top-36 z-10 ml-auto w-full max-w-[15rem] sm:max-w-sm' : ''}><LivestreamPanel compact={compactPlayer} game={game} onToggleSize={togglePlayerSize} showSizeControl={!isDesktop} /></div>}
      <div aria-labelledby={`game-center-tab-${view}`} className={showPlayer ? 'mt-3' : ''} id={`game-center-${view}`} role="tabpanel">
        {view === 'gamecast' && <GameCast game={game} openPlayByPlay={() => selectView('play-by-play')} />}
        {view === 'box-score' && <BoxScore game={game} />}
        {view === 'play-by-play' && <PlayByPlay game={game} />}
      </div>
    </div>
  </section>
}

export function PublicGamePage() {
  const { organization = '', competition = '', season = '', game = '' } = useParams()
  const queryClient = useQueryClient()
  const queryKey = ['public-game', organization, competition, season, game]
  const query = useQuery({ queryKey, queryFn: () => getPublicGame(organization, competition, season, Number(game)), retry: false, refetchInterval: 5_000 })
  const basePath = `/organizations/${organization}/competitions/${competition}/seasons/${season}`
  useEffect(() => {
    const echo = realtime()
    if (!echo || !game) return
    echo.channel(`games.${game}`).listen('.game.updated', (payload: { game: Game }) => queryClient.setQueryData(['public-game', organization, competition, season, game], payload.game))
    return () => { echo.leave(`games.${game}`) }
  }, [competition, game, organization, queryClient, season])

  if (query.isPending) return <main className="grid min-h-[70vh] place-items-center text-slate-300">Loading game…</main>
  if (!query.data) return <main className="grid min-h-[70vh] place-items-center px-4 text-center"><div><h1 className="text-3xl font-black">Game unavailable</h1><Link className="button button-secondary mt-5" to={basePath}>Return to league</Link></div></main>

  const gameData = query.data
  const showScore = ['live', 'final'].includes(gameData.status) || gameData.home_score > 0 || gameData.away_score > 0
  return <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12">
    <Link className="text-sm font-bold text-amber-400 hover:text-amber-300" to={basePath}>← Back to competition</Link>
    <section className="scoreboard mt-6">
      <div className="flex flex-wrap items-center justify-between gap-3"><p className="eyebrow">{gameData.round || gameData.division.name}</p><span className="rounded-full border border-white/15 px-3 py-1 text-xs font-black uppercase">{gameData.status_label}</span></div>
      <div className="mt-8 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center sm:gap-8">
        {[gameData.home_team, gameData.away_team].map((item, index) => <div className={index ? 'order-3' : ''} key={item.registration_id}><span className="mx-auto grid h-16 w-16 place-items-center rounded-2xl text-sm font-black sm:h-20 sm:w-20" style={{ background: item.primary_color, color: item.secondary_color }}>{(item.short_name || item.name).slice(0, 3)}</span><h1 className="mt-3 text-sm font-black sm:text-xl">{item.name}</h1></div>)}
        <div className="order-2">{showScore ? <p className="text-4xl font-black tabular-nums sm:text-6xl">{gameData.home_score}<span className="px-2 text-slate-600">–</span>{gameData.away_score}</p> : <p className="text-xl font-black text-slate-500">VS</p>}</div>
      </div>
      {gameData.status === 'live' && <div className="mt-6 flex items-center justify-center gap-3"><span className="rounded-full bg-rose-500 px-3 py-1 text-xs font-black uppercase tracking-wider text-white">Live</span><span className="text-lg font-black text-amber-400">{gameData.period_label}</span><span className="text-3xl font-black tabular-nums"><LiveClock key={`${query.dataUpdatedAt}-${gameData.clock_seconds_remaining}`} seconds={gameData.clock_seconds_remaining} running={gameData.clock_running} /></span></div>}
      {gameData.period_scores.length > 0 && <div className="mt-6 overflow-x-auto"><table className="mx-auto min-w-[320px] text-center text-xs"><thead className="text-slate-500"><tr><th className="px-3 py-2 text-left">Team</th>{gameData.period_scores.map((row) => <th className="px-3 py-2" key={row.period}>{row.period > 4 ? `OT${row.period - 4 > 1 ? row.period - 4 : ''}` : `Q${row.period}`}</th>)}<th className="px-3 py-2">Total</th></tr></thead><tbody className="font-black"><tr><td className="px-3 py-2 text-left">{gameData.home_team.short_name || gameData.home_team.name}</td>{gameData.period_scores.map((row) => <td className="px-3 py-2" key={row.period}>{row.home}</td>)}<td className="px-3 py-2 text-amber-400">{gameData.home_score}</td></tr><tr><td className="px-3 py-2 text-left">{gameData.away_team.short_name || gameData.away_team.name}</td>{gameData.period_scores.map((row) => <td className="px-3 py-2" key={row.period}>{row.away}</td>)}<td className="px-3 py-2 text-amber-400">{gameData.away_score}</td></tr></tbody></table></div>}
      <div className="mt-8 grid gap-2 rounded-2xl bg-white/5 p-4 text-center text-sm text-slate-300 sm:grid-cols-3"><span>{new Date(gameData.scheduled_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</span><span>{gameData.venue?.name ?? 'Venue to be announced'}</span><span>{gameData.division.name}</span></div>
      {gameData.status_reason && <p className="mt-4 rounded-xl bg-amber-400/10 p-4 text-sm text-amber-100">{gameData.status_reason}</p>}
    </section>
    <GameCenter game={gameData} />
    {!!gameData.schedule_history?.length && <section className="panel mt-6"><div className="panel-heading"><div><p className="panel-kicker">Official notices</p><h2 className="panel-title">Game updates</h2></div><span className="count-pill">{gameData.schedule_history.length}</span></div><ol className="mt-5 space-y-3">{gameData.schedule_history.map((change, index) => <li className="rounded-xl border border-white/10 p-4" key={`${change.created_at}-${index}`}><p className="text-sm font-bold capitalize">{change.change_type} update</p><p className="mt-1 text-sm leading-6 text-slate-300">{change.reason}</p><p className="mt-2 text-xs text-slate-500">{change.created_at ? new Date(change.created_at).toLocaleString('en-PH') : ''}</p></li>)}</ol></section>}
  </main>
}

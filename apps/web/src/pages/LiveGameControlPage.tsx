import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'

import { validationMessage } from '../api/client'
import {
  applyLiveGameAction,
  getGame,
  substituteGamePlayer,
  updateGameLineup,
  updateGamePlayerStat,
} from '../features/games/api'
import type { Game, GamePlayerStat, LiveGameAction } from '../features/games/api'

type Side = 'home' | 'away'
const trackedStats = [
  ['rebounds', 'REB'], ['assists', 'AST'], ['steals', 'STL'],
  ['blocks', 'BLK'], ['turnovers', 'TO'], ['fouls', 'PF'],
] as const

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

function eventLabel(event: NonNullable<Game['live_events']>[number], game: Game) {
  const team = event.team_registration_id === game.home_team.registration_id ? game.home_team.name : game.away_team.name
  const player = (id?: number) => game.box_score.find((row) => row.player_registration.id === id)?.player_registration.player.display_name
  if (event.event_type === 'score') return `${player(event.details?.player_registration_id) ?? team} +${event.points_delta}`
  if (event.event_type === 'correction') return `${player(event.details?.player_registration_id) ?? team} ${event.points_delta} · ${event.details?.reason ?? 'Correction'}`
  if (event.event_type === 'player_stat') return `${player(event.details?.player_registration_id) ?? 'Player'} ${event.details?.delta === -1 ? 'corrected' : 'recorded'} ${event.details?.stat?.replaceAll('_', ' ') ?? 'stat'}`
  if (event.event_type === 'substitution') return `${player(event.details?.player_in_registration_id) ?? 'Player in'} for ${player(event.details?.player_out_registration_id) ?? 'player out'}`
  if (event.event_type === 'clock_adjustment') return `Clock corrected to ${clock(event.details?.clock_seconds_after ?? event.clock_seconds_remaining)} · ${event.details?.reason ?? 'Official correction'}`
  return event.event_type.replaceAll('_', ' ')
}

export function LiveGameControlPage() {
  const { organization = '', competition = '', season = '', game = '' } = useParams()
  const gameId = Number(game)
  const queryClient = useQueryClient()
  const queryKey = ['game-control', organization, competition, season, gameId]
  const [activeSide, setActiveSide] = useState<Side>('home')
  const [selectedStatId, setSelectedStatId] = useState<number | null>(null)
  const [benchOpen, setBenchOpen] = useState(false)
  const [lineupOpen, setLineupOpen] = useState(false)
  const [clockEditorOpen, setClockEditorOpen] = useState(false)
  const [clockMinutes, setClockMinutes] = useState('0')
  const [clockSeconds, setClockSeconds] = useState('00')
  const [clockReason, setClockReason] = useState('')
  const query = useQuery({ queryKey, queryFn: () => getGame(organization, competition, season, gameId), refetchInterval: 10_000 })
  const storeGame = (data: Game) => queryClient.setQueryData(queryKey, data)
  const mutation = useMutation({ mutationFn: (input: LiveGameAction) => applyLiveGameAction(organization, competition, season, gameId, input), onSuccess: (data, input) => { storeGame(data); if (input.action === 'clock_adjust') setClockEditorOpen(false) } })
  const lineupMutation = useMutation({
    mutationFn: (input: Parameters<typeof updateGameLineup>[4]) => updateGameLineup(organization, competition, season, gameId, input),
    onSuccess: (data) => { storeGame(data); setLineupOpen(false) },
  })
  const statMutation = useMutation({
    mutationFn: ({ playerStat, input }: { playerStat: number; input: Parameters<typeof updateGamePlayerStat>[5] }) => updateGamePlayerStat(organization, competition, season, gameId, playerStat, input),
    onSuccess: storeGame,
  })
  const substitutionMutation = useMutation({
    mutationFn: (input: Parameters<typeof substituteGamePlayer>[4]) => substituteGamePlayer(organization, competition, season, gameId, input),
    onSuccess: (data) => { storeGame(data); setBenchOpen(false) },
  })

  const gameData = query.data
  const team = gameData ? (activeSide === 'home' ? gameData.home_team : gameData.away_team) : null
  const teamPlayers = gameData?.box_score.filter((row) => row.team_registration_id === team?.registration_id) ?? []
  const onCourt = teamPlayers.filter((row) => row.is_on_court).sort((a, b) => (a.court_slot ?? 99) - (b.court_slot ?? 99))
  const bench = teamPlayers.filter((row) => !row.is_on_court)
  const selectedPlayer = onCourt.find((row) => row.id === selectedStatId) ?? onCourt[0]

  function act(input: LiveGameAction) {
    if (!mutation.isPending) mutation.mutate(input)
  }
  function currentClockSeconds() {
    if (!gameData) return 0
    const elapsed = gameData.clock_running ? Math.floor((Date.now() - query.dataUpdatedAt) / 1000) : 0
    return Math.max(0, gameData.clock_seconds_remaining - elapsed)
  }
  function openClockEditor() {
    const seconds = currentClockSeconds()
    setClockMinutes(String(Math.floor(seconds / 60)))
    setClockSeconds(String(seconds % 60).padStart(2, '0'))
    setClockReason('')
    setClockEditorOpen(true)
  }
  function changeClockDraft(delta: number) {
    const total = Math.max(0, Math.min(3600, Number(clockMinutes || 0) * 60 + Number(clockSeconds || 0) + delta))
    setClockMinutes(String(Math.floor(total / 60)))
    setClockSeconds(String(total % 60).padStart(2, '0'))
  }
  function saveClockAdjustment(event: FormEvent) {
    event.preventDefault()
    act({ action: 'clock_adjust', clock_seconds: Number(clockMinutes || 0) * 60 + Number(clockSeconds || 0), reason: clockReason.trim() })
  }
  function correctPlayer(player: GamePlayerStat) {
    const rawPoints = window.prompt('How many points should be removed? Enter 1, 2, or 3:', '1')?.trim()
    if (!rawPoints || !['1', '2', '3'].includes(rawPoints)) return
    const reason = window.prompt(`Reason for this official −${rawPoints} correction:`)?.trim()
    if (reason) act({ action: 'correct', team: activeSide, points: Number(rawPoints) as 1 | 2 | 3, player_registration_id: player.player_registration.id, reason })
  }
  function adjustStat(player: GamePlayerStat, stat: Parameters<typeof updateGamePlayerStat>[5]['stat'], delta: -1 | 1) {
    let reason: string | undefined
    if (delta < 0 || gameData?.status === 'final') {
      reason = window.prompt(`Reason for changing ${stat}:`)?.trim()
      if (!reason) return
    }
    statMutation.mutate({ playerStat: player.id, input: { stat, delta, reason } })
  }

  if (query.isPending) return <main className="grid min-h-[70vh] place-items-center text-slate-300">Loading scorer console…</main>
  if (!gameData) return <main className="grid min-h-[70vh] place-items-center text-slate-300">Game unavailable.</main>
  const isLive = gameData.status === 'live'

  return <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
    <div className="flex flex-wrap items-center justify-between gap-3"><Link className="text-sm font-bold text-amber-400" to={`/app/organizations/${organization}/schedule`}>← Schedule</Link><span className="role-pill uppercase">Scorer console</span></div>

    <section className="scoreboard mt-5">
      <div className="flex items-center justify-between gap-3"><p className="eyebrow">{gameData.round || gameData.division.name}</p><span className="rounded-full border border-white/15 px-3 py-1 text-xs font-black uppercase">{gameData.status_label}</span></div>
      <div className="mt-5 flex items-center justify-center gap-4 text-center"><span className="rounded-xl bg-white/5 px-4 py-2 text-xl font-black text-amber-400">{gameData.period_label}</span><button className="group rounded-2xl px-3 py-2 transition hover:bg-white/5" disabled={!['live', 'suspended'].includes(gameData.status)} onClick={openClockEditor} title="Adjust official game clock" type="button"><span className="block text-5xl font-black tabular-nums sm:text-7xl"><LiveClock key={`${query.dataUpdatedAt}-${gameData.clock_seconds_remaining}`} seconds={gameData.clock_seconds_remaining} running={gameData.clock_running} /></span><span className="mt-1 block text-[10px] font-black uppercase tracking-wider text-slate-500 group-hover:text-amber-400">Tap to adjust</span></button></div>
      <div className="mt-7 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center"><div><p className="truncate text-sm font-black sm:text-lg">{gameData.home_team.name}</p><p className="text-5xl font-black tabular-nums sm:text-7xl">{gameData.home_score}</p></div><span className="text-slate-500">–</span><div><p className="truncate text-sm font-black sm:text-lg">{gameData.away_team.name}</p><p className="text-5xl font-black tabular-nums sm:text-7xl">{gameData.away_score}</p></div></div>
      <div className="mt-6 grid gap-3 sm:grid-cols-3">
        {['scheduled', 'delayed', 'suspended'].includes(gameData.status) ? <button className="button button-primary" disabled={mutation.isPending} onClick={() => act({ action: 'start' })}>{gameData.status === 'suspended' ? 'Resume game' : 'Start game'}</button> : <button className="button button-secondary" disabled={!isLive || mutation.isPending} onClick={() => act({ action: gameData.clock_running ? 'clock_pause' : 'clock_start' })}>{gameData.clock_running ? 'Pause clock' : 'Run clock'}</button>}
        <button className="button button-secondary" disabled={!isLive || mutation.isPending} onClick={() => act({ action: 'next_period' })}>Next period</button>
        <button className="danger-button justify-center" disabled={!['live', 'suspended'].includes(gameData.status) || mutation.isPending} onClick={() => window.confirm('Finalize this result and update the standings?') && act({ action: 'finalize' })}>Finalize game</button>
      </div>
      {clockEditorOpen && <form className="clock-adjuster mt-5" onSubmit={saveClockAdjustment}>
        <div className="flex items-start justify-between gap-3"><div><p className="eyebrow">Official correction</p><h2 className="mt-1 text-lg font-black">Adjust game clock</h2><p className="mt-1 text-xs text-slate-400">The clock {gameData.clock_running ? 'will keep running' : 'will remain paused'} after saving.</p></div><button aria-label="Close clock adjustment" className="rounded-lg px-3 py-2 text-slate-400 hover:bg-white/10" onClick={() => setClockEditorOpen(false)} type="button">✕</button></div>
        <div className="mt-4 grid grid-cols-[1fr_auto_1fr] items-end gap-3"><label className="field"><span>Minutes</span><input max="60" min="0" onChange={(event) => setClockMinutes(event.target.value)} required type="number" value={clockMinutes} /></label><span className="pb-3 text-2xl font-black">:</span><label className="field"><span>Seconds</span><input max="59" min="0" onChange={(event) => setClockSeconds(event.target.value)} required type="number" value={clockSeconds} /></label></div>
        <div className="mt-3 grid grid-cols-4 gap-2">{[-10, -1, 1, 10].map((delta) => <button className="clock-nudge" key={delta} onClick={() => changeClockDraft(delta)} type="button">{delta > 0 ? '+' : ''}{delta}s</button>)}</div>
        <label className="field mt-4"><span>Reason for correction</span><input maxLength={500} onChange={(event) => setClockReason(event.target.value)} placeholder="Example: Matched the official scoreboard" required type="text" value={clockReason} /></label>
        <button className="button button-primary mt-4 w-full" disabled={mutation.isPending || !clockReason.trim()}>{mutation.isPending ? 'Saving correction…' : `Set clock to ${clock(Number(clockMinutes || 0) * 60 + Number(clockSeconds || 0))}`}</button>
      </form>}
      {mutation.isError && <p className="form-error mt-4">{validationMessage(mutation.error)}</p>}
    </section>

    <section className="panel mt-6">
      <div className="panel-heading"><div><p className="panel-kicker">Fast scoring</p><h2 className="panel-title">Players on the court</h2></div><span className="count-pill">{onCourt.length}/5 active</span></div>
      <div className="mt-5 grid grid-cols-2 gap-2 rounded-2xl bg-white/5 p-1.5">
        {(['home', 'away'] as const).map((side) => { const sideTeam = side === 'home' ? gameData.home_team : gameData.away_team; const score = side === 'home' ? gameData.home_score : gameData.away_score; return <button className={`rounded-xl px-3 py-3 text-sm font-black transition ${activeSide === side ? 'bg-amber-400 text-slate-950 shadow-lg' : 'text-slate-300 hover:bg-white/5'}`} key={side} onClick={() => { setActiveSide(side); setBenchOpen(false) }}>{sideTeam.name} · {score}</button> })}
      </div>

      {onCourt.length ? <>
        <div className="mt-5 grid grid-cols-5 gap-2">
          {onCourt.map((player, index) => <button className={`min-w-0 rounded-2xl border p-2 text-center transition sm:p-3 ${selectedPlayer?.id === player.id ? 'border-amber-400 bg-amber-400/10' : 'border-white/10 bg-white/[.025]'}`} key={player.id} onClick={() => setSelectedStatId(player.id)}><span className="mx-auto grid h-9 w-9 place-items-center rounded-full bg-white/10 text-xs font-black">#{player.player_registration.jersey_number}</span><strong className="mt-2 block truncate text-[10px] sm:text-sm">{player.player_registration.player.display_name}</strong><span className="mt-1 block text-[10px] font-black text-amber-400">{player.points} PTS</span><span className="text-[9px] text-slate-500">Slot {player.court_slot ?? index + 1}</span></button>)}
        </div>
        {selectedPlayer && <div className="mt-5 rounded-2xl border border-white/10 bg-slate-950/60 p-4">
          <div className="flex items-center justify-between gap-3"><div><p className="text-xs font-black uppercase tracking-wider text-emerald-400">On court</p><h3 className="text-lg font-black">#{selectedPlayer.player_registration.jersey_number} {selectedPlayer.player_registration.player.display_name}</h3></div><strong className="text-2xl text-amber-400">{selectedPlayer.points} PTS</strong></div>
          <div className="mt-4 grid grid-cols-4 gap-2">{([1, 2, 3] as const).map((points) => <button className="rounded-xl bg-amber-400 px-2 py-3 text-sm font-black text-slate-950 disabled:opacity-40" disabled={!isLive || mutation.isPending} key={points} onClick={() => act({ action: 'score', team: activeSide, points, player_registration_id: selectedPlayer.player_registration.id })}>+{points} PT{points > 1 ? 'S' : ''}</button>)}<button className="rounded-xl border border-rose-400/30 px-2 py-3 text-xs font-black text-rose-300" disabled={!['live', 'final'].includes(gameData.status) || mutation.isPending || selectedPlayer.points === 0} onClick={() => correctPlayer(selectedPlayer)}>Correct</button></div>
          <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">{trackedStats.map(([stat, label]) => <div className="rounded-xl border border-white/10 p-2 text-center" key={stat}><p className="text-[10px] font-black text-slate-500">{label}</p><div className="mt-1 flex items-center justify-center gap-3"><button className="h-8 w-8 rounded-lg bg-white/10 font-black" disabled={!['live', 'final'].includes(gameData.status) || statMutation.isPending || selectedPlayer[stat] === 0} onClick={() => adjustStat(selectedPlayer, stat, -1)}>−</button><strong className="w-5 tabular-nums">{selectedPlayer[stat]}</strong><button className="h-8 w-8 rounded-lg bg-white/10 font-black" disabled={!['live', 'final'].includes(gameData.status) || statMutation.isPending} onClick={() => adjustStat(selectedPlayer, stat, 1)}>+</button></div></div>)}</div>
          <button className="button button-secondary mt-4 w-full" disabled={!['live', 'suspended'].includes(gameData.status) || bench.length === 0} onClick={() => setBenchOpen((open) => !open)}>Substitute {selectedPlayer.player_registration.player.display_name} ↔</button>
        </div>}
        {benchOpen && <div className="mt-4 rounded-2xl border border-amber-400/30 p-4"><p className="text-sm font-black">Choose the player coming in</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{bench.map((player) => <button className="flex items-center justify-between rounded-xl border border-white/10 bg-white/[.025] p-3 text-left hover:border-amber-400" disabled={substitutionMutation.isPending} key={player.id} onClick={() => selectedPlayer && substitutionMutation.mutate({ team: activeSide, player_out_registration_id: selectedPlayer.player_registration.id, player_in_registration_id: player.player_registration.id })}><span><strong>#{player.player_registration.jersey_number} {player.player_registration.player.display_name}</strong><small className="block text-slate-500">{player.points} PTS · {player.rebounds} REB · {player.assists} AST</small></span><span className="text-xs font-black text-amber-400">Bring in</span></button>)}</div></div>}
      </> : <div className="empty-state mt-5">Select and save five starters for {team?.name} before scoring individual players.</div>}
      {(statMutation.isError || substitutionMutation.isError) && <p className="form-error mt-4">{validationMessage(statMutation.error || substitutionMutation.error)}</p>}
    </section>

    <section className="panel mt-6">
      <button className="flex w-full items-center justify-between text-left" onClick={() => setLineupOpen((open) => !open)} type="button"><div><p className="panel-kicker">Pregame setup</p><h2 className="panel-title">Game roster and starting five</h2></div><span className="count-pill">{lineupOpen ? 'Close' : 'Edit lineup'}</span></button>
      {lineupOpen && <form className="mt-5" onSubmit={(event) => { event.preventDefault(); const form = new FormData(event.currentTarget); const homeStarters = form.getAll('home_starters').map(Number); const awayStarters = form.getAll('away_starters').map(Number); lineupMutation.mutate({ home_player_registration_ids: form.getAll('home_players').map(Number), away_player_registration_ids: form.getAll('away_players').map(Number), ...(homeStarters.length ? { home_starter_player_registration_ids: homeStarters } : {}), ...(awayStarters.length ? { away_starter_player_registration_ids: awayStarters } : {}) }) }}>
        <p className="text-sm text-slate-400">Select the available game roster, then mark exactly five starters for each team. Those five immediately become the players shown on court.</p>
        <div className="mt-5 grid gap-5 md:grid-cols-2">{(['home', 'away'] as const).map((side) => { const sideTeam = side === 'home' ? gameData.home_team : gameData.away_team; const rows = gameData.box_score.filter((row) => row.team_registration_id === sideTeam.registration_id); const selected = new Set(rows.map((row) => row.player_registration.id)); const starters = new Set(rows.filter((row) => row.is_starter).map((row) => row.player_registration.id)); return <fieldset className="rounded-2xl border border-white/10 p-4" key={side}><legend className="px-2 font-black">{sideTeam.name}</legend><div className="mb-2 grid grid-cols-[1fr_auto] gap-3 text-[10px] font-black uppercase tracking-wider text-slate-500"><span>Available player</span><span>Starter</span></div><div className="grid gap-2">{gameData.available_rosters?.[side]?.filter((player) => player.status === 'approved').map((player) => <label className="grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl bg-white/[.03] p-3 text-sm" key={player.id}><input defaultChecked={selected.has(player.id)} name={`${side}_players`} type="checkbox" value={player.id} /><span><strong className="block">#{player.jersey_number} {player.player.display_name}</strong><small className="text-slate-500">{player.position_label}</small></span><input aria-label={`Mark ${player.player.display_name} as a starter`} defaultChecked={starters.has(player.id)} name={`${side}_starters`} type="checkbox" value={player.id} /></label>)}</div></fieldset> })}</div>
        {lineupMutation.isError && <p className="form-error mt-4">{validationMessage(lineupMutation.error)}</p>}
        <button className="button button-primary mt-4" disabled={lineupMutation.isPending || ['final', 'cancelled', 'postponed'].includes(gameData.status)}>{lineupMutation.isPending ? 'Saving lineup…' : 'Save roster and starting five'}</button>
      </form>}
    </section>

    <section className="panel mt-6"><div className="panel-heading"><div><p className="panel-kicker">Audit trail</p><h2 className="panel-title">Recent game actions</h2></div><span className="count-pill">{gameData.live_events?.length ?? 0}</span></div><ol className="mt-5 space-y-2">{gameData.live_events?.length ? gameData.live_events.slice(0, 20).map((event) => <li className="flex items-center justify-between gap-3 rounded-xl border border-white/10 p-3" key={event.id}><div><p className="text-sm font-bold capitalize">{eventLabel(event, gameData)}</p><p className="mt-1 text-xs text-slate-500">{event.recorded_by?.name ?? 'System'} · {event.created_at ? new Date(event.created_at).toLocaleTimeString('en-PH') : ''}</p></div><span className="font-black tabular-nums">{event.home_score_after}–{event.away_score_after}</span></li>) : <li className="empty-state">No live actions yet.</li>}</ol></section>
  </main>
}

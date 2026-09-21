import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'

import { getPublicTeam } from '../features/teams/api'

export function PublicTeamPage() {
  const { organization = '', team = '' } = useParams()
  const query = useQuery({ queryKey: ['public-team', organization, team], queryFn: () => getPublicTeam(organization, team), retry: false })

  if (query.isPending) return <main className="grid min-h-[70vh] place-items-center text-slate-300">Loading team…</main>
  if (!query.data) return <main className="grid min-h-[70vh] place-items-center px-4 text-center"><div><h1 className="text-3xl font-black">Team unavailable</h1><Link className="button button-secondary mt-5" to="/">Return home</Link></div></main>

  const teamData = query.data
  return <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-16">
    <section className="overflow-hidden rounded-3xl border border-white/10 bg-slate-900/80"><div className="h-3" style={{ background: `linear-gradient(90deg, ${teamData.primary_color}, ${teamData.secondary_color})` }} /><div className="p-6 sm:p-10"><div className="flex flex-col gap-5 sm:flex-row sm:items-center">{teamData.logo_url ? <img className="h-24 w-24 rounded-3xl object-cover" src={teamData.logo_url} alt={`${teamData.name} logo`} /> : <span className="grid h-24 w-24 place-items-center rounded-3xl text-3xl font-black" style={{ background: teamData.primary_color, color: teamData.secondary_color }}>{(teamData.short_name || teamData.name).slice(0, 2).toUpperCase()}</span>}<div><p className="eyebrow">{teamData.organization.name}</p><h1 className="mt-2 text-4xl font-black tracking-tight">{teamData.name}</h1><p className="mt-2 text-slate-400">{teamData.location ?? 'Local basketball team'}</p></div></div></div></section>
    <div className="mt-8 space-y-6">{teamData.participations.length ? teamData.participations.map((participation) => <section className="panel" key={participation.id}><div className="panel-heading"><div><p className="panel-kicker">{participation.competition}</p><h2 className="panel-title">{participation.season} · {participation.division}</h2></div><span className="count-pill">{participation.roster.length} approved</span></div>{participation.roster.length ? <div className="mt-6 grid gap-3 sm:grid-cols-2">{participation.roster.map((player) => <article className="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[.03] p-4" key={player.id}>{player.photo_url ? <img className="h-14 w-14 rounded-full object-cover" src={player.photo_url} alt="" /> : <span className="grid h-14 w-14 place-items-center rounded-full bg-white/5 text-lg font-black">#{player.jersey_number}</span>}<div><strong>{player.name}</strong><p className="mt-1 text-sm text-slate-400">#{player.jersey_number} · {player.position_label}</p></div></article>)}</div> : <p className="empty-state mt-6">The approved roster has not been published yet.</p>}</section>) : <section className="panel"><p className="empty-state">No approved competition participation is published yet.</p></section>}</div>
  </main>
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'

import { validationMessage } from '../api/client'
import { useAuthQuery } from '../features/auth/api'
import { acceptInvitation, getInvitation, registerFromInvitation } from '../features/organizations/api'

export function InvitationAcceptPage() {
  const [params] = useSearchParams()
  const queryClient = useQueryClient()
  const auth = useAuthQuery()
  const token = params.get('token') ?? ''
  const invitation = useQuery({
    queryKey: ['invitation', token],
    queryFn: () => getInvitation(token),
    enabled: token.length === 64,
    retry: false,
  })
  const accept = useMutation({
    mutationFn: () => acceptInvitation(token),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['organizations'] })
      await queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
    },
  })
  const activate = useMutation({
    mutationFn: (input: { name: string; password: string; password_confirmation: string }) =>
      registerFromInvitation(token, input),
    onSuccess: async (user) => {
      queryClient.setQueryData(['auth', 'user'], user)
      await queryClient.invalidateQueries({ queryKey: ['organizations'] })
    },
  })

  function submitActivation(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    activate.mutate({
      name: String(data.get('name')),
      password: String(data.get('password')),
      password_confirmation: String(data.get('password_confirmation')),
    })
  }

  const redirect = encodeURIComponent(`/invitations/accept?token=${token}`)
  const error = invitation.error ?? accept.error ?? activate.error
  const organization = accept.data?.name ?? invitation.data?.organization.name
  const role = accept.data?.role ?? invitation.data?.role
  const completed = Boolean(accept.data || activate.data)

  return (
    <main className="grid min-h-[75vh] place-items-center px-4 py-12">
      <section className="panel w-full max-w-lg text-center">
        <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-400 text-2xl">🏀</span>
        <p className="eyebrow mt-6">Organization invitation</p>
        <h1 className="mt-3 text-3xl font-black">Join the operations team</h1>

        {invitation.isPending && token && <p className="mt-5 text-sm text-slate-400">Checking invitation…</p>}
        {!token && <p className="form-error mt-5">This invitation link is incomplete.</p>}
        {invitation.data && (
          <div className="mt-5 rounded-2xl border border-white/10 bg-white/[.03] p-4 text-left text-sm">
            <p className="font-bold text-white">{invitation.data.organization.name}</p>
            <p className="mt-1 text-slate-400">{invitation.data.email} · {invitation.data.role_label}</p>
          </div>
        )}
        {error && <p className="form-error mt-5">{validationMessage(error)}</p>}

        {completed ? (
          <div className="mt-6">
            <p className="form-success">You joined {organization} as {role?.replace('_', ' ')}.</p>
            <Link className="button button-primary mt-5" to="/app">Open dashboard</Link>
          </div>
        ) : auth.data ? (
          <div>
            <p className="mt-5 text-sm leading-6 text-slate-400">Signed in as {auth.data.email}. It must match the invited email address.</p>
            <button className="button button-primary mt-7" disabled={!invitation.data || accept.isPending} onClick={() => accept.mutate()}>
              {accept.isPending ? 'Accepting…' : 'Accept invitation'}
            </button>
          </div>
        ) : invitation.data ? (
          <form className="form-stack mt-6 text-left" onSubmit={submitActivation}>
            <label className="field">Your name<input name="name" autoComplete="name" required /></label>
            <label className="field">Create password<input name="password" type="password" autoComplete="new-password" minLength={8} required /></label>
            <label className="field">Confirm password<input name="password_confirmation" type="password" autoComplete="new-password" minLength={8} required /></label>
            <button className="button button-primary" disabled={activate.isPending}>
              {activate.isPending ? 'Creating account…' : 'Activate account and join'}
            </button>
            <p className="text-center text-xs leading-5 text-slate-500">
              Already have a staff account? <Link className="text-amber-300" to={`/login?redirect=${redirect}`}>Sign in</Link>
            </p>
          </form>
        ) : null}
      </section>
    </main>
  )
}

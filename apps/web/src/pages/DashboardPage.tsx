import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { useState } from 'react'
import { Link } from 'react-router'

import { validationMessage } from '../api/client'
import { resendVerification, useAuthQuery } from '../features/auth/api'
import {
  createOrganization,
  inviteMember,
  listMembers,
  listOrganizations,
  removeMember,
  updateMemberRole,
} from '../features/organizations/api'

const roles = [
  ['admin', 'Administrator'],
  ['league_manager', 'League manager'],
  ['scorer', 'Scorer'],
]

export function DashboardPage() {
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const organizations = useQuery({
    queryKey: ['organizations'],
    queryFn: listOrganizations,
  })
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null)
  const activeSlug = selectedSlug ?? organizations.data?.[0]?.slug ?? null
  const selected = organizations.data?.find(
    (organization) => organization.slug === activeSlug,
  )
  const members = useQuery({
    queryKey: ['organizations', activeSlug, 'members'],
    queryFn: () => listMembers(activeSlug!),
    enabled: Boolean(activeSlug && auth.data?.email_verified_at),
  })
  const verify = useMutation({ mutationFn: resendVerification })
  const create = useMutation({
    mutationFn: createOrganization,
    onSuccess: async (organization) => {
      await queryClient.invalidateQueries({ queryKey: ['organizations'] })
      setSelectedSlug(organization.slug)
    },
  })
  const invite = useMutation({
    mutationFn: (input: { email: string; role: string }) =>
      inviteMember(activeSlug!, input),
  })
  const updateRole = useMutation({
    mutationFn: ({
      membershipId,
      role,
    }: {
      membershipId: number
      role: string
    }) => updateMemberRole(activeSlug!, membershipId, role),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: ['organizations', activeSlug, 'members'],
      }),
  })
  const remove = useMutation({
    mutationFn: (membershipId: number) =>
      removeMember(activeSlug!, membershipId),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: ['organizations', activeSlug, 'members'],
      }),
  })

  function submitOrganization(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    create.mutate(
      {
        name: String(data.get('name')),
        description: String(data.get('description')),
      },
      {
        onSuccess: () => form.reset(),
      },
    )
  }

  function submitInvitation(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    invite.mutate(
      { email: String(data.get('email')), role: String(data.get('role')) },
      {
        onSuccess: () => form.reset(),
      },
    )
  }

  const canManageMembers =
    selected?.role === 'owner' ||
    selected?.role === 'admin' ||
    auth.data?.is_platform_admin

  return (
    <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="eyebrow">Organizer workspace</p>
          <h1 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
            Hello, {auth.data?.name}
          </h1>
          <p className="mt-2 text-slate-400">
            Set up your organization and decide who can operate each part of
            your leagues.
          </p>
        </div>
        <span className="self-start rounded-full border border-white/10 px-3 py-1.5 text-xs text-slate-300 sm:self-auto">
          P6 · Competition control
        </span>
      </div>

      {!auth.data?.email_verified_at && (
        <section className="mt-8 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-5">
          <h2 className="font-black text-amber-300">
            Verify your email to unlock organizer tools
          </h2>
          <p className="mt-2 text-sm leading-6 text-amber-100/80">
            Open Mailpit at localhost:8025 and follow the verification link, or
            send a new message.
          </p>
          <button
            className="button button-secondary mt-4"
            disabled={verify.isPending}
            onClick={() => verify.mutate()}
          >
            {verify.isPending ? 'Sending…' : 'Resend verification'}
          </button>
          {verify.data && (
            <p className="mt-3 text-sm text-emerald-300">{verify.data}</p>
          )}
        </section>
      )}

      <div className="mt-8 grid gap-6 lg:grid-cols-[.75fr_1.25fr]">
        <section className="space-y-6">
          <div className="panel">
            <div className="panel-heading">
              <div>
                <p className="panel-kicker">Organizations</p>
                <h2 className="panel-title">Your workspaces</h2>
              </div>
              <span className="count-pill">
                {organizations.data?.length ?? 0}
              </span>
            </div>
            <div className="mt-5 space-y-2">
              {organizations.isPending && (
                <p className="text-sm text-slate-400">Loading organizations…</p>
              )}
              {organizations.data?.map((organization) => (
                <button
                  className={`organization-row ${activeSlug === organization.slug ? 'organization-row-active' : ''}`}
                  key={organization.id}
                  onClick={() => setSelectedSlug(organization.slug)}
                >
                  <span>
                    <strong className="block text-left">
                      {organization.name}
                    </strong>
                    <small className="text-slate-400">
                      {organization.role.replace('_', ' ')}
                    </small>
                  </span>
                  <span className="text-slate-500">›</span>
                </button>
              ))}
              {!organizations.isPending && !organizations.data?.length && (
                <p className="empty-state">
                  {auth.data?.is_platform_admin
                    ? 'No organization yet. Create your first one below.'
                    : 'You do not belong to an organization yet. Access is granted by invitation.'}
                </p>
              )}
            </div>
          </div>

          {auth.data?.is_platform_admin && (
            <div className="panel">
              <p className="panel-kicker">New workspace</p>
              <h2 className="panel-title">Create organization</h2>
              <form className="form-stack mt-5" onSubmit={submitOrganization}>
                <label className="field">
                  Organization name
                  <input
                    name="name"
                    placeholder="Jaen Summer League"
                    required
                    disabled={!auth.data?.email_verified_at}
                  />
                </label>
                <label className="field">
                  Short description
                  <textarea
                    name="description"
                    rows={3}
                    placeholder="Who you are and which competitions you organize"
                    disabled={!auth.data?.email_verified_at}
                  />
                </label>
                {create.isError && (
                  <p className="form-error">
                    {validationMessage(create.error)}
                  </p>
                )}
                <button
                  className="button button-primary"
                  disabled={create.isPending || !auth.data?.email_verified_at}
                >
                  {create.isPending ? 'Creating…' : 'Create organization'}
                </button>
              </form>
            </div>
          )}
        </section>

        <section className="panel min-h-[520px]">
          {!selected ? (
            <div className="grid min-h-[440px] place-items-center text-center">
              <div>
                <p className="text-5xl">🏀</p>
                <h2 className="mt-4 text-xl font-black">
                  Select an organization
                </h2>
                <p className="mt-2 text-sm text-slate-400">
                  Members and access controls will appear here.
                </p>
              </div>
            </div>
          ) : (
            <>
              <div className="panel-heading">
                <div>
                  <p className="panel-kicker">Team access</p>
                  <h2 className="panel-title">{selected.name}</h2>
                  <p className="mt-1 text-sm capitalize text-slate-400">
                    Your role: {selected.role.replace('_', ' ')}
                  </p>
                </div>
                <span className="count-pill">
                  {members.data?.length ?? selected.member_count ?? 0} people
                </span>
              </div>
              <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                  className="button button-secondary"
                  to={`/app/organizations/${selected.slug}/competitions`}
                >
                  Competitions
                </Link>
                <Link
                  className="button button-secondary"
                  to={`/app/organizations/${selected.slug}/teams`}
                >
                  Teams and rosters
                </Link>
                <Link
                  className="button button-secondary"
                  to={`/app/organizations/${selected.slug}/schedule`}
                >
                  Schedule and results
                </Link>
                <Link
                  className="button button-primary"
                  to={`/app/organizations/${selected.slug}/competition-control`}
                >
                  Standings and brackets
                </Link>
              </div>

              {canManageMembers && (
                <form
                  className="mt-6 grid gap-3 rounded-2xl border border-white/10 bg-white/[.03] p-4 sm:grid-cols-[1fr_180px_auto]"
                  onSubmit={submitInvitation}
                >
                  <label className="field">
                    <span className="sr-only">Email</span>
                    <input
                      name="email"
                      type="email"
                      placeholder="staff@example.com"
                      required
                    />
                  </label>
                  <label className="field">
                    <span className="sr-only">Role</span>
                    <select name="role" defaultValue="scorer">
                      {roles.map(([value, label]) => (
                        <option key={value} value={value}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <button
                    className="button button-primary"
                    disabled={invite.isPending}
                  >
                    {invite.isPending ? 'Sending…' : 'Invite'}
                  </button>
                  {invite.isError && (
                    <p className="form-error sm:col-span-3">
                      {validationMessage(invite.error)}
                    </p>
                  )}
                  {invite.isSuccess && (
                    <p className="form-success sm:col-span-3">
                      Invitation sent. Preview it in Mailpit.
                    </p>
                  )}
                </form>
              )}

              <div className="mt-6 divide-y divide-white/10">
                {members.isPending && (
                  <p className="py-8 text-center text-sm text-slate-400">
                    Loading members…
                  </p>
                )}
                {members.data?.map((member) => {
                  const canEdit = canManageMembers && member.role !== 'owner'
                  return (
                    <div
                      className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                      key={member.id}
                    >
                      <div className="flex items-center gap-3">
                        <span className="avatar">
                          {member.user.name.slice(0, 2).toUpperCase()}
                        </span>
                        <span>
                          <strong className="block">{member.user.name}</strong>
                          <small className="text-slate-400">
                            {member.user.email}
                          </small>
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        {canEdit ? (
                          <select
                            className="compact-select"
                            value={member.role}
                            onChange={(event) =>
                              updateRole.mutate({
                                membershipId: member.id,
                                role: event.target.value,
                              })
                            }
                          >
                            {roles.map(([value, label]) => (
                              <option key={value} value={value}>
                                {label}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <span className="role-pill">{member.role_label}</span>
                        )}
                        {canEdit && (
                          <button
                            className="danger-button"
                            disabled={remove.isPending}
                            onClick={() => remove.mutate(member.id)}
                          >
                            Remove
                          </button>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
              {(updateRole.isError || remove.isError) && (
                <p className="form-error mt-4">
                  {validationMessage(updateRole.error ?? remove.error)}
                </p>
              )}
            </>
          )}
        </section>
      </div>
    </main>
  )
}

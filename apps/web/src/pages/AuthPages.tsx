import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { FormEvent, ReactNode } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router'

import { validationMessage } from '../api/client'
import {
  forgotPassword,
  login,
  resetPassword,
  useAuthQuery,
} from '../features/auth/api'

function AuthShell({ eyebrow, title, detail, children }: {
  eyebrow: string
  title: string
  detail: string
  children: ReactNode
}) {
  return (
    <main className="auth-background px-4 py-12 sm:py-20">
      <section className="mx-auto w-full max-w-md rounded-3xl border border-white/10 bg-slate-900/90 p-6 shadow-2xl shadow-black/30 backdrop-blur sm:p-8">
        <p className="eyebrow">{eyebrow}</p>
        <h1 className="mt-3 text-3xl font-black tracking-tight">{title}</h1>
        <p className="mt-3 text-sm leading-6 text-slate-400">{detail}</p>
        <div className="mt-8">{children}</div>
      </section>
    </main>
  )
}

function SubmitButton({ pending, children }: { pending: boolean; children: ReactNode }) {
  return <button className="button button-primary mt-2 w-full" disabled={pending}>{pending ? 'Please wait…' : children}</button>
}

export function LoginPage() {
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const signIn = useMutation({
    mutationFn: login,
    onSuccess: (user) => {
      queryClient.setQueryData(['auth', 'user'], user)
      navigate(params.get('redirect') || '/app')
    },
  })

  if (auth.data) return <Navigate replace to="/app" />

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    signIn.mutate({
      email: String(data.get('email')),
      password: String(data.get('password')),
      remember: data.get('remember') === 'on',
    })
  }

  return (
    <AuthShell eyebrow="Welcome back" title="Sign in" detail="Manage your organization, staff access, and upcoming leagues.">
      <form className="form-stack" onSubmit={submit}>
        <label className="field">Email address<input name="email" type="email" autoComplete="email" required /></label>
        <label className="field">Password<input name="password" type="password" autoComplete="current-password" required /></label>
        <div className="flex items-center justify-between gap-4 text-sm">
          <label className="flex items-center gap-2 text-slate-300"><input name="remember" type="checkbox" /> Remember me</label>
          <Link className="text-amber-400 hover:text-amber-300" to="/forgot-password">Forgot password?</Link>
        </div>
        {signIn.isError && <p className="form-error">{validationMessage(signIn.error)}</p>}
        <SubmitButton pending={signIn.isPending}>Sign in</SubmitButton>
      </form>
      <p className="mt-6 text-center text-sm text-slate-400">Staff accounts are created securely from an organization invitation.</p>
    </AuthShell>
  )
}

export function ForgotPasswordPage() {
  const requestReset = useMutation({ mutationFn: forgotPassword })

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    requestReset.mutate(String(new FormData(event.currentTarget).get('email')))
  }

  return (
    <AuthShell eyebrow="Account recovery" title="Reset your password" detail="We’ll send a secure reset link to your development inbox in Mailpit.">
      <form className="form-stack" onSubmit={submit}>
        <label className="field">Email address<input name="email" type="email" autoComplete="email" required /></label>
        {requestReset.isError && <p className="form-error">{validationMessage(requestReset.error)}</p>}
        {requestReset.data && <p className="form-success">{requestReset.data}</p>}
        <SubmitButton pending={requestReset.isPending}>Send reset link</SubmitButton>
      </form>
      <Link className="mt-6 block text-center text-sm text-amber-400" to="/login">Back to sign in</Link>
    </AuthShell>
  )
}

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const reset = useMutation({
    mutationFn: resetPassword,
    onSuccess: () => navigate('/login?reset=1'),
  })

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    reset.mutate({
      token: params.get('token') ?? '',
      email: params.get('email') ?? '',
      password: String(data.get('password')),
      password_confirmation: String(data.get('password_confirmation')),
    })
  }

  return (
    <AuthShell eyebrow="Choose a password" title="Secure your account" detail={`Resetting access for ${params.get('email') ?? 'your account'}.`}>
      <form className="form-stack" onSubmit={submit}>
        <label className="field">New password<input name="password" type="password" minLength={8} autoComplete="new-password" required /></label>
        <label className="field">Confirm password<input name="password_confirmation" type="password" minLength={8} autoComplete="new-password" required /></label>
        {reset.isError && <p className="form-error">{validationMessage(reset.error)}</p>}
        <SubmitButton pending={reset.isPending}>Update password</SubmitButton>
      </form>
    </AuthShell>
  )
}

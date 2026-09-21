import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, Navigate, Outlet, useNavigate } from 'react-router'

import { logout, useAuthQuery } from './features/auth/api'
import { useHealthQuery } from './features/health/api'

function Brand() {
  return (
    <Link className="flex items-center gap-3 font-black tracking-tight" to="/">
      <span className="grid h-9 w-9 place-items-center rounded-full bg-amber-400 text-lg text-slate-950">
        GB
      </span>
      <span className="hidden sm:inline">Grassroots Basketball</span>
    </Link>
  )
}

function ApiStatus() {
  const health = useHealthQuery()
  const ready = health.isSuccess

  return (
    <span className={`status ${ready ? 'status-ready' : health.isError ? 'status-error' : 'status-pending'}`}>
      <span className="h-1.5 w-1.5 rounded-full bg-current" />
      {ready ? 'Live' : health.isError ? 'Offline' : 'Checking'}
    </span>
  )
}

type Theme = 'dark' | 'light'

function initialTheme(): Theme {
  const saved = window.localStorage.getItem('gb-theme')
  if (saved === 'dark' || saved === 'light') return saved
  return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'
}

function ThemeToggle() {
  const [theme, setTheme] = useState<Theme>(initialTheme)
  useEffect(() => {
    document.documentElement.classList.toggle('light', theme === 'light')
    document.documentElement.style.colorScheme = theme
    window.localStorage.setItem('gb-theme', theme)
  }, [theme])
  const next = theme === 'dark' ? 'light' : 'dark'
  return <button aria-label={`Use ${next} theme`} className="theme-toggle" onClick={() => setTheme(next)} title={`Use ${next} theme`} type="button"><span aria-hidden="true">{theme === 'dark' ? '☀' : '☾'}</span><span className="hidden sm:inline">{theme === 'dark' ? 'Light' : 'Dark'}</span></button>
}

export function AppLayout() {
  const auth = useAuthQuery()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const signOut = useMutation({
    mutationFn: logout,
    onSuccess: async () => {
      queryClient.setQueryData(['auth', 'user'], null)
      await queryClient.invalidateQueries()
      navigate('/')
    },
  })

  return (
    <div className="app-shell min-h-screen">
      <header className="app-header sticky top-0 z-30 backdrop-blur-xl">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
          <Brand />
          <nav className="flex items-center gap-2" aria-label="Account navigation">
            <ApiStatus />
            <ThemeToggle />
            <Link className="nav-link hidden md:block" to="/organizations/jaen-community-basketball/competitions/jaen-inter-purok-basketball/seasons/2026-pilot-season">Pilot league</Link>
            {auth.data ? (
              <>
                <Link className="nav-link" to="/app">Dashboard</Link>
                <button className="nav-link" disabled={signOut.isPending} onClick={() => signOut.mutate()}>
                  Sign out
                </button>
              </>
            ) : (
              <Link className="nav-link" to="/login">Organizer sign in</Link>
            )}
          </nav>
        </div>
      </header>
      <Outlet />
    </div>
  )
}

export function ProtectedRoute() {
  const auth = useAuthQuery()

  if (auth.isPending) {
    return <main className="grid min-h-[70vh] place-items-center text-slate-300">Loading your account…</main>
  }

  if (!auth.data) return <Navigate replace to="/login" />

  return <Outlet />
}

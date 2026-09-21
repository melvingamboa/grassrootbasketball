import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router'

import { AppLayout, ProtectedRoute } from './App'
import './index.css'
import {
  ForgotPasswordPage,
  LoginPage,
  ResetPasswordPage,
} from './pages/AuthPages'
import { DashboardPage } from './pages/DashboardPage'
import { CompetitionManagementPage } from './pages/CompetitionManagementPage'
import { CompetitionControlPage } from './pages/CompetitionControlPage'
import { HomePage } from './pages/HomePage'
import { InvitationAcceptPage } from './pages/InvitationAcceptPage'
import { PublicCompetitionPage } from './pages/PublicCompetitionPage'
import { PublicGamePage } from './pages/PublicGamePage'
import { PublicTeamPage } from './pages/PublicTeamPage'
import { ScheduleManagementPage } from './pages/ScheduleManagementPage'
import { LiveGameControlPage } from './pages/LiveGameControlPage'
import { TeamManagementPage } from './pages/TeamManagementPage'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { refetchOnWindowFocus: false, staleTime: 15_000 },
    mutations: { retry: false },
  },
})

const router = createBrowserRouter([
  {
    path: '/',
    Component: AppLayout,
    children: [
      { index: true, Component: HomePage },
      { path: 'login', Component: LoginPage },
      { path: 'forgot-password', Component: ForgotPasswordPage },
      { path: 'reset-password', Component: ResetPasswordPage },
      { path: 'invitations/accept', Component: InvitationAcceptPage },
      {
        path: 'organizations/:organization/teams/:team',
        Component: PublicTeamPage,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season',
        element: <PublicCompetitionPage />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/today',
        element: <PublicCompetitionPage view="today" />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/schedule',
        element: <PublicCompetitionPage view="schedule" />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/results',
        element: <PublicCompetitionPage view="results" />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/standings',
        element: <PublicCompetitionPage view="standings" />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/bracket',
        element: <PublicCompetitionPage view="bracket" />,
      },
      {
        path: 'organizations/:organization/competitions/:competition/seasons/:season/games/:game',
        Component: PublicGamePage,
      },
      {
        Component: ProtectedRoute,
        children: [
          { path: 'app', Component: DashboardPage },
          {
            path: 'app/organizations/:organization/competitions',
            Component: CompetitionManagementPage,
          },
          {
            path: 'app/organizations/:organization/teams',
            Component: TeamManagementPage,
          },
          {
            path: 'app/organizations/:organization/schedule',
            Component: ScheduleManagementPage,
          },
          {
            path: 'app/organizations/:organization/competitions/:competition/seasons/:season/games/:game/live',
            Component: LiveGameControlPage,
          },
          {
            path: 'app/organizations/:organization/competition-control',
            Component: CompetitionControlPage,
          },
        ],
      },
    ],
  },
])

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  </StrictMode>,
)

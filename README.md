# Grassroots Basketball League Platform

Mobile-first league management and live game platform for grassroots basketball competitions.

## P0 Lite baseline

- One inter-purok pilot with one open division.
- Maximum registered roster: **20 players per team**.
- Single round robin with a top-four single-game playoff baseline.
- Four 10-minute quarters and five-minute overtime baseline.
- One official scorer device per game.
- Initial live data: score, period, clock, team fouls, and timeouts.
- Player statistics, native mobile, and advanced brackets are deferred.

All competition values remain configurable. See `docs/decisions/P0-LITE-BASELINE.md`.

## Stack

- Laravel 13 API on PHP 8.4
- React 19, TypeScript, Vite, React Router
- Tailwind CSS 4
- Axios and TanStack Query
- MySQL 8.4
- Redis 7.4
- Docker Compose

## Prerequisites

- Docker Desktop with WSL2 integration enabled for `Ubuntu-20.04`
- Docker Compose v2

The repository is intended to live inside WSL for better container bind-mount performance.

## Start the project

```bash
cd /home/oneechan/i4projects/grassroots-basketball-platform
cp .env.example .env
docker compose up -d --build
docker compose exec api php artisan migrate
```

Open:

- Web application: http://localhost:5175
- Laravel API health: http://localhost:8010/api/v1/health
- Mailpit: http://localhost:8025

## TablePlus connection

Create a MySQL connection with:

| Field | Value |
|---|---|
| Host | `127.0.0.1` |
| Port | `3308` |
| User | `grassroots` |
| Password | `grassroots_local` |
| Database | `grassroots_basketball` |
| SSL | Off for localhost |

The non-default host port `3308` avoids conflicts with existing local MySQL containers. Inside Docker, Laravel connects to `mysql:3306`.

## P2 local review accounts

Open `http://localhost:5175/login` and use either account:

| Account | Email | Password | Access |
|---|---|---|---|
| Platform administrator | `admin@grassroots.test` | `password` | Creates organizations and has global oversight. |
| Demo organizer | `organizer@grassroots.test` | `password` | Owns the seeded Jaen Community Basketball workspace. |

Public viewers do not need accounts. Public registration is disabled; organizer and staff accounts are activated through invitations. Invitation and password-reset emails can be inspected in Mailpit at `http://localhost:8025`.

## P3 local pilot configuration

Sign in as the demo organizer, select **Jaen Community Basketball**, then choose **Manage competitions and venues**. The local seed includes a geographic hierarchy, venue, competition, 2026 season settings, and Open Division that can be edited during review.

## P4 teams and rosters

From the same organization dashboard, choose **Teams and rosters**, select the competition, season, and team, then use **Create player and add to roster**. This primary workflow creates the reusable player profile and team registration together. Returning players can instead be selected from the existing-player panel, and the complete player directory remains available under advanced tools. Viewers can open the seeded public roster at `http://localhost:5175/organizations/jaen-community-basketball/teams/lambakin-ballers` without signing in.

Media uses `MEDIA_DISK=public` locally. Set `MEDIA_DISK=s3` with the AWS/S3-compatible environment values for production object storage.

## P5 schedule, announcements, and results

From the organization dashboard, choose **Schedule and results** to publish fixtures, review team or venue conflict warnings, reschedule with an audit reason, announce disruptions, and enter official final scores. Published notices and game information are available without an account.

Open the seeded public alpha at `http://localhost:5175/organizations/jaen-community-basketball/competitions/jaen-inter-purok-basketball/seasons/2026-pilot-season`. It includes a competition home, today's games, the full schedule, official results, and game-detail pages.

## Common commands

```bash
docker compose ps
docker compose logs -f api web
docker compose exec api php artisan test
docker compose exec api ./vendor/bin/pint --test
docker compose exec web npm run lint
docker compose exec web npm run build
docker compose down
```

Do not use `docker compose down -v` unless you intentionally want to erase the local MySQL and Redis volumes.

## Project structure

```text
apps/api       Laravel REST API
apps/web       React/Vite web application
docker         Development container definitions
docs           Architecture decisions and project status
compose.yaml   Local development environment
```

## Current status

See `docs/PROJECT_STATUS.md`.

# P1 Foundation Architecture

## Decision

Use a modular API-first monorepo with independently deployable Laravel and React applications.

```text
React/Vite web client
        |
        | REST /api/v1
        v
Laravel API ---- MySQL
        |
        +-------- Redis
```

## Development topology

Docker Compose provides:

- `api`: PHP 8.4 and Laravel 13 development server
- `web`: Node 22 and Vite development server
- `mysql`: MySQL 8.4 with a persistent named volume
- `redis`: Redis 7.4 with append-only persistence
- `mailpit`: local email capture

MySQL is published on `127.0.0.1:3308` for TablePlus. Port 3308 avoids conflicts with existing local MySQL containers.

## Deliberate P1 exclusions

- Authentication and Sanctum configuration belong to P2.
- Organization models and authorization belong to P2.
- Horizon and Reverb processes are introduced with live functionality.
- Product entities and migrations begin in P3.

## Source-of-truth rule

The Laravel API owns product validation and data. React treats API responses as server state through TanStack Query. The web client does not duplicate business rules as authoritative logic.

# P2 Identity and Access Foundation

## Outcome

P2 establishes secure identity and multi-organizer access without restricting public viewers. The public site remains open and viewers do not create accounts during the pilot. Accounts exist only for authorized organization and league operations.

## Authentication

- Laravel Sanctum cookie-based SPA authentication with CSRF protection
- No public registration endpoint or viewer account requirement
- Invitation-based staff account activation, plus login, logout, and current-user endpoints
- Email verification links delivered to Mailpit in local development
- Password reset links routed back to the React application
- Login throttling and generic recovery responses to reduce account enumeration
- Sanctum remains capable of supporting future React Native bearer tokens through the same API

## Authorization model

Platform administrators have a global override. Each organization otherwise grants one role per member:

Only a platform administrator can create an organization. The new organization receives an owner membership, and its owner or administrator can invite operational staff.

| Role | Organization settings | Members and invitations | League management | Live scoring |
|---|---:|---:|---:|---:|
| Owner | Yes | Yes, including owners | Yes | Yes |
| Administrator | Yes | Yes, excluding owner access | Yes | Yes |
| League Manager | No | No | Yes | Yes |
| Scorer | No | No | No | Yes |

League and scoring permissions are defined now and will be applied when those resources arrive in later phases.

## Safety decisions

- Invitation tokens are stored only as SHA-256 hashes and expire after seven days.
- An invitation can activate a new account only for the email address chosen by the organization.
- Newly activated invitation accounts are treated as email-verified because control of the invitation email has already been demonstrated.
- Invitation acceptance requires the authenticated email to match the invited email.
- The final owner cannot be removed or demoted.
- Administrators cannot grant, change, or remove owner access.
- Organization creation and initial ownership are committed in one database transaction.
- Authorization is enforced by Laravel policies and services, not only by the React interface.

## Local review accounts

| Account | Email | Password | Role |
|---|---|---|---|
| Platform administrator | `admin@grassroots.test` | `password` | Creates organizations; global override |
| Demo organizer | `organizer@grassroots.test` | `password` | Owner of Jaen Community Basketball |

These deterministic accounts are created only when the Laravel environment is `local`.

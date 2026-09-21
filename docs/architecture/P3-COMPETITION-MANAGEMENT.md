# P3 Locations and Competition Management

## Outcome

P3 lets authorized organizers configure the complete structure of a pilot season: geographic coverage, venues, reusable competitions, season editions, rules, and divisions. The domain remains generic and supports inter-purok, inter-barangay, inter-town, open, and invitational competitions.

## Domain model

- `administrative_areas` is a validated province > municipality/city > barangay > purok hierarchy.
- `venues` belong to an organization and store an area, address, optional coordinates, and active/inactive status.
- `competitions` belong to an organization and represent a reusable league or tournament identity.
- `seasons` are dated competition editions with a timezone, format, lifecycle, primary venue, and basketball rules.
- `divisions` belong to a season and support open, seniors, juniors, and custom categories.

Creating a season transactionally creates an Open Division. The approved pilot maximum of 20 registered players is enforced by season validation.

## Lifecycles

- Competition: draft, active, completed, archived
- Season: registration, scheduled, active, completed, archived
- Venue: active, inactive

Historical records are archived through statuses rather than deleted.

## Authorization

- Platform administrators can manage every organization and create administrative areas.
- Owners, administrators, and league managers can manage their organization's competition configuration.
- Scorers have read-only organization access at this phase.
- Outsiders cannot read or mutate another organization's operational configuration.
- A season cannot reference a venue from another organization.

## API surface

- `GET|POST /api/v1/administrative-areas`
- `GET|POST /api/v1/organizations/{organization}/venues`
- `PATCH /api/v1/organizations/{organization}/venues/{venue}`
- `GET|POST /api/v1/organizations/{organization}/competitions`
- `GET|PATCH /api/v1/organizations/{organization}/competitions/{competition}`
- `POST /api/v1/organizations/{organization}/competitions/{competition}/seasons`
- `PATCH /api/v1/organizations/{organization}/competitions/{competition}/seasons/{season}`
- `POST|PATCH|DELETE .../seasons/{season}/divisions/{division?}`

All write endpoints require an authenticated, verified account and server-side authorization.

## Organizer experience

From the access dashboard, select an organization and open **Manage competitions and venues**. The responsive P3 workspace provides competition list/create/view/edit, venue directory/create/edit, season edition/settings, lifecycle controls, and division management with loading, empty, success, and validation-error handling.

## Local pilot data

The local seed includes the Nueva Ecija > Jaen > Lambakin > Purok 1 hierarchy, Jaen Municipal Gym, Jaen Inter-Purok Basketball, a 2026 pilot season, and its Open Division. These are demonstration records only; no application logic is hard-coded to Jaen.

# P4 Teams and Player Registration

## Outcome

P4 gives authorized organizers a complete workflow for reusable teams and players, division registration, roster approval, and safe public roster publishing.

## Domain model

- `teams` belong to an organization and store reusable identity, location, colors, status, and an optional logo.
- `players` belong to an organization and store reusable identity plus private operational fields.
- `season_team_registrations` place one team into one division for a season.
- `player_registrations` assign a player, jersey number, position, and approval status to that season team.

Team and player identities can be reused in later seasons without duplicating historical records.

## Roster rules

- A team can be registered only once per season.
- A player can appear only once on a season team roster.
- Jersey numbers range from 0 through 99 and are unique per roster.
- A roster cannot exceed the season's configured maximum, which is capped at the approved 20-player pilot limit.
- Team and player registrations support pending, approved, rejected, and withdrawn states.
- Only approved teams and approved, active players appear publicly.

## Media storage

Team logos and player photos accept JPG, PNG, and WebP images up to 2 MB. Files use the configurable `MEDIA_DISK`; local development uses Laravel's public disk and production can use the installed S3-compatible Flysystem adapter. Replacing an image removes the previous object.

## Public data boundary

The public team resource exposes team identity, current approved competition participation, and approved roster names, photos, jersey numbers, and positions. It never serializes player birth dates, email addresses, phone numbers, internal status, or other organizer fields.

## Authorization

- Platform administrators, organization owners, administrators, and league managers can manage teams and rosters.
- Scorers can view organization operations but cannot change P4 configuration.
- Outsiders cannot access another organization's team or player directory.
- Teams, players, divisions, season registrations, and roster registrations are validated against the parent organization and season.

## Organizer experience

Select an organization and open **Teams and rosters**. After choosing a competition, season, and registered team, the primary roster workflow is **Create player and add to roster**. One mobile-friendly form creates the reusable organization player and the season roster registration in a single database transaction, including jersey number, position, approval status, and optional private contact details.

The quick-add operation enforces the 20-player roster limit, duplicate-player checks, and unique jersey numbers before either record is committed. If validation fails, no orphan player record is left behind. Organizers can still select an existing player for returning athletes, while the organization-wide player directory is kept in an advanced panel to reduce clutter during normal roster entry.

## Public experience

Public team pages are available at `/organizations/{organization}/teams/{team}` without an account. The homepage links to the seeded Lambakin Ballers roster for local review.

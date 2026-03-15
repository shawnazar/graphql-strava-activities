# Admin Settings

The plugin settings are at **Strava → Settings** in the WordPress admin.

## Credentials

| Setting | Description |
|---|---|
| Client ID | Your Strava API application's Client ID |
| Client Secret | Your app's Client Secret (encrypted at rest if configured) |
| Access Token | OAuth access token (encrypted at rest if configured) |
| Refresh Token | OAuth refresh token (encrypted at rest if configured) |
| Token Expires At | Unix timestamp when the access token expires |

The plugin automatically refreshes expired tokens using your refresh token.

## Display

| Setting | Description | Default |
|---|---|---|
| Distance Unit | Miles or kilometres | Miles |
| Activities to Fetch | Number of activities to sync from Strava (max 200) | 200 |
| Include Activities Without Routes | Show indoor/treadmill/yoga activities without GPS data | Off |

## SVG Customisation

| Setting | Description | Default |
|---|---|---|
| Stroke Colour | Hex colour for route map lines | `#0d9488` |
| Stroke Width | Line thickness in pixels | `2.5` |

These can also be overridden with filters — see [Filters & Hooks](filters.md).

## Sync

| Setting | Description | Default |
|---|---|---|
| Sync Frequency | How often to refresh activity data via WordPress cron | Every 12 Hours |
| Last Sync | Timestamp of the most recent sync | — |
| Cached Activities | Number of activities currently cached | — |

### Available Sync Frequencies

| Frequency | Syncs/Day | Est. API Calls/Day |
|---|---|---|
| Every 15 Minutes | 96 | ~576 |
| Every 30 Minutes | 48 | ~288 |
| Every Hour | 24 | ~144 |
| Every 2 Hours | 12 | ~72 |
| Every 4 Hours | 6 | ~36 |
| Every 6 Hours | 4 | ~24 |
| Every 12 Hours | 2 | ~12 |
| Once Daily | 1 | ~6 |
| Once Weekly | 1/7 | ~1 |
| Every 2 Weeks | 1/14 | ~1 |
| Monthly | 1/30 | ~1 |

Strava's API limit is **1,000 calls per day**. Each sync uses approximately 6 calls (1 list + up to 5 detail requests for photos).

### Manual Resync

Click **Resync Activities** to immediately refresh cached data regardless of the cron schedule.

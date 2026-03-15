# Strava API

The plugin uses the [Strava API v3](https://developers.strava.com/) to fetch activity data.

## Endpoints Used

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v3/athlete/activities` | GET | Fetch list of recent activities |
| `/api/v3/activities/{id}` | GET | Fetch activity detail (for photos) |
| `/oauth/token` | POST | Refresh expired access token |

All requests go to `https://www.strava.com`.

## Authentication

The plugin uses OAuth 2.0 Bearer tokens:

```
Authorization: Bearer {access_token}
```

When the access token expires (checked via `wpgraphql_strava_token_expires_at`), the plugin automatically refreshes it using the stored refresh token and client credentials.

## Rate Limits

| Limit | Value |
|---|---|
| Short-term | 100 requests per 15 minutes |
| Daily | 1,000 requests per day |

### Plugin Usage Per Sync

Each cache refresh uses approximately:

- **1** list request (`/athlete/activities`)
- **Up to 5** detail requests (`/activities/{id}`) for photo enrichment
- **Total**: ~6 requests per sync

The plugin includes a **200ms delay** between detail requests to avoid bursting.

### Estimated Daily Usage by Frequency

| Sync Frequency | Est. Daily Calls |
|---|---|
| Every 15 min | ~576 |
| Every 12 hours (default) | ~12 |
| Daily | ~6 |
| Weekly | ~1 |
| Monthly | ~1 |

## Data Model

### List Endpoint Response

The `/athlete/activities` endpoint returns an array of activity summaries. Key fields used by the plugin:

| API Field | Plugin Field | Notes |
|---|---|---|
| `name` | `title` | |
| `distance` | `distance` | Converted to mi/km |
| `moving_time` | `duration` | Formatted as "1h 16m" |
| `start_date` | `date` | ISO 8601 |
| `type` | `type` | |
| `map.summary_polyline` | `svgMap` | Decoded and rendered to SVG |
| `id` | `stravaUrl` | Constructed as `https://www.strava.com/activities/{id}` |
| `total_elevation_gain` | `elevationGain` | Metres |
| `average_speed` | `averageSpeed` | m/s |
| `max_speed` | `maxSpeed` | m/s |
| `average_heartrate` | `averageHeartrate` | bpm, nullable |
| `max_heartrate` | `maxHeartrate` | bpm, nullable |
| `kilojoules` | `calories` | Converted: kJ × 0.239 |
| `kudos_count` | `kudosCount` | |
| `comment_count` | `commentCount` | |
| `location_city` | `city` | |
| `location_country` | `country` | |
| `private` | `isPrivate` | |

### Detail Endpoint Response

The `/activities/{id}` endpoint returns a full activity detail. The plugin only uses it to fetch the primary photo URL from `photos.primary.urls`.

## Token Refresh Flow

```
1. Check if access token has expired (expires_at < now)
2. POST to /oauth/token with:
   - client_id
   - client_secret
   - refresh_token
   - grant_type=refresh_token
3. Store new access_token, refresh_token, expires_at
4. Retry the original API request with the new token
```

On a 401 response, the plugin also attempts a token refresh before returning empty results.

## References

- [Strava API Documentation](https://developers.strava.com/docs/reference/)
- [Strava API Agreement](https://www.strava.com/legal/api)
- [Strava Brand Guidelines](https://developers.strava.com/guidelines/)

# Activity Fields

The `StravaActivity` type exposes 21 fields. All data comes from the Strava list endpoint — no extra API calls needed per activity.

## Field Reference

| Field | Type | Description | Source |
|---|---|---|---|
| `title` | String | Activity name | `name` |
| `distance` | Float | Distance in miles or km (based on settings) | `distance` |
| `duration` | String | Formatted moving time (e.g. "1h 16m") | `moving_time` |
| `date` | String | Start date in ISO 8601 format | `start_date` |
| `type` | String | Activity type (Ride, Run, Walk, etc.) | `type` |
| `unit` | String | Distance unit — "mi" or "km" | Setting |
| `svgMap` | String | Inline SVG route map markup | `map.summary_polyline` |
| `stravaUrl` | String | Link to the activity on Strava | Constructed from `id` |
| `photoUrl` | String | Primary activity photo URL (nullable) | `photos.primary.urls` |
| `elevationGain` | Float | Total elevation gain in metres | `total_elevation_gain` |
| `averageSpeed` | Float | Average speed in m/s | `average_speed` |
| `maxSpeed` | Float | Maximum speed in m/s | `max_speed` |
| `averageHeartrate` | Float | Average heart rate in bpm (nullable) | `average_heartrate` |
| `maxHeartrate` | Int | Max heart rate in bpm (nullable) | `max_heartrate` |
| `calories` | Float | Estimated calories burned (nullable) | `kilojoules` × 0.239 |
| `kudosCount` | Int | Number of kudos | `kudos_count` |
| `commentCount` | Int | Number of comments | `comment_count` |
| `city` | String | City where the activity started | `location_city` |
| `country` | String | Country where the activity started | `location_country` |
| `isPrivate` | Boolean | Whether this is a private activity | `private` |
| `poweredByStrava` | String | Strava attribution text for brand compliance | Static |

## Nullable Fields

These fields may return `null` if the data is unavailable:

- `photoUrl` — only present if the activity has a primary photo
- `averageHeartrate` / `maxHeartrate` — requires a heart rate monitor
- `calories` — only available for certain activity types

## Distance Units

The `distance` field is automatically converted based on the **Display Unit** setting:

- **Miles** (`mi`) — default
- **Kilometres** (`km`)

The `unit` field tells you which unit is in use, so your frontend can display the correct label.

## Activity Types

Common Strava activity types:

`Ride`, `Run`, `Walk`, `Hike`, `Swim`, `WeightTraining`, `Yoga`, `Workout`, `VirtualRide`, `VirtualRun`, `EBikeRide`, `Kayaking`, `RockClimbing`, `Snowboard`, `Ski`, `IceSkate`

Filter by type using the `type` query argument or the `wpgraphql_strava_activity_types` filter.

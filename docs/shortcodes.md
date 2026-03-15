# Shortcodes

For non-headless WordPress sites, the plugin provides 5 shortcodes to display Strava data in posts and pages.

## Available Shortcodes

### `[strava_activities]`

Displays a list of activity cards.

| Attribute | Type | Default | Description |
|---|---|---|---|
| `count` | int | `5` | Number of activities to show |
| `type` | string | — | Filter by activity type (e.g. `"Ride"`) |

```
[strava_activities count="10" type="Run"]
```

### `[strava_activity]`

Displays a single activity card.

| Attribute | Type | Default | Description |
|---|---|---|---|
| `index` | int | `0` | Zero-based index into cached activities |

```
[strava_activity index="0"]
```

### `[strava_map]`

Displays just the SVG route map for a single activity.

| Attribute | Type | Default | Description |
|---|---|---|---|
| `index` | int | `0` | Zero-based index into cached activities |
| `width` | int | `300` | SVG width in pixels |
| `height` | int | `200` | SVG height in pixels |
| `color` | string | — | Stroke colour override (hex) |

```
[strava_map index="0" width="400" height="300" color="#FC5200"]
```

### `[strava_stats]`

Displays aggregate statistics across all cached activities.

No attributes. Shows total activities, total distance, total duration, and total elevation gain.

```
[strava_stats]
```

### `[strava_latest]`

Displays the most recent activity, optionally filtered by type.

| Attribute | Type | Default | Description |
|---|---|---|---|
| `type` | string | — | Filter by activity type |

```
[strava_latest type="Ride"]
```

## Strava Brand Attribution

Per [Strava Brand Guidelines](https://developers.strava.com/guidelines/), pages displaying Strava data must include "Powered by Strava" attribution. The shortcode activity cards include "View on Strava" links styled per brand guidelines, but you are responsible for adding the "Powered by Strava" text to your page.

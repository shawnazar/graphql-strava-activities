# Filters & Hooks

All filters use the `wpgraphql_strava_` prefix.

## Filter Reference

### `wpgraphql_strava_cache_ttl`

Cache duration in seconds.

| | |
|---|---|
| **Default** | `43200` (12 hours) |
| **Location** | `cache.php` |
| **Parameter** | `int $ttl` |

```php
// Cache for 1 hour
add_filter( 'wpgraphql_strava_cache_ttl', function () {
    return HOUR_IN_SECONDS;
} );
```

### `wpgraphql_strava_svg_color`

SVG route map stroke colour.

| | |
|---|---|
| **Default** | `#0d9488` |
| **Location** | `svg.php` |
| **Parameter** | `string $color` |

```php
add_filter( 'wpgraphql_strava_svg_color', function () {
    return '#FC5200'; // Strava orange
} );
```

### `wpgraphql_strava_svg_stroke_width`

SVG route map stroke width.

| | |
|---|---|
| **Default** | `2.5` |
| **Location** | `svg.php` |
| **Parameter** | `float $width` |

```php
add_filter( 'wpgraphql_strava_svg_stroke_width', function () {
    return 4.0;
} );
```

### `wpgraphql_strava_svg_attributes`

Extra attributes added to the `<svg>` element.

| | |
|---|---|
| **Default** | `[]` |
| **Location** | `svg.php` |
| **Parameter** | `array $attrs` |

```php
add_filter( 'wpgraphql_strava_svg_attributes', function ( $attrs ) {
    $attrs['class'] = 'route-map';
    $attrs['data-theme'] = 'dark';
    return $attrs;
} );
```

### `wpgraphql_strava_activities`

Filter the processed activities array before it's cached.

| | |
|---|---|
| **Default** | — |
| **Location** | `cache.php` |
| **Parameter** | `array $activities` |

```php
// Remove activities shorter than 1 mile
add_filter( 'wpgraphql_strava_activities', function ( $activities ) {
    return array_filter( $activities, function ( $a ) {
        return $a['distance'] >= 1.0;
    } );
} );
```

### `wpgraphql_strava_activity_types`

Whitelist of allowed activity types. Empty array means all types.

| | |
|---|---|
| **Default** | `[]` (all) |
| **Location** | `cache.php` |
| **Parameter** | `array $types` |

```php
// Only show rides and runs
add_filter( 'wpgraphql_strava_activity_types', function () {
    return [ 'Ride', 'Run' ];
} );
```

### `wpgraphql_strava_activities_to_fetch`

Maximum number of activities to fetch from Strava per sync (clamped to 200).

| | |
|---|---|
| **Default** | `200` |
| **Location** | `cache.php` |
| **Parameter** | `int $count` |

```php
// Fetch only 50 activities per sync
add_filter( 'wpgraphql_strava_activities_to_fetch', function () {
    return 50;
} );
```

### `wpgraphql_strava_svg_dark_color`

SVG route map stroke colour for dark mode (`prefers-color-scheme: dark`).

| | |
|---|---|
| **Default** | `#60d4c8` |
| **Location** | `svg.php` |
| **Parameter** | `string $dark_color` |

```php
add_filter( 'wpgraphql_strava_svg_dark_color', function () {
    return '#1dd1a1'; // Custom dark teal
} );
```

### `wpgraphql_strava_activity_icon`

Dashicon CSS class for an activity type in the admin Activities list.

| | |
|---|---|
| **Default** | Mapped by type (e.g. `dashicons-bike` for Ride) |
| **Location** | `cache.php` |
| **Parameters** | `string $icon` (Dashicon class), `string $type` (Activity type) |

```php
add_filter( 'wpgraphql_strava_activity_icon', function ( $icon, $type ) {
    if ( 'Hike' === $type ) {
        return 'dashicons-location';
    }
    return $icon;
}, 10, 2 );
```

## Action Hooks

### `wpgraphql_strava_before_sync`

Fires before activities are fetched from the Strava API.

| | |
|---|---|
| **Location** | `cache.php` |
| **Parameters** | None |

```php
add_action( 'wpgraphql_strava_before_sync', function () {
    error_log( 'Strava sync starting...' );
} );
```

### `wpgraphql_strava_after_sync`

Fires after activities have been fetched, processed, and cached.

| | |
|---|---|
| **Location** | `cache.php` |
| **Parameters** | `array $activities` (processed), `int $raw_count` (from API) |

```php
add_action( 'wpgraphql_strava_after_sync', function ( $activities, $raw_count ) {
    error_log( "Synced $raw_count activities, cached " . count( $activities ) );
}, 10, 2 );
```

### `wpgraphql_strava_webhook_event`

Fires when a Strava webhook event triggers a cache clear.

| | |
|---|---|
| **Location** | `webhook.php` |
| **Parameters** | `string $aspect_type` (create/update/delete), `array $body` (full webhook payload) |

```php
add_action( 'wpgraphql_strava_webhook_event', function ( $aspect_type, $body ) {
    if ( 'create' === $aspect_type ) {
        // Notify on new activity
    }
}, 10, 2 );
```

### `wpgraphql_strava_cron_refresh`

WordPress cron action that triggers a cache refresh. Scheduled automatically based on the sync frequency setting.

```php
// Run something after every cron sync
add_action( 'wpgraphql_strava_cron_refresh', function () {
    // Custom post-sync logic
}, 20 );
```

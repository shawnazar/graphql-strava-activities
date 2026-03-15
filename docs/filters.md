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

## Action Hooks

### `wpgraphql_strava_cron_refresh`

WordPress cron action that triggers a cache refresh. Scheduled automatically based on the sync frequency setting.

```php
// Run something after every sync
add_action( 'wpgraphql_strava_cron_refresh', function () {
    // Custom post-sync logic
}, 20 );
```

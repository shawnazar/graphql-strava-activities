# GraphQL Strava Activities

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759B.svg)](https://wordpress.org/)
[![WPGraphQL 2.0+](https://img.shields.io/badge/WPGraphQL-2.0%2B-4B32C3.svg)](https://www.wpgraphql.com/)
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support-orange.svg)](https://www.buymeacoffee.com/shawnazar)

A WordPress plugin that extends [WPGraphQL](https://www.wpgraphql.com/) with Strava activity data, server-side SVG route maps, and activity photos — no JavaScript map libraries required.

## Features

- **GraphQL API** — Query Strava activities with filtering by type and count
- **SVG Route Maps** — Server-rendered inline SVG from Strava polyline data
- **Activity Photos** — Primary photo fetching for your activities
- **Caching** — Transient-based with configurable TTL and sync frequency
- **Credential Encryption** — Optional AES-256-CBC at-rest encryption
- **Extensible** — Filters for cache TTL, SVG appearance, activity types, and more

## Requirements

- PHP 8.2+
- WordPress 6.0+
- [WPGraphQL](https://www.wpgraphql.com/) 2.0+ (required — plugin will not activate without it)

## Installation

### From WordPress Admin

1. Install and activate [WPGraphQL](https://www.wpgraphql.com/)
2. Upload the plugin zip via **Plugins → Add New → Upload Plugin**
3. Activate and go to **Strava → Getting Started** for setup instructions

### Manual

```bash
cd wp-content/plugins/
git clone https://github.com/shawnazar/wp-graphql-strava.git graphql-strava-activities
```

Activate in WordPress, then visit **Strava** in the admin menu.

## Quick Start

1. Create a Strava API application at [strava.com/settings/api](https://www.strava.com/settings/api)
2. Enter your credentials in **Strava → Settings**
3. Click **Resync Activities**
4. Query via GraphQL:

```graphql
{
  stravaActivities(first: 10, type: "Ride") {
    title
    distance
    duration
    date
    type
    unit
    svgMap
    stravaUrl
    photoUrl
    elevationGain
    averageSpeed
    maxSpeed
    averageHeartrate
    maxHeartrate
    calories
    kudosCount
    commentCount
    city
    country
    isPrivate
    poweredByStrava
  }
}
```

## Filters

All filters use the `wpgraphql_strava_` prefix:

| Filter | Default | Description |
|---|---|---|
| `wpgraphql_strava_cache_ttl` | `43200` (12h) | Cache duration in seconds |
| `wpgraphql_strava_svg_color` | `#0d9488` | SVG stroke colour |
| `wpgraphql_strava_svg_stroke_width` | `2.5` | SVG stroke width |
| `wpgraphql_strava_svg_attributes` | `[]` | Extra SVG element attributes |
| `wpgraphql_strava_activities` | — | Filter activities before caching |
| `wpgraphql_strava_activity_types` | `[]` (all) | Whitelist of allowed types |

```php
// Example: only show rides and runs
add_filter( 'wpgraphql_strava_activity_types', function () {
    return [ 'Ride', 'Run' ];
} );
```

## Shortcodes

For non-headless WordPress sites — use in posts and pages:

```
[strava_activities count="10" type="Ride"]   — Activity card list
[strava_activity index="0"]                  — Single activity card
[strava_map index="0"]                       — SVG route map only
[strava_stats]                               — Aggregate stats
[strava_latest type="Run"]                   — Most recent activity
```

## Credential Encryption (Optional)

Add to `wp-config.php`:

```php
define( 'GRAPHQL_STRAVA_ENCRYPTION_KEY', 'your-64-char-hex-key' );
```

Generate a key: `wp eval "echo bin2hex(random_bytes(32));"`

## Development

```bash
composer install      # Install dev dependencies (WPCS)
composer lint         # Check coding standards
composer lint:fix     # Auto-fix violations
```

No build step — pure PHP.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on reporting bugs, suggesting features, and submitting pull requests.

## Security

To report a vulnerability, see [SECURITY.md](SECURITY.md). Please do **not** use public GitHub issues for security reports.

## License

[MIT](LICENSE) — Copyright (c) 2026 Shawn Azar

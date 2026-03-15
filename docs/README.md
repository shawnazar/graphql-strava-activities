# GraphQL Strava Activities

A WordPress plugin that extends [WPGraphQL](https://www.wpgraphql.com/) with Strava activity data, server-side SVG route maps, and activity photos.

## Features

- **GraphQL API** — Query Strava activities with filtering by type and count
- **SVG Route Maps** — Server-rendered inline SVG from Strava polyline data — no Mapbox or Google Maps needed
- **Activity Photos** — Primary photo fetching for your activities
- **Caching** — Transient-based with configurable TTL and 11 sync frequency options
- **Credential Encryption** — Optional AES-256-CBC at-rest encryption for API tokens
- **Shortcodes** — 5 shortcodes for non-headless WordPress sites
- **Extensible** — 6 filters for cache TTL, SVG appearance, activity types, and more

## Quick Start

1. Install [WPGraphQL](https://www.wpgraphql.com/) 2.0+
2. Install and activate GraphQL Strava Activities
3. Create a [Strava API application](https://www.strava.com/settings/api)
4. Enter credentials in **Strava → Settings**
5. Query your activities:

```graphql
{
  stravaActivities(first: 5, type: "Ride") {
    title
    distance
    duration
    svgMap
    stravaUrl
  }
}
```

## Requirements

- PHP 8.2+
- WordPress 6.0+
- [WPGraphQL](https://www.wpgraphql.com/) 2.0+

## Documentation

Use the sidebar to navigate the full documentation, covering everything from initial setup to developer architecture.

## Links

- [GitHub Repository](https://github.com/shawnazar/wp-graphql-strava)
- [Report an Issue](https://github.com/shawnazar/wp-graphql-strava/issues)
- [Discussions](https://github.com/shawnazar/wp-graphql-strava/discussions)

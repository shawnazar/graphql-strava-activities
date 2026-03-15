# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WPGraphQL for Strava** is an open-source WordPress plugin that extends WPGraphQL
with Strava activity data. It provides server-side SVG route map generation, activity
photo fetching, and structured GraphQL types — no JavaScript map libraries required.

This is a standalone plugin extracted from the `shawnazar/shawnazar-me-ts` project.
It should work for any headless WordPress site using WPGraphQL, not just shawnazar.me.

## Tech Stack

| Concern | Tool |
|---|---|
| Language | PHP 8.0+ |
| WordPress | 6.0+ |
| Dependency | WPGraphQL 2.0+ |
| License | MIT |
| Text domain | `wp-graphql-strava` |
| Function prefix | `wpgraphql_strava_` |
| Option prefix | `wpgraphql_strava_` |

## Architecture

```
wp-graphql-strava/
├── wp-graphql-strava.php       # Plugin bootstrap, dependency check, cron
├── includes/
│   ├── admin.php               # WP Admin settings page (credentials, SVG, display, rate limits)
│   ├── api.php                 # Strava API client (list activities, detail, token refresh)
│   ├── cache.php               # Transient caching with configurable TTL
│   ├── graphql.php             # WPGraphQL type + query registration
│   ├── polyline.php            # Google encoded polyline decoder
│   └── svg.php                 # Polyline → SVG route map generator
├── LICENSE                     # MIT
├── README.md                   # User-facing documentation
├── CONTRIBUTING.md             # Contributor guidelines
├── CODE_OF_CONDUCT.md          # Contributor Covenant v2.1
├── SECURITY.md                 # Vulnerability reporting
├── CHANGELOG.md                # Version history
├── .gitignore
└── .distignore                 # Files excluded from release zip
```

## Key Features to Implement

### GraphQL Schema
```graphql
type StravaActivity {
  title: String
  distance: Float          # In miles or km based on settings
  duration: String         # Formatted: "1h 16m"
  date: String             # ISO 8601
  svgMap: String           # Inline SVG markup
  stravaUrl: String        # Link to activity on Strava
  type: String             # Ride, Run, Walk, etc.
  photoUrl: String         # Primary activity photo URL
  unit: String             # "mi" or "km"
}

type Query {
  stravaActivities(first: Int, type: String): [StravaActivity]
}
```

### Admin Settings (under "Strava" top-level menu)
- **Credentials**: Client ID, Client Secret, Access Token, Refresh Token
- **SVG Customization**: stroke color, stroke width (with defaults)
- **Display**: units (miles/km), activity types to include
- **Sync**: resync button, last sync time, cached count, rate limit info

### Filters & Hooks (extensibility)
All filters use `wpgraphql_strava_` prefix:
- `wpgraphql_strava_cache_ttl` — cache duration (default 12 hours)
- `wpgraphql_strava_svg_color` — SVG stroke color
- `wpgraphql_strava_svg_stroke_width` — SVG stroke width
- `wpgraphql_strava_activities` — filter processed activities before caching
- `wpgraphql_strava_activity_types` — allowed activity types

## Code Style

- PHP: 4 spaces indent, WordPress coding standards
- PHPDoc on every function
- All user input sanitized, all output escaped
- Nonces on all forms
- Text domain: `wp-graphql-strava` for all translatable strings

## Security Rules

- Never store API tokens in code — only in WP options (database)
- All admin pages check `current_user_can('manage_options')`
- All form submissions verify nonces
- All option values sanitized on save
- No direct file access (ABSPATH check at top of every file)
- Rate limit API calls (200ms delay between detail calls)
- Document all data flows in SECURITY.md

## Publishing to WordPress.org

To submit to the WordPress plugin directory:
1. Plugin must follow WordPress coding standards
2. Must use `sanitize_*`, `esc_*`, and `wp_nonce_*` properly
3. No external CDN dependencies (all assets bundled)
4. Must have a readme.txt (WordPress format, not GitHub markdown)
5. Submit at https://wordpress.org/plugins/developers/add/
6. Review process takes 1-7 days
7. After approval, use SVN to deploy updates

## Relationship to shawnazar-me-ts

This plugin was extracted from `shawnazar/shawnazar-me-ts` (issue #41).
The parent project's `wordpress/plugins/shawnazar-site/` still has its own
Strava integration. Once this standalone plugin is stable, the parent
project should switch to using this plugin instead.

## Commands

```bash
# No build step needed — pure PHP plugin
# For development, mount in Docker:
# docker-compose volume: ./:/var/www/html/wp-content/plugins/wp-graphql-strava
```

# Architecture

## File Structure

```
wp-graphql-strava.php          # Bootstrap, dependency check, cron scheduling
includes/
├── encryption.php             # Optional AES-256-CBC credential encryption
├── polyline.php               # Google encoded polyline → [lat, lng] decoder
├── svg.php                    # [lat, lng] → inline SVG route map
├── api.php                    # Strava API client (activities, detail, token refresh)
├── cache.php                  # Transient caching, photo enrichment, normalization
├── admin.php                  # Settings page + Getting Started + Preview + Activities
├── graphql.php                # StravaActivity type + stravaActivities query
├── shortcodes.php             # WordPress shortcodes for non-headless sites
└── class-wpgraphql-strava-activities-list-table.php  # WP_List_Table for Activities page
```

## Load Order

Load order matters — `encryption.php` and `polyline.php` must load before modules that depend on them. The bootstrap file (`wp-graphql-strava.php`) handles this:

1. `encryption.php` — needed by api.php and admin.php for credential access
2. `polyline.php` — needed by svg.php for polyline decoding
3. `svg.php` — needed by cache.php for map generation
4. `api.php` — needed by cache.php for Strava API calls
5. `cache.php` — needed by graphql.php, admin.php, shortcodes.php
6. `admin.php` — WordPress admin UI
7. `graphql.php` — WPGraphQL type registration
8. `shortcodes.php` — WordPress shortcodes
9. `class-wpgraphql-strava-activities-list-table.php` — loaded by admin.php when needed

## Data Flow

```
Strava API → api.php → cache.php → transient
                                       ↓
                              graphql.php (GraphQL queries)
                              shortcodes.php (shortcode rendering)
                              admin.php (preview page, activities list)
```

1. **Fetch** — `wpgraphql_strava_fetch_activities()` calls the Strava list endpoint
2. **Enrich** — `wpgraphql_strava_get_cached_activities()` fetches photo details for up to 5 activities
3. **Normalise** — `wpgraphql_strava_process_activities()` converts raw API data to the output format (distance conversion, duration formatting, SVG generation, URL construction)
4. **Cache** — Stored as a WordPress transient with configurable TTL (default 12 hours)
5. **Serve** — GraphQL resolver, shortcodes, and admin pages all read from the cached array

## Cron

The plugin schedules a WordPress cron event (`wpgraphql_strava_cron_refresh`) that calls `wpgraphql_strava_refresh_cache()` at the configured frequency. Custom schedules are registered via the `cron_schedules` filter.

## Naming Conventions

| Concern | Prefix |
|---|---|
| Functions | `wpgraphql_strava_` |
| Options | `wpgraphql_strava_` |
| Constants | `WPGRAPHQL_STRAVA_` |
| Text domain | `graphql-strava-activities` |
| Filters | `wpgraphql_strava_` |
| Cron hook | `wpgraphql_strava_cron_refresh` |
| Cache key | `wpgraphql_strava_activities` |

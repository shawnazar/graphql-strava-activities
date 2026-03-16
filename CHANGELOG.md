# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-03-16

### Fixed
- Upgraded `github/codeql-action` from v3 to v4 (Node.js 24 support)
- Set `FORCE_JAVASCRIPT_ACTIONS_TO_NODE24` environment variable in CI
- Release workflow uses `RELEASE_PAT` for proper CI triggering on release PRs and tags
- Stale release branch cleanup on workflow retry

## [0.1.0] - 2026-03-15

Initial public release.

### Added
- **GraphQL API** — `StravaActivity` type with 22 fields, `stravaActivities` query with `first`, `offset`, and `type` arguments
- **REST API** — `GET /wp-json/wpgraphql-strava/v1/activities` endpoint with `count`, `offset`, `type` params
- **Server-side SVG route maps** — inline SVG generation from Google encoded polylines, no JavaScript required
- **One-click OAuth** — "Connect with Strava" button with automatic token exchange and CSRF protection
- **Strava API client** — activity fetching, detail enrichment, automatic OAuth token refresh, rate limiting
- **Transient caching** — configurable TTL with 11 sync frequency options (15 min to monthly)
- **Admin settings** — credentials, SVG customization (color, stroke width), display units, sync controls
- **Connection status** — token expiry monitoring, Test Connection button, health notices
- **Activity photos** — primary photo fetching and enrichment for top 5 activities
- **Shortcodes** — 5 shortcodes (`strava_activities`, `strava_activity`, `strava_map`, `strava_stats`, `strava_latest`) with classic editor generator button
- **Credential encryption** — optional AES-256-CBC at-rest encryption via `WPGRAPHQL_STRAVA_ENCRYPTION_KEY`
- **Self-hosted updater** — GitHub Releases API update checker for manually-installed copies
- **Speed conversion** — `averageSpeed` and `maxSpeed` converted to mph/km/h matching distance unit, `speedUnit` field
- **Extensibility** — filters for cache TTL, SVG appearance, activity types, fetch count; `before_sync`/`after_sync` action hooks
- **Security** — `wp_kses` SVG output with explicit tag allowlist, nonce verification, input validation, capability checks
- **65 automated tests** — unit tests (encryption, polyline, SVG) and integration tests (API, cache, GraphQL, shortcodes)
- **CI pipeline** — PHPCS, PHPStan level 5, PHPUnit, Plugin Check, security scanning, SARIF code scanning, dependency review
- **Automated releases** — version bump workflow with PR-based flow, tag-on-merge, GitHub Release + WP.org deploy
- **Documentation** — Docsify site, Getting Started guide, field reference, filter docs, architecture docs

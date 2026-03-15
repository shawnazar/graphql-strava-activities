# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.4] - 2026-03-15

### Added
- One-click "Connect with Strava" OAuth flow — eliminates manual curl token exchange
- OAuth callback handler with CSRF protection via state nonce
- Automatic first sync after successful OAuth authorization
- "Test Connection" button on Settings page — verifies credentials and shows athlete name
- Connection Status card on Settings page with token expiry date and status indicator
- Token health admin notice on plugin pages when credentials are missing or expired
- Specific API error descriptions (401, 403, 429, 5xx) replacing generic "API returned status" messages
- `offset` argument for GraphQL `stravaActivities` query for pagination
- `speedUnit` GraphQL field ("mph" or "km/h") matching the distance unit setting
- Speed fields (`averageSpeed`, `maxSpeed`) now converted to match distance unit (mph or km/h)
- `wpgraphql_strava_before_sync` and `wpgraphql_strava_after_sync` action hooks
- `wpgraphql_strava_activities_to_fetch` filter (default 200, max 200)
- Copy-to-clipboard buttons on GraphQL query examples in Getting Started page
- Input validation: `first` clamped to 0-200, `offset` validated, `type` sanitized
- REST API endpoint: `GET /wp-json/wpgraphql-strava/v1/activities` with `count`, `offset`, `type` params
- SVG route thumbnails in admin Activities list table (replaces checkmarks)
- Plugin Check (PCP) job in CI workflow
- GitHub topics for repository discoverability
- Shortcode generator button in the classic editor ("Strava" button next to "Add Media")
- CI validation gates: version consistency check, distribution archive check, text domain check
- Plugin Check (PCP) now fails CI on errors (was output-only)
- Security CI job: `composer audit`, debug code detection, hardcoded secret scanning, unsafe PHP function detection
- PHPCS and PHPStan results uploaded to GitHub Code Scanning via SARIF
- Automated release workflow — code owners run `Release Version` from Actions tab (patch/minor/major)
- Dependency Review on PRs (fails on high-severity vulnerabilities)

## [1.0.3] - 2026-03-15

### Changed
- Version bump to test self-hosted update checker

## [1.0.2] - 2026-03-15

### Added
- Self-hosted update checker — manually-installed copies receive update notifications via GitHub Releases API
- Integration tests for API client (11 tests: token refresh, error handling, 401 retry)
- Integration tests for GraphQL schema (7 tests: type registration, resolver, filtering)
- Integration tests for shortcodes (12 tests: all 5 shortcodes, card rendering)
- `wpgraphql_strava_allowed_svg_tags()` helper for explicit SVG output escaping
- WordPress.org placeholder banner and icon SVG assets

### Fixed
- Added nonce verification to Activities list table filter form
- Whitelisted sort parameters in list table to prevent arbitrary key access
- Replaced `phpcs:ignore` SVG output with `wp_kses()` and explicit tag allowlist

## [1.0.1] - 2026-03-15

### Fixed
- Replaced `error_log()` calls in API client with `wp_trigger_error()` for production use
- Replaced `strip_tags()` in test mock with `wp_strip_all_tags()` for WPCS compliance
- Renamed `GRAPHQL_STRAVA_ENCRYPTION_KEY` constant to `WPGRAPHQL_STRAVA_ENCRYPTION_KEY` to match plugin prefix convention
- Added `phpcs:ignore` comments on WPGraphQL stub functions (must match upstream names)
- Added `.phpunit.result.cache` to `.distignore`
- Updated "Tested up to" to WordPress 6.9

### Changed
- GitHub Pages documentation site with Docsify (user guide + developer guide)
- Updated readme.txt sync frequency description and GraphQL example to include all 21 fields
- Added `composer analyse` command to CLAUDE.md
- Added `poweredByStrava` field to README.md GraphQL example

### Added
- Weekly, every 2 weeks, and monthly sync frequency options
- "Powered by Strava" attribution in admin footer and brand attribution docs on Getting Started page
- `poweredByStrava` GraphQL field for frontend attribution compliance
- Strava brand-compliant orange (#FC5200) styling on admin "View on Strava" links
- Official "Connect with Strava" button SVG on Getting Started page
- PHPUnit test suite with unit and integration tests
- Unit tests for polyline decoder, encryption, and SVG generator
- Integration tests for cache module (duration formatting, activity processing, distance conversion)
- GitHub Actions CI workflow — PHP 8.0-8.3 matrix with lint + test on every push/PR
- `composer test`, `composer test:unit`, `composer test:integration` scripts
- GrumPHP pre-commit hooks — lint and tests run automatically on every commit
- SUPPORT.md with help channels and in-plugin docs reference
- Getting Started is now the default page when clicking "Strava" in admin
- Settings moved to Strava → Settings submenu
- Setting to include activities without GPS routes (indoor, treadmill, yoga)
- PHPStan level 5 static analysis with WordPress extension
- `composer analyse` script and PHPStan in GrumPHP pre-commit
- GitHub Release automation — creates release with zip on tag push
- Repo topics, discussion categories, required PR approvals
- Sample Strava API response fixture for test mocking

## [1.0.0] - 2026-03-15

### Added
- Plugin bootstrap with WPGraphQL dependency check and cron scheduling
- Strava API client with OAuth token refresh and rate limiting
- Transient caching with configurable TTL and photo enrichment
- Google polyline decoder and server-side SVG route map generator
- WPGraphQL schema: StravaActivity type and stravaActivities query with type filter
- Admin settings page: credentials, SVG customization, display units, sync frequency
- Getting Started documentation page with setup guide and code examples
- Preview page with live activity cards and demo data
- Optional AES-256-CBC at-rest credential encryption
- WordPress.org readme.txt with Third-Party Service disclosure
- GitHub Actions workflow for WordPress.org SVN deployment
- Dependabot for Composer and GitHub Actions updates
- WPCS 3.0 linting via composer lint

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

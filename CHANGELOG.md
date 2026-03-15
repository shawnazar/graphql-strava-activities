# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

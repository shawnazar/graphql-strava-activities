# CLAUDE.md

Guidance for Claude Code and contributors working in this repository.

## What This Is

**GraphQL Strava Activities** — a WordPress plugin that extends WPGraphQL with
Strava activity data, server-side SVG route maps, and activity photos.

## Naming & Prefixes

| Concern | Value |
|---|---|
| Plugin name | `GraphQL Strava Activities` |
| WordPress.org slug | `graphql-strava-activities` |
| Text domain | `graphql-strava-activities` |
| Function prefix | `wpgraphql_strava_` |
| Option prefix | `wpgraphql_strava_` |
| Constant prefix | `WPGRAPHQL_STRAVA_` |

## Requirements

- PHP 8.0+
- WordPress 6.0+
- WPGraphQL 2.0+

## Architecture

```
wp-graphql-strava.php          # Bootstrap, dependency check, cron scheduling
includes/
├── encryption.php             # Optional AES-256-CBC credential encryption
├── polyline.php               # Google encoded polyline → [lat, lng] decoder
├── svg.php                    # [lat, lng] → inline SVG route map
├── api.php                    # Strava API client (activities, detail, token refresh)
├── cache.php                  # Transient caching, photo enrichment, normalization
├── admin.php                  # Settings page + Getting Started documentation
└── graphql.php                # StravaActivity type + stravaActivities query
```

**Load order matters** — `encryption.php` and `polyline.php` must load before modules
that depend on them. The bootstrap file handles this.

## Workflow

1. Every task starts with a GitHub issue.
2. Create a feature branch from main: `feat/[issue-number]-[short-description]`
   or `fix/[issue-number]-[short-description]`.
3. Write or update tests for any code changes. Run `composer test` to verify
   nothing is broken before committing. Mock external data (Strava API responses,
   HTTP calls) — never hit real APIs in tests.
4. Update `CHANGELOG.md` under an `[Unreleased]` section with a summary of the change.
   Use categories: Added, Changed, Fixed, Removed.
5. Commit with descriptive messages that include `Closes #N`.
6. Push the branch and create a PR via `gh pr create` linked to the issue.
7. Merge to main after the PR is created.
8. Document architectural decisions as comments on the GitHub issue.

### Testing

- Unit tests live in `tests/Unit/` — pure logic, no WordPress needed.
- Integration tests live in `tests/Integration/` — require the WP test suite.
- Test fixtures (sample API responses) live in `tests/fixtures/`.
- Always mock HTTP responses. Never call the real Strava API.
- Run `composer test` before every commit to catch regressions.

## Code Style

- WordPress coding standards (WPCS 3.0) — enforced via `composer lint`
- `declare(strict_types=1)` in every PHP file
- PHPDoc on every function
- 4-space indentation
- All user input sanitized (`sanitize_*`), all output escaped (`esc_*`)
- Nonces on all admin forms
- Text domain `graphql-strava-activities` for all translatable strings

## Security Rules

- ABSPATH check at the top of every PHP file
- Admin pages require `current_user_can('manage_options')`
- Sensitive options (tokens, client secret) go through `wpgraphql_strava_get_option()` /
  `wpgraphql_strava_update_option()` which handle optional AES-256-CBC encryption
- Never store credentials in code — only in `wp_options`
- Rate limit Strava API calls (200ms delay between detail requests)

## Filters

All filters use the `wpgraphql_strava_` prefix:

| Filter | Default | Purpose |
|---|---|---|
| `wpgraphql_strava_cache_ttl` | `43200` (12h) | Cache duration in seconds |
| `wpgraphql_strava_svg_color` | `#0d9488` | SVG stroke colour |
| `wpgraphql_strava_svg_stroke_width` | `2.5` | SVG stroke width |
| `wpgraphql_strava_svg_attributes` | `[]` | Extra SVG element attributes |
| `wpgraphql_strava_activities` | — | Filter activities before caching |
| `wpgraphql_strava_activity_types` | `[]` (all) | Whitelist of allowed types |

## Releasing

- Slug: `graphql-strava-activities`
- `readme.txt` follows WordPress.org format with Third-Party Service disclosure
- Tag a version → `.github/workflows/deploy.yml` auto-deploys to WordPress.org SVN
- SVN credentials: GitHub Secrets `SVN_USERNAME` and `SVN_PASSWORD`

## Commands

```bash
composer install      # Install dev dependencies (WPCS)
composer lint         # Check coding standards
composer lint:fix     # Auto-fix violations
```

No build step — pure PHP plugin.

# Contributing

## Development Setup

1. Fork and clone the repository:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/YOUR-USERNAME/wp-graphql-strava.git graphql-strava-activities
   ```
2. Install dev dependencies:
   ```bash
   composer install
   ```
   This installs WPCS, PHPStan, PHPUnit, and GrumPHP pre-commit hooks.
3. Install and activate [WPGraphQL](https://www.wpgraphql.com/) in your WordPress instance.

## Workflow

1. **Create a GitHub issue first** — every PR references an issue
2. **Branch from main:**
   - `feat/[issue-number]-[short-description]` for features
   - `fix/[issue-number]-[short-description]` for bug fixes
3. **Write or update tests** — mock HTTP responses, never call the real Strava API
4. **Update CHANGELOG.md** under `[Unreleased]` with a summary (Added, Changed, Fixed, Removed)
5. **Commit** with descriptive messages including `Closes #N`
6. **Push and create a PR** via `gh pr create` or the GitHub web UI
7. **Merge** after the PR passes CI

## Commands

```bash
composer install          # Install deps + GrumPHP hooks
composer lint             # Check WordPress coding standards
composer lint:fix         # Auto-fix violations
composer analyse          # PHPStan level 5 static analysis
composer test             # Run all tests
composer test:unit        # Unit tests only
composer test:integration # Integration tests only
```

## Pre-commit Hooks

GrumPHP runs automatically on every commit:

1. **phpcs** — WordPress coding standards
2. **phpstan** — PHPStan level 5
3. **phpunit** — full test suite

If any task fails, the commit is blocked.

## Code Style

- WordPress Coding Standards (WPCS 3.0)
- `declare(strict_types=1)` in every PHP file
- PHPDoc on every function
- 4-space indentation
- All user input sanitized (`sanitize_*`), all output escaped (`esc_*`)
- Nonces on all admin forms
- Text domain: `graphql-strava-activities`

## Security Checklist

- [ ] All user input sanitized
- [ ] All output escaped
- [ ] Admin pages check `current_user_can('manage_options')`
- [ ] Forms use nonces
- [ ] Sensitive options use `wpgraphql_strava_get_option()` / `wpgraphql_strava_update_option()`
- [ ] No credentials in code

## Reporting Bugs

Open a [GitHub issue](https://github.com/shawnazar/wp-graphql-strava/issues) with:
- WordPress, PHP, and WPGraphQL versions
- Steps to reproduce
- Expected vs actual behaviour
- Any error messages from `debug.log`

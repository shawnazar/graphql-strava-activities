# Contributing to GraphQL Strava Activities

Thank you for your interest in contributing! This guide will help you get started.

## Reporting Bugs

1. Search [existing issues](https://github.com/shawnazar/wp-graphql-strava/issues) to avoid duplicates.
2. Open a new issue with:
   - WordPress, PHP, and WPGraphQL versions
   - Steps to reproduce
   - Expected vs actual behaviour
   - Any error messages from `debug.log`

## Suggesting Features

Open a [GitHub issue](https://github.com/shawnazar/wp-graphql-strava/issues/new) describing:
- What you'd like to see
- Why it would be useful
- Any implementation ideas

## Development Setup

1. Fork and clone the repository
2. Mount the plugin in a local WordPress environment:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/YOUR-USERNAME/wp-graphql-strava.git graphql-strava-activities
   ```
3. Install dev dependencies:
   ```bash
   composer install
   ```
4. Install and activate [WPGraphQL](https://www.wpgraphql.com/) in your WordPress instance.

## Submitting a Pull Request

1. **Create a GitHub issue first** — every PR should reference an issue.
2. **Branch from main:**
   - `feat/[issue-number]-[short-description]` for new features
   - `fix/[issue-number]-[short-description]` for bug fixes
3. **Follow the code style:**
   - WordPress coding standards (run `composer lint` to check)
   - `declare(strict_types=1)` in every PHP file
   - PHPDoc on every function
   - Text domain: `graphql-strava-activities`
4. **Write or update tests** for your changes.
5. **Commit with descriptive messages** that include `Closes #N`.
6. **Push and open a PR** via `gh pr create` or the GitHub web UI.

## Code Style

This project uses [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) enforced via PHP_CodeSniffer:

```bash
composer lint         # Check for violations
composer lint:fix     # Auto-fix what's possible
```

## Security

- All user input must be sanitized (`sanitize_*` functions)
- All output must be escaped (`esc_*` functions)
- All forms must use nonces (`wp_nonce_field` / `check_admin_referer`)
- Admin pages must check `current_user_can('manage_options')`
- Sensitive options must use `wpgraphql_strava_get_option()` / `wpgraphql_strava_update_option()`
- Never commit credentials or API keys

## Questions?

Open an issue — happy to help!

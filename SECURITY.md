# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | Yes       |

## Reporting a Problem

If you discover a security issue, please report it privately. **Do not** open a public GitHub issue.

**Email:** [security@shawnazar.me](mailto:security@shawnazar.me)

Include:
- Description of the issue
- Steps to reproduce
- Affected versions
- Any potential impact

You should receive an acknowledgement within 48 hours. We aim to release a fix within 7 days of confirmation.

## Scope

This policy covers the GraphQL Strava Activities WordPress plugin code in this repository. Issues with the Strava API itself should be reported to [Strava](https://www.strava.com/legal/security).

## Credential Storage

- API tokens are stored in the WordPress `wp_options` table
- Optional AES-256-CBC encryption is available via the `GRAPHQL_STRAVA_ENCRYPTION_KEY` constant in `wp-config.php`
- All credentials are sanitized before storage and escaped on output

## Best Practices for Users

- Keep WordPress, WPGraphQL, and this plugin up to date
- Use the optional encryption feature for stored credentials
- Restrict access to your WordPress admin dashboard
- Use HTTPS on your WordPress site

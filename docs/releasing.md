# Releasing

## Version Tagging

1. Update `CHANGELOG.md` — move items from `[Unreleased]` to a new version section
2. Update the version number in:
   - `wp-graphql-strava.php` (plugin header `Version:` and `WPGRAPHQL_STRAVA_VERSION` constant)
   - `readme.txt` (`Stable tag:`)
3. Commit: `git commit -m "release: v1.2.0"`
4. Tag: `git tag v1.2.0`
5. Push: `git push origin main --tags`

## WordPress.org Deployment

Tagging a version triggers `.github/workflows/deploy.yml`, which automatically deploys to the WordPress.org SVN repository.

### Requirements

- GitHub Secrets: `SVN_USERNAME` and `SVN_PASSWORD`
- The workflow uses the [WordPress.org Plugin Deploy](https://github.com/10up/action-wordpress-plugin-deploy) action

### What Gets Deployed

Files **excluded** from the release are listed in `.distignore`:

- Development files (`.git/`, `.github/`, tests, stubs)
- Tooling configs (composer.json, phpcs.xml.dist, grumphp.yml, phpstan.neon.dist)
- Documentation (CLAUDE.md, CONTRIBUTING.md, etc.)

Files **included**: plugin PHP files, `includes/`, `assets/`, `readme.txt`, `LICENSE`

## GitHub Releases

A separate workflow creates a GitHub Release with a zip archive when a tag is pushed. This provides an alternative download for users who don't use WordPress.org.

## Changelog Format

Follow [Keep a Changelog](https://keepachangelog.com/) with these categories:

- **Added** — new features
- **Changed** — changes to existing functionality
- **Fixed** — bug fixes
- **Removed** — removed features

## Slug

The WordPress.org slug is `graphql-strava-activities`. This must match the plugin directory name and the `readme.txt` format.

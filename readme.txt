=== GraphQL Strava Activities ===
Contributors: shawnazar
Tags: graphql, strava, wpgraphql, fitness, activities
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.2
Requires Plugins: wp-graphql
Stable tag: 1.0.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Compatible with Strava — extends WPGraphQL with activity data, server-side SVG route maps, and photos.

== Description ==

GraphQL Strava Activities brings your Strava activities into your headless WordPress site via GraphQL. Query your rides, runs, and walks with distance, duration, photos, and beautiful server-side SVG route maps — no JavaScript map libraries required.

**Requirements:**

* [WPGraphQL](https://www.wpgraphql.com/) 2.0 or newer (required — the plugin will not activate without it)
* A [Strava API application](https://www.strava.com/settings/api) with OAuth credentials

**Features:**

* **GraphQL API** — Query Strava activities through WPGraphQL with filtering by type and count.
* **SVG Route Maps** — Server-rendered inline SVG route maps from Strava polyline data. No Mapbox or Google Maps needed.
* **Activity Photos** — Fetches primary photos from your Strava activities.
* **Caching** — Transient-based caching with configurable TTL to stay within Strava rate limits.
* **Admin Settings** — Full settings page for credentials, SVG customization, display units, and sync controls.
* **Credential Encryption** — Optional AES-256-CBC at-rest encryption for your API tokens.
* **Configurable Sync** — Choose from 11 sync frequencies: every 15 minutes to monthly.
* **Extensible** — Filters and hooks for customizing cache TTL, SVG appearance, activity types, and more.

**Example GraphQL Query:**

    {
      stravaActivities(first: 10, type: "Ride") {
        title
        distance
        duration
        date
        type
        unit
        speedUnit
        svgMap
        stravaUrl
        photoUrl
        elevationGain
        averageSpeed
        maxSpeed
        averageHeartrate
        maxHeartrate
        calories
        kudosCount
        commentCount
        city
        country
        isPrivate
        poweredByStrava
      }
    }

== Third-Party Service ==

This plugin connects to the [Strava API v3](https://developers.strava.com/) to fetch your activity data (title, distance, duration, route polylines, and photos). Data is sent to and received from `https://www.strava.com/api/v3/` and `https://www.strava.com/oauth/token`.

No data is sent until you enter your API credentials in the plugin settings. The plugin sends your OAuth tokens to authenticate requests and receives activity data in return. Activity data is cached locally in WordPress transients.

* [Strava API Agreement](https://www.strava.com/legal/api)
* [Strava Privacy Policy](https://www.strava.com/legal/privacy)
* [Strava Terms of Service](https://www.strava.com/legal/terms)

== Installation ==

**Prerequisites:**

This plugin requires [WPGraphQL](https://www.wpgraphql.com/) 2.0 or newer. Install and activate WPGraphQL first — without it, this plugin will display an admin notice and will not load.

**Install the plugin:**

1. Upload the `graphql-strava-activities` folder to `/wp-content/plugins/`, or install directly from the WordPress plugin directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Strava** in the admin menu and enter your Strava API credentials.
4. Visit **Strava → Getting Started** for a step-by-step setup guide.
5. Query your activities via GraphQL!

**Getting Strava API Credentials:**

1. Go to [Strava API Settings](https://www.strava.com/settings/api) and create an application.
2. Copy the Client ID and Client Secret into the plugin settings.
3. Generate an Access Token and Refresh Token using Strava's OAuth flow (detailed instructions are on the Getting Started page in your WordPress admin).

== Frequently Asked Questions ==

= Does this require WPGraphQL? =

Yes. WPGraphQL 2.0 or newer is a hard dependency — the plugin will not activate without it. WPGraphQL provides the GraphQL server that this plugin extends. Install it from [wpgraphql.com](https://www.wpgraphql.com/) or the WordPress plugin directory.

= How often are activities synced? =

By default, activities are cached and refreshed every 12 hours via WordPress cron. You can choose from 11 sync frequencies (every 15 minutes, 30 minutes, hourly, every 2/4/6/12 hours, daily, weekly, every 2 weeks, or monthly) in the settings. You can also trigger a manual resync from the admin settings page.

= Can I change the SVG route map appearance? =

Yes. The admin settings page lets you customize stroke color and width. Developers can also use the `wpgraphql_strava_svg_color` and `wpgraphql_strava_svg_stroke_width` filters.

= What activity types are supported? =

All Strava activity types (Ride, Run, Walk, Hike, Swim, etc.) are supported. You can filter which types appear using the admin settings or the `wpgraphql_strava_activity_types` filter.

= Does this work with any headless frontend? =

Yes. Any frontend that can query WPGraphQL (Next.js, Gatsby, Astro, etc.) will work.

== Screenshots ==

1. Admin settings page with credential management.
2. SVG route map rendered from Strava polyline data.
3. GraphQL query and response in WPGraphiQL.

== Changelog ==

= 1.0.2 =
* Added: Self-hosted update checker — manually-installed copies receive update notifications via GitHub Releases.
* Added: Nonce verification on Activities list table filter form.
* Added: wp_kses SVG escaping with explicit tag allowlist replacing phpcs:ignore comments.
* Added: 30 new integration tests (65 total) for API client, GraphQL schema, and shortcodes.
* Added: Placeholder banner and icon SVG assets for WordPress.org.
* Added: Whitelisted sort parameters in list table to prevent arbitrary key access.

= 1.0.1 =
* Fixed: Replaced error_log() with wp_trigger_error() for production use.
* Fixed: Renamed GRAPHQL_STRAVA_ENCRYPTION_KEY to WPGRAPHQL_STRAVA_ENCRYPTION_KEY for prefix consistency.
* Fixed: Replaced strip_tags() with wp_strip_all_tags() in test mocks.
* Fixed: Updated "Tested up to" to WordPress 6.9.

= 1.0.0 =
* Initial release.
* GraphQL schema with StravaActivity type and stravaActivities query.
* Server-side SVG route map generation from encoded polylines.
* Strava API client with OAuth token refresh.
* Transient caching with configurable TTL.
* Admin settings page for credentials, SVG customization, display options, and sync.
* Activity photo fetching.
* Extensible via WordPress filters and hooks.

== Upgrade Notice ==

= 1.0.2 =
Adds automatic update notifications for GitHub-installed copies. Security and test improvements.

= 1.0.1 =
Breaking: If you use credential encryption, rename GRAPHQL_STRAVA_ENCRYPTION_KEY to WPGRAPHQL_STRAVA_ENCRYPTION_KEY in wp-config.php.

= 1.0.0 =
Initial release.

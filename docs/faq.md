# FAQ

## Does this require WPGraphQL?

Yes. [WPGraphQL](https://www.wpgraphql.com/) 2.0 or newer is a hard dependency — the plugin will not activate without it. WPGraphQL provides the GraphQL server that this plugin extends.

## How often are activities synced?

By default, every 12 hours via WordPress cron. You can choose from 11 frequencies in **Strava → Settings**: every 15 minutes, 30 minutes, hourly, every 2/4/6/12 hours, daily, weekly, every 2 weeks, or monthly. You can also click **Resync Activities** for an immediate refresh.

## Can I change the SVG route map appearance?

Yes. The settings page lets you customise stroke colour and width. Developers can also use the `wpgraphql_strava_svg_color` and `wpgraphql_strava_svg_stroke_width` filters. See [SVG Route Maps](svg-maps.md).

## What activity types are supported?

All Strava activity types (Ride, Run, Walk, Hike, Swim, etc.). You can filter which types appear using the admin settings or the `wpgraphql_strava_activity_types` filter.

## Does this work with any headless frontend?

Yes. Any frontend that can query WPGraphQL — Next.js, Gatsby, Astro, Nuxt, etc.

## Can I use this without a headless frontend?

Yes. The plugin includes [5 shortcodes](shortcodes.md) for displaying activities on traditional WordPress sites.

## Why aren't my activities showing?

Common causes:

1. **No credentials** — check that all 5 credential fields are filled in **Strava → Settings**
2. **Expired token** — the plugin refreshes tokens automatically, but if the refresh token is invalid you'll need to re-authorise via the [OAuth flow](oauth-setup.md)
3. **No sync yet** — click **Resync Activities** in settings
4. **Private activities** — if you used the `activity:read` scope instead of `activity:read_all`, private activities won't appear
5. **Activity type filter** — check if the `wpgraphql_strava_activity_types` filter is restricting types

## What data is sent to Strava?

The plugin sends your OAuth tokens to authenticate API requests. No personal data from your WordPress site is sent to Strava. See the [Strava API](strava-api.md) page for details on endpoints used.

## How do I enable credential encryption?

Add a `GRAPHQL_STRAVA_ENCRYPTION_KEY` constant to `wp-config.php`. See [Encryption](encryption.md) for full setup instructions.

## Is there a rate limit?

Strava allows 100 requests per 15 minutes and 1,000 per day. The plugin includes a 200ms delay between detail requests and estimates API usage in the sync frequency dropdown. With the default 12-hour sync, usage is approximately 12 calls/day.

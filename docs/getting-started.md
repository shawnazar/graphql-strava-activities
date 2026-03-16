# Getting Started

This guide walks you through installing the plugin, connecting your Strava account, and running your first GraphQL query.

## Prerequisites

- WordPress 6.0 or newer
- PHP 8.2 or newer
- [WPGraphQL](https://www.wpgraphql.com/) 2.0 or newer — install and activate it first

## Installation

### From WordPress Admin

1. Download the plugin zip from [GitHub Releases](https://github.com/shawnazar/graphql-strava-activities/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

### Manual

```bash
cd wp-content/plugins/
git clone https://github.com/shawnazar/graphql-strava-activities.git graphql-strava-activities
```

Activate in **Plugins** menu.

## Setup

1. **Create a Strava API application** at [strava.com/settings/api](https://www.strava.com/settings/api) — set the Authorization Callback Domain to your site's domain
2. Go to **Strava → Settings** in your WordPress admin
3. Enter your **Client ID** and **Client Secret**, click **Save Settings**
4. Click the **"Connect with Strava"** button — this handles the OAuth flow automatically
5. Your tokens are fetched and activities synced immediately

> **Manual setup**: If you prefer, you can still enter tokens manually — see [OAuth Setup](oauth-setup.md)

## First Query

Once synced, go to **GraphQL → GraphiQL IDE** and run:

```graphql
{
  stravaActivities(first: 5) {
    title
    distance
    duration
    date
    type
    svgMap
    stravaUrl
  }
}
```

You should see your recent activities with inline SVG route maps.

## Next Steps

- [Activity Fields](fields.md) — full reference of all 23 queryable fields
- [Admin Settings](settings.md) — configure sync frequency, SVG appearance, and display units
- [SVG Route Maps](svg-maps.md) — customise route map appearance
- [Shortcodes](shortcodes.md) — display activities on non-headless WordPress sites

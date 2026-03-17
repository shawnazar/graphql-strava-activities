# Astro Strava Activities Example

A minimal Astro page fetching Strava activities from WordPress via GraphQL. Static site generation — zero JavaScript shipped to the client.

## Setup

1. Install the GraphQL Strava Activities plugin on your WordPress site
2. Copy `StravaActivities.astro` into your Astro `src/pages/` directory
3. Set your endpoint in the component or via environment variable

## Features

- Server-rendered at build time (SSG)
- Zero client-side JavaScript
- SVG maps rendered inline
- Dark mode support via CSS media queries (built into the SVG)

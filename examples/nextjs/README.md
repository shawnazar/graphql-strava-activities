# Next.js Strava Activities Starter

A minimal Next.js app fetching Strava activities from WordPress via GraphQL.

## Setup

1. Install the GraphQL Strava Activities plugin on your WordPress site
2. Copy this folder to start a new project
3. Set your GraphQL endpoint in `.env.local`:

```
NEXT_PUBLIC_GRAPHQL_ENDPOINT=https://yoursite.com/graphql
```

4. Install and run:

```bash
npm install
npm run dev
```

## Files

- `page.js` — Main page fetching and displaying activities
- `components/ActivityCard.js` — Activity card with SVG map
- `components/Stats.js` — Aggregate statistics

## Customisation

- Edit the GraphQL query in `page.js` to select different fields
- Modify component styles to match your design system
- Add pagination using the `offset` argument

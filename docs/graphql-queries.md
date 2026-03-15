# GraphQL Queries

The plugin registers a `stravaActivities` query on the WPGraphQL root query type.

## Basic Query

```graphql
{
  stravaActivities {
    title
    distance
    duration
    date
    svgMap
  }
}
```

Returns all cached activities with the requested fields.

## Query Arguments

| Argument | Type | Default | Description |
|---|---|---|---|
| `first` | Int | `0` (all) | Number of activities to return |
| `type` | String | — | Filter by activity type (e.g. `"Ride"`, `"Run"`) |

## Filtered Query

```graphql
{
  stravaActivities(first: 10, type: "Ride") {
    title
    distance
    duration
    date
    type
    unit
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
```

## Example Response

```json
{
  "data": {
    "stravaActivities": [
      {
        "title": "Morning Ride",
        "distance": 25.4,
        "duration": "1h 16m",
        "date": "2026-03-15T08:30:00Z",
        "type": "Ride",
        "unit": "mi",
        "svgMap": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 300 200\"...>",
        "stravaUrl": "https://www.strava.com/activities/12345678901",
        "photoUrl": "https://dgtzuqphqg23d.cloudfront.net/...",
        "elevationGain": 312.5,
        "averageSpeed": 5.56,
        "maxSpeed": 12.34,
        "averageHeartrate": 145.0,
        "maxHeartrate": 172,
        "calories": 580.0,
        "kudosCount": 12,
        "commentCount": 3,
        "city": "San Francisco",
        "country": "United States",
        "isPrivate": false,
        "poweredByStrava": "Powered by Strava"
      }
    ]
  }
}
```

## Frontend Examples

### Next.js

```javascript
const res = await fetch('/graphql', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    query: `{
      stravaActivities(first: 10) {
        title distance duration svgMap stravaUrl
      }
    }`
  })
});
const { data } = await res.json();
```

### Rendering SVG Maps

The `svgMap` field returns inline SVG markup. Render it directly:

```jsx
<div dangerouslySetInnerHTML={{ __html: activity.svgMap }} />
```

No JavaScript map libraries required — the SVG is generated server-side.

## Strava Brand Attribution

Per [Strava Brand Guidelines](https://developers.strava.com/guidelines/), frontends displaying Strava data must:

1. Display **"Powered by Strava"** attribution (use the `poweredByStrava` field)
2. Style **"View on Strava"** links with Strava orange (`#FC5200`), bold, or underline

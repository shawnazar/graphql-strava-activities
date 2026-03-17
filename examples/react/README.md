# React Components for GraphQL Strava Activities

Reusable React components for consuming the GraphQL Strava Activities API.

## Components

### `<StravaActivities />`

```jsx
import { StravaActivities } from './components';

<StravaActivities
  endpoint="https://yoursite.com/graphql"
  count={10}
  type="Ride"
/>
```

### `<StravaMap />`

```jsx
<StravaMap svgMarkup={activity.svgMap} />
```

### `<StravaStats />`

```jsx
<StravaStats activities={activities} />
```

## Usage

Copy the components from `components.jsx` into your project, or use them as a reference for your own implementation.

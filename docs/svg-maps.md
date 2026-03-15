# SVG Route Maps

The plugin generates inline SVG route maps from Strava's encoded polyline data — no JavaScript map libraries required.

## How It Works

1. Strava stores each activity's route as a [Google encoded polyline](https://developers.google.com/maps/documentation/utilities/polylinealgorithm)
2. The plugin decodes the polyline into latitude/longitude coordinates
3. Coordinates are normalised and projected into an SVG viewBox
4. The result is a lightweight `<svg>` element with a `<path>` tracing the route

## Customisation

### Admin Settings

Go to **Strava → Settings** to configure:

- **Stroke Colour** — hex colour for the route line (default: `#0d9488`)
- **Stroke Width** — line thickness in pixels (default: `2.5`)

### Filters

Override in your theme's `functions.php`:

```php
// Change route colour to Strava orange
add_filter( 'wpgraphql_strava_svg_color', function () {
    return '#FC5200';
} );

// Make lines thicker
add_filter( 'wpgraphql_strava_svg_stroke_width', function () {
    return 4.0;
} );

// Add a CSS class to all SVGs
add_filter( 'wpgraphql_strava_svg_attributes', function ( $attrs ) {
    $attrs['class'] = 'strava-route-map';
    return $attrs;
} );
```

### Shortcode Parameters

The `[strava_map]` shortcode supports per-map overrides:

```
[strava_map index="0" width="400" height="300" color="#FF0000"]
```

## Rendering in Frontends

The `svgMap` GraphQL field returns the complete SVG markup as a string. Render it directly in your frontend:

### React / Next.js

```jsx
<div dangerouslySetInnerHTML={{ __html: activity.svgMap }} />
```

### Astro

```astro
<Fragment set:html={activity.svgMap} />
```

### Vue

```vue
<div v-html="activity.svgMap"></div>
```

## SVG Output

The generated SVG includes:

- `role="img"` and `aria-label` for accessibility
- `viewBox` for responsive scaling
- `stroke-linecap="round"` and `stroke-linejoin="round"` for smooth lines
- 10% padding on all sides
- No fill — just the route path

Activities without GPS data (indoor rides, treadmill runs, yoga, etc.) return an empty string for `svgMap`.

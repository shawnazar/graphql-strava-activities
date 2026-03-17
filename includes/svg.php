<?php
/**
 * Polyline to SVG route map generator.
 *
 * Converts decoded polyline coordinates into inline SVG markup
 * for server-side route map rendering — no JavaScript required.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return allowed HTML tags and attributes for SVG route maps.
 *
 * Used with wp_kses() to safely output SVG markup generated
 * by wpgraphql_strava_polyline_to_svg().
 *
 * @return array<string, array<string, true>> Allowed tags and attributes.
 */
function wpgraphql_strava_allowed_svg_tags(): array {
	return [
		'svg'  => [
			'xmlns'    => true,
			'viewbox'  => true,
			'width'    => true,
			'height'   => true,
			'role'     => true,
			'aria-label' => true,
			'class'    => true,
			'style'    => true,
		],
		'style' => [],
		'rect'  => [
			'x'       => true,
			'y'       => true,
			'width'   => true,
			'height'  => true,
			'rx'      => true,
			'fill'    => true,
			'opacity' => true,
		],
		'line'  => [
			'x1'               => true,
			'y1'               => true,
			'x2'               => true,
			'y2'               => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-dasharray' => true,
			'opacity'          => true,
		],
		'path'  => [
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'opacity'         => true,
		],
	];
}

/**
 * Convert an encoded polyline to an inline SVG route map.
 *
 * Stroke color and width fall back to saved options, then to defaults.
 * Override via function parameters or filters:
 *  - `wpgraphql_strava_svg_color`
 *  - `wpgraphql_strava_svg_stroke_width`
 *  - `wpgraphql_strava_svg_attributes`
 *
 * @param string $polyline     Encoded polyline string.
 * @param int    $width        SVG width in pixels.
 * @param int    $height       SVG height in pixels.
 * @param string $stroke_color Stroke color override (hex).
 * @param float  $stroke_width Stroke width override.
 * @return string SVG markup, or empty string when polyline has < 2 points.
 */
function wpgraphql_strava_polyline_to_svg(
	string $polyline,
	int $width = 300,
	int $height = 200,
	string $stroke_color = '',
	float $stroke_width = 0.0
): string {
	$points = wpgraphql_strava_decode_polyline( $polyline );

	if ( count( $points ) < 2 ) {
		return '';
	}

	// Resolve stroke color: parameter → option → filter → default.
	if ( empty( $stroke_color ) ) {
		$stroke_color = get_option( 'wpgraphql_strava_svg_color', '#0d9488' );
	}

	/** This filter is documented in includes/svg.php */
	$stroke_color = (string) apply_filters( 'wpgraphql_strava_svg_color', $stroke_color );

	// Resolve stroke width: parameter → option → filter → default.
	if ( $stroke_width <= 0.0 ) {
		$stroke_width = (float) get_option( 'wpgraphql_strava_svg_stroke_width', 2.5 );
	}

	/** This filter is documented in includes/svg.php */
	$stroke_width = (float) apply_filters( 'wpgraphql_strava_svg_stroke_width', $stroke_width );

	/**
	 * Filters the SVG stroke colour used in dark mode (prefers-color-scheme: dark).
	 *
	 * @param string $dark_color Dark mode stroke colour.
	 */
	$dark_color = (string) apply_filters( 'wpgraphql_strava_svg_dark_color', '#60d4c8' );

	// Extract coordinate bounds.
	$lats = array_column( $points, 0 );
	$lngs = array_column( $points, 1 );

	$min_lat = min( $lats );
	$max_lat = max( $lats );
	$min_lng = min( $lngs );
	$max_lng = max( $lngs );

	$lat_range = $max_lat - $min_lat;
	$lng_range = $max_lng - $min_lng;

	// Prevent division by zero for single-point or zero-range data.
	if ( 0.0 === $lat_range ) {
		$lat_range = 0.001;
	}
	if ( 0.0 === $lng_range ) {
		$lng_range = 0.001;
	}

	// 10 % padding on each side.
	$padding     = 0.1;
	$draw_width  = $width * ( 1 - 2 * $padding );
	$draw_height = $height * ( 1 - 2 * $padding );
	$offset_x    = $width * $padding;
	$offset_y    = $height * $padding;

	// Normalise coordinates to fit the SVG viewBox.
	// Latitude is inverted (higher lat = lower y in SVG).
	$svg_points = [];
	foreach ( $points as $point ) {
		$x            = $offset_x + ( ( $point[1] - $min_lng ) / $lng_range ) * $draw_width;
		$y            = $offset_y + ( 1 - ( $point[0] - $min_lat ) / $lat_range ) * $draw_height;
		$svg_points[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}

	// Build SVG path data.
	$path_data = 'M' . $svg_points[0];
	for ( $i = 1, $count = count( $svg_points ); $i < $count; $i++ ) {
		$path_data .= 'L' . $svg_points[ $i ];
	}

	/**
	 * Filters the extra attributes applied to the SVG element.
	 *
	 * @param array<string, string> $attrs Key-value attribute pairs.
	 */
	$extra_attrs = (array) apply_filters( 'wpgraphql_strava_svg_attributes', [] );
	$attrs_str   = '';
	foreach ( $extra_attrs as $key => $value ) {
		$attrs_str .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
	}

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="img" aria-label="%3$s"%4$s>'
		. '<style>path{stroke:%6$s}@media(prefers-color-scheme:dark){path{stroke:%8$s}}</style>'
		. '<path d="%5$s" fill="none" stroke-width="%7$s" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>',
		$width,
		$height,
		esc_attr__( 'Activity route map', 'graphql-strava-activities' ),
		$attrs_str,
		esc_attr( $path_data ),
		esc_attr( $stroke_color ),
		esc_attr( (string) $stroke_width ),
		esc_attr( $dark_color )
	);
}

/**
 * Generate an elevation profile SVG from decoded polyline points.
 *
 * Uses latitude changes as a proxy for elevation variation when actual
 * elevation data is not available per-point. The result is a stylised
 * area chart.
 *
 * @param string $polyline       Encoded polyline string.
 * @param float  $elevation_gain Total elevation gain in metres (from Strava).
 * @param int    $width          SVG width in pixels.
 * @param int    $height         SVG height in pixels.
 * @return string SVG markup, or empty string if insufficient data.
 */
function wpgraphql_strava_elevation_profile_svg(
	string $polyline,
	float $elevation_gain = 0.0,
	int $width = 300,
	int $height = 80
): string {
	$points = wpgraphql_strava_decode_polyline( $polyline );

	if ( count( $points ) < 3 || $elevation_gain <= 0.0 ) {
		return '';
	}

	// Calculate cumulative distance and use lat deltas as elevation proxy.
	$distances   = [ 0.0 ];
	$elevations  = [ 0.0 ];
	$total_dist  = 0.0;
	$total_climb = 0.0;

	for ( $i = 1, $count = count( $points ); $i < $count; $i++ ) {
		$dlat = $points[ $i ][0] - $points[ $i - 1 ][0];
		$dlng = $points[ $i ][1] - $points[ $i - 1 ][1];
		$dist = sqrt( $dlat * $dlat + $dlng * $dlng );

		$total_dist    += $dist;
		$distances[]    = $total_dist;
		$total_climb   += abs( $dlat );
		$elevations[]   = $elevations[ $i - 1 ] + $dlat;
	}

	if ( $total_dist <= 0.0 ) {
		return '';
	}

	// Normalise elevation to match the real elevation gain.
	$scale     = $total_climb > 0.0 ? $elevation_gain / $total_climb : 1.0;
	$min_elev  = PHP_FLOAT_MAX;
	$max_elev  = PHP_FLOAT_MIN;

	foreach ( $elevations as &$e ) {
		$e *= $scale;
		$min_elev = min( $min_elev, $e );
		$max_elev = max( $max_elev, $e );
	}
	unset( $e );

	$elev_range = $max_elev - $min_elev;
	if ( $elev_range <= 0.0 ) {
		$elev_range = 1.0;
	}

	$padding    = 0.05;
	$draw_w     = $width * ( 1 - 2 * $padding );
	$draw_h     = $height * ( 1 - 2 * $padding );
	$offset_x   = $width * $padding;
	$offset_y   = $height * $padding;

	// Build path.
	$path_points = [];
	for ( $i = 0, $count = count( $distances ); $i < $count; $i++ ) {
		$x = $offset_x + ( $distances[ $i ] / $total_dist ) * $draw_w;
		$y = $offset_y + ( 1 - ( $elevations[ $i ] - $min_elev ) / $elev_range ) * $draw_h;
		$path_points[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}

	// Area path: line across top, then close to bottom.
	$line_path = 'M' . implode( 'L', $path_points );
	$area_path = $line_path
		. 'L' . round( $offset_x + $draw_w, 1 ) . ',' . round( $offset_y + $draw_h, 1 )
		. 'L' . round( $offset_x, 1 ) . ',' . round( $offset_y + $draw_h, 1 ) . 'Z';

	$stroke_color = (string) apply_filters( 'wpgraphql_strava_svg_color', get_option( 'wpgraphql_strava_svg_color', '#0d9488' ) );
	$dark_color   = (string) apply_filters( 'wpgraphql_strava_svg_dark_color', '#60d4c8' );

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="img" aria-label="%3$s">'
		. '<style>.ep-fill{fill:%4$s;opacity:0.2}.ep-line{stroke:%4$s;fill:none;stroke-width:1.5}'
		. '@media(prefers-color-scheme:dark){.ep-fill{fill:%5$s}.ep-line{stroke:%5$s}}</style>'
		. '<path class="ep-fill" d="%6$s"/>'
		. '<path class="ep-line" d="%7$s" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>',
		$width,
		$height,
		esc_attr__( 'Elevation profile', 'graphql-strava-activities' ),
		esc_attr( $stroke_color ),
		esc_attr( $dark_color ),
		esc_attr( $area_path ),
		esc_attr( $line_path )
	);
}

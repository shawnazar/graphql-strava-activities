<?php
/**
 * WordPress shortcodes for displaying Strava activities.
 *
 * Provides shortcodes so non-headless WordPress sites can display
 * Strava data in posts and pages without GraphQL queries.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpgraphql_strava_register_shortcodes' );

/**
 * Register all Strava shortcodes.
 *
 * @return void
 */
function wpgraphql_strava_register_shortcodes(): void {
	add_shortcode( 'strava_activities', 'wpgraphql_strava_shortcode_activities' );
	add_shortcode( 'strava_activity', 'wpgraphql_strava_shortcode_activity' );
	add_shortcode( 'strava_map', 'wpgraphql_strava_shortcode_map' );
	add_shortcode( 'strava_stats', 'wpgraphql_strava_shortcode_stats' );
	add_shortcode( 'strava_latest', 'wpgraphql_strava_shortcode_latest' );
}

/**
 * Render an activity card as HTML.
 *
 * Shared by multiple shortcodes.
 *
 * @param array<string, mixed> $activity Activity data.
 * @return string HTML markup.
 */
function wpgraphql_strava_render_shortcode_card( array $activity ): string {
	$date = '';
	if ( ! empty( $activity['date'] ) ) {
		$ts   = strtotime( $activity['date'] );
		$date = $ts ? wp_date( 'M j, Y', $ts ) : '';
	}

	$html  = '<div class="strava-activity-card" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:16px;font-family:sans-serif;">';

	if ( ! empty( $activity['svgMap'] ) ) {
		$html .= '<div class="strava-activity-map" style="margin-bottom:12px;">';
		$html .= $activity['svgMap'];
		$html .= '</div>';
	}

	$html .= '<div class="strava-activity-details">';
	$html .= '<strong style="font-size:16px;">' . esc_html( $activity['title'] ?? '' ) . '</strong>';

	if ( ! empty( $date ) ) {
		$html .= '<div style="color:#6b7280;font-size:13px;margin-top:2px;">' . esc_html( $date ) . '</div>';
	}

	$html .= '<div style="display:flex;gap:16px;margin-top:8px;font-size:14px;">';
	$html .= '<span><strong>' . esc_html( (string) ( $activity['distance'] ?? '0' ) ) . '</strong> ' . esc_html( $activity['unit'] ?? 'mi' ) . '</span>';
	$html .= '<span><strong>' . esc_html( $activity['duration'] ?? '' ) . '</strong></span>';
	$html .= '<span>' . esc_html( $activity['type'] ?? '' ) . '</span>';
	$html .= '</div>';

	if ( ! empty( $activity['photoUrl'] ) ) {
		$html .= '<div style="margin-top:12px;"><img src="' . esc_url( $activity['photoUrl'] ) . '" alt="' . esc_attr__( 'Activity photo', 'graphql-strava-activities' ) . '" style="max-width:100%;border-radius:6px;" /></div>';
	}

	if ( ! empty( $activity['stravaUrl'] ) ) {
		$html .= '<div style="margin-top:8px;"><a href="' . esc_url( $activity['stravaUrl'] ) . '" target="_blank" rel="noopener noreferrer" style="color:#FC5200;font-weight:bold;text-decoration:none;font-size:13px;">' . esc_html__( 'View on Strava', 'graphql-strava-activities' ) . ' &rarr;</a></div>';
	}

	$html .= '</div></div>';

	return $html;
}

/**
 * [strava_activities] — Render a list of activity cards.
 *
 * Attributes: count (int), type (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_activities( $atts ): string {
	$atts = shortcode_atts(
		[
			'count' => '10',
			'type'  => '',
		],
		$atts,
		'strava_activities'
	);

	$count      = max( 1, (int) $atts['count'] );
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! empty( $atts['type'] ) ) {
		$type_filter = sanitize_text_field( $atts['type'] );
		$activities  = array_values(
			array_filter(
				$activities,
				static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type_filter
			)
		);
	}

	$activities = array_slice( $activities, 0, $count );

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	$html = '<div class="strava-activities">';
	foreach ( $activities as $activity ) {
		$html .= wpgraphql_strava_render_shortcode_card( $activity );
	}
	$html .= '</div>';

	return $html;
}

/**
 * [strava_activity] — Render a single activity card by index.
 *
 * Attributes: index (int, 0-based).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_activity( $atts ): string {
	$atts = shortcode_atts(
		[ 'index' => '0' ],
		$atts,
		'strava_activity'
	);

	$index      = max( 0, (int) $atts['index'] );
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! isset( $activities[ $index ] ) ) {
		return '<p class="strava-empty">' . esc_html__( 'Activity not found.', 'graphql-strava-activities' ) . '</p>';
	}

	return wpgraphql_strava_render_shortcode_card( $activities[ $index ] );
}

/**
 * [strava_map] — Render just the SVG route map for an activity.
 *
 * Attributes: index (int), width (int), height (int), color (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string SVG markup.
 */
function wpgraphql_strava_shortcode_map( $atts ): string {
	$atts = shortcode_atts(
		[
			'index'  => '0',
			'width'  => '300',
			'height' => '200',
			'color'  => '',
		],
		$atts,
		'strava_map'
	);

	$activities = wpgraphql_strava_get_cached_activities( 0 );
	$index      = max( 0, (int) $atts['index'] );

	if ( ! isset( $activities[ $index ] ) || empty( $activities[ $index ]['svgMap'] ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No route map available.', 'graphql-strava-activities' ) . '</p>';
	}

	// If custom dimensions or color requested, we need the raw polyline — not available from cache.
	// Return the cached SVG wrapped in a container with the requested dimensions.
	$width  = max( 100, (int) $atts['width'] );
	$height = max( 100, (int) $atts['height'] );

	return '<div class="strava-map" style="width:' . esc_attr( (string) $width ) . 'px;max-width:100%;">'
		. $activities[ $index ]['svgMap']
		. '</div>';
}

/**
 * [strava_stats] — Render aggregate activity statistics.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_stats( $atts ): string {
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	$total_distance = 0.0;
	$total_count    = count( $activities );
	$unit           = $activities[0]['unit'] ?? 'mi';
	$types          = [];

	foreach ( $activities as $activity ) {
		$total_distance += (float) ( $activity['distance'] ?? 0 );
		$type            = $activity['type'] ?? 'Other';
		if ( ! isset( $types[ $type ] ) ) {
			$types[ $type ] = 0;
		}
		++$types[ $type ];
	}

	$html  = '<div class="strava-stats" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-family:sans-serif;">';
	$html .= '<div style="display:flex;gap:24px;flex-wrap:wrap;">';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Activities', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( (string) $total_count ) . '</div></div>';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Total Distance', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format( $total_distance, 1 ) ) . ' <span style="font-size:14px;font-weight:400;">' . esc_html( $unit ) . '</span></div></div>';
	$html .= '</div>';

	if ( count( $types ) > 0 ) {
		arsort( $types );
		$html .= '<div style="margin-top:12px;font-size:13px;color:#6b7280;">';
		$parts = [];
		foreach ( $types as $type => $type_count ) {
			$parts[] = esc_html( $type ) . ': ' . esc_html( (string) $type_count );
		}
		$html .= implode( ' &middot; ', $parts );
		$html .= '</div>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * [strava_latest] — Render the most recent activity, optionally filtered by type.
 *
 * Attributes: type (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_latest( $atts ): string {
	$atts = shortcode_atts(
		[ 'type' => '' ],
		$atts,
		'strava_latest'
	);

	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! empty( $atts['type'] ) ) {
		$type_filter = sanitize_text_field( $atts['type'] );
		$activities  = array_values(
			array_filter(
				$activities,
				static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type_filter
			)
		);
	}

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	return wpgraphql_strava_render_shortcode_card( $activities[0] );
}

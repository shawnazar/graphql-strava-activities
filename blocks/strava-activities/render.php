<?php
/**
 * Server-side render for the Strava Activities block.
 *
 * @package WPGraphQL\Strava
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is already escaped.
echo wpgraphql_strava_render_block( $attributes );

/**
 * Build and execute the shortcode for this block.
 *
 * @param array<string, mixed> $wpgraphql_strava_block_attrs Block attributes.
 * @return string Rendered shortcode output.
 */
function wpgraphql_strava_render_block( array $wpgraphql_strava_block_attrs ): string {
	$wpgraphql_strava_shortcode = $wpgraphql_strava_block_attrs['shortcode'] ?? 'strava_activities';
	$wpgraphql_strava_atts      = '';

	switch ( $wpgraphql_strava_shortcode ) {
		case 'strava_activities':
			$wpgraphql_strava_count = (int) ( $wpgraphql_strava_block_attrs['count'] ?? 10 );
			$wpgraphql_strava_type  = sanitize_text_field( $wpgraphql_strava_block_attrs['type'] ?? '' );
			$wpgraphql_strava_atts  = ' count="' . $wpgraphql_strava_count . '"';
			if ( ! empty( $wpgraphql_strava_type ) ) {
				$wpgraphql_strava_atts .= ' type="' . esc_attr( $wpgraphql_strava_type ) . '"';
			}
			break;

		case 'strava_activity':
		case 'strava_map':
			$wpgraphql_strava_index = (int) ( $wpgraphql_strava_block_attrs['index'] ?? 0 );
			$wpgraphql_strava_atts  = ' index="' . $wpgraphql_strava_index . '"';
			break;

		case 'strava_latest':
			$wpgraphql_strava_type = sanitize_text_field( $wpgraphql_strava_block_attrs['type'] ?? '' );
			if ( ! empty( $wpgraphql_strava_type ) ) {
				$wpgraphql_strava_atts = ' type="' . esc_attr( $wpgraphql_strava_type ) . '"';
			}
			break;
	}

	return do_shortcode( '[' . $wpgraphql_strava_shortcode . $wpgraphql_strava_atts . ']' );
}

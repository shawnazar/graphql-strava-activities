<?php
/**
 * REST API endpoint for Strava activities.
 *
 * Registers a public endpoint so non-headless sites or REST-based
 * frontends can access cached Strava activity data.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'wpgraphql_strava_register_rest_routes' );

/**
 * Register the REST API route.
 *
 * @return void
 */
function wpgraphql_strava_register_rest_routes(): void {
	register_rest_route(
		'wpgraphql-strava/v1',
		'/activities',
		[
			'methods'             => 'GET',
			'callback'            => 'wpgraphql_strava_rest_activities',
			'permission_callback' => '__return_true',
			'args'                => [
				'count'  => [
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'maximum'           => 200,
					'sanitize_callback' => 'absint',
				],
				'offset' => [
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'sanitize_callback' => 'absint',
				],
				'type'    => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'user_id' => [
					'type'              => 'integer',
					'default'           => 0,
					'minimum'           => 0,
					'sanitize_callback' => 'absint',
				],
			],
		]
	);
}

/**
 * Handle the /activities REST request.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response Activities response.
 */
function wpgraphql_strava_rest_activities( \WP_REST_Request $request ): \WP_REST_Response {
	$count      = (int) $request->get_param( 'count' );
	$offset     = (int) $request->get_param( 'offset' );
	$type       = (string) $request->get_param( 'type' );
	$user_id    = (int) $request->get_param( 'user_id' );
	$activities = wpgraphql_strava_get_user_activities( 0, $user_id );

	// Type filter.
	if ( ! empty( $type ) ) {
		$activities = array_values(
			array_filter(
				$activities,
				static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type
			)
		);
	}

	$total = count( $activities );

	// Apply offset and count.
	if ( $offset > 0 || $count > 0 ) {
		$activities = array_slice( $activities, $offset, $count > 0 ? $count : null );
	}

	$response = new \WP_REST_Response( $activities, 200 );
	$response->header( 'X-WP-Total', (string) $total );

	return $response;
}

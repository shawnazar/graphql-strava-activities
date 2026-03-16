<?php
/**
 * Strava webhook event handler.
 *
 * Receives real-time activity create/update/delete events from Strava
 * and triggers a cache refresh. Falls back to cron-based syncing when
 * webhooks are not configured.
 *
 * @package WPGraphQL\Strava
 * @see https://developers.strava.com/docs/webhooks/
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'wpgraphql_strava_register_webhook_routes' );

/**
 * Register the webhook REST routes.
 *
 * @return void
 */
function wpgraphql_strava_register_webhook_routes(): void {
	// Verification challenge (GET) — Strava sends this when you create a subscription.
	register_rest_route(
		'wpgraphql-strava/v1',
		'/webhook',
		[
			[
				'methods'             => 'GET',
				'callback'            => 'wpgraphql_strava_webhook_verify',
				'permission_callback' => '__return_true',
				'args'                => [
					'hub.mode'         => [
						'type' => 'string',
						'required' => true,
					],
					'hub.verify_token' => [
						'type' => 'string',
						'required' => true,
					],
					'hub.challenge'    => [
						'type' => 'string',
						'required' => true,
					],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => 'wpgraphql_strava_webhook_event',
				'permission_callback' => '__return_true',
			],
		]
	);
}

/**
 * Handle the Strava webhook verification challenge.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error Response.
 */
function wpgraphql_strava_webhook_verify( \WP_REST_Request $request ) {
	$mode         = sanitize_text_field( $request->get_param( 'hub.mode' ) ?? '' );
	$verify_token = sanitize_text_field( $request->get_param( 'hub.verify_token' ) ?? '' );
	$challenge    = sanitize_text_field( $request->get_param( 'hub.challenge' ) ?? '' );

	$expected_token = get_option( 'wpgraphql_strava_webhook_verify_token', '' );

	if ( 'subscribe' !== $mode || empty( $expected_token ) || $verify_token !== $expected_token ) {
		return new \WP_Error( 'forbidden', 'Invalid verify token.', [ 'status' => 403 ] );
	}

	return new \WP_REST_Response( [ 'hub.challenge' => $challenge ], 200 );
}

/**
 * Handle incoming Strava webhook events.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response Response.
 */
function wpgraphql_strava_webhook_event( \WP_REST_Request $request ): \WP_REST_Response {
	$body = $request->get_json_params();

	$object_type = sanitize_text_field( $body['object_type'] ?? '' );
	$aspect_type = sanitize_text_field( $body['aspect_type'] ?? '' );

	// Only handle activity events.
	if ( 'activity' !== $object_type ) {
		return new \WP_REST_Response( [ 'status' => 'ignored' ], 200 );
	}

	// Clear cache on create, update, or delete.
	if ( in_array( $aspect_type, [ 'create', 'update', 'delete' ], true ) ) {
		delete_transient( 'wpgraphql_strava_activities' );

		/**
		 * Fires when a Strava webhook event triggers a cache clear.
		 *
		 * @param string $aspect_type Event type (create, update, delete).
		 * @param array<string, mixed> $body Full webhook payload.
		 */
		do_action( 'wpgraphql_strava_webhook_event', $aspect_type, $body );
	}

	// Strava requires a 200 response within 2 seconds.
	return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
}

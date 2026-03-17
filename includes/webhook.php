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

	$object_id = (int) ( $body['object_id'] ?? 0 );

	// Handle activity events with partial cache updates.
	if ( in_array( $aspect_type, [ 'create', 'update', 'delete' ], true ) ) {
		$cached = wpgraphql_strava_cache_get( WPGRAPHQL_STRAVA_CACHE_KEY );

		if ( 'delete' === $aspect_type && is_array( $cached ) && $object_id > 0 ) {
			// Remove the deleted activity from cache without re-fetching.
			$strava_url = 'https://www.strava.com/activities/' . $object_id;
			$cached     = array_values(
				array_filter( $cached, static fn( $a ) => ( $a['stravaUrl'] ?? '' ) !== $strava_url )
			);
			wpgraphql_strava_cache_set( WPGRAPHQL_STRAVA_CACHE_KEY, $cached, WPGRAPHQL_STRAVA_CACHE_TTL );
		} else {
			// For create/update, clear cache so next request fetches fresh data.
			wpgraphql_strava_cache_delete( WPGRAPHQL_STRAVA_CACHE_KEY );
		}

		/**
		 * Fires when a Strava webhook event is processed.
		 *
		 * @param string $aspect_type Event type (create, update, delete).
		 * @param array<string, mixed> $body Full webhook payload.
		 */
		do_action( 'wpgraphql_strava_webhook_event', $aspect_type, $body );

		// Publish event for GraphQL subscription consumers.
		wpgraphql_strava_publish_subscription_event( $aspect_type, $object_id );
	}

	// Strava requires a 200 response within 2 seconds.
	return new \WP_REST_Response( [ 'status' => 'ok' ], 200 );
}

/**
 * Publish a subscription event for GraphQL consumers.
 *
 * Stores the latest activity event in a transient so subscription
 * resolvers or polling clients can detect changes. When WPGraphQL
 * adds native subscription support, this can be extended to push
 * events directly.
 *
 * @param string $event_type create, update, or delete.
 * @param int    $activity_id Strava activity ID.
 * @return void
 */
function wpgraphql_strava_publish_subscription_event( string $event_type, int $activity_id ): void {
	$event = [
		'type'        => $event_type,
		'activityId'  => $activity_id,
		'timestamp'   => time(),
	];

	set_transient( 'wpgraphql_strava_last_event', $event, HOUR_IN_SECONDS );

	/**
	 * Fires when a subscription event is published.
	 *
	 * Developers can hook into this to push events to external
	 * subscription systems (e.g. Pusher, Mercure, or a custom
	 * WebSocket server).
	 *
	 * @param array<string, mixed> $event Event data.
	 */
	do_action( 'wpgraphql_strava_subscription_event', $event );
}

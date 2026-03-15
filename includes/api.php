<?php
/**
 * Strava API client.
 *
 * Handles fetching activities, activity detail (photos), and OAuth token refresh.
 * All functions return empty arrays on failure — they never throw.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strava API v3 base URL.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_API_BASE', 'https://www.strava.com/api/v3' );

/**
 * Fetch recent activities from the Strava API.
 *
 * Uses the stored access token and refreshes automatically when expired.
 *
 * @param int $count Number of activities to fetch (max 200).
 * @return array<int, array<string, mixed>> Activity data from Strava.
 */
function wpgraphql_strava_fetch_activities( int $count = 200 ): array {
	$access_token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

	if ( empty( $access_token ) ) {
		return [];
	}

	// Refresh the token if it has expired.
	$expires_at = (int) get_option( 'wpgraphql_strava_token_expires_at', 0 );
	if ( $expires_at > 0 && time() >= $expires_at ) {
		$access_token = wpgraphql_strava_refresh_access_token();
		if ( empty( $access_token ) ) {
			return [];
		}
	}

	$response = wp_remote_get(
		WPGRAPHQL_STRAVA_API_BASE . '/athlete/activities',
		[
			'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
			'timeout' => 15,
			'body'    => [
				'per_page' => $count,
				'page'     => 1,
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		wp_trigger_error( __FUNCTION__, 'WPGraphQL Strava: API error — ' . $response->get_error_message() );
		return [];
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $status_code ) {
		$error_context = wpgraphql_strava_describe_api_error( $status_code );
		wp_trigger_error( __FUNCTION__, 'WPGraphQL Strava: ' . $error_context );

		// Try refreshing the token once on 401.
		if ( 401 === $status_code ) {
			$new_token = wpgraphql_strava_refresh_access_token();
			if ( ! empty( $new_token ) ) {
				return wpgraphql_strava_fetch_activities_with_token( $new_token, $count );
			}
		}

		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : [];
}

/**
 * Fetch activities using a specific access token.
 *
 * Used internally after a token refresh to avoid recursion.
 *
 * @param string $token Access token.
 * @param int    $count Number of activities.
 * @return array<int, array<string, mixed>>
 */
function wpgraphql_strava_fetch_activities_with_token( string $token, int $count ): array {
	$response = wp_remote_get(
		WPGRAPHQL_STRAVA_API_BASE . '/athlete/activities',
		[
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 15,
			'body'    => [
				'per_page' => $count,
				'page'     => 1,
			],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : [];
}

/**
 * Fetch a single activity's detail (includes photos).
 *
 * Rate-limit note: Strava allows 100 requests per 15 minutes and
 * 1 000 per day.  Each cache refresh uses 1 list call + up to 5
 * detail calls = 6 total.  With twice-daily cron that is 12/day.
 *
 * @param int    $activity_id Strava activity ID.
 * @param string $token       Access token override (uses stored when empty).
 * @return array<string, mixed> Activity detail, or empty array on failure.
 */
function wpgraphql_strava_fetch_activity_detail( int $activity_id, string $token = '' ): array {
	if ( empty( $token ) ) {
		$token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );
	}

	if ( empty( $token ) || $activity_id <= 0 ) {
		return [];
	}

	$response = wp_remote_get(
		WPGRAPHQL_STRAVA_API_BASE . '/activities/' . $activity_id,
		[
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 10,
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? $data : [];
}

/**
 * Refresh the Strava access token using the stored refresh token.
 *
 * Persists updated tokens and expiry to the database on success.
 *
 * @return string New access token, or empty string on failure.
 */
function wpgraphql_strava_refresh_access_token(): string {
	$client_id     = get_option( 'wpgraphql_strava_client_id', '' );
	$client_secret = wpgraphql_strava_get_option( 'wpgraphql_strava_client_secret' );
	$refresh_token = wpgraphql_strava_get_option( 'wpgraphql_strava_refresh_token' );

	if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
		return '';
	}

	$response = wp_remote_post(
		'https://www.strava.com/oauth/token',
		[
			'timeout' => 15,
			'body'    => [
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			],
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_trigger_error( __FUNCTION__, 'WPGraphQL Strava: token refresh failed.' );
		return '';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
		return '';
	}

	// Persist updated tokens (encrypted at rest when key is configured).
	wpgraphql_strava_update_option( 'wpgraphql_strava_access_token', sanitize_text_field( $data['access_token'] ) );

	if ( ! empty( $data['refresh_token'] ) ) {
		wpgraphql_strava_update_option( 'wpgraphql_strava_refresh_token', sanitize_text_field( $data['refresh_token'] ) );
	}

	if ( ! empty( $data['expires_at'] ) ) {
		update_option( 'wpgraphql_strava_token_expires_at', (int) $data['expires_at'] );
	}

	return $data['access_token'];
}

/**
 * Return a human-readable description for a Strava API HTTP status code.
 *
 * @param int $status_code HTTP status code.
 * @return string Description.
 */
function wpgraphql_strava_describe_api_error( int $status_code ): string {
	switch ( $status_code ) {
		case 401:
			return 'Unauthorized (401) — access token is invalid or expired.';
		case 403:
			return 'Forbidden (403) — insufficient permissions. Check your OAuth scopes.';
		case 404:
			return 'Not Found (404) — the requested resource does not exist.';
		case 429:
			return 'Rate Limited (429) — too many requests. Strava allows 100 requests per 15 minutes.';
		default:
			if ( $status_code >= 500 ) {
				return 'Server Error (' . $status_code . ') — Strava is experiencing issues. Try again later.';
			}
			return 'Unexpected status ' . $status_code . '.';
	}
}

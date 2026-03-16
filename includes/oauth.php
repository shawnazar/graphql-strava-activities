<?php
/**
 * OAuth callback handler for Strava authorization.
 *
 * Handles the Strava OAuth redirect, exchanges the authorization code
 * for access/refresh tokens, and persists them via the existing
 * encryption infrastructure.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strava OAuth authorization endpoint.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_OAUTH_AUTHORIZE_URL', 'https://www.strava.com/oauth/authorize' );

/**
 * Strava OAuth token endpoint.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_OAUTH_TOKEN_URL', 'https://www.strava.com/oauth/token' );

add_action( 'admin_init', 'wpgraphql_strava_handle_oauth_callback' );

/**
 * Handle the OAuth callback from Strava.
 *
 * Listens for the `strava_oauth_code` query parameter on the Getting Started
 * page. Verifies the state nonce, exchanges the code for tokens, and saves them.
 *
 * @return void
 */
function wpgraphql_strava_handle_oauth_callback(): void {
	// Only process on our admin page with the oauth code present.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state parameter serves as nonce.
	if ( ! isset( $_GET['strava_oauth_code'] ) || ! isset( $_GET['state'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Verify the state parameter (CSRF protection).
	$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
	if ( ! wp_verify_nonce( $state, 'wpgraphql_strava_oauth' ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'OAuth failed: invalid state parameter. Please try again.', 'graphql-strava-activities' ),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	$code          = sanitize_text_field( wp_unslash( $_GET['strava_oauth_code'] ) );
	$client_id     = get_option( 'wpgraphql_strava_client_id', '' );
	$client_secret = wpgraphql_strava_get_option( 'wpgraphql_strava_client_secret' );

	if ( empty( $client_id ) || empty( $client_secret ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'OAuth failed: Client ID and Client Secret must be saved first.', 'graphql-strava-activities' ),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	// Exchange the authorization code for tokens.
	$response = wp_remote_post(
		WPGRAPHQL_STRAVA_OAUTH_TOKEN_URL,
		[
			'timeout' => 15,
			'body'    => [
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'grant_type'    => 'authorization_code',
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'OAuth failed: ', 'graphql-strava-activities' ) . $response->get_error_message(),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$data        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $status_code || ! is_array( $data ) || empty( $data['access_token'] ) ) {
		$error_detail = '';
		if ( is_array( $data ) && ! empty( $data['message'] ) ) {
			$error_detail = sanitize_text_field( $data['message'] );
		}

		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: HTTP status code, 2: Error detail */
					__( 'OAuth token exchange failed (HTTP %1$d). %2$s', 'graphql-strava-activities' ),
					$status_code,
					$error_detail
				),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	// Persist tokens (encrypted at rest when key is configured).
	wpgraphql_strava_update_option( 'wpgraphql_strava_access_token', sanitize_text_field( $data['access_token'] ) );

	if ( ! empty( $data['refresh_token'] ) ) {
		wpgraphql_strava_update_option( 'wpgraphql_strava_refresh_token', sanitize_text_field( $data['refresh_token'] ) );
	}

	if ( ! empty( $data['expires_at'] ) ) {
		update_option( 'wpgraphql_strava_token_expires_at', (int) $data['expires_at'] );
	}

	// Trigger an immediate sync so the user sees activities right away.
	wpgraphql_strava_cache_delete( WPGRAPHQL_STRAVA_CACHE_KEY );
	$activities = wpgraphql_strava_get_cached_activities( 1 );

	if ( ! empty( $activities ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %d: Number of activities synced */
					__( 'Connected to Strava! Synced %d activities.', 'graphql-strava-activities' ),
					count( wpgraphql_strava_get_cached_activities( 0 ) )
				),
			],
			30
		);
	} else {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'success',
				'message' => __( 'Connected to Strava! No activities found yet — they will appear after your next workout.', 'graphql-strava-activities' ),
			],
			30
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
	exit;
}

/**
 * Build the Strava OAuth authorization URL.
 *
 * Uses the plugin's own callback URL as the redirect_uri. The state
 * parameter is a nonce for CSRF protection.
 *
 * @return string Authorization URL, or empty string if Client ID is not configured.
 */
function wpgraphql_strava_get_oauth_url(): string {
	$client_id = get_option( 'wpgraphql_strava_client_id', '' );

	if ( empty( $client_id ) ) {
		return '';
	}

	$state        = wp_create_nonce( 'wpgraphql_strava_oauth' );
	$redirect_uri = wpgraphql_strava_get_oauth_redirect_uri();

	return add_query_arg(
		[
			'client_id'       => $client_id,
			'response_type'   => 'code',
			'redirect_uri'    => $redirect_uri,
			'scope'           => 'read,activity:read_all',
			'approval_prompt' => 'force',
			'state'           => $state,
		],
		WPGRAPHQL_STRAVA_OAUTH_AUTHORIZE_URL
	);
}

/**
 * Get the OAuth redirect URI that Strava will send the user back to.
 *
 * This must match the "Authorization Callback Domain" configured in
 * the Strava API application settings.
 *
 * @return string Redirect URI.
 */
function wpgraphql_strava_get_oauth_redirect_uri(): string {
	return admin_url( 'admin.php?page=wpgraphql-strava-settings' );
}

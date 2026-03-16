<?php
/**
 * GDPR privacy hooks for personal data export and erasure.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_privacy_personal_data_exporters', 'wpgraphql_strava_register_exporter' );
add_filter( 'wp_privacy_personal_data_erasers', 'wpgraphql_strava_register_eraser' );
add_action( 'admin_init', 'wpgraphql_strava_add_privacy_policy' );

/**
 * Register the personal data exporter.
 *
 * @param array<string, array<string, mixed>> $exporters Registered exporters.
 * @return array<string, array<string, mixed>> Modified exporters.
 */
function wpgraphql_strava_register_exporter( array $exporters ): array {
	$exporters['graphql-strava-activities'] = [
		'exporter_friendly_name' => __( 'GraphQL Strava Activities', 'graphql-strava-activities' ),
		'callback'               => 'wpgraphql_strava_export_personal_data',
	];
	return $exporters;
}

/**
 * Register the personal data eraser.
 *
 * @param array<string, array<string, mixed>> $erasers Registered erasers.
 * @return array<string, array<string, mixed>> Modified erasers.
 */
function wpgraphql_strava_register_eraser( array $erasers ): array {
	$erasers['graphql-strava-activities'] = [
		'eraser_friendly_name' => __( 'GraphQL Strava Activities', 'graphql-strava-activities' ),
		'callback'             => 'wpgraphql_strava_erase_personal_data',
	];
	return $erasers;
}

/**
 * Export cached Strava activity data.
 *
 * @param string $email_address User email (not used — single-user plugin).
 * @param int    $page          Page number.
 * @return array<string, mixed> Export response.
 */
function wpgraphql_strava_export_personal_data( string $email_address, int $page = 1 ): array {
	$activities = wpgraphql_strava_get_cached_activities( 0 );
	$data       = [];

	foreach ( $activities as $activity ) {
		$data[] = [
			'group_id'    => 'strava-activities',
			'group_label' => __( 'Strava Activities', 'graphql-strava-activities' ),
			'item_id'     => 'strava-activity-' . sanitize_title( $activity['title'] ?? 'unknown' ),
			'data'        => [
				[
					'name' => __( 'Title', 'graphql-strava-activities' ),
					'value' => $activity['title'] ?? '',
				],
				[
					'name' => __( 'Type', 'graphql-strava-activities' ),
					'value' => $activity['type'] ?? '',
				],
				[
					'name' => __( 'Date', 'graphql-strava-activities' ),
					'value' => $activity['date'] ?? '',
				],
				[
					'name' => __( 'Distance', 'graphql-strava-activities' ),
					'value' => ( $activity['distance'] ?? '' ) . ' ' . ( $activity['unit'] ?? '' ),
				],
				[
					'name' => __( 'City', 'graphql-strava-activities' ),
					'value' => $activity['city'] ?? '',
				],
				[
					'name' => __( 'Country', 'graphql-strava-activities' ),
					'value' => $activity['country'] ?? '',
				],
			],
		];
	}

	return [
		'data' => $data,
		'done' => true,
	];
}

/**
 * Erase cached Strava data and stored credentials.
 *
 * @param string $email_address User email (not used — single-user plugin).
 * @param int    $page          Page number.
 * @return array<string, mixed> Erasure response.
 */
function wpgraphql_strava_erase_personal_data( string $email_address, int $page = 1 ): array {
	wpgraphql_strava_cache_delete( WPGRAPHQL_STRAVA_CACHE_KEY );
	delete_option( 'wpgraphql_strava_access_token' );
	delete_option( 'wpgraphql_strava_refresh_token' );
	delete_option( 'wpgraphql_strava_token_expires_at' );
	delete_option( 'wpgraphql_strava_last_sync' );

	return [
		'items_removed'  => true,
		'items_retained' => false,
		'messages'       => [ __( 'Strava activity cache and tokens have been erased.', 'graphql-strava-activities' ) ],
		'done'           => true,
	];
}

/**
 * Add privacy policy suggestion text.
 *
 * @return void
 */
function wpgraphql_strava_add_privacy_policy(): void {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = sprintf(
		'<h2>%s</h2><p>%s</p>',
		__( 'Strava Activity Data', 'graphql-strava-activities' ),
		__( 'This site displays fitness activity data from Strava. Activity data (title, distance, duration, route maps, and location) is cached locally and refreshed periodically. API credentials are stored in the WordPress database with optional at-rest encryption. For more information, see the Strava Privacy Policy at https://www.strava.com/legal/privacy.', 'graphql-strava-activities' )
	);

	wp_add_privacy_policy_content( 'GraphQL Strava Activities', $content );
}

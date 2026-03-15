<?php
/**
 * Transient caching for Strava activities.
 *
 * Fetches, normalises, and caches activity data with a configurable TTL.
 * Enriches top activities with photos and filters by route availability.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient key for cached activities.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_CACHE_KEY', 'wpgraphql_strava_activities' );

/**
 * Default cache TTL in seconds (12 hours).
 *
 * @var int
 */
define( 'WPGRAPHQL_STRAVA_CACHE_TTL', 12 * HOUR_IN_SECONDS );

/**
 * Get cached Strava activities, fetching fresh data when the cache is empty.
 *
 * Always requests the maximum from the API (200) and caches the full set.
 * The $count parameter only limits how many are returned to the caller.
 *
 * @param int $count Number of activities to return (0 = all cached).
 * @return array<int, array<string, mixed>> Processed activity data.
 */
function wpgraphql_strava_get_cached_activities( int $count = 0 ): array {
	$cached = get_transient( WPGRAPHQL_STRAVA_CACHE_KEY );

	if ( is_array( $cached ) && count( $cached ) > 0 ) {
		return $count > 0 ? array_slice( $cached, 0, $count ) : $cached;
	}

	$access_token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

	if ( empty( $access_token ) ) {
		return [];
	}

	// Fetch maximum activities from Strava (1 API call).
	$raw_activities = wpgraphql_strava_fetch_activities( 200 );

	if ( empty( $raw_activities ) ) {
		return [];
	}

	$activities = wpgraphql_strava_process_activities( $raw_activities );

	// Fetch photos for the top 5 activities (5 detail API calls max).
	$photo_limit = min( count( $activities ), 5 );
	for ( $i = 0; $i < $photo_limit; $i++ ) {
		if ( ! empty( $activities[ $i ]['photoUrl'] ) || empty( $activities[ $i ]['stravaUrl'] ) ) {
			continue;
		}

		$strava_id = (int) basename( $activities[ $i ]['stravaUrl'] );
		if ( $strava_id <= 0 ) {
			continue;
		}

		$detail = wpgraphql_strava_fetch_activity_detail( $strava_id );
		if ( ! empty( $detail['photos']['primary']['urls'] ) ) {
			$urls                         = $detail['photos']['primary']['urls'];
			$activities[ $i ]['photoUrl'] = esc_url_raw( $urls['600'] ?? $urls['100'] ?? '' );
		}

		// 200 ms pause between detail calls to respect rate limits.
		usleep( 200000 );
	}

	/**
	 * Filters processed activities before they are cached.
	 *
	 * @param array<int, array<string, mixed>> $activities Processed activities.
	 */
	$activities = (array) apply_filters( 'wpgraphql_strava_activities', $activities );

	/**
	 * Filters the cache TTL in seconds.
	 *
	 * @param int $ttl Cache duration in seconds. Default 12 hours.
	 */
	$ttl = (int) apply_filters( 'wpgraphql_strava_cache_ttl', WPGRAPHQL_STRAVA_CACHE_TTL );

	set_transient( WPGRAPHQL_STRAVA_CACHE_KEY, $activities, $ttl );
	update_option( 'wpgraphql_strava_last_sync', time() );

	return $count > 0 ? array_slice( $activities, 0, $count ) : $activities;
}

/**
 * Process raw Strava API activities into the normalised format.
 *
 * @param array<int, array<string, mixed>> $raw_activities Raw API response.
 * @return array<int, array<string, mixed>> Processed activities.
 */
function wpgraphql_strava_process_activities( array $raw_activities ): array {
	$unit = get_option( 'wpgraphql_strava_units', 'mi' );

	/**
	 * Filters the allowed activity types.
	 *
	 * Return an empty array to allow all types.
	 *
	 * @param string[] $types Activity type slugs (e.g. "Ride", "Run").
	 */
	$allowed_types = (array) apply_filters( 'wpgraphql_strava_activity_types', [] );

	$processed = [];

	foreach ( $raw_activities as $activity ) {
		if ( ! is_array( $activity ) ) {
			continue;
		}

		// Filter by activity type when a whitelist is set.
		$type = $activity['type'] ?? 'Workout';
		if ( ! empty( $allowed_types ) && ! in_array( $type, $allowed_types, true ) ) {
			continue;
		}

		// Optionally skip activities without a GPS route.
		$polyline       = $activity['map']['summary_polyline'] ?? '';
		$include_no_route = (bool) get_option( 'wpgraphql_strava_include_no_route', false );
		if ( empty( $polyline ) && ! $include_no_route ) {
			continue;
		}

		// Distance conversion.
		$distance_raw = isset( $activity['distance'] ) ? (float) $activity['distance'] : 0.0;
		$distance     = 'km' === $unit
			? round( $distance_raw / 1000, 2 )
			: round( $distance_raw / 1609.344, 2 );

		$moving_time = isset( $activity['moving_time'] ) ? (int) $activity['moving_time'] : 0;

		$strava_id = $activity['id'] ?? 0;

		// Extract primary photo URL if present in the list response.
		$photo_url = '';
		if ( ! empty( $activity['photos']['primary']['urls'] ) ) {
			$urls      = $activity['photos']['primary']['urls'];
			$photo_url = $urls['600'] ?? $urls['100'] ?? '';
		}

		$processed[] = [
			'title'     => sanitize_text_field( $activity['name'] ?? __( 'Activity', 'graphql-strava-activities' ) ),
			'distance'  => $distance,
			'duration'  => wpgraphql_strava_format_duration( $moving_time ),
			'date'      => $activity['start_date'] ?? '',
			'svgMap'    => wpgraphql_strava_polyline_to_svg( $polyline ),
			'stravaUrl' => $strava_id ? 'https://www.strava.com/activities/' . $strava_id : '',
			'type'      => sanitize_text_field( $type ),
			'photoUrl'  => esc_url_raw( $photo_url ),
			'unit'      => $unit,
		];
	}

	return $processed;
}

/**
 * Format seconds into a human-readable duration string.
 *
 * @param int $seconds Total seconds.
 * @return string Formatted duration, e.g. "1h 16m".
 */
function wpgraphql_strava_format_duration( int $seconds ): string {
	$hours   = intdiv( $seconds, 3600 );
	$minutes = intdiv( $seconds % 3600, 60 );

	if ( $hours > 0 ) {
		return $hours . 'h ' . $minutes . 'm';
	}

	return $minutes . 'm';
}

/**
 * Force-refresh the Strava activities cache.
 *
 * Called by WP-Cron and the admin "Resync" button.
 *
 * @return void
 */
function wpgraphql_strava_refresh_cache(): void {
	delete_transient( WPGRAPHQL_STRAVA_CACHE_KEY );
	wpgraphql_strava_get_cached_activities();
}

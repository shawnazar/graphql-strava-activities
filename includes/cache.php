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
 * Get a dashicon class for a Strava activity type.
 *
 * @param string $type Strava activity type.
 * @return string Dashicon CSS class.
 */
function wpgraphql_strava_activity_icon( string $type ): string {
	$map = [
		'Ride'           => 'dashicons-bike',
		'VirtualRide'    => 'dashicons-bike',
		'EBikeRide'      => 'dashicons-bike',
		'Run'            => 'dashicons-universal-access-alt',
		'VirtualRun'     => 'dashicons-universal-access-alt',
		'Walk'           => 'dashicons-universal-access-alt',
		'Hike'           => 'dashicons-location-alt',
		'Swim'           => 'dashicons-palmtree',
		'WeightTraining' => 'dashicons-superhero',
		'Yoga'           => 'dashicons-heart',
		'Workout'        => 'dashicons-superhero',
	];

	/**
	 * Filters the icon class for a Strava activity type.
	 *
	 * @param string $icon Dashicon CSS class.
	 * @param string $type Activity type.
	 */
	return (string) apply_filters(
		'wpgraphql_strava_activity_icon',
		$map[ $type ] ?? 'dashicons-chart-line',
		$type
	);
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
 * Object cache group for this plugin.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_CACHE_GROUP', 'wpgraphql_strava' );

/**
 * Get a cached value, using object cache when available.
 *
 * @param string $key Cache key.
 * @return mixed Cached value or false.
 */
function wpgraphql_strava_cache_get( string $key ) {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_get( $key, WPGRAPHQL_STRAVA_CACHE_GROUP );
	}
	return get_transient( $key );
}

/**
 * Set a cached value, using object cache when available.
 *
 * @param string $key   Cache key.
 * @param mixed  $value Value to cache.
 * @param int    $ttl   Time to live in seconds.
 * @return bool True on success.
 */
function wpgraphql_strava_cache_set( string $key, $value, int $ttl = 0 ): bool {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_set( $key, $value, WPGRAPHQL_STRAVA_CACHE_GROUP, $ttl );
	}
	return set_transient( $key, $value, $ttl );
}

/**
 * Delete a cached value.
 *
 * @param string $key Cache key.
 * @return bool True on success.
 */
function wpgraphql_strava_cache_delete( string $key ): bool {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_delete( $key, WPGRAPHQL_STRAVA_CACHE_GROUP );
	}
	return delete_transient( $key );
}

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
	$cached = wpgraphql_strava_cache_get( WPGRAPHQL_STRAVA_CACHE_KEY );

	if ( is_array( $cached ) && count( $cached ) > 0 ) {
		return $count > 0 ? array_slice( $cached, 0, $count ) : $cached;
	}

	$access_token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

	if ( empty( $access_token ) ) {
		return [];
	}

	/**
	 * Filters the number of activities to fetch from Strava.
	 *
	 * @param int $fetch_count Number of activities to request (max 200).
	 */
	$fetch_count = min( (int) apply_filters( 'wpgraphql_strava_activities_to_fetch', 200 ), 200 );

	/** Fires before activities are fetched from the Strava API. */
	do_action( 'wpgraphql_strava_before_sync' );

	$raw_activities = wpgraphql_strava_fetch_activities( $fetch_count );

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

	wpgraphql_strava_cache_set( WPGRAPHQL_STRAVA_CACHE_KEY, $activities, $ttl );
	update_option( 'wpgraphql_strava_last_sync', time() );

	/**
	 * Fires after activities have been fetched and cached.
	 *
	 * @param array<int, array<string, mixed>> $activities Processed activities.
	 * @param int                              $raw_count  Number of raw activities from Strava.
	 */
	do_action( 'wpgraphql_strava_after_sync', $activities, count( $raw_activities ) );

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

		// Skip private activities unless the toggle is on.
		$is_private      = ! empty( $activity['private'] );
		$include_private = (bool) get_option( 'wpgraphql_strava_include_private', false );
		if ( $is_private && ! $include_private ) {
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

		// Distance and speed conversion.
		$distance_raw    = isset( $activity['distance'] ) ? (float) $activity['distance'] : 0.0;
		$avg_speed_raw   = isset( $activity['average_speed'] ) ? (float) $activity['average_speed'] : 0.0;
		$max_speed_raw   = isset( $activity['max_speed'] ) ? (float) $activity['max_speed'] : 0.0;

		if ( 'km' === $unit ) {
			$distance  = round( $distance_raw / 1000, 2 );
			$avg_speed = round( $avg_speed_raw * 3.6, 2 );      // m/s → km/h.
			$max_speed = round( $max_speed_raw * 3.6, 2 );
			$speed_unit = 'km/h';
		} else {
			$distance  = round( $distance_raw / 1609.344, 2 );
			$avg_speed = round( $avg_speed_raw * 2.23694, 2 );  // m/s → mph.
			$max_speed = round( $max_speed_raw * 2.23694, 2 );
			$speed_unit = 'mph';
		}

		$moving_time = isset( $activity['moving_time'] ) ? (int) $activity['moving_time'] : 0;

		$strava_id = $activity['id'] ?? 0;

		// Extract primary photo URL if present in the list response.
		$photo_url = '';
		if ( ! empty( $activity['photos']['primary']['urls'] ) ) {
			$urls      = $activity['photos']['primary']['urls'];
			$photo_url = $urls['600'] ?? $urls['100'] ?? '';
		}

		$processed[] = [
			'title'          => sanitize_text_field( $activity['name'] ?? __( 'Activity', 'graphql-strava-activities' ) ),
			'distance'       => $distance,
			'duration'       => wpgraphql_strava_format_duration( $moving_time ),
			'date'           => $activity['start_date'] ?? '',
			'svgMap'         => wpgraphql_strava_polyline_to_svg( $polyline ),
			'elevationProfileSvg' => wpgraphql_strava_elevation_profile_svg( $polyline, isset( $activity['total_elevation_gain'] ) ? (float) $activity['total_elevation_gain'] : 0.0 ),
			'stravaUrl'      => $strava_id ? 'https://www.strava.com/activities/' . $strava_id : '',
			'type'           => sanitize_text_field( $type ),
			'photoUrl'       => esc_url_raw( $photo_url ),
			'unit'           => $unit,
			'speedUnit'      => $speed_unit,
			'elevationGain'  => isset( $activity['total_elevation_gain'] ) ? round( (float) $activity['total_elevation_gain'], 1 ) : 0.0,
			'averageSpeed'   => $avg_speed,
			'maxSpeed'       => $max_speed,
			'averageHeartrate' => isset( $activity['average_heartrate'] ) ? round( (float) $activity['average_heartrate'] ) : null,
			'maxHeartrate'   => isset( $activity['max_heartrate'] ) ? (int) $activity['max_heartrate'] : null,
			'calories'       => isset( $activity['kilojoules'] ) ? round( (float) $activity['kilojoules'] * 0.239006, 0 ) : null,
			'kudosCount'     => isset( $activity['kudos_count'] ) ? (int) $activity['kudos_count'] : 0,
			'commentCount'   => isset( $activity['comment_count'] ) ? (int) $activity['comment_count'] : 0,
			'startLatlng'    => $activity['start_latlng'] ?? null,
			'city'           => sanitize_text_field( $activity['location_city'] ?? '' ),
			'country'        => sanitize_text_field( $activity['location_country'] ?? '' ),
			'isPrivate'        => ! empty( $activity['private'] ),
			'poweredByStrava'  => 'Powered by Strava',
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
	wpgraphql_strava_cache_delete( WPGRAPHQL_STRAVA_CACHE_KEY );
	wpgraphql_strava_get_cached_activities();
}

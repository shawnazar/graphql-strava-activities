<?php
/**
 * WPGraphQL type and query registration.
 *
 * Registers the StravaActivity object type and stravaActivities root query field.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'graphql_register_types', 'wpgraphql_strava_register_types' );

/**
 * Register the StravaActivity type and stravaActivities root query field.
 *
 * @return void
 */
function wpgraphql_strava_register_types(): void {

	register_graphql_object_type(
		'StravaActivity',
		[
			'description' => __( 'A Strava activity with route map.', 'graphql-strava-activities' ),
			'fields'      => [
				'title'     => [
					'type'        => 'String',
					'description' => __( 'Activity name.', 'graphql-strava-activities' ),
				],
				'distance'  => [
					'type'        => 'Float',
					'description' => __( 'Distance in miles or kilometres based on settings.', 'graphql-strava-activities' ),
				],
				'duration'  => [
					'type'        => 'String',
					'description' => __( 'Formatted duration (e.g. "1h 16m").', 'graphql-strava-activities' ),
				],
				'date'      => [
					'type'        => 'String',
					'description' => __( 'Activity start date in ISO 8601 format.', 'graphql-strava-activities' ),
				],
				'svgMap'    => [
					'type'        => 'String',
					'description' => __( 'Inline SVG markup of the activity route map.', 'graphql-strava-activities' ),
				],
				'stravaUrl' => [
					'type'        => 'String',
					'description' => __( 'URL to the activity on Strava.', 'graphql-strava-activities' ),
				],
				'type'      => [
					'type'        => 'String',
					'description' => __( 'Activity type (Ride, Run, Walk, etc.).', 'graphql-strava-activities' ),
				],
				'photoUrl'  => [
					'type'        => 'String',
					'description' => __( 'URL to the primary activity photo.', 'graphql-strava-activities' ),
				],
				'unit'      => [
					'type'        => 'String',
					'description' => __( 'Distance unit — "mi" or "km".', 'graphql-strava-activities' ),
				],
			],
		]
	);

	register_graphql_field(
		'RootQuery',
		'stravaActivities',
		[
			'type'        => [ 'list_of' => 'StravaActivity' ],
			'description' => __( 'Recent Strava activities with route maps.', 'graphql-strava-activities' ),
			'args'        => [
				'first' => [
					'type'         => 'Int',
					'description'  => __( 'Number of activities to return. 0 = all cached.', 'graphql-strava-activities' ),
					'defaultValue' => 0,
				],
				'type'  => [
					'type'        => 'String',
					'description' => __( 'Filter by activity type (e.g. "Ride", "Run").', 'graphql-strava-activities' ),
				],
			],
			'resolve'     => static function ( $root, array $args ): array {
				$count      = max( (int) ( $args['first'] ?? 0 ), 0 );
				$activities = wpgraphql_strava_get_cached_activities( $count );

				// Client-side type filter.
				$type_filter = $args['type'] ?? '';
				if ( ! empty( $type_filter ) ) {
					$activities = array_values(
						array_filter(
							$activities,
							static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type_filter
						)
					);

					// Re-apply count limit after filtering.
					if ( $count > 0 ) {
						$activities = array_slice( $activities, 0, $count );
					}
				}

				return $activities;
			},
		]
	);
}

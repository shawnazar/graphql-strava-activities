<?php
/**
 * Google encoded polyline decoder.
 *
 * Decodes encoded polyline strings into arrays of latitude/longitude pairs.
 *
 * @package WPGraphQL\Strava
 * @see     https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decode a Google encoded polyline string into coordinate pairs.
 *
 * @param string $encoded The encoded polyline string.
 * @return array<int, array{0: float, 1: float}> Array of [lat, lng] pairs.
 */
function wpgraphql_strava_decode_polyline( string $encoded ): array {
	if ( empty( $encoded ) ) {
		return [];
	}

	$points = [];
	$index  = 0;
	$len    = strlen( $encoded );
	$lat    = 0;
	$lng    = 0;

	while ( $index < $len ) {
		// Decode latitude.
		$shift  = 0;
		$result = 0;

		do {
			if ( $index >= $len ) {
				break 2;
			}
			$byte = ord( $encoded[ $index ] ) - 63;
			++$index;
			$result |= ( $byte & 0x1F ) << $shift;
			$shift  += 5;
		} while ( $byte >= 0x20 );

		$lat += ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 );

		// Decode longitude.
		$shift  = 0;
		$result = 0;

		do {
			if ( $index >= $len ) {
				break 2;
			}
			$byte = ord( $encoded[ $index ] ) - 63;
			++$index;
			$result |= ( $byte & 0x1F ) << $shift;
			$shift  += 5;
		} while ( $byte >= 0x20 );

		$lng += ( $result & 1 ) ? ~( $result >> 1 ) : ( $result >> 1 );

		$points[] = [ $lat / 1e5, $lng / 1e5 ];
	}

	return $points;
}

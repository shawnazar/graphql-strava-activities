<?php
/**
 * Unit tests for the polyline decoder.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Unit;

use PHPUnit\Framework\TestCase;

// Load the file under test — no WordPress dependencies.
require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';

class PolylineTest extends TestCase {

    public function test_empty_string_returns_empty_array(): void {
        $this->assertSame( [], wpgraphql_strava_decode_polyline( '' ) );
    }

    public function test_decodes_known_polyline(): void {
        // Simple polyline encoding for a known set of coordinates.
        $encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';
        $points  = wpgraphql_strava_decode_polyline( $encoded );

        $this->assertCount( 3, $points );

        // First point should be approximately (38.5, -120.2).
        $this->assertEqualsWithDelta( 38.5, $points[0][0], 0.01 );
        $this->assertEqualsWithDelta( -120.2, $points[0][1], 0.01 );

        // Second point should be approximately (40.7, -120.95).
        $this->assertEqualsWithDelta( 40.7, $points[1][0], 0.01 );
        $this->assertEqualsWithDelta( -120.95, $points[1][1], 0.01 );

        // Third point should be approximately (43.252, -126.453).
        $this->assertEqualsWithDelta( 43.252, $points[2][0], 0.01 );
        $this->assertEqualsWithDelta( -126.453, $points[2][1], 0.01 );
    }

    public function test_returns_array_of_lat_lng_pairs(): void {
        $encoded = 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC';
        $points  = wpgraphql_strava_decode_polyline( $encoded );

        $this->assertNotEmpty( $points );

        foreach ( $points as $point ) {
            $this->assertCount( 2, $point, 'Each point should have exactly 2 values (lat, lng)' );
            $this->assertIsFloat( $point[0] );
            $this->assertIsFloat( $point[1] );
        }
    }

    public function test_single_point_polyline(): void {
        // Encoding of a single point will produce at least one coordinate pair.
        $encoded = '_p~iF~ps|U';
        $points  = wpgraphql_strava_decode_polyline( $encoded );

        $this->assertCount( 1, $points );
        $this->assertEqualsWithDelta( 38.5, $points[0][0], 0.01 );
        $this->assertEqualsWithDelta( -120.2, $points[0][1], 0.01 );
    }
}

<?php
/**
 * Integration tests for the caching module.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase {

    /**
     * Simulated transient storage.
     *
     * @var array<string, mixed>
     */
    private array $transients = [];

    /**
     * Simulated options storage.
     *
     * @var array<string, mixed>
     */
    private array $options = [];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->transients = [];
        $this->options    = [
            'wpgraphql_strava_access_token'      => '',
            'wpgraphql_strava_units'             => 'mi',
            'wpgraphql_strava_svg_color'         => '#0d9488',
            'wpgraphql_strava_svg_stroke_width'  => 2.5,
        ];

        Functions\stubTranslationFunctions();

        Functions\when( 'get_option' )->alias(
            function ( string $option, $default = '' ) {
                return $this->options[ $option ] ?? $default;
            }
        );

        Functions\when( 'update_option' )->alias(
            function ( string $option, $value ): bool {
                $this->options[ $option ] = $value;
                return true;
            }
        );

        Functions\when( 'get_transient' )->alias(
            function ( string $key ) {
                return $this->transients[ $key ] ?? false;
            }
        );

        Functions\when( 'set_transient' )->alias(
            function ( string $key, $value, int $ttl = 0 ): bool {
                $this->transients[ $key ] = $value;
                return true;
            }
        );

        Functions\when( 'delete_transient' )->alias(
            function ( string $key ): bool {
                unset( $this->transients[ $key ] );
                return true;
            }
        );

        Functions\when( 'apply_filters' )->alias(
            function ( string $tag, $value ) {
                return $value;
            }
        );

        Functions\when( 'sanitize_text_field' )->alias(
            function ( string $str ) {
                return trim( strip_tags( $str ) );
            }
        );

        Functions\when( 'esc_url_raw' )->alias(
            function ( string $url ) {
                return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
            }
        );

        Functions\when( 'esc_attr' )->alias(
            function ( string $text ) {
                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
            }
        );

        Functions\when( 'esc_attr__' )->alias(
            function ( string $text ) {
                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
            }
        );

        // Load modules in order.
        require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
        if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
        }
        if ( ! function_exists( 'wpgraphql_strava_format_duration' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
        }
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_format_duration_minutes_only(): void {
        $this->assertSame( '42m', wpgraphql_strava_format_duration( 2520 ) );
    }

    public function test_format_duration_hours_and_minutes(): void {
        $this->assertSame( '1h 5m', wpgraphql_strava_format_duration( 3900 ) );
    }

    public function test_format_duration_zero(): void {
        $this->assertSame( '0m', wpgraphql_strava_format_duration( 0 ) );
    }

    public function test_process_activities_filters_no_route(): void {
        $fixture    = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw        = json_decode( $fixture, true );
        $processed  = wpgraphql_strava_process_activities( $raw );

        // The fixture has 3 activities, but Yoga has no polyline — should be filtered out.
        $this->assertCount( 2, $processed );

        $types = array_column( $processed, 'type' );
        $this->assertContains( 'Run', $types );
        $this->assertContains( 'Ride', $types );
        $this->assertNotContains( 'Yoga', $types );
    }

    public function test_process_activities_converts_distance_miles(): void {
        $this->options['wpgraphql_strava_units'] = 'mi';

        $fixture   = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw       = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        // 8241.5 meters = ~5.12 miles.
        $this->assertEqualsWithDelta( 5.12, $processed[0]['distance'], 0.02 );
        $this->assertSame( 'mi', $processed[0]['unit'] );
    }

    public function test_process_activities_converts_distance_km(): void {
        $this->options['wpgraphql_strava_units'] = 'km';

        $fixture   = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw       = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        // 8241.5 meters = 8.24 km.
        $this->assertEqualsWithDelta( 8.24, $processed[0]['distance'], 0.01 );
        $this->assertSame( 'km', $processed[0]['unit'] );
    }

    public function test_process_activities_extracts_photo_url(): void {
        $fixture   = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw       = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        // First activity has a photo.
        $this->assertSame( 'https://example.com/photo-600.jpg', $processed[0]['photoUrl'] );

        // Second activity has no photo.
        $this->assertSame( '', $processed[1]['photoUrl'] );
    }

    public function test_process_activities_generates_svg_map(): void {
        $fixture   = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw       = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        $this->assertStringStartsWith( '<svg', $processed[0]['svgMap'] );
    }

    public function test_process_activities_builds_strava_url(): void {
        $fixture   = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw       = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        $this->assertSame( 'https://www.strava.com/activities/12345678901', $processed[0]['stravaUrl'] );
    }

    public function test_get_cached_activities_returns_empty_without_token(): void {
        $this->options['wpgraphql_strava_access_token'] = '';

        $result = wpgraphql_strava_get_cached_activities();

        $this->assertSame( [], $result );
    }

    public function test_get_cached_activities_returns_cached_data(): void {
        $cached = [
            [ 'title' => 'Cached Run', 'distance' => 5.0 ],
            [ 'title' => 'Cached Ride', 'distance' => 20.0 ],
        ];
        $this->transients['wpgraphql_strava_activities'] = $cached;

        $result = wpgraphql_strava_get_cached_activities();
        $this->assertCount( 2, $result );
        $this->assertSame( 'Cached Run', $result[0]['title'] );
    }

    public function test_get_cached_activities_respects_count(): void {
        $cached = [
            [ 'title' => 'First' ],
            [ 'title' => 'Second' ],
            [ 'title' => 'Third' ],
        ];
        $this->transients['wpgraphql_strava_activities'] = $cached;

        $result = wpgraphql_strava_get_cached_activities( 2 );
        $this->assertCount( 2, $result );
        $this->assertSame( 'First', $result[0]['title'] );
        $this->assertSame( 'Second', $result[1]['title'] );
    }
}

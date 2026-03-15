<?php
/**
 * Integration tests for the shortcodes module.
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

class ShortcodesTest extends TestCase {

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

	/**
	 * Sample activities for test fixtures.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_activities(): array {
		return [
			[
				'title'      => 'Morning Run',
				'distance'   => 5.12,
				'duration'   => '42m',
				'date'       => '2025-06-15T08:00:00Z',
				'type'       => 'Run',
				'unit'       => 'mi',
				'svgMap'     => '<svg viewBox="0 0 300 200"><polyline points="10,20 30,40" /></svg>',
				'stravaUrl'  => 'https://www.strava.com/activities/111',
				'photoUrl'   => 'https://example.com/photo1.jpg',
			],
			[
				'title'      => 'Evening Ride',
				'distance'   => 20.5,
				'duration'   => '1h 5m',
				'date'       => '2025-06-14T18:00:00Z',
				'type'       => 'Ride',
				'unit'       => 'mi',
				'svgMap'     => '<svg viewBox="0 0 300 200"><polyline points="50,60 70,80" /></svg>',
				'stravaUrl'  => 'https://www.strava.com/activities/222',
				'photoUrl'   => '',
			],
			[
				'title'      => 'Lunch Run',
				'distance'   => 3.1,
				'duration'   => '25m',
				'date'       => '2025-06-13T12:00:00Z',
				'type'       => 'Run',
				'unit'       => 'mi',
				'svgMap'     => '<svg viewBox="0 0 300 200"><polyline points="90,10 110,30" /></svg>',
				'stravaUrl'  => 'https://www.strava.com/activities/333',
				'photoUrl'   => '',
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->transients = [];
		$this->options    = [
			'wpgraphql_strava_access_token'     => 'test-token',
			'wpgraphql_strava_units'            => 'mi',
			'wpgraphql_strava_svg_color'        => '#0d9488',
			'wpgraphql_strava_svg_stroke_width' => 2.5,
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

		Functions\when( 'wp_strip_all_tags' )->alias(
			function ( string $str ) {
				return strip_tags( $str ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test mock.
			}
		);

		Functions\when( 'sanitize_text_field' )->alias(
			function ( string $str ) {
				return trim( wp_strip_all_tags( $str ) );
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

		Functions\when( 'esc_html' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_html__' )->alias(
			function ( string $text ) {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			}
		);

		Functions\when( 'esc_url' )->alias(
			function ( string $url ) {
				return $url;
			}
		);

		Functions\when( 'shortcode_atts' )->alias(
			function ( $defaults, $atts ) {
				return array_merge( $defaults, (array) $atts );
			}
		);

		Functions\when( 'wp_date' )->alias(
			function ( string $format, int $timestamp ) {
				return gmdate( $format, $timestamp );
			}
		);

		Functions\when( 'wp_kses' )->alias(
			function ( string $string ) {
				return $string;
			}
		);

		Functions\when( 'add_action' )->justReturn();
		Functions\when( 'add_shortcode' )->justReturn();

		// Load modules in order.
		if ( ! function_exists( 'wpgraphql_strava_encryption_enabled' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';
		}
		require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
		if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_format_duration' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
		}
		if ( ! function_exists( 'wpgraphql_strava_shortcode_activities' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/shortcodes.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_activities_shortcode_returns_cards(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_activities( [] );

		$this->assertStringContainsString( 'strava-activities', $html );
		$this->assertStringContainsString( 'Morning Run', $html );
		$this->assertStringContainsString( 'Evening Ride', $html );
		$this->assertStringContainsString( 'Lunch Run', $html );
		$this->assertSame( 3, substr_count( $html, 'strava-activity-card' ) );
	}

	public function test_activities_shortcode_respects_count(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_activities( [ 'count' => '2' ] );

		$this->assertSame( 2, substr_count( $html, 'strava-activity-card' ) );
		$this->assertStringContainsString( 'Morning Run', $html );
		$this->assertStringContainsString( 'Evening Ride', $html );
		$this->assertStringNotContainsString( 'Lunch Run', $html );
	}

	public function test_activities_shortcode_filters_by_type(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_activities( [ 'type' => 'Run' ] );

		$this->assertSame( 2, substr_count( $html, 'strava-activity-card' ) );
		$this->assertStringContainsString( 'Morning Run', $html );
		$this->assertStringContainsString( 'Lunch Run', $html );
		$this->assertStringNotContainsString( 'Evening Ride', $html );
	}

	public function test_activities_shortcode_returns_empty_message(): void {
		// Empty token ensures get_cached_activities returns [] without calling the API.
		$this->options['wpgraphql_strava_access_token'] = '';
		unset( $this->transients['wpgraphql_strava_activities'] );

		$html = wpgraphql_strava_shortcode_activities( [] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'No Strava activities found.', $html );
	}

	public function test_activity_shortcode_returns_single_card(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_activity( [ 'index' => '0' ] );

		$this->assertSame( 1, substr_count( $html, 'strava-activity-card' ) );
		$this->assertStringContainsString( 'Morning Run', $html );
		$this->assertStringNotContainsString( 'Evening Ride', $html );
	}

	public function test_activity_shortcode_returns_not_found(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_activity( [ 'index' => '99' ] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'Activity not found.', $html );
	}

	public function test_map_shortcode_returns_svg(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_map( [ 'index' => '0' ] );

		$this->assertStringContainsString( 'strava-map', $html );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( '</svg>', $html );
	}

	public function test_map_shortcode_returns_empty_message(): void {
		$activities            = $this->sample_activities();
		$activities[0]['svgMap'] = '';
		$this->transients['wpgraphql_strava_activities'] = [ $activities[0] ];

		$html = wpgraphql_strava_shortcode_map( [ 'index' => '0' ] );

		$this->assertStringContainsString( 'strava-empty', $html );
		$this->assertStringContainsString( 'No route map available.', $html );
	}

	public function test_stats_shortcode_returns_aggregate(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_stats( [] );

		$this->assertStringContainsString( 'strava-stats', $html );
		// Total count: 3 activities.
		$this->assertStringContainsString( '3', $html );
		// Total distance: 5.12 + 20.5 + 3.1 = 28.72 => formatted as 28.7.
		$this->assertStringContainsString( '28.7', $html );
		// Unit.
		$this->assertStringContainsString( 'mi', $html );
		// Type breakdown.
		$this->assertStringContainsString( 'Run', $html );
		$this->assertStringContainsString( 'Ride', $html );
	}

	public function test_latest_shortcode_returns_first_activity(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_latest( [] );

		$this->assertSame( 1, substr_count( $html, 'strava-activity-card' ) );
		$this->assertStringContainsString( 'Morning Run', $html );
	}

	public function test_latest_shortcode_filters_by_type(): void {
		$this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

		$html = wpgraphql_strava_shortcode_latest( [ 'type' => 'Ride' ] );

		$this->assertSame( 1, substr_count( $html, 'strava-activity-card' ) );
		$this->assertStringContainsString( 'Evening Ride', $html );
		$this->assertStringNotContainsString( 'Morning Run', $html );
	}

	public function test_render_card_includes_all_elements(): void {
		$activity = $this->sample_activities()[0];

		$html = wpgraphql_strava_render_shortcode_card( $activity );

		// Card wrapper.
		$this->assertStringContainsString( 'strava-activity-card', $html );
		// Title.
		$this->assertStringContainsString( 'Morning Run', $html );
		// Date (Jun 15, 2025).
		$this->assertStringContainsString( 'Jun 15, 2025', $html );
		// Distance.
		$this->assertStringContainsString( '5.12', $html );
		// Unit.
		$this->assertStringContainsString( 'mi', $html );
		// Duration.
		$this->assertStringContainsString( '42m', $html );
		// Type.
		$this->assertStringContainsString( 'Run', $html );
		// Photo.
		$this->assertStringContainsString( 'https://example.com/photo1.jpg', $html );
		// SVG map.
		$this->assertStringContainsString( 'strava-activity-map', $html );
		$this->assertStringContainsString( '<svg', $html );
		// Strava link.
		$this->assertStringContainsString( 'https://www.strava.com/activities/111', $html );
		$this->assertStringContainsString( 'View on Strava', $html );
	}
}

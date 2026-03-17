<?php
/**
 * Integration tests for the GraphQL type registration module.
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

class GraphQLTest extends TestCase {

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
	 * All captured object types by name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $captured_types = [];

	/**
	 * All captured fields by "type.field" key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $captured_fields = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->transients = [];
		$this->options    = [
			'wpgraphql_strava_access_token'     => 'fake-token',
			'wpgraphql_strava_units'            => 'mi',
			'wpgraphql_strava_svg_color'        => '#0d9488',
			'wpgraphql_strava_svg_stroke_width' => 2.5,
		];

		$this->captured_types  = [];
		$this->captured_fields = [];

		Functions\stubTranslationFunctions();

		Functions\when( 'get_option' )->alias(
			function ( string $option, $default = '' ) {
				return $this->options[ $option ] ?? $default;
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

		Functions\when( 'add_action' )->justReturn( true );

		Functions\when( 'register_graphql_object_type' )->alias(
			function ( string $type_name, array $config ): void {
				$this->captured_types[ $type_name ] = $config;
			}
		);

		Functions\when( 'register_graphql_field' )->alias(
			function ( string $type_name, string $field_name, array $config ): void {
				$this->captured_fields[ $type_name . '.' . $field_name ] = $config;
			}
		);

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
		require_once dirname( __DIR__, 2 ) . '/includes/graphql.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Helper to call wpgraphql_strava_register_types and return the resolver callback.
	 *
	 * @return callable The resolver callback from register_graphql_field.
	 */
	private function register_and_get_resolver(): callable {
		wpgraphql_strava_register_types();
		$key = 'RootQuery.stravaActivities';
		$this->assertArrayHasKey( $key, $this->captured_fields, 'register_graphql_field was not called for stravaActivities.' );
		$this->assertArrayHasKey( 'resolve', $this->captured_fields[ $key ] );
		return $this->captured_fields[ $key ]['resolve'];
	}

	/**
	 * Helper to build test activity data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_activities(): array {
		return [
			[
				'title'            => 'Morning Run',
				'distance'         => 5.12,
				'duration'         => '42m',
				'date'             => '2025-01-15T08:00:00Z',
				'type'             => 'Run',
				'unit'             => 'mi',
				'svgMap'           => '<svg></svg>',
				'stravaUrl'        => 'https://www.strava.com/activities/111',
				'photoUrl'         => '',
				'elevationGain'    => 50.0,
				'averageSpeed'     => 3.5,
				'maxSpeed'         => 4.2,
				'averageHeartrate' => 145.0,
				'maxHeartrate'     => 172,
				'calories'         => 350.0,
				'kudosCount'       => 5,
				'commentCount'     => 1,
				'city'             => 'Portland',
				'country'          => 'United States',
				'isPrivate'        => false,
				'poweredByStrava'  => 'Powered by Strava',
			],
			[
				'title'            => 'Evening Ride',
				'distance'         => 20.5,
				'duration'         => '1h 5m',
				'date'             => '2025-01-14T17:30:00Z',
				'type'             => 'Ride',
				'unit'             => 'mi',
				'svgMap'           => '<svg></svg>',
				'stravaUrl'        => 'https://www.strava.com/activities/222',
				'photoUrl'         => 'https://example.com/photo.jpg',
				'elevationGain'    => 120.0,
				'averageSpeed'     => 7.8,
				'maxSpeed'         => 12.1,
				'averageHeartrate' => 130.0,
				'maxHeartrate'     => 160,
				'calories'         => 600.0,
				'kudosCount'       => 10,
				'commentCount'     => 3,
				'city'             => 'Portland',
				'country'          => 'United States',
				'isPrivate'        => false,
				'poweredByStrava'  => 'Powered by Strava',
			],
			[
				'title'            => 'Lunch Run',
				'distance'         => 3.1,
				'duration'         => '25m',
				'date'             => '2025-01-13T12:00:00Z',
				'type'             => 'Run',
				'unit'             => 'mi',
				'svgMap'           => '<svg></svg>',
				'stravaUrl'        => 'https://www.strava.com/activities/333',
				'photoUrl'         => '',
				'elevationGain'    => 30.0,
				'averageSpeed'     => 3.2,
				'maxSpeed'         => 3.8,
				'averageHeartrate' => null,
				'maxHeartrate'     => null,
				'calories'         => null,
				'kudosCount'       => 2,
				'commentCount'     => 0,
				'city'             => 'Portland',
				'country'          => 'United States',
				'isPrivate'        => false,
				'poweredByStrava'  => 'Powered by Strava',
			],
			[
				'title'            => 'Walk in the Park',
				'distance'         => 1.5,
				'duration'         => '30m',
				'date'             => '2025-01-12T09:00:00Z',
				'type'             => 'Walk',
				'unit'             => 'mi',
				'svgMap'           => '<svg></svg>',
				'stravaUrl'        => 'https://www.strava.com/activities/444',
				'photoUrl'         => '',
				'elevationGain'    => 10.0,
				'averageSpeed'     => 1.4,
				'maxSpeed'         => 1.8,
				'averageHeartrate' => 100.0,
				'maxHeartrate'     => 110,
				'calories'         => 150.0,
				'kudosCount'       => 1,
				'commentCount'     => 0,
				'city'             => 'Portland',
				'country'          => 'United States',
				'isPrivate'        => false,
				'poweredByStrava'  => 'Powered by Strava',
			],
		];
	}

	public function test_register_types_registers_strava_activity_type(): void {
		wpgraphql_strava_register_types();

		$this->assertArrayHasKey( 'StravaActivity', $this->captured_types );
		$config = $this->captured_types['StravaActivity'];
		$this->assertArrayHasKey( 'description', $config );
		$this->assertArrayHasKey( 'fields', $config );

		$fields = $config['fields'];

		$expected_fields = [
			'title',
			'distance',
			'duration',
			'date',
			'svgMap',
			'stravaUrl',
			'elevationProfileSvg',
			'type',
			'photoUrl',
			'unit',
			'speedUnit',
			'elevationGain',
			'averageSpeed',
			'maxSpeed',
			'averageHeartrate',
			'maxHeartrate',
			'calories',
			'kudosCount',
			'commentCount',
			'city',
			'country',
			'isPrivate',
			'poweredByStrava',
		];

		$this->assertCount( 23, $fields, 'StravaActivity should have exactly 23 fields.' );

		foreach ( $expected_fields as $field_name ) {
			$this->assertArrayHasKey( $field_name, $fields, "Missing field: {$field_name}" );
			$this->assertArrayHasKey( 'type', $fields[ $field_name ], "Field {$field_name} must have a type." );
			$this->assertArrayHasKey( 'description', $fields[ $field_name ], "Field {$field_name} must have a description." );
		}
	}

	public function test_register_types_registers_root_query_field(): void {
		wpgraphql_strava_register_types();

		$key = 'RootQuery.stravaActivities';
		$this->assertArrayHasKey( $key, $this->captured_fields );
		$config = $this->captured_fields[ $key ];
		$this->assertArrayHasKey( 'type', $config );
		$this->assertArrayHasKey( 'description', $config );
		$this->assertArrayHasKey( 'args', $config );
		$this->assertArrayHasKey( 'resolve', $config );
		$this->assertArrayHasKey( 'first', $config['args'] );
		$this->assertArrayHasKey( 'type', $config['args'] );
		$this->assertArrayHasKey( 'userId', $config['args'] );
	}

	public function test_resolver_returns_all_cached_activities(): void {
		$activities = $this->sample_activities();
		$this->transients['wpgraphql_strava_activities'] = $activities;

		$resolver = $this->register_and_get_resolver();
		$result   = $resolver( null, [ 'first' => 0 ] );

		$this->assertCount( 4, $result );
		$this->assertSame( 'Morning Run', $result[0]['title'] );
		$this->assertSame( 'Walk in the Park', $result[3]['title'] );
	}

	public function test_resolver_respects_first_argument(): void {
		$activities = $this->sample_activities();
		$this->transients['wpgraphql_strava_activities'] = $activities;

		$resolver = $this->register_and_get_resolver();
		$result   = $resolver( null, [ 'first' => 2 ] );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Morning Run', $result[0]['title'] );
		$this->assertSame( 'Evening Ride', $result[1]['title'] );
	}

	public function test_resolver_filters_by_type(): void {
		$activities = $this->sample_activities();
		$this->transients['wpgraphql_strava_activities'] = $activities;

		$resolver = $this->register_and_get_resolver();
		$result   = $resolver( null, [ 'first' => 0, 'type' => 'Run' ] );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Morning Run', $result[0]['title'] );
		$this->assertSame( 'Lunch Run', $result[1]['title'] );
	}

	public function test_resolver_applies_count_after_type_filter(): void {
		$activities = $this->sample_activities();
		$this->transients['wpgraphql_strava_activities'] = $activities;

		$resolver = $this->register_and_get_resolver();
		$result   = $resolver( null, [ 'first' => 1, 'type' => 'Run' ] );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Morning Run', $result[0]['title'] );
	}

	public function test_resolver_returns_empty_when_no_activities(): void {
		// Empty token ensures get_cached_activities returns [] without calling the API.
		$this->options['wpgraphql_strava_access_token'] = '';
		unset( $this->transients['wpgraphql_strava_activities'] );

		$resolver = $this->register_and_get_resolver();
		$result   = $resolver( null, [ 'first' => 0 ] );

		$this->assertSame( [], $result );
	}
}

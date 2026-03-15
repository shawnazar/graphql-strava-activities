<?php
/**
 * Integration tests for the Strava API client.
 *
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {

	/**
	 * Simulated options storage.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options = [
			'wpgraphql_strava_access_token'      => 'test-access-token',
			'wpgraphql_strava_refresh_token'     => 'test-refresh-token',
			'wpgraphql_strava_client_id'         => '12345',
			'wpgraphql_strava_client_secret'     => 'test-client-secret',
			'wpgraphql_strava_token_expires_at'  => 0,
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

		Functions\when( 'wp_unslash' )->alias(
			function ( $value ) {
				return $value;
			}
		);

		Functions\when( 'wp_trigger_error' )->alias(
			function () {
				// Silently consume error triggers in tests.
			}
		);

		Functions\when( 'is_wp_error' )->alias(
			function ( $thing ): bool {
				return $thing instanceof \WP_Error;
			}
		);

		// Load modules in order.
		if ( ! function_exists( 'wpgraphql_strava_get_option' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';
		}

		if ( ! function_exists( 'wpgraphql_strava_fetch_activities' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api.php';
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_fetch_activities_returns_empty_without_token(): void {
		$this->options['wpgraphql_strava_access_token'] = '';

		$result = wpgraphql_strava_fetch_activities();

		$this->assertSame( [], $result );
	}

	public function test_fetch_activities_returns_parsed_response(): void {
		$activities = [
			[ 'id' => 1, 'name' => 'Morning Run' ],
			[ 'id' => 2, 'name' => 'Evening Ride' ],
		];

		Functions\when( 'wp_remote_get' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $activities ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		$result = wpgraphql_strava_fetch_activities( 10 );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Morning Run', $result[0]['name'] );
		$this->assertSame( 'Evening Ride', $result[1]['name'] );
	}

	public function test_fetch_activities_returns_empty_on_wp_error(): void {
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Connection failed' );

		Functions\when( 'wp_remote_get' )->justReturn( $wp_error );

		$result = wpgraphql_strava_fetch_activities();

		$this->assertSame( [], $result );
	}

	public function test_fetch_activities_returns_empty_on_non_200(): void {
		Functions\when( 'wp_remote_get' )->justReturn( [ 'status' => 500, 'body' => '' ] );

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		$result = wpgraphql_strava_fetch_activities();

		$this->assertSame( [], $result );
	}

	public function test_fetch_activities_refreshes_token_on_401(): void {
		$get_call_count = 0;
		$activities     = [ [ 'id' => 1, 'name' => 'Retried Run' ] ];

		Functions\when( 'wp_remote_get' )->alias(
			function () use ( &$get_call_count, $activities ) {
				++$get_call_count;
				if ( 1 === $get_call_count ) {
					// First call returns 401.
					return [ 'status' => 401, 'body' => '' ];
				}
				// Retry after refresh returns success.
				return [ 'status' => 200, 'body' => json_encode( $activities ) ];
			}
		);

		// Mock the token refresh POST call.
		$refresh_response = [
			'access_token'  => 'new-access-token',
			'refresh_token' => 'new-refresh-token',
			'expires_at'    => time() + 21600,
		];

		Functions\when( 'wp_remote_post' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $refresh_response ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		$result = wpgraphql_strava_fetch_activities();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Retried Run', $result[0]['name'] );
	}

	public function test_fetch_activities_refreshes_expired_token(): void {
		// Set token as expired.
		$this->options['wpgraphql_strava_token_expires_at'] = time() - 100;

		$activities = [ [ 'id' => 1, 'name' => 'Post-refresh Run' ] ];

		// Mock the token refresh POST call.
		$refresh_response = [
			'access_token'  => 'refreshed-token',
			'refresh_token' => 'new-refresh-token',
			'expires_at'    => time() + 21600,
		];

		Functions\when( 'wp_remote_post' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $refresh_response ) ]
		);

		Functions\when( 'wp_remote_get' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $activities ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		$result = wpgraphql_strava_fetch_activities();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Post-refresh Run', $result[0]['name'] );
	}

	public function test_fetch_activity_detail_returns_data(): void {
		$detail = [
			'id'     => 99,
			'name'   => 'Detailed Run',
			'photos' => [ 'count' => 2 ],
		];

		Functions\when( 'wp_remote_get' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $detail ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		$result = wpgraphql_strava_fetch_activity_detail( 99, 'some-token' );

		$this->assertSame( 99, $result['id'] );
		$this->assertSame( 'Detailed Run', $result['name'] );
	}

	public function test_fetch_activity_detail_returns_empty_on_failure(): void {
		$wp_error = Mockery::mock( 'WP_Error' );

		Functions\when( 'wp_remote_get' )->justReturn( $wp_error );

		$result = wpgraphql_strava_fetch_activity_detail( 99, 'some-token' );

		$this->assertSame( [], $result );
	}

	public function test_refresh_access_token_returns_new_token(): void {
		$refresh_response = [
			'access_token'  => 'brand-new-token',
			'refresh_token' => 'brand-new-refresh',
			'expires_at'    => time() + 21600,
		];

		Functions\when( 'wp_remote_post' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $refresh_response ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		$result = wpgraphql_strava_refresh_access_token();

		$this->assertSame( 'brand-new-token', $result );
	}

	public function test_refresh_access_token_returns_empty_on_failure(): void {
		$wp_error = Mockery::mock( 'WP_Error' );

		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );

		$result = wpgraphql_strava_refresh_access_token();

		$this->assertSame( '', $result );
	}

	public function test_refresh_access_token_persists_tokens(): void {
		$refresh_response = [
			'access_token'  => 'persisted-token',
			'refresh_token' => 'persisted-refresh',
			'expires_at'    => 1700000000,
		];

		Functions\when( 'wp_remote_post' )->justReturn(
			[ 'status' => 200, 'body' => json_encode( $refresh_response ) ]
		);

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ) {
				return $response['status'] ?? 200;
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			function ( $response ) {
				return $response['body'] ?? '';
			}
		);

		wpgraphql_strava_refresh_access_token();

		// Tokens are persisted via wpgraphql_strava_update_option which may encrypt.
		// Verify by decrypting the stored values.
		$stored_access  = $this->options['wpgraphql_strava_access_token'];
		$stored_refresh = $this->options['wpgraphql_strava_refresh_token'];

		$this->assertSame( 'persisted-token', wpgraphql_strava_decrypt( $stored_access ) );
		$this->assertSame( 'persisted-refresh', wpgraphql_strava_decrypt( $stored_refresh ) );
		$this->assertSame( 1700000000, $this->options['wpgraphql_strava_token_expires_at'] );
	}
}

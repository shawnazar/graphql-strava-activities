<?php
/**
 * Integration tests for the webhook handler and privacy hooks.
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

class WebhookPrivacyTest extends TestCase {

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
     * Track delete_option calls.
     *
     * @var array<int, string>
     */
    private array $deleted_options = [];

    /**
     * Track wp_add_privacy_policy_content calls.
     *
     * @var array<int, array<string, string>>
     */
    private array $privacy_policy_calls = [];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        $this->transients           = [];
        $this->deleted_options      = [];
        $this->privacy_policy_calls = [];
        $this->options              = [
            'wpgraphql_strava_access_token'         => 'test-token',
            'wpgraphql_strava_units'                => 'mi',
            'wpgraphql_strava_svg_color'            => '#0d9488',
            'wpgraphql_strava_svg_stroke_width'     => 2.5,
            'wpgraphql_strava_webhook_verify_token' => 'my-secret-token',
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

        Functions\when( 'delete_option' )->alias(
            function ( string $option ): bool {
                $this->deleted_options[] = $option;
                unset( $this->options[ $option ] );
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

        Functions\when( 'sanitize_title' )->alias(
            function ( string $title ) {
                return strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $title ) ?? '' );
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

        Functions\when( 'do_action' )->alias(
            function () {
                // No-op for tests.
            }
        );

        Functions\when( 'wp_add_privacy_policy_content' )->alias(
            function ( string $plugin_name, string $policy_text ) {
                $this->privacy_policy_calls[] = [
                    'plugin_name' => $plugin_name,
                    'policy_text' => $policy_text,
                ];
            }
        );

        // Load modules in order.
        if ( ! function_exists( 'wpgraphql_strava_decode_polyline' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/polyline.php';
        }
        if ( ! function_exists( 'wpgraphql_strava_polyline_to_svg' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/svg.php';
        }
        if ( ! function_exists( 'wpgraphql_strava_format_duration' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/cache.php';
        }
        if ( ! function_exists( 'wpgraphql_strava_webhook_verify' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/webhook.php';
        }
        if ( ! function_exists( 'wpgraphql_strava_export_personal_data' ) ) {
            require_once dirname( __DIR__, 2 ) . '/includes/privacy.php';
        }
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper to create a mock WP_REST_Request.
     *
     * @param array<string, mixed> $params Query parameters.
     * @param array<string, mixed> $json   JSON body parameters.
     * @return \WP_REST_Request
     */
    private function make_request( array $params = [], array $json = [] ): \WP_REST_Request {
        $request = \Mockery::mock( 'WP_REST_Request' );

        $request->shouldReceive( 'get_param' )->andReturnUsing(
            function ( string $key ) use ( $params ) {
                return $params[ $key ] ?? null;
            }
        );

        $request->shouldReceive( 'get_json_params' )->andReturn( $json );

        return $request;
    }

    /**
     * Sample cached activities for testing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sample_activities(): array {
        return [
            [
                'title'     => 'Morning Run',
                'type'      => 'Run',
                'date'      => '2025-12-01T08:00:00Z',
                'distance'  => 5.12,
                'unit'      => 'mi',
                'city'      => 'Portland',
                'country'   => 'United States',
                'stravaUrl' => 'https://www.strava.com/activities/111',
            ],
            [
                'title'     => 'Evening Ride',
                'type'      => 'Ride',
                'date'      => '2025-12-02T18:00:00Z',
                'distance'  => 20.5,
                'unit'      => 'mi',
                'city'      => 'Seattle',
                'country'   => 'United States',
                'stravaUrl' => 'https://www.strava.com/activities/222',
            ],
        ];
    }

    // ─── Webhook tests ──────────────────────────────────────────────

    public function test_webhook_verify_rejects_invalid_token(): void {
        $request = $this->make_request( [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'wrong-token',
            'hub.challenge'    => 'abc123',
        ] );

        $result = wpgraphql_strava_webhook_verify( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_webhook_verify_accepts_valid_token(): void {
        $request = $this->make_request( [
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'my-secret-token',
            'hub.challenge'    => 'challenge-value',
        ] );

        $result = wpgraphql_strava_webhook_verify( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertSame( 'challenge-value', $data['hub.challenge'] );
        $this->assertSame( 200, $result->get_status() );
    }

    public function test_webhook_ignores_non_activity_events(): void {
        $request = $this->make_request( [], [
            'object_type' => 'athlete',
            'aspect_type' => 'update',
            'object_id'   => 99,
        ] );

        $result = wpgraphql_strava_webhook_event( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertSame( 'ignored', $data['status'] );
    }

    public function test_webhook_delete_removes_activity_from_cache(): void {
        $this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

        $request = $this->make_request( [], [
            'object_type' => 'activity',
            'aspect_type' => 'delete',
            'object_id'   => 111,
        ] );

        $result = wpgraphql_strava_webhook_event( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 'ok', $result->get_data()['status'] );

        // Cache should have only the remaining activity.
        $cached = $this->transients['wpgraphql_strava_activities'];
        $this->assertCount( 1, $cached );
        $this->assertSame( 'https://www.strava.com/activities/222', $cached[0]['stravaUrl'] );
    }

    public function test_webhook_create_clears_cache(): void {
        $this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

        $request = $this->make_request( [], [
            'object_type' => 'activity',
            'aspect_type' => 'create',
            'object_id'   => 333,
        ] );

        $result = wpgraphql_strava_webhook_event( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 'ok', $result->get_data()['status'] );

        // Cache should be deleted (not present in transients).
        $this->assertArrayNotHasKey( 'wpgraphql_strava_activities', $this->transients );
    }

    // ─── Privacy tests ──────────────────────────────────────────────

    public function test_export_returns_activity_data(): void {
        $this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

        $result = wpgraphql_strava_export_personal_data( 'user@example.com' );

        $this->assertTrue( $result['done'] );
        $this->assertCount( 2, $result['data'] );

        // Verify the first exported item structure.
        $first = $result['data'][0];
        $this->assertSame( 'strava-activities', $first['group_id'] );
        $this->assertStringContainsString( 'strava-activity-', $first['item_id'] );
        $this->assertCount( 6, $first['data'] );

        // Verify exported field values.
        $fields = array_column( $first['data'], 'value', 'name' );
        $this->assertSame( 'Morning Run', $fields['Title'] );
        $this->assertSame( 'Run', $fields['Type'] );
        $this->assertSame( 'Portland', $fields['City'] );
        $this->assertSame( 'United States', $fields['Country'] );
    }

    public function test_export_returns_empty_when_no_activities(): void {
        // No cached activities.
        $this->options['wpgraphql_strava_access_token'] = '';

        $result = wpgraphql_strava_export_personal_data( 'user@example.com' );

        $this->assertTrue( $result['done'] );
        $this->assertSame( [], $result['data'] );
    }

    public function test_erase_clears_cache_and_tokens(): void {
        $this->transients['wpgraphql_strava_activities'] = $this->sample_activities();

        $result = wpgraphql_strava_erase_personal_data( 'user@example.com' );

        $this->assertTrue( $result['items_removed'] );
        $this->assertFalse( $result['items_retained'] );
        $this->assertTrue( $result['done'] );
        $this->assertCount( 1, $result['messages'] );

        // Cache should be deleted.
        $this->assertArrayNotHasKey( 'wpgraphql_strava_activities', $this->transients );

        // Verify token options were deleted.
        $this->assertContains( 'wpgraphql_strava_access_token', $this->deleted_options );
        $this->assertContains( 'wpgraphql_strava_refresh_token', $this->deleted_options );
        $this->assertContains( 'wpgraphql_strava_token_expires_at', $this->deleted_options );
        $this->assertContains( 'wpgraphql_strava_last_sync', $this->deleted_options );
    }

    public function test_privacy_policy_content_registered(): void {
        wpgraphql_strava_add_privacy_policy();

        $this->assertCount( 1, $this->privacy_policy_calls );
        $this->assertSame( 'GraphQL Strava Activities', $this->privacy_policy_calls[0]['plugin_name'] );
        $this->assertStringContainsString( 'Strava Activity Data', $this->privacy_policy_calls[0]['policy_text'] );
        $this->assertStringContainsString( 'strava.com/legal/privacy', $this->privacy_policy_calls[0]['policy_text'] );
    }
}

<?php
/**
 * Unit tests for the encryption module.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

namespace GraphQLStrava\Tests\Unit;

use PHPUnit\Framework\TestCase;

// Load the file under test.
require_once dirname( __DIR__, 2 ) . '/includes/encryption.php';

class EncryptionTest extends TestCase {

    /**
     * Set up a valid encryption key before each test.
     */
    protected function setUp(): void {
        parent::setUp();

        // Define the encryption key if not already defined.
        if ( ! defined( 'GRAPHQL_STRAVA_ENCRYPTION_KEY' ) ) {
            define( 'GRAPHQL_STRAVA_ENCRYPTION_KEY', bin2hex( random_bytes( 32 ) ) );
        }
    }

    public function test_encryption_enabled_when_key_defined(): void {
        $this->assertTrue( wpgraphql_strava_encryption_enabled() );
    }

    public function test_encrypt_returns_prefixed_string(): void {
        $encrypted = wpgraphql_strava_encrypt( 'my-secret-token' );

        $this->assertStringStartsWith( 'enc:', $encrypted );
        $this->assertNotSame( 'my-secret-token', $encrypted );
    }

    public function test_encrypt_decrypt_round_trip(): void {
        $original  = 'strava-access-token-abc123';
        $encrypted = wpgraphql_strava_encrypt( $original );
        $decrypted = wpgraphql_strava_decrypt( $encrypted );

        $this->assertSame( $original, $decrypted );
    }

    public function test_decrypt_plain_text_returns_as_is(): void {
        // Values without the enc: prefix should pass through unchanged.
        $plain = 'not-encrypted-value';

        $this->assertSame( $plain, wpgraphql_strava_decrypt( $plain ) );
    }

    public function test_encrypt_empty_string_returns_empty(): void {
        $this->assertSame( '', wpgraphql_strava_encrypt( '' ) );
    }

    public function test_decrypt_empty_string_returns_empty(): void {
        $this->assertSame( '', wpgraphql_strava_decrypt( '' ) );
    }

    public function test_each_encryption_produces_different_ciphertext(): void {
        $value = 'same-value';
        $enc1  = wpgraphql_strava_encrypt( $value );
        $enc2  = wpgraphql_strava_encrypt( $value );

        // Different IVs should produce different ciphertexts.
        $this->assertNotSame( $enc1, $enc2 );

        // But both should decrypt to the same value.
        $this->assertSame( $value, wpgraphql_strava_decrypt( $enc1 ) );
        $this->assertSame( $value, wpgraphql_strava_decrypt( $enc2 ) );
    }

    public function test_decrypt_corrupted_data_returns_empty(): void {
        $this->assertSame( '', wpgraphql_strava_decrypt( 'enc:not-valid-base64!!!' ) );
    }
}

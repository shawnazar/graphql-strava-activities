<?php
/**
 * At-rest encryption for Strava credentials.
 *
 * Encrypts sensitive options (tokens, client secret) before they are
 * stored in wp_options and decrypts them on retrieval. Uses AES-256-CBC
 * via OpenSSL.
 *
 * To enable, define GRAPHQL_STRAVA_ENCRYPTION_KEY in wp-config.php:
 *
 *     define( 'GRAPHQL_STRAVA_ENCRYPTION_KEY', 'your-random-64-char-hex-string' );
 *
 * Generate a key with: wp eval "echo bin2hex(random_bytes(32));"
 *
 * When no key is defined, values are stored and retrieved as plain text
 * — the same behaviour as every other WordPress plugin.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cipher used for encryption.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_CIPHER', 'aes-256-cbc' );

/**
 * Option names that contain sensitive values and should be encrypted.
 *
 * @var string[]
 */
define(
	'WPGRAPHQL_STRAVA_ENCRYPTED_OPTIONS',
	[
		'wpgraphql_strava_client_secret',
		'wpgraphql_strava_access_token',
		'wpgraphql_strava_refresh_token',
	]
);

/**
 * Check whether at-rest encryption is available.
 *
 * @return bool True when a valid encryption key is defined and OpenSSL is loaded.
 */
function wpgraphql_strava_encryption_enabled(): bool {
	return defined( 'GRAPHQL_STRAVA_ENCRYPTION_KEY' )
		&& ! empty( GRAPHQL_STRAVA_ENCRYPTION_KEY )
		&& function_exists( 'openssl_encrypt' );
}

/**
 * Encrypt a plain-text value.
 *
 * @param string $value Plain text.
 * @return string Base64-encoded ciphertext with IV prepended, or original value if encryption is unavailable.
 */
function wpgraphql_strava_encrypt( string $value ): string {
	if ( empty( $value ) || ! wpgraphql_strava_encryption_enabled() ) {
		return $value;
	}

	$key    = hex2bin( GRAPHQL_STRAVA_ENCRYPTION_KEY );
	$iv_len = openssl_cipher_iv_length( WPGRAPHQL_STRAVA_CIPHER );

	if ( false === $key || false === $iv_len ) {
		return $value;
	}

	$iv         = openssl_random_pseudo_bytes( $iv_len );
	$ciphertext = openssl_encrypt( $value, WPGRAPHQL_STRAVA_CIPHER, $key, OPENSSL_RAW_DATA, $iv );

	if ( false === $ciphertext ) {
		return $value;
	}

	// Prepend IV so we can extract it on decryption.  Prefix with "enc:" so
	// we can distinguish encrypted values from plain-text ones.
	return 'enc:' . base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Decrypt a value previously encrypted by wpgraphql_strava_encrypt().
 *
 * @param string $value Stored value (may be encrypted or plain text).
 * @return string Decrypted plain text, or original value if not encrypted / decryption unavailable.
 */
function wpgraphql_strava_decrypt( string $value ): string {
	// Not encrypted — return as-is.
	if ( empty( $value ) || ! str_starts_with( $value, 'enc:' ) ) {
		return $value;
	}

	if ( ! wpgraphql_strava_encryption_enabled() ) {
		// Value is encrypted but key is no longer available.
		return '';
	}

	$key    = hex2bin( GRAPHQL_STRAVA_ENCRYPTION_KEY );
	$iv_len = openssl_cipher_iv_length( WPGRAPHQL_STRAVA_CIPHER );

	if ( false === $key || false === $iv_len ) {
		return '';
	}

	$raw = base64_decode( substr( $value, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

	if ( false === $raw || strlen( $raw ) <= $iv_len ) {
		return '';
	}

	$iv         = substr( $raw, 0, $iv_len );
	$ciphertext = substr( $raw, $iv_len );
	$plaintext  = openssl_decrypt( $ciphertext, WPGRAPHQL_STRAVA_CIPHER, $key, OPENSSL_RAW_DATA, $iv );

	return ( false !== $plaintext ) ? $plaintext : '';
}

/**
 * Get a sensitive option, decrypting it if necessary.
 *
 * Use this instead of get_option() for encrypted option names.
 *
 * @param string $option  Option name.
 * @param string $default Default value.
 * @return string Decrypted value.
 */
function wpgraphql_strava_get_option( string $option, string $default = '' ): string {
	$value = get_option( $option, $default );

	if ( ! is_string( $value ) || empty( $value ) ) {
		return $default;
	}

	if ( in_array( $option, WPGRAPHQL_STRAVA_ENCRYPTED_OPTIONS, true ) ) {
		return wpgraphql_strava_decrypt( $value );
	}

	return $value;
}

/**
 * Save a sensitive option, encrypting it if a key is configured.
 *
 * Use this instead of update_option() for encrypted option names.
 *
 * @param string $option Option name.
 * @param string $value  Plain-text value to store.
 * @return bool True on success.
 */
function wpgraphql_strava_update_option( string $option, string $value ): bool {
	if ( in_array( $option, WPGRAPHQL_STRAVA_ENCRYPTED_OPTIONS, true ) ) {
		$value = wpgraphql_strava_encrypt( $value );
	}

	return update_option( $option, $value );
}

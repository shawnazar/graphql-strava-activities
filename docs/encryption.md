# Credential Encryption

The plugin optionally encrypts sensitive credentials (access token, refresh token, client secret) at rest using AES-256-CBC via OpenSSL.

## Why Encrypt?

By default, WordPress stores option values as plain text in the `wp_options` database table. If your database is compromised, API tokens would be exposed. At-rest encryption adds a layer of protection.

## Setup

Add this to your `wp-config.php`:

```php
define( 'GRAPHQL_STRAVA_ENCRYPTION_KEY', 'your-64-character-hex-key' );
```

### Generate a Key

Using WP-CLI:

```bash
wp eval "echo bin2hex(random_bytes(32));"
```

Or with PHP:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

This produces a 64-character hex string (256-bit key).

## How It Works

When `GRAPHQL_STRAVA_ENCRYPTION_KEY` is defined:

1. **Saving** — Values are encrypted with AES-256-CBC before being stored in `wp_options`. Encrypted values are prefixed with `enc:` for identification.
2. **Reading** — Values prefixed with `enc:` are decrypted on retrieval.
3. **No key** — If the constant is not defined, values are stored and retrieved as plain text (default WordPress behaviour).

## Encrypted Options

These option names are encrypted when a key is configured:

- `wpgraphql_strava_client_secret`
- `wpgraphql_strava_access_token`
- `wpgraphql_strava_refresh_token`

## API Functions

Use these instead of `get_option()` / `update_option()` for sensitive values:

```php
// Read (auto-decrypts)
$token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

// Write (auto-encrypts)
wpgraphql_strava_update_option( 'wpgraphql_strava_access_token', $new_token );
```

## Important Notes

- **Keep the key safe** — if you lose the encryption key, encrypted values cannot be recovered. You'll need to re-enter your Strava credentials.
- **Don't change the key** — changing the key after values are encrypted will make existing encrypted values unreadable.
- **Requires OpenSSL** — the `openssl` PHP extension must be installed (included in most PHP distributions).
- **Migration** — If you add a key to an existing installation, re-save your credentials in the settings page to encrypt them.

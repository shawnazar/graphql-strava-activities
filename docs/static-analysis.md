# Static Analysis

The project uses [PHPStan](https://phpstan.org/) for static analysis at level 5, with the WordPress extension for WordPress-specific function stubs.

## Running PHPStan

```bash
composer analyse
```

## Configuration

PHPStan is configured in `phpstan.neon.dist`:

- **Level**: 5
- **Paths**: `wp-graphql-strava.php`, `includes/`
- **WordPress extension**: Provides stubs for WordPress functions, hooks, and globals
- **Custom stubs**: Located in `stubs/` for any additional function declarations

## Pre-commit Hook

PHPStan runs automatically on every commit via GrumPHP. If analysis fails, the commit is blocked.

## Adding Stubs

If PHPStan reports undefined functions (e.g., from WPGraphQL), add stubs in the `stubs/` directory:

```php
<?php
// stubs/wpgraphql.php

function register_graphql_object_type( string $type_name, array $config ): void {}
function register_graphql_field( string $type_name, string $field_name, array $config ): void {}
```

Then reference the stub in `phpstan.neon.dist`.

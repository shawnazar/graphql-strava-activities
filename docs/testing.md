# Testing

## Test Structure

```
tests/
├── bootstrap.php              # Test bootstrap (loads WordPress stubs)
├── fixtures/
│   └── strava-api-response.json  # Sample Strava API response
├── Unit/
│   ├── EncryptionTest.php     # AES-256-CBC encrypt/decrypt (8 tests)
│   ├── PolylineTest.php       # Polyline decoder (4 tests)
│   └── SvgTest.php            # SVG route map generator (9 tests)
└── Integration/
    ├── ApiTest.php            # Strava API client (11 tests)
    ├── CacheTest.php          # Cache module (14 tests)
    ├── GraphQLTest.php        # GraphQL schema & resolver (7 tests)
    └── ShortcodesTest.php     # Shortcode rendering (12 tests)
```

**65 tests total** across 7 test files (21 unit + 44 integration).

## Running Tests

```bash
composer test             # All tests
composer test:unit        # Unit tests only (no WordPress needed)
composer test:integration # Integration tests only
```

## Writing Tests

### Unit Tests

Unit tests live in `tests/Unit/`. They test pure logic with no WordPress dependencies.

```php
class MyTest extends \PHPUnit\Framework\TestCase {
    public function test_example(): void {
        $result = wpgraphql_strava_some_function( 'input' );
        $this->assertSame( 'expected', $result );
    }
}
```

### Integration Tests

Integration tests live in `tests/Integration/`. They can use WordPress functions (stubbed via the test bootstrap).

```php
class MyCacheTest extends \PHPUnit\Framework\TestCase {
    public function test_process_activities(): void {
        $fixture = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
        $raw = json_decode( $fixture, true );
        $processed = wpgraphql_strava_process_activities( $raw );

        $this->assertNotEmpty( $processed );
        $this->assertArrayHasKey( 'title', $processed[0] );
    }
}
```

### Test Fixtures

Sample API responses live in `tests/fixtures/`. Use them to mock Strava API data:

```php
$fixture = file_get_contents( dirname( __DIR__ ) . '/fixtures/strava-api-response.json' );
$raw = json_decode( $fixture, true );
```

### Rules

- **Always mock HTTP responses** — never call the real Strava API
- **Use fixtures** for sample API data
- **Run `composer test` before every commit** — GrumPHP enforces this automatically
- **Cover new functionality** — add tests for any code changes

## PHPUnit Configuration

The `phpunit.xml.dist` file defines two test suites:

- `unit` — `tests/Unit/`
- `integration` — `tests/Integration/`

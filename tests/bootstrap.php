<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads Brain\Monkey for unit tests. Integration tests requiring the
 * full WordPress test suite should be run separately with WP_TESTS_DIR set.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

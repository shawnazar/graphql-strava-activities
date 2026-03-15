<?php
/**
 * PHPUnit bootstrap file.
 *
 * Defines ABSPATH so plugin files don't exit, then loads
 * Composer autoload for Brain\Monkey and test dependencies.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

// Define ABSPATH so plugin files don't call exit.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Define WordPress time constants used by the plugin.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

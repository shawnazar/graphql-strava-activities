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

// Stub WordPress cache functions for testing.
if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
    function wp_using_ext_object_cache() {
        return false;
    }
}

// Stub WordPress core classes for testing.
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public $status;
        public $headers = [];
        public function __construct( $data = null, $status = 200 ) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
        public function header( $key, $value ) { $this->headers[ $key ] = $value; }
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        private $data;
        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
        public function get_error_data() { return $this->data; }
    }
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

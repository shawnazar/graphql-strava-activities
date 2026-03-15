<?php
/**
 * Plugin Name:       GraphQL Strava Activities
 * Plugin URI:        https://github.com/shawnazar/wp-graphql-strava
 * Description:       Extends WPGraphQL with Strava activity data, server-side SVG route maps, and activity photos.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  wp-graphql
 * Author:            Shawn Azar
 * Author URI:        https://shawnazar.me
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       graphql-strava-activities
 * Domain Path:       /languages
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_VERSION', '1.0.0' );

/**
 * Plugin root directory path.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin root directory URL.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimum WPGraphQL version required.
 *
 * @var string
 */
define( 'WPGRAPHQL_STRAVA_MIN_WPGRAPHQL', '2.0.0' );

/**
 * Check whether WPGraphQL is active and meets the minimum version.
 *
 * @return bool True when WPGraphQL is ready.
 */
function wpgraphql_strava_dependencies_met(): bool {
	if ( ! class_exists( 'WPGraphQL' ) ) {
		return false;
	}

	if ( defined( 'WPGRAPHQL_VERSION' ) && version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_STRAVA_MIN_WPGRAPHQL, '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Display an admin notice when WPGraphQL is missing or outdated.
 *
 * @return void
 */
function wpgraphql_strava_missing_dependency_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Plugin name, 2: Required plugin, 3: Required version */
		esc_html__( '%1$s requires %2$s %3$s or newer. Please install and activate it.', 'graphql-strava-activities' ),
		'<strong>GraphQL Strava Activities</strong>',
		'<a href="https://www.wpgraphql.com/" target="_blank" rel="noopener noreferrer">WPGraphQL</a>',
		WPGRAPHQL_STRAVA_MIN_WPGRAPHQL
	);

	printf( '<div class="notice notice-error"><p>%s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
}

/**
 * Bootstrap the plugin once all plugins are loaded.
 *
 * @return void
 */
function wpgraphql_strava_init(): void {
	if ( ! wpgraphql_strava_dependencies_met() ) {
		add_action( 'admin_notices', 'wpgraphql_strava_missing_dependency_notice' );
		return;
	}

	// Core modules — order matters.
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/encryption.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/polyline.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/svg.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/api.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/cache.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/admin.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/graphql.php';
}

add_action( 'plugins_loaded', 'wpgraphql_strava_init' );

// Cron — refresh cached activities on schedule.
add_action( 'wpgraphql_strava_cron_refresh', 'wpgraphql_strava_refresh_cache' );

/**
 * On activation: schedule the cron event.
 *
 * @return void
 */
function wpgraphql_strava_activate(): void {
	$schedule = get_option( 'wpgraphql_strava_cron_schedule', 'twicedaily' );
	if ( ! wp_next_scheduled( 'wpgraphql_strava_cron_refresh' ) ) {
		wp_schedule_event( time(), $schedule, 'wpgraphql_strava_cron_refresh' );
	}
}

register_activation_hook( __FILE__, 'wpgraphql_strava_activate' );

/**
 * Reschedule cron when the sync frequency option changes.
 *
 * @param mixed $old_value Previous schedule value.
 * @param mixed $new_value New schedule value.
 * @return void
 */
function wpgraphql_strava_reschedule_cron( $old_value, $new_value ): void {
	if ( $old_value === $new_value ) {
		return;
	}

	wp_clear_scheduled_hook( 'wpgraphql_strava_cron_refresh' );
	wp_schedule_event( time(), (string) $new_value, 'wpgraphql_strava_cron_refresh' );
}

add_action( 'update_option_wpgraphql_strava_cron_schedule', 'wpgraphql_strava_reschedule_cron', 10, 2 );

/**
 * On deactivation: clear the cron event and flush cache.
 *
 * @return void
 */
function wpgraphql_strava_deactivate(): void {
	wp_clear_scheduled_hook( 'wpgraphql_strava_cron_refresh' );
	delete_transient( 'wpgraphql_strava_activities' );
}

register_deactivation_hook( __FILE__, 'wpgraphql_strava_deactivate' );

<?php
/**
 * Plugin Name:       GraphQL Strava Activities
 * Plugin URI:        https://github.com/shawnazar/graphql-strava-activities
 * Description:       Compatible with Strava — extends WPGraphQL with activity data, server-side SVG route maps, and photos.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Requires Plugins:  wp-graphql
 * Author:            Shawn Azar
 * Author URI:        https://shawnazar.me
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       graphql-strava-activities
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
define( 'WPGRAPHQL_STRAVA_VERSION', '1.0.4' );

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
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/oauth.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/admin.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/class-wpgraphql-strava-activities-list-table.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/graphql.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/rest-api.php';
	require_once WPGRAPHQL_STRAVA_DIR . 'includes/shortcodes.php';
}

add_action( 'plugins_loaded', 'wpgraphql_strava_init' );

// Self-hosted update checker — runs independently of WPGraphQL.
require_once WPGRAPHQL_STRAVA_DIR . 'includes/updater.php';

// Cron — refresh cached activities on schedule.
add_action( 'wpgraphql_strava_cron_refresh', 'wpgraphql_strava_refresh_cache' );

/**
 * Register custom cron schedules for sync intervals WordPress doesn't provide.
 *
 * @param array<string, array<string, mixed>> $schedules Existing schedules.
 * @return array<string, array<string, mixed>> Modified schedules.
 */
function wpgraphql_strava_cron_schedules( array $schedules ): array {
	$custom = [
		'every_15_minutes' => [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'graphql-strava-activities' ),
		],
		'every_30_minutes' => [
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 Minutes', 'graphql-strava-activities' ),
		],
		'every_2_hours'    => [
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 2 Hours', 'graphql-strava-activities' ),
		],
		'every_4_hours'    => [
			'interval' => 4 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 4 Hours', 'graphql-strava-activities' ),
		],
		'every_6_hours'    => [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 Hours', 'graphql-strava-activities' ),
		],
		'weekly'           => [
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'graphql-strava-activities' ),
		],
		'every_2_weeks'    => [
			'interval' => 14 * DAY_IN_SECONDS,
			'display'  => __( 'Every 2 Weeks', 'graphql-strava-activities' ),
		],
		'monthly'          => [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Monthly', 'graphql-strava-activities' ),
		],
	];

	return array_merge( $schedules, $custom );
}

add_filter( 'cron_schedules', 'wpgraphql_strava_cron_schedules' );

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
 * Force a cache refresh when the "include no route" toggle changes.
 *
 * The cached activities were built with the previous setting, so they
 * must be rebuilt to include or exclude GPS-less activities.
 *
 * @param mixed $old_value Previous value.
 * @param mixed $new_value New value.
 * @return void
 */
function wpgraphql_strava_flush_on_route_toggle( $old_value, $new_value ): void {
	if ( $old_value === $new_value ) {
		return;
	}

	delete_transient( 'wpgraphql_strava_activities' );
}

add_action( 'update_option_wpgraphql_strava_include_no_route', 'wpgraphql_strava_flush_on_route_toggle', 10, 2 );
add_action( 'update_option_wpgraphql_strava_include_private', 'wpgraphql_strava_flush_on_route_toggle', 10, 2 );

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

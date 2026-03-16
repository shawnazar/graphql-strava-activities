<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- WP-CLI command file.
/**
 * WP-CLI commands for Strava activity management.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage Strava activities.
 */
class WPGRAPHQL_Strava_CLI_Command {

	/**
	 * Sync activities from Strava.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Clear cache before syncing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp strava sync
	 *     wp strava sync --force
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function sync( array $args, array $assoc_args ): void {
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			delete_transient( 'wpgraphql_strava_activities' );
			\WP_CLI::log( 'Cache cleared.' );
		}

		$activities = wpgraphql_strava_get_cached_activities( 0 );

		if ( empty( $activities ) ) {
			\WP_CLI::warning( 'No activities returned. Check your credentials.' );
			return;
		}

		$count     = count( $activities );
		$last_sync = (int) get_option( 'wpgraphql_strava_last_sync', 0 );
		$sync_time = $last_sync > 0 ? wp_date( 'Y-m-d H:i:s', $last_sync ) : 'unknown';

		\WP_CLI::success( sprintf( '%d activities synced. Last sync: %s', $count, $sync_time ) );
	}

	/**
	 * Show connection status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp strava status
	 *
	 * @return void
	 */
	public function status(): void {
		$has_token  = ! empty( wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' ) );
		$expires_at = (int) get_option( 'wpgraphql_strava_token_expires_at', 0 );
		$last_sync  = (int) get_option( 'wpgraphql_strava_last_sync', 0 );
		$cached     = get_transient( 'wpgraphql_strava_activities' );
		$count      = is_array( $cached ) ? count( $cached ) : 0;

		\WP_CLI::log( sprintf( 'Connected:  %s', $has_token ? 'yes' : 'no' ) );
		\WP_CLI::log( sprintf( 'Token expires: %s', $expires_at > 0 ? wp_date( 'Y-m-d H:i:s', $expires_at ) : 'n/a' ) );
		\WP_CLI::log( sprintf( 'Last sync:  %s', $last_sync > 0 ? wp_date( 'Y-m-d H:i:s', $last_sync ) : 'never' ) );
		\WP_CLI::log( sprintf( 'Cached activities: %d', $count ) );
	}
}

\WP_CLI::add_command( 'strava', 'WPGRAPHQL_Strava_CLI_Command' );

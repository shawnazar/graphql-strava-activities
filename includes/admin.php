<?php
/**
 * WP Admin settings page for Strava.
 *
 * Provides UI for credentials, SVG customization, display options,
 * sync controls, and rate-limit information.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'wpgraphql_strava_add_admin_menu' );
add_action( 'admin_init', 'wpgraphql_strava_register_settings' );
add_action( 'admin_init', 'wpgraphql_strava_handle_resync' );

/**
 * Add a top-level "Strava" menu page.
 *
 * @return void
 */
function wpgraphql_strava_add_admin_menu(): void {
	// Top-level menu points to Getting Started.
	add_menu_page(
		__( 'Getting Started', 'graphql-strava-activities' ),
		__( 'Strava', 'graphql-strava-activities' ),
		'manage_options',
		'wpgraphql-strava',
		'wpgraphql_strava_render_guide_page',
		'dashicons-chart-line',
		81
	);

	// Rename the auto-generated first submenu from "Strava" to "Getting Started".
	add_submenu_page(
		'wpgraphql-strava',
		__( 'Getting Started', 'graphql-strava-activities' ),
		__( 'Getting Started', 'graphql-strava-activities' ),
		'manage_options',
		'wpgraphql-strava',
		'wpgraphql_strava_render_guide_page'
	);

	add_submenu_page(
		'wpgraphql-strava',
		__( 'Strava Settings', 'graphql-strava-activities' ),
		__( 'Settings', 'graphql-strava-activities' ),
		'manage_options',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_render_admin_page'
	);

	add_submenu_page(
		'wpgraphql-strava',
		__( 'Activities', 'graphql-strava-activities' ),
		__( 'Activities', 'graphql-strava-activities' ),
		'manage_options',
		'wpgraphql-strava-activities',
		'wpgraphql_strava_render_activities_page'
	);

	add_submenu_page(
		'wpgraphql-strava',
		__( 'Preview', 'graphql-strava-activities' ),
		__( 'Preview', 'graphql-strava-activities' ),
		'manage_options',
		'wpgraphql-strava-preview',
		'wpgraphql_strava_render_preview_page'
	);
}

/**
 * Register all plugin settings, sections, and fields.
 *
 * @return void
 */
function wpgraphql_strava_register_settings(): void {

	// ------------------------------------------------------------------
	// Section: Credentials
	// ------------------------------------------------------------------
	add_settings_section(
		'wpgraphql_strava_credentials',
		__( 'API Credentials', 'graphql-strava-activities' ),
		static function (): void {
			printf(
				'<p>%s <a href="https://www.strava.com/settings/api" target="_blank" rel="noopener noreferrer">%s</a>.</p>',
				esc_html__( 'Enter your Strava API credentials. Get them from', 'graphql-strava-activities' ),
				esc_html__( 'Strava API Settings', 'graphql-strava-activities' )
			);
		},
		'wpgraphql-strava-settings'
	);

	$credential_fields = [
		'wpgraphql_strava_client_id'     => __( 'Client ID', 'graphql-strava-activities' ),
		'wpgraphql_strava_client_secret' => __( 'Client Secret', 'graphql-strava-activities' ),
		'wpgraphql_strava_access_token'  => __( 'Access Token', 'graphql-strava-activities' ),
		'wpgraphql_strava_refresh_token' => __( 'Refresh Token', 'graphql-strava-activities' ),
	];

	foreach ( $credential_fields as $option => $label ) {
		$is_sensitive = in_array( $option, WPGRAPHQL_STRAVA_ENCRYPTED_OPTIONS, true );

		register_setting(
			'wpgraphql_strava_settings',
			$option,
			[
				'type'              => 'string',
				'sanitize_callback' => $is_sensitive
					? 'wpgraphql_strava_sanitize_and_encrypt'
					: 'sanitize_text_field',
				'default'           => '',
			]
		);

		$field_type = ( 'wpgraphql_strava_client_id' === $option ) ? 'text' : 'password';

		add_settings_field(
			$option,
			$label,
			'wpgraphql_strava_render_text_field',
			'wpgraphql-strava-settings',
			'wpgraphql_strava_credentials',
			[
				'option' => $option,
				'type'   => $field_type,
			]
		);
	}

	// Token expiry — hidden, auto-managed.
	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_token_expires_at',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		]
	);

	// ------------------------------------------------------------------
	// Section: SVG Customization
	// ------------------------------------------------------------------
	add_settings_section(
		'wpgraphql_strava_svg',
		__( 'SVG Route Map', 'graphql-strava-activities' ),
		static function (): void {
			echo '<p>' . esc_html__( 'Customise the appearance of the server-rendered route maps.', 'graphql-strava-activities' ) . '</p>';
		},
		'wpgraphql-strava-settings'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_svg_color',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#0d9488',
		]
	);

	add_settings_field(
		'wpgraphql_strava_svg_color',
		__( 'Stroke Color', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_color_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_svg'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_svg_stroke_width',
		[
			'type'              => 'number',
			'sanitize_callback' => static fn( $val ) => max( 0.5, min( 10.0, (float) $val ) ),
			'default'           => 2.5,
		]
	);

	add_settings_field(
		'wpgraphql_strava_svg_stroke_width',
		__( 'Stroke Width', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_number_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_svg',
		[
			'option' => 'wpgraphql_strava_svg_stroke_width',
			'min'    => '0.5',
			'max'    => '10',
			'step'   => '0.5',
		]
	);

	// ------------------------------------------------------------------
	// Section: Display
	// ------------------------------------------------------------------
	add_settings_section(
		'wpgraphql_strava_display',
		__( 'Display', 'graphql-strava-activities' ),
		'__return_null',
		'wpgraphql-strava-settings'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_cron_schedule',
		[
			'type'              => 'string',
			'sanitize_callback' => static fn( $val ) => in_array( $val, [ 'every_15_minutes', 'every_30_minutes', 'hourly', 'every_2_hours', 'every_4_hours', 'every_6_hours', 'twicedaily', 'daily' ], true ) ? $val : 'twicedaily',
			'default'           => 'twicedaily',
		]
	);

	add_settings_field(
		'wpgraphql_strava_cron_schedule',
		__( 'Sync Frequency', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_cron_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_display'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_units',
		[
			'type'              => 'string',
			'sanitize_callback' => static fn( $val ) => in_array( $val, [ 'mi', 'km' ], true ) ? $val : 'mi',
			'default'           => 'mi',
		]
	);

	add_settings_field(
		'wpgraphql_strava_units',
		__( 'Distance Units', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_units_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_display'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_include_no_route',
		[
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		]
	);

	add_settings_field(
		'wpgraphql_strava_include_no_route',
		__( 'Activities Without Routes', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_no_route_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_display'
	);

	register_setting(
		'wpgraphql_strava_settings',
		'wpgraphql_strava_include_private',
		[
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		]
	);

	add_settings_field(
		'wpgraphql_strava_include_private',
		__( 'Private Activities', 'graphql-strava-activities' ),
		'wpgraphql_strava_render_private_field',
		'wpgraphql-strava-settings',
		'wpgraphql_strava_display'
	);
}

// ------------------------------------------------------------------
// Sanitize callback.
// ------------------------------------------------------------------

/**
 * Sanitize and encrypt a sensitive option value.
 *
 * @param mixed $value Raw input value.
 * @return string Sanitized and optionally encrypted value.
 */
function wpgraphql_strava_sanitize_and_encrypt( $value ): string {
	$clean = sanitize_text_field( (string) $value );

	return wpgraphql_strava_encrypt( $clean );
}

// ------------------------------------------------------------------
// Field renderers.
// ------------------------------------------------------------------

/**
 * Render a text or password input field.
 *
 * Decrypts the stored value for display in the form.
 *
 * @param array{option: string, type: string} $args Field arguments.
 * @return void
 */
function wpgraphql_strava_render_text_field( array $args ): void {
	$value = wpgraphql_strava_get_option( $args['option'] );
	printf(
		'<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
		esc_attr( $args['type'] ),
		esc_attr( $args['option'] ),
		esc_attr( $value )
	);
}

/**
 * Render the stroke-color picker field.
 *
 * @return void
 */
function wpgraphql_strava_render_color_field(): void {
	$value = get_option( 'wpgraphql_strava_svg_color', '#0d9488' );
	printf(
		'<input type="color" name="wpgraphql_strava_svg_color" value="%s" />',
		esc_attr( $value )
	);
}

/**
 * Render a number input field.
 *
 * @param array{option: string, min: string, max: string, step: string} $args Field arguments.
 * @return void
 */
function wpgraphql_strava_render_number_field( array $args ): void {
	$value = get_option( $args['option'], '' );
	printf(
		'<input type="number" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text" />',
		esc_attr( $args['option'] ),
		esc_attr( (string) $value ),
		esc_attr( $args['min'] ),
		esc_attr( $args['max'] ),
		esc_attr( $args['step'] )
	);
}

/**
 * Render the distance-units radio buttons.
 *
 * @return void
 */
function wpgraphql_strava_render_units_field(): void {
	$value = get_option( 'wpgraphql_strava_units', 'mi' );
	?>
	<fieldset>
		<label>
			<input type="radio" name="wpgraphql_strava_units" value="mi" <?php checked( $value, 'mi' ); ?> />
			<?php esc_html_e( 'Miles', 'graphql-strava-activities' ); ?>
		</label>
		<br />
		<label>
			<input type="radio" name="wpgraphql_strava_units" value="km" <?php checked( $value, 'km' ); ?> />
			<?php esc_html_e( 'Kilometres', 'graphql-strava-activities' ); ?>
		</label>
	</fieldset>
	<?php
}

/**
 * Render the "include activities without routes" checkbox.
 *
 * @return void
 */
function wpgraphql_strava_render_no_route_field(): void {
	$value = (bool) get_option( 'wpgraphql_strava_include_no_route', false );
	?>
	<label>
		<input type="checkbox" name="wpgraphql_strava_include_no_route" value="1" <?php checked( $value ); ?> />
		<?php esc_html_e( 'Include indoor and GPS-less activities (yoga, weight training, treadmill, etc.)', 'graphql-strava-activities' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'When enabled, activities without GPS routes will appear with an empty svgMap field. Changing this setting triggers a forced resync.', 'graphql-strava-activities' ); ?>
	</p>
	<?php
}

/**
 * Render the "include private activities" checkbox.
 *
 * @return void
 */
function wpgraphql_strava_render_private_field(): void {
	$value = (bool) get_option( 'wpgraphql_strava_include_private', false );
	?>
	<label>
		<input type="checkbox" name="wpgraphql_strava_include_private" value="1" <?php checked( $value ); ?> />
		<?php esc_html_e( 'Include private activities in GraphQL results', 'graphql-strava-activities' ); ?>
	</label>
	<p class="description" style="color: #d63638;">
		<?php esc_html_e( 'Warning: If your GraphQL endpoint is publicly accessible, enabling this will expose your private Strava activities. Changing this setting triggers a forced resync.', 'graphql-strava-activities' ); ?>
	</p>
	<?php
}

/**
 * Render the cron schedule dropdown.
 *
 * @return void
 */
function wpgraphql_strava_render_cron_field(): void {
	$value   = get_option( 'wpgraphql_strava_cron_schedule', 'twicedaily' );
	$options = [
		'every_15_minutes' => __( 'Every 15 Minutes', 'graphql-strava-activities' ),
		'every_30_minutes' => __( 'Every 30 Minutes', 'graphql-strava-activities' ),
		'hourly'           => __( 'Every Hour', 'graphql-strava-activities' ),
		'every_2_hours'    => __( 'Every 2 Hours', 'graphql-strava-activities' ),
		'every_4_hours'    => __( 'Every 4 Hours', 'graphql-strava-activities' ),
		'every_6_hours'    => __( 'Every 6 Hours', 'graphql-strava-activities' ),
		'twicedaily'       => __( 'Every 12 Hours', 'graphql-strava-activities' ),
		'daily'            => __( 'Once Daily', 'graphql-strava-activities' ),
	];

	// Estimate daily API calls: ~6 per sync × syncs per day.
	$intervals   = [
		'every_15_minutes' => 96,
		'every_30_minutes' => 48,
		'hourly'           => 24,
		'every_2_hours'    => 12,
		'every_4_hours'    => 6,
		'every_6_hours'    => 4,
		'twicedaily'       => 2,
		'daily'            => 1,
	];
	$syncs_per_day = $intervals[ $value ] ?? 2;
	$daily_calls   = $syncs_per_day * 6;
	?>
	<select name="wpgraphql_strava_cron_schedule">
		<?php foreach ( $options as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description">
		<?php
		printf(
			/* translators: 1: Estimated daily API calls, 2: Daily limit */
			esc_html__( 'Estimated API usage: ~%1$d calls/day (limit: 1,000). Intervals under 1 hour use significant API quota.', 'graphql-strava-activities' ),
			(int) $daily_calls,
			(int) 1000
		);
		?>
	</p>
	<?php
}

// ------------------------------------------------------------------
// Resync handler.
// ------------------------------------------------------------------

/**
 * Handle the "Resync Activities" form submission.
 *
 * @return void
 */
function wpgraphql_strava_handle_resync(): void {
	if (
		! isset( $_POST['wpgraphql_strava_resync'] )
		|| ! check_admin_referer( 'wpgraphql_strava_resync', 'wpgraphql_strava_resync_nonce' )
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	// Force refresh.
	delete_transient( WPGRAPHQL_STRAVA_CACHE_KEY );
	$activities = wpgraphql_strava_get_cached_activities( 1 );

	if ( ! empty( $activities ) ) {
		$activity = $activities[0];
		$message  = sprintf(
			/* translators: 1: Activity title, 2: Distance, 3: Duration */
			__( 'Sync successful! Latest: "%1$s" — %2$s %3$s', 'graphql-strava-activities' ),
			$activity['title'],
			$activity['distance'],
			$activity['duration']
		);
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'success',
				'message' => $message,
			],
			30
		);
	} else {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'Sync failed. Check your credentials and try again.', 'graphql-strava-activities' ),
			],
			30
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
	exit;
}

// ------------------------------------------------------------------
// Shared footer.
// ------------------------------------------------------------------

/**
 * Render the plugin footer with author links.
 *
 * @return void
 */
function wpgraphql_strava_render_admin_footer(): void {
	?>
	<hr />
	<div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: #646970; margin-top: 8px;">
		<span>
			<?php
			printf(
				/* translators: %s: Author website link */
				esc_html__( 'Built by %s', 'graphql-strava-activities' ),
				'<a href="https://shawnazar.me" target="_blank" rel="noopener noreferrer">Shawn Azar</a>'
			);
			?>
		</span>
		<span>&middot;</span>
		<a href="https://www.buymeacoffee.com/shawnazar" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy Me a Coffee', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<a href="https://github.com/shawnazar/wp-graphql-strava" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<a href="https://github.com/shawnazar/wp-graphql-strava/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an Issue', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<a href="https://github.com/shawnazar/wp-graphql-strava/discussions" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Discussions', 'graphql-strava-activities' ); ?></a>
	</div>
	<?php
}

// ------------------------------------------------------------------
// Page renderer.
// ------------------------------------------------------------------

/**
 * Render the Strava admin settings page.
 *
 * @return void
 */
function wpgraphql_strava_render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Flash notice.
	$notice = get_transient( 'wpgraphql_strava_admin_notice' );
	if ( is_array( $notice ) ) {
		delete_transient( 'wpgraphql_strava_admin_notice' );
	}

	$has_token         = ! empty( wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' ) );
	$last_sync         = (int) get_option( 'wpgraphql_strava_last_sync', 0 );
	$cached_activities = get_transient( WPGRAPHQL_STRAVA_CACHE_KEY );
	$cached_count      = is_array( $cached_activities ) ? count( $cached_activities ) : 0;
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( is_array( $notice ) ) : ?>
			<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php settings_errors(); ?>

		<!-- Settings form -->
		<form action="options.php" method="post">
			<?php
			settings_fields( 'wpgraphql_strava_settings' );
			do_settings_sections( 'wpgraphql-strava-settings' );
			submit_button( __( 'Save Settings', 'graphql-strava-activities' ) );
			?>
		</form>

		<hr />

		<!-- Sync section -->
		<h2><?php esc_html_e( 'Sync', 'graphql-strava-activities' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Last Sync', 'graphql-strava-activities' ); ?></th>
				<td>
					<?php if ( $last_sync > 0 ) : ?>
						<?php echo esc_html( wp_date( 'F j, Y \a\t g:i A', $last_sync ) ); ?>
					<?php else : ?>
						<em><?php esc_html_e( 'Never synced', 'graphql-strava-activities' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cached Activities', 'graphql-strava-activities' ); ?></th>
				<td><?php echo esc_html( (string) $cached_count ); ?></td>
			</tr>
		</table>

		<form method="post">
			<?php wp_nonce_field( 'wpgraphql_strava_resync', 'wpgraphql_strava_resync_nonce' ); ?>
			<input type="hidden" name="wpgraphql_strava_resync" value="1" />
			<?php submit_button( __( 'Resync Activities', 'graphql-strava-activities' ), 'secondary', 'submit', false ); ?>
			<?php if ( ! $has_token ) : ?>
				<p class="description" style="margin-top: 8px;">
					<?php esc_html_e( 'No access token configured. Enter your credentials above first.', 'graphql-strava-activities' ); ?>
				</p>
			<?php endif; ?>
		</form>

		<hr />

		<!-- Rate limits -->
		<h2><?php esc_html_e( 'API Rate Limits', 'graphql-strava-activities' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: Strava rate-limits documentation link */
				esc_html__( 'Strava enforces %s. This plugin is designed to stay well within these limits.', 'graphql-strava-activities' ),
				'<a href="https://developers.strava.com/docs/rate-limits/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'API rate limits', 'graphql-strava-activities' ) . '</a>'
			);
			?>
		</p>

		<table class="widefat striped" style="max-width: 600px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Limit', 'graphql-strava-activities' ); ?></th>
					<th><?php esc_html_e( 'Allowed', 'graphql-strava-activities' ); ?></th>
					<th><?php esc_html_e( 'Plugin Usage', 'graphql-strava-activities' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Per 15 minutes', 'graphql-strava-activities' ); ?></td>
					<td><?php esc_html_e( '100 requests', 'graphql-strava-activities' ); ?></td>
					<td><?php esc_html_e( '~6 per sync (1 list + 5 detail)', 'graphql-strava-activities' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Per day', 'graphql-strava-activities' ); ?></td>
					<td><?php esc_html_e( '1,000 requests', 'graphql-strava-activities' ); ?></td>
					<td><?php esc_html_e( '~12 (twice-daily cron)', 'graphql-strava-activities' ); ?></td>
				</tr>
			</tbody>
		</table>

		<p class="description" style="margin-top: 8px;">
			<?php esc_html_e( 'Each sync: 1 list call + up to 5 detail calls for photos, with a 200 ms delay between detail calls. Manual resyncs count toward the same limits.', 'graphql-strava-activities' ); ?>
		</p>

		<?php wpgraphql_strava_render_admin_footer(); ?>
	</div>
	<?php
}

// ------------------------------------------------------------------
// Getting Started / Documentation page.
// ------------------------------------------------------------------

/**
 * Render the Getting Started guide page.
 *
 * @return void
 */
function wpgraphql_strava_render_guide_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=wpgraphql-strava-settings' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Getting Started with GraphQL Strava Activities', 'graphql-strava-activities' ); ?></h1>

		<div style="max-width: 800px;">

			<!-- Quick Start -->
			<div class="card" style="max-width: 800px; padding: 16px 24px; margin-top: 16px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Quick Start', 'graphql-strava-activities' ); ?></h2>
				<ol style="font-size: 14px; line-height: 1.8;">
					<li>
						<?php
						printf(
							/* translators: %s: Link to Strava API settings */
							esc_html__( 'Create a Strava API application at %s', 'graphql-strava-activities' ),
							'<a href="https://www.strava.com/settings/api" target="_blank" rel="noopener noreferrer">strava.com/settings/api</a>'
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: Link to plugin settings page */
							esc_html__( 'Enter your Client ID, Client Secret, Access Token, and Refresh Token on the %s page.', 'graphql-strava-activities' ),
							'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'graphql-strava-activities' ) . '</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Click "Resync Activities" to fetch your latest activities.', 'graphql-strava-activities' ); ?></li>
					<li><?php esc_html_e( 'Query your activities via GraphQL — see examples below.', 'graphql-strava-activities' ); ?></li>
				</ol>
			</div>

			<!-- Getting Strava Tokens -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Getting Your Strava Tokens', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'After creating your Strava API application, you need to generate an Access Token and Refresh Token through the OAuth flow:', 'graphql-strava-activities' ); ?></p>
				<ol style="font-size: 14px; line-height: 1.8;">
					<li>
						<?php esc_html_e( 'Visit the following URL in your browser (replace YOUR_CLIENT_ID):', 'graphql-strava-activities' ); ?>
						<br />
						<code style="display: block; padding: 8px; margin: 8px 0; background: #f0f0f1; font-size: 13px; word-break: break-all;">https://www.strava.com/oauth/authorize?client_id=YOUR_CLIENT_ID&amp;response_type=code&amp;redirect_uri=http://localhost&amp;scope=read,activity:read_all&amp;approval_prompt=force</code>
					</li>
					<li><?php esc_html_e( 'Authorise the app. You will be redirected to localhost with a "code" parameter in the URL.', 'graphql-strava-activities' ); ?></li>
					<li>
						<?php esc_html_e( 'Exchange the code for tokens using curl or any HTTP client:', 'graphql-strava-activities' ); ?>
						<br />
						<code style="display: block; padding: 8px; margin: 8px 0; background: #f0f0f1; font-size: 13px; word-break: break-all;">curl -X POST https://www.strava.com/oauth/token -d client_id=YOUR_CLIENT_ID -d client_secret=YOUR_CLIENT_SECRET -d code=YOUR_CODE -d grant_type=authorization_code</code>
					</li>
					<li><?php esc_html_e( 'The response contains your access_token, refresh_token, and expires_at. Enter them in the plugin settings.', 'graphql-strava-activities' ); ?></li>
				</ol>
				<p style="font-size: 13px; color: #646970;">
					<?php esc_html_e( 'The plugin automatically refreshes your access token when it expires — you only need to do this once.', 'graphql-strava-activities' ); ?>
				</p>
			</div>

			<!-- Encryption -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Credential Encryption (Optional)', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'For additional security, you can encrypt your stored credentials at rest. Add this line to your wp-config.php:', 'graphql-strava-activities' ); ?></p>
				<code style="display: block; padding: 8px; margin: 8px 0; background: #f0f0f1; font-size: 13px;">define( 'GRAPHQL_STRAVA_ENCRYPTION_KEY', 'your-64-character-hex-key' );</code>
				<p><?php esc_html_e( 'Generate a key with:', 'graphql-strava-activities' ); ?>
					<code>wp eval "echo bin2hex(random_bytes(32));"</code>
				</p>
				<p style="font-size: 13px; color: #646970;">
					<?php
					if ( wpgraphql_strava_encryption_enabled() ) {
						echo '<span style="color: #00a32a;">&#10003; </span>';
						esc_html_e( 'Encryption is active. Your credentials are encrypted at rest.', 'graphql-strava-activities' );
					} else {
						esc_html_e( 'Encryption is not configured. Credentials are stored as plain text (the WordPress default).', 'graphql-strava-activities' );
					}
					?>
				</p>
			</div>

			<!-- GraphQL Examples -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'GraphQL Query Examples', 'graphql-strava-activities' ); ?></h2>

				<h3><?php esc_html_e( 'Fetch all activities with full data', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities {
	title distance duration date type unit
	svgMap stravaUrl photoUrl
	elevationGain averageSpeed maxSpeed
	averageHeartrate maxHeartrate calories
	kudosCount commentCount city country isPrivate
  }
}</pre>

				<h3><?php esc_html_e( 'Fetch latest 5 rides with performance data', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities(first: 5, type: "Ride") {
	title distance duration svgMap
	elevationGain averageSpeed calories
  }
}</pre>

				<h3><?php esc_html_e( 'Fetch runs with heart rate and location', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities(type: "Run") {
	title distance duration date
	averageHeartrate maxHeartrate
	city country photoUrl stravaUrl
  }
}</pre>
			</div>

			<!-- StravaActivity Fields -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'StravaActivity Fields', 'graphql-strava-activities' ); ?></h2>
				<table class="widefat striped" style="max-width: 100%;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Type', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Description', 'graphql-strava-activities' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>title</code></td><td>String</td><td><?php esc_html_e( 'Activity name', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>distance</code></td><td>Float</td><td><?php esc_html_e( 'Distance in miles or km (based on settings)', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>duration</code></td><td>String</td><td><?php esc_html_e( 'Formatted duration, e.g. "1h 16m"', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>date</code></td><td>String</td><td><?php esc_html_e( 'Start date in ISO 8601 format', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>svgMap</code></td><td>String</td><td><?php esc_html_e( 'Inline SVG route map markup', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>stravaUrl</code></td><td>String</td><td><?php esc_html_e( 'Link to activity on Strava', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>type</code></td><td>String</td><td><?php esc_html_e( 'Activity type — Ride, Run, Walk, Hike, Swim, etc.', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>photoUrl</code></td><td>String</td><td><?php esc_html_e( 'Primary activity photo URL', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>unit</code></td><td>String</td><td><?php esc_html_e( '"mi" or "km"', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>elevationGain</code></td><td>Float</td><td><?php esc_html_e( 'Total elevation gain in metres', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>averageSpeed</code></td><td>Float</td><td><?php esc_html_e( 'Average speed in metres per second', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>maxSpeed</code></td><td>Float</td><td><?php esc_html_e( 'Maximum speed in metres per second', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>averageHeartrate</code></td><td>Float</td><td><?php esc_html_e( 'Average heart rate in bpm (null if no HR data)', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>maxHeartrate</code></td><td>Int</td><td><?php esc_html_e( 'Maximum heart rate in bpm (null if no HR data)', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>calories</code></td><td>Float</td><td><?php esc_html_e( 'Estimated calories burned (null if unavailable)', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>kudosCount</code></td><td>Int</td><td><?php esc_html_e( 'Number of kudos on this activity', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>commentCount</code></td><td>Int</td><td><?php esc_html_e( 'Number of comments on this activity', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>city</code></td><td>String</td><td><?php esc_html_e( 'City where the activity started', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>country</code></td><td>String</td><td><?php esc_html_e( 'Country where the activity started', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>isPrivate</code></td><td>Boolean</td><td><?php esc_html_e( 'Whether this is a private activity', 'graphql-strava-activities' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<!-- Query Arguments -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Query Arguments', 'graphql-strava-activities' ); ?></h2>
				<table class="widefat striped" style="max-width: 100%;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Argument', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Type', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Description', 'graphql-strava-activities' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>first</code></td><td>Int</td><td><?php esc_html_e( 'Limit the number of activities returned. 0 = all cached.', 'graphql-strava-activities' ); ?></td></tr>
						<tr><td><code>type</code></td><td>String</td><td><?php esc_html_e( 'Filter by activity type, e.g. "Ride", "Run", "Walk".', 'graphql-strava-activities' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<!-- Filters & Hooks -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Filters & Hooks for Developers', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'All filters use the wpgraphql_strava_ prefix. Add them in your theme\'s functions.php or a custom plugin.', 'graphql-strava-activities' ); ?></p>

				<table class="widefat striped" style="max-width: 100%;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filter', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Default', 'graphql-strava-activities' ); ?></th>
							<th><?php esc_html_e( 'Description', 'graphql-strava-activities' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>wpgraphql_strava_cache_ttl</code></td>
							<td>43200</td>
							<td><?php esc_html_e( 'Cache duration in seconds (default 12 hours).', 'graphql-strava-activities' ); ?></td>
						</tr>
						<tr>
							<td><code>wpgraphql_strava_svg_color</code></td>
							<td>#0d9488</td>
							<td><?php esc_html_e( 'SVG route map stroke colour.', 'graphql-strava-activities' ); ?></td>
						</tr>
						<tr>
							<td><code>wpgraphql_strava_svg_stroke_width</code></td>
							<td>2.5</td>
							<td><?php esc_html_e( 'SVG route map stroke width.', 'graphql-strava-activities' ); ?></td>
						</tr>
						<tr>
							<td><code>wpgraphql_strava_svg_attributes</code></td>
							<td>[]</td>
							<td><?php esc_html_e( 'Extra key-value attributes added to the SVG element.', 'graphql-strava-activities' ); ?></td>
						</tr>
						<tr>
							<td><code>wpgraphql_strava_activities</code></td>
							<td>&mdash;</td>
							<td><?php esc_html_e( 'Filter processed activities before they are cached.', 'graphql-strava-activities' ); ?></td>
						</tr>
						<tr>
							<td><code>wpgraphql_strava_activity_types</code></td>
							<td>[] (all)</td>
							<td><?php esc_html_e( 'Whitelist of activity types to include, e.g. ["Ride", "Run"].', 'graphql-strava-activities' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h3 style="margin-top: 16px;"><?php esc_html_e( 'Example: Only show rides and runs', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">add_filter( 'wpgraphql_strava_activity_types', function () {
	return [ 'Ride', 'Run' ];
} );</pre>

				<h3><?php esc_html_e( 'Example: Change SVG colour to blue', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">add_filter( 'wpgraphql_strava_svg_color', function () {
	return '#3b82f6';
} );</pre>

				<h3><?php esc_html_e( 'Example: Set cache to 6 hours', 'graphql-strava-activities' ); ?></h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">add_filter( 'wpgraphql_strava_cache_ttl', function () {
	return 6 * HOUR_IN_SECONDS;
} );</pre>
			</div>

			<!-- Using with headless frontends -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Using with Headless Frontends', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'This plugin works with any frontend that can query WPGraphQL:', 'graphql-strava-activities' ); ?></p>

				<h3>Next.js</h3>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">const { data } = await fetch('/graphql', {
	method: 'POST',
	headers: { 'Content-Type': 'application/json' },
	body: JSON.stringify({
	query: `{
		stravaActivities(first: 10) {
		title distance duration svgMap
		}
	}`
	})
}).then(r => r.json());</pre>

				<h3><?php esc_html_e( 'Rendering SVG Maps', 'graphql-strava-activities' ); ?></h3>
				<p><?php esc_html_e( 'The svgMap field returns inline SVG markup. You can render it directly in your frontend:', 'graphql-strava-activities' ); ?></p>
				<pre style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">&lt;div dangerouslySetInnerHTML={{ __html: activity.svgMap }} /&gt;</pre>
				<p style="font-size: 13px; color: #646970;">
					<?php esc_html_e( 'The SVG is generated server-side — no Mapbox, Google Maps, or Leaflet required.', 'graphql-strava-activities' ); ?>
				</p>
			</div>

			<?php wpgraphql_strava_render_admin_footer(); ?>

		</div>
	</div>
	<?php
}

// ------------------------------------------------------------------
// Preview page.
// ------------------------------------------------------------------

/**
 * Get demo activities for the preview page when no real data is available.
 *
 * @return array<int, array<string, mixed>> Sample activity data.
 */
function wpgraphql_strava_demo_activities(): array {
	$unit = get_option( 'wpgraphql_strava_units', 'mi' );

	return [
		[
			'title'     => 'Morning Run — Wash Park Loop',
			'distance'  => 'km' === $unit ? 8.24 : 5.12,
			'duration'  => '42m',
			'date'      => gmdate( 'c', strtotime( '-2 days' ) ),
			'svgMap'    => wpgraphql_strava_polyline_to_svg( 'o~l~Fv}naSqAmBwCcA{BjA_CxBoAvC' ),
			'stravaUrl' => 'https://www.strava.com/activities/example-1',
			'type'      => 'Run',
			'photoUrl'  => '',
			'unit'      => $unit,
		],
		[
			'title'     => 'Lunch Ride — Cherry Creek Trail',
			'distance'  => 'km' === $unit ? 29.66 : 18.43,
			'duration'  => '1h 5m',
			'date'      => gmdate( 'c', strtotime( '-3 days' ) ),
			'svgMap'    => wpgraphql_strava_polyline_to_svg( 'kel~FhwoaSsEqHwIaFoLbCaJrGoFvKcBxI' ),
			'stravaUrl' => 'https://www.strava.com/activities/example-2',
			'type'      => 'Ride',
			'photoUrl'  => '',
			'unit'      => $unit,
		],
		[
			'title'     => 'Trail Run — Red Rocks',
			'distance'  => 'km' === $unit ? 12.63 : 7.85,
			'duration'  => '1h 16m',
			'date'      => gmdate( 'c', strtotime( '-5 days' ) ),
			'svgMap'    => wpgraphql_strava_polyline_to_svg( 'gxk~F`{raSaGwDoJcBoNvAcKxEgFjI' ),
			'stravaUrl' => 'https://www.strava.com/activities/example-3',
			'type'      => 'Run',
			'photoUrl'  => '',
			'unit'      => $unit,
		],
	];
}

/**
 * Render a single activity preview card.
 *
 * @param array<string, mixed> $activity Activity data.
 * @param bool                 $is_demo  Whether this is demo data.
 * @return void
 */
function wpgraphql_strava_render_activity_card( array $activity, bool $is_demo = false ): void {
	$date_formatted = '';
	if ( ! empty( $activity['date'] ) ) {
		$timestamp      = strtotime( $activity['date'] );
		$date_formatted = $timestamp ? wp_date( 'F j, Y \a\t g:i A', $timestamp ) : $activity['date'];
	}
	?>
	<div class="card" style="max-width: 800px; padding: 0; overflow: hidden;">
		<div style="display: flex; gap: 0; flex-wrap: wrap;">

			<!-- SVG Map -->
			<div style="flex: 0 0 300px; background: #f9fafb; display: flex; align-items: center; justify-content: center; padding: 16px; min-height: 200px;">
				<?php if ( ! empty( $activity['svgMap'] ) ) : ?>
					<?php echo $activity['svgMap']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG generated by wpgraphql_strava_polyline_to_svg which escapes internally. ?>
				<?php else : ?>
					<span style="color: #9ca3af; font-style: italic;"><?php esc_html_e( 'No route data', 'graphql-strava-activities' ); ?></span>
				<?php endif; ?>
			</div>

			<!-- Activity details -->
			<div style="flex: 1; padding: 20px 24px; min-width: 280px;">
				<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
					<h3 style="margin: 0; font-size: 16px;">
						<?php echo esc_html( $activity['title'] ?? '' ); ?>
					</h3>
					<?php if ( $is_demo ) : ?>
						<span style="background: #dbeafe; color: #1e40af; font-size: 11px; padding: 2px 8px; border-radius: 9999px; font-weight: 500;">
							<?php esc_html_e( 'DEMO', 'graphql-strava-activities' ); ?>
						</span>
					<?php endif; ?>
				</div>

				<p style="color: #6b7280; margin: 4px 0 16px; font-size: 13px;">
					<?php echo esc_html( $date_formatted ); ?>
				</p>

				<div style="display: flex; gap: 24px; flex-wrap: wrap;">
					<div>
						<span style="font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Distance', 'graphql-strava-activities' ); ?></span>
						<div style="font-size: 20px; font-weight: 600;">
							<?php echo esc_html( (string) ( $activity['distance'] ?? 0 ) ); ?>
							<span style="font-size: 13px; font-weight: 400; color: #6b7280;"><?php echo esc_html( $activity['unit'] ?? 'mi' ); ?></span>
						</div>
					</div>
					<div>
						<span style="font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Duration', 'graphql-strava-activities' ); ?></span>
						<div style="font-size: 20px; font-weight: 600;"><?php echo esc_html( $activity['duration'] ?? '' ); ?></div>
					</div>
					<div>
						<span style="font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Type', 'graphql-strava-activities' ); ?></span>
						<div style="font-size: 20px; font-weight: 600;"><?php echo esc_html( $activity['type'] ?? '' ); ?></div>
					</div>
				</div>

				<?php if ( ! empty( $activity['photoUrl'] ) ) : ?>
					<div style="margin-top: 16px;">
						<img src="<?php echo esc_url( $activity['photoUrl'] ); ?>" alt="<?php esc_attr_e( 'Activity photo', 'graphql-strava-activities' ); ?>" style="max-width: 200px; border-radius: 6px;" />
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $activity['stravaUrl'] ) && ! $is_demo ) : ?>
					<p style="margin: 16px 0 0;">
						<a href="<?php echo esc_url( $activity['stravaUrl'] ); ?>" target="_blank" rel="noopener noreferrer" style="font-size: 13px;">
							<?php esc_html_e( 'View on Strava', 'graphql-strava-activities' ); ?> &rarr;
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render the Preview admin page.
 *
 * @return void
 */
function wpgraphql_strava_render_preview_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Try real data first, fall back to demo.
	$activities = wpgraphql_strava_get_cached_activities( 3 );
	$is_demo    = empty( $activities );

	if ( $is_demo ) {
		$activities = wpgraphql_strava_demo_activities();
	}

	$stroke_color = get_option( 'wpgraphql_strava_svg_color', '#0d9488' );
	$stroke_width = get_option( 'wpgraphql_strava_svg_stroke_width', 2.5 );
	$settings_url = admin_url( 'admin.php?page=wpgraphql-strava-settings' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Preview', 'graphql-strava-activities' ); ?></h1>

		<?php if ( $is_demo ) : ?>
			<div class="notice notice-info is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Link to settings page */
						esc_html__( 'Showing demo data. %s to see your real Strava activities here.', 'graphql-strava-activities' ),
						'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Connect your Strava account', 'graphql-strava-activities' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div style="max-width: 800px;">

			<!-- SVG Settings -->
			<div class="card" style="max-width: 800px; padding: 16px 24px; margin-top: 16px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Current SVG Settings', 'graphql-strava-activities' ); ?></h2>
				<div style="display: flex; align-items: center; gap: 24px;">
					<div>
						<span style="font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Stroke Colour', 'graphql-strava-activities' ); ?></span>
						<div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
							<span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background: <?php echo esc_attr( $stroke_color ); ?>; border: 1px solid #d1d5db;"></span>
							<code><?php echo esc_html( $stroke_color ); ?></code>
						</div>
					</div>
					<div>
						<span style="font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Stroke Width', 'graphql-strava-activities' ); ?></span>
						<div style="font-size: 16px; font-weight: 600; margin-top: 4px;"><?php echo esc_html( (string) $stroke_width ); ?>px</div>
					</div>
					<div style="margin-left: auto;">
						<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Edit in Settings', 'graphql-strava-activities' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Activity Cards -->
			<h2 style="margin-top: 24px;"><?php esc_html_e( 'Activity Cards', 'graphql-strava-activities' ); ?></h2>
			<p style="color: #646970; margin-bottom: 16px;">
				<?php
				if ( $is_demo ) {
					esc_html_e( 'This is how your activities will look once connected. The SVG maps below are generated from sample polyline data.', 'graphql-strava-activities' );
				} else {
					esc_html_e( 'Showing your latest cached activities. This is the data returned by the stravaActivities GraphQL query.', 'graphql-strava-activities' );
				}
				?>
			</p>

			<?php foreach ( $activities as $activity ) : ?>
				<?php wpgraphql_strava_render_activity_card( $activity, $is_demo ); ?>
			<?php endforeach; ?>

			<!-- Raw Field Values -->
			<h2 style="margin-top: 24px;"><?php esc_html_e( 'GraphQL Field Values', 'graphql-strava-activities' ); ?></h2>
			<p style="color: #646970; margin-bottom: 16px;">
				<?php esc_html_e( 'Raw field values for the first activity — exactly what your frontend receives from the GraphQL API.', 'graphql-strava-activities' ); ?>
			</p>

			<?php if ( ! empty( $activities[0] ) ) : ?>
				<div class="card" style="max-width: 800px; padding: 0;">
					<table class="widefat striped" style="margin: 0;">
						<thead>
							<tr>
								<th style="width: 140px;"><?php esc_html_e( 'Field', 'graphql-strava-activities' ); ?></th>
								<th><?php esc_html_e( 'Value', 'graphql-strava-activities' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$first          = $activities[0];
							$fields_to_show = [
								'title'            => $first['title'] ?? '',
								'distance'         => (string) ( $first['distance'] ?? '' ),
								'duration'         => $first['duration'] ?? '',
								'date'             => $first['date'] ?? '',
								'type'             => $first['type'] ?? '',
								'unit'             => $first['unit'] ?? '',
								'stravaUrl'        => $first['stravaUrl'] ?? '',
								'photoUrl'         => $first['photoUrl'] ?? '(none)',
								'elevationGain'    => (string) ( $first['elevationGain'] ?? '0' ),
								'averageSpeed'     => (string) ( $first['averageSpeed'] ?? '0' ),
								'maxSpeed'         => (string) ( $first['maxSpeed'] ?? '0' ),
								'averageHeartrate' => isset( $first['averageHeartrate'] ) ? (string) $first['averageHeartrate'] : '(none)',
								'maxHeartrate'     => isset( $first['maxHeartrate'] ) ? (string) $first['maxHeartrate'] : '(none)',
								'calories'         => isset( $first['calories'] ) ? (string) $first['calories'] : '(none)',
								'kudosCount'       => (string) ( $first['kudosCount'] ?? '0' ),
								'commentCount'     => (string) ( $first['commentCount'] ?? '0' ),
								'city'             => $first['city'] ?? '(none)',
								'country'          => $first['country'] ?? '(none)',
								'isPrivate'        => ! empty( $first['isPrivate'] ) ? 'true' : 'false',
								'svgMap'           => ! empty( $first['svgMap'] )
									? substr( $first['svgMap'], 0, 80 ) . '…'
									: '(empty)',
							];
							foreach ( $fields_to_show as $field => $value ) :
								?>
								<tr>
									<td><code><?php echo esc_html( $field ); ?></code></td>
									<td><code style="word-break: break-all;"><?php echo esc_html( $value ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php wpgraphql_strava_render_admin_footer(); ?>

		</div>
	</div>
	<?php
}

// ------------------------------------------------------------------
// Activities list page.
// ------------------------------------------------------------------

/**
 * Render the Activities admin page with WP_List_Table.
 *
 * @return void
 */
function wpgraphql_strava_render_activities_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$table = new WPGRAPHQL_Strava_Activities_List_Table();
	$table->prepare_items();

	$total = $table->get_pagination_arg( 'total_items' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Strava Activities', 'graphql-strava-activities' ); ?></h1>
		<span style="color:#646970;margin-left:8px;">
			<?php
			printf(
				/* translators: %d: Total number of cached activities */
				esc_html__( '%d cached', 'graphql-strava-activities' ),
				(int) $total
			);
			?>
		</span>

		<hr class="wp-header-end" />

		<form method="get">
			<input type="hidden" name="page" value="wpgraphql-strava-activities" />
			<?php $table->display(); ?>
		</form>

		<?php wpgraphql_strava_render_admin_footer(); ?>
	</div>
	<?php
}

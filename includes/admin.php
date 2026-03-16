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
add_action( 'admin_init', 'wpgraphql_strava_intercept_oauth_redirect' );
add_action( 'admin_init', 'wpgraphql_strava_handle_quick_setup' );
add_action( 'admin_init', 'wpgraphql_strava_handle_csv_export' );

/**
 * Handle CSV export of cached activities.
 *
 * @return void
 */
function wpgraphql_strava_handle_csv_export(): void {
	if (
		! isset( $_GET['wpgraphql_strava_export_csv'] )
		|| ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpgraphql_strava_export_csv' )
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	$activities = wpgraphql_strava_get_cached_activities( 0 );

	$filename = 'strava-activities-' . wp_date( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );
	if ( false === $output ) {
		return;
	}

	// Header row.
	// Header row.
	$headers = [ 'Title', 'Type', 'Distance', 'Unit', 'Duration', 'Date', 'Elevation Gain', 'Avg Speed', 'Max Speed', 'Speed Unit', 'Avg HR', 'Max HR', 'Calories', 'Kudos', 'Comments', 'City', 'Country', 'Private', 'Strava URL' ];
	fputcsv( $output, $headers );

	foreach ( $activities as $activity ) {
		fputcsv(
			$output,
			[
				$activity['title'] ?? '',
				$activity['type'] ?? '',
				$activity['distance'] ?? '',
				$activity['unit'] ?? '',
				$activity['duration'] ?? '',
				$activity['date'] ?? '',
				$activity['elevationGain'] ?? '',
				$activity['averageSpeed'] ?? '',
				$activity['maxSpeed'] ?? '',
				$activity['speedUnit'] ?? '',
				$activity['averageHeartrate'] ?? '',
				$activity['maxHeartrate'] ?? '',
				$activity['calories'] ?? '',
				$activity['kudosCount'] ?? '',
				$activity['commentCount'] ?? '',
				$activity['city'] ?? '',
				$activity['country'] ?? '',
				! empty( $activity['isPrivate'] ) ? 'Yes' : 'No',
				$activity['stravaUrl'] ?? '',
			]
		);
	}

	fclose( $output );
	exit;
}

/**
 * Handle the quick setup form on the Getting Started page.
 *
 * Saves Client ID and Client Secret so the OAuth button becomes available
 * without requiring the user to visit the full Settings page.
 *
 * @return void
 */
function wpgraphql_strava_handle_quick_setup(): void {
	if (
		! isset( $_POST['wpgraphql_strava_quick_setup'] )
		|| ! check_admin_referer( 'wpgraphql_strava_quick_setup', 'wpgraphql_strava_quick_setup_nonce' )
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	$client_id     = isset( $_POST['wpgraphql_strava_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpgraphql_strava_client_id'] ) ) : '';
	$client_secret = isset( $_POST['wpgraphql_strava_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wpgraphql_strava_client_secret'] ) ) : '';

	if ( empty( $client_id ) || empty( $client_secret ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'Both Client ID and Client Secret are required.', 'graphql-strava-activities' ),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava' ) );
		exit;
	}

	update_option( 'wpgraphql_strava_client_id', $client_id );
	wpgraphql_strava_update_option( 'wpgraphql_strava_client_secret', $client_secret );

	set_transient(
		'wpgraphql_strava_admin_notice',
		[
			'type'    => 'success',
			'message' => __( 'Credentials saved! Click "Connect with Strava" below to authorize.', 'graphql-strava-activities' ),
		],
		30
	);

	wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava' ) );
	exit;
}

/**
 * Intercept the Strava OAuth redirect on the settings page.
 *
 * Strava sends the user back with ?code=...&state=... in the URL.
 * We rewrite these to our handler's expected parameter names and redirect.
 *
 * @return void
 */
function wpgraphql_strava_intercept_oauth_redirect(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state parameter is verified by the handler.
	if ( ! isset( $_GET['page'], $_GET['code'], $_GET['state'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'wpgraphql-strava-settings' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$redirect = add_query_arg(
		[
			'page'              => 'wpgraphql-strava-settings',
			'strava_oauth_code' => sanitize_text_field( wp_unslash( $_GET['code'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'state'             => sanitize_text_field( wp_unslash( $_GET['state'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		],
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}

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
			'sanitize_callback' => static fn( $val ) => in_array( $val, [ 'every_15_minutes', 'every_30_minutes', 'hourly', 'every_2_hours', 'every_4_hours', 'every_6_hours', 'twicedaily', 'daily', 'weekly', 'every_2_weeks', 'monthly' ], true ) ? $val : 'twicedaily',
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
		'weekly'           => __( 'Once Weekly', 'graphql-strava-activities' ),
		'every_2_weeks'    => __( 'Every 2 Weeks', 'graphql-strava-activities' ),
		'monthly'          => __( 'Monthly', 'graphql-strava-activities' ),
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
		'weekly'           => 1.0 / 7,
		'every_2_weeks'    => 1.0 / 14,
		'monthly'          => 1.0 / 30,
	];
	$syncs_per_day = $intervals[ $value ] ?? 2;
	$daily_calls   = (int) max( 1, ceil( $syncs_per_day * 6 ) );
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
		$has_token_for_sync = ! empty( wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' ) );
		$error_message      = $has_token_for_sync
			? __( 'Sync failed. The Strava API returned an error — check the token status above or try "Test Connection".', 'graphql-strava-activities' )
			: __( 'Sync failed. No access token configured — enter your credentials or connect with Strava first.', 'graphql-strava-activities' );

		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => $error_message,
			],
			30
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
	exit;
}

add_action( 'admin_init', 'wpgraphql_strava_handle_test_connection' );

/**
 * Handle the "Test Connection" button on the settings page.
 *
 * Makes a lightweight API call to the /athlete endpoint to verify credentials.
 *
 * @return void
 */
function wpgraphql_strava_handle_test_connection(): void {
	if (
		! isset( $_POST['wpgraphql_strava_test_connection'] )
		|| ! check_admin_referer( 'wpgraphql_strava_test_connection', 'wpgraphql_strava_test_nonce' )
		|| ! current_user_can( 'manage_options' )
	) {
		return;
	}

	$access_token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

	if ( empty( $access_token ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'No access token configured. Enter your credentials or connect with Strava first.', 'graphql-strava-activities' ),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	$response = wp_remote_get(
		WPGRAPHQL_STRAVA_API_BASE . '/athlete',
		[
			'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
			'timeout' => 10,
		]
	);

	if ( is_wp_error( $response ) ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'Connection failed: ', 'graphql-strava-activities' ) . $response->get_error_message(),
			],
			30
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
		exit;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$data        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 === $status_code && is_array( $data ) && ! empty( $data['firstname'] ) ) {
		$name = sanitize_text_field( $data['firstname'] );
		if ( ! empty( $data['lastname'] ) ) {
			$name .= ' ' . sanitize_text_field( $data['lastname'] );
		}
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %s: Strava athlete name */
					__( 'Connected to Strava as %s.', 'graphql-strava-activities' ),
					$name
				),
			],
			30
		);
	} elseif ( 401 === $status_code ) {
		// Try refreshing the token.
		$new_token = wpgraphql_strava_refresh_access_token();
		if ( ! empty( $new_token ) ) {
			set_transient(
				'wpgraphql_strava_admin_notice',
				[
					'type'    => 'success',
					'message' => __( 'Token was expired but refreshed successfully. Connection is working.', 'graphql-strava-activities' ),
				],
				30
			);
		} else {
			set_transient(
				'wpgraphql_strava_admin_notice',
				[
					'type'    => 'error',
					'message' => __( 'Access token expired and refresh failed. Please re-authorize with Strava.', 'graphql-strava-activities' ),
				],
				30
			);
		}
	} elseif ( 429 === $status_code ) {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => __( 'Strava API rate limit reached. Please wait 15 minutes and try again.', 'graphql-strava-activities' ),
			],
			30
		);
	} else {
		set_transient(
			'wpgraphql_strava_admin_notice',
			[
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Connection failed with HTTP status %d. Check your credentials.', 'graphql-strava-activities' ),
					$status_code
				),
			],
			30
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wpgraphql-strava-settings' ) );
	exit;
}

// Token health notice — warn on all admin pages when credentials are missing or expired.
add_action( 'admin_notices', 'wpgraphql_strava_token_health_notice' );

/**
 * Display an admin notice when the Strava connection needs attention.
 *
 * Only shown on the plugin's admin pages to avoid cluttering the dashboard.
 *
 * @return void
 */
function wpgraphql_strava_token_health_notice(): void {
	// Only show on our plugin pages.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! str_starts_with( $page, 'wpgraphql-strava' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$client_id    = get_option( 'wpgraphql_strava_client_id', '' );
	$access_token = wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' );

	// No credentials at all — likely first-time user.
	if ( empty( $client_id ) ) {
		return;
	}

	// Has Client ID but no token — needs to connect.
	if ( empty( $access_token ) ) {
		$settings_url = admin_url( 'admin.php?page=wpgraphql-strava-settings' );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Strava is not connected. Click "Connect with Strava" on the Settings page to authorize.', 'graphql-strava-activities' ),
			esc_url( $settings_url ),
			esc_html__( 'Go to Settings', 'graphql-strava-activities' )
		);
		return;
	}

	// Token expired and refresh might have failed.
	$expires_at = (int) get_option( 'wpgraphql_strava_token_expires_at', 0 );
	if ( $expires_at > 0 && $expires_at <= time() ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Your Strava access token has expired. The plugin will attempt to refresh it on the next sync.', 'graphql-strava-activities' )
		);
	}
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
		<a href="https://github.com/shawnazar/graphql-strava-activities" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<a href="https://github.com/shawnazar/graphql-strava-activities/issues" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Report an Issue', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<a href="https://github.com/shawnazar/graphql-strava-activities/discussions" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Discussions', 'graphql-strava-activities' ); ?></a>
		<span>&middot;</span>
		<span>
			<?php
			printf(
				/* translators: %s: Strava website link */
				esc_html__( 'Powered by %s', 'graphql-strava-activities' ),
				'<a href="https://www.strava.com" target="_blank" rel="noopener noreferrer" style="color: #FC5200; font-weight: bold;">Strava</a>'
			);
			?>
		</span>
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
	$token_expires_at  = (int) get_option( 'wpgraphql_strava_token_expires_at', 0 );
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

		<!-- Connection Status -->
		<div class="card" style="max-width: 600px; padding: 16px 24px; margin: 16px 0;">
			<h3 style="margin-top: 8px;"><?php esc_html_e( 'Connection Status', 'graphql-strava-activities' ); ?></h3>
			<table class="form-table" role="presentation" style="margin: 0;">
				<tr>
					<th scope="row" style="padding: 8px 10px 8px 0;"><?php esc_html_e( 'Status', 'graphql-strava-activities' ); ?></th>
					<td style="padding: 8px 0;">
						<?php if ( $has_token ) : ?>
							<span style="color: #00a32a; font-weight: 500;">&#10003; <?php esc_html_e( 'Connected', 'graphql-strava-activities' ); ?></span>
						<?php else : ?>
							<span style="color: #d63638; font-weight: 500;">&#10007; <?php esc_html_e( 'Not connected', 'graphql-strava-activities' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $has_token && $token_expires_at > 0 ) : ?>
					<tr>
						<th scope="row" style="padding: 8px 10px 8px 0;"><?php esc_html_e( 'Token Expires', 'graphql-strava-activities' ); ?></th>
						<td style="padding: 8px 0;">
							<?php
							$now         = time();
							$days_left   = (int) ceil( ( $token_expires_at - $now ) / DAY_IN_SECONDS );
							$expiry_date = wp_date( 'F j, Y \a\t g:i A', $token_expires_at );

							if ( $token_expires_at <= $now ) :
								?>
								<span style="color: #d63638; font-weight: 500;">
									<?php esc_html_e( 'Expired', 'graphql-strava-activities' ); ?>
								</span>
								<span style="color: #646970; font-size: 13px;">
									— <?php esc_html_e( 'will auto-refresh on next sync', 'graphql-strava-activities' ); ?>
								</span>
							<?php elseif ( $days_left <= 7 ) : ?>
								<span style="color: #dba617; font-weight: 500;">
									<?php echo esc_html( $expiry_date ); ?>
								</span>
								<span style="color: #646970; font-size: 13px;">
									<?php
									printf(
										/* translators: %d: Number of days until token expires */
										'(' . esc_html( _n( '%d day left', '%d days left', $days_left, 'graphql-strava-activities' ) ) . ')',
										esc_html( (string) $days_left )
									);
									?>
								</span>
							<?php else : ?>
								<?php echo esc_html( $expiry_date ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<?php if ( $has_token ) : ?>
				<form method="post" style="margin-top: 8px;">
					<?php wp_nonce_field( 'wpgraphql_strava_test_connection', 'wpgraphql_strava_test_nonce' ); ?>
					<input type="hidden" name="wpgraphql_strava_test_connection" value="1" />
					<?php submit_button( __( 'Test Connection', 'graphql-strava-activities' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<?php
		$oauth_url = function_exists( 'wpgraphql_strava_get_oauth_url' ) ? wpgraphql_strava_get_oauth_url() : '';
		if ( ! empty( $oauth_url ) ) :
			?>
			<div class="card" style="max-width: 600px; padding: 16px 24px; margin: 16px 0;">
				<?php if ( $has_token ) : ?>
					<h3 style="margin-top: 8px;"><?php esc_html_e( 'Reconnect with Strava', 'graphql-strava-activities' ); ?></h3>
					<p><?php esc_html_e( 'Already connected. Click below to re-authorize if you need to update your tokens.', 'graphql-strava-activities' ); ?></p>
				<?php else : ?>
					<h3 style="margin-top: 8px;"><?php esc_html_e( 'Connect with Strava', 'graphql-strava-activities' ); ?></h3>
					<p><?php esc_html_e( 'Save your Client ID and Client Secret above, then click the button below to authorize. Tokens will be fetched automatically.', 'graphql-strava-activities' ); ?></p>
				<?php endif; ?>
				<a href="<?php echo esc_url( $oauth_url ); ?>">
					<img src="<?php echo esc_url( plugins_url( 'assets/btn-strava-connectwith-orange.svg', WPGRAPHQL_STRAVA_DIR . 'graphql-strava-activities.php' ) ); ?>"
						alt="<?php esc_attr_e( 'Connect with Strava', 'graphql-strava-activities' ); ?>"
						style="height: 48px;" />
				</a>
				<p style="font-size: 13px; color: #646970; margin-top: 8px;">
					<?php esc_html_e( 'Your Strava API application\'s "Authorization Callback Domain" must be set to:', 'graphql-strava-activities' ); ?>
					<code><?php echo esc_html( wp_parse_url( admin_url(), PHP_URL_HOST ) ); ?></code>
				</p>
			</div>
		<?php endif; ?>

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

	// Flash notice (from quick setup form).
	$notice = get_transient( 'wpgraphql_strava_admin_notice' );
	if ( is_array( $notice ) ) {
		delete_transient( 'wpgraphql_strava_admin_notice' );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Getting Started with GraphQL Strava Activities', 'graphql-strava-activities' ); ?></h1>

		<?php if ( is_array( $notice ) ) : ?>
			<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<div style="max-width: 800px;">

			<!-- Quick Start -->
			<div class="card" style="max-width: 800px; padding: 16px 24px; margin-top: 16px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Quick Start', 'graphql-strava-activities' ); ?></h2>
				<ol style="font-size: 14px; line-height: 1.8;">
					<li>
						<?php
						printf(
							/* translators: %s: Link to Strava API settings */
							esc_html__( 'Create a Strava API application at %s (set the Authorization Callback Domain to your site\'s domain).', 'graphql-strava-activities' ),
							'<a href="https://www.strava.com/settings/api" target="_blank" rel="noopener noreferrer">strava.com/settings/api</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Enter your Client ID and Client Secret below and click "Save & Continue".', 'graphql-strava-activities' ); ?></li>
					<li><?php esc_html_e( 'Click "Connect with Strava" to authorize — your tokens will be fetched automatically and activities synced.', 'graphql-strava-activities' ); ?></li>
					<li><?php esc_html_e( 'Query your activities via GraphQL — see examples below.', 'graphql-strava-activities' ); ?></li>
				</ol>
			</div>

			<!-- Getting Strava Tokens -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Getting Your Strava Tokens', 'graphql-strava-activities' ); ?></h2>

				<?php
				$guide_oauth_url = function_exists( 'wpgraphql_strava_get_oauth_url' ) ? wpgraphql_strava_get_oauth_url() : '';
				$guide_has_token = ! empty( wpgraphql_strava_get_option( 'wpgraphql_strava_access_token' ) );

				if ( $guide_has_token ) :
					?>
					<p style="color: #00a32a; font-weight: 500;">
						<span>&#10003;</span>
						<?php esc_html_e( 'You are connected to Strava. Tokens are stored and will refresh automatically.', 'graphql-strava-activities' ); ?>
					</p>
				<?php elseif ( ! empty( $guide_oauth_url ) ) : ?>
					<p><?php esc_html_e( 'Your credentials are saved. Click below to authorize:', 'graphql-strava-activities' ); ?></p>
					<a href="<?php echo esc_url( $guide_oauth_url ); ?>">
						<img src="<?php echo esc_url( plugins_url( 'assets/btn-strava-connectwith-orange.svg', WPGRAPHQL_STRAVA_DIR . 'graphql-strava-activities.php' ) ); ?>"
							alt="<?php esc_attr_e( 'Connect with Strava', 'graphql-strava-activities' ); ?>"
							style="height: 48px; margin: 8px 0; display: block;" />
					</a>
					<p style="font-size: 13px; color: #646970;">
						<?php esc_html_e( 'Your Strava API application\'s "Authorization Callback Domain" must be set to:', 'graphql-strava-activities' ); ?>
						<code><?php echo esc_html( wp_parse_url( admin_url(), PHP_URL_HOST ) ); ?></code>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Enter your Strava API credentials to get started:', 'graphql-strava-activities' ); ?></p>
					<form method="post" style="margin: 12px 0;">
						<?php wp_nonce_field( 'wpgraphql_strava_quick_setup', 'wpgraphql_strava_quick_setup_nonce' ); ?>
						<input type="hidden" name="wpgraphql_strava_quick_setup" value="1" />
						<table class="form-table" role="presentation" style="margin: 0;">
							<tr>
								<th scope="row" style="padding: 8px 10px 8px 0; width: 120px;">
									<label for="quick-client-id"><?php esc_html_e( 'Client ID', 'graphql-strava-activities' ); ?></label>
								</th>
								<td style="padding: 8px 0;">
									<input type="text" id="quick-client-id" name="wpgraphql_strava_client_id"
										value="<?php echo esc_attr( get_option( 'wpgraphql_strava_client_id', '' ) ); ?>"
										class="regular-text" required />
								</td>
							</tr>
							<tr>
								<th scope="row" style="padding: 8px 10px 8px 0;">
									<label for="quick-client-secret"><?php esc_html_e( 'Client Secret', 'graphql-strava-activities' ); ?></label>
								</th>
								<td style="padding: 8px 0;">
									<input type="password" id="quick-client-secret" name="wpgraphql_strava_client_secret"
										class="regular-text" required />
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Save & Continue', 'graphql-strava-activities' ), 'primary', 'submit', false ); ?>
					</form>
					<p style="font-size: 13px; color: #646970;">
						<?php esc_html_e( 'Your Strava API application\'s "Authorization Callback Domain" must be set to:', 'graphql-strava-activities' ); ?>
						<code><?php echo esc_html( wp_parse_url( admin_url(), PHP_URL_HOST ) ); ?></code>
					</p>
				<?php endif; ?>

				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-size: 13px; color: #646970;"><?php esc_html_e( 'Manual setup (advanced)', 'graphql-strava-activities' ); ?></summary>
					<ol style="font-size: 14px; line-height: 1.8; margin-top: 8px;">
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
				</details>

				<p style="font-size: 13px; color: #646970; margin-top: 8px;">
					<?php esc_html_e( 'The plugin automatically refreshes your access token when it expires — you only need to do this once.', 'graphql-strava-activities' ); ?>
				</p>
			</div>

			<!-- Encryption -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Credential Encryption (Optional)', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'For additional security, you can encrypt your stored credentials at rest. Add this line to your wp-config.php:', 'graphql-strava-activities' ); ?></p>
				<code style="display: block; padding: 8px; margin: 8px 0; background: #f0f0f1; font-size: 13px;">define( 'WPGRAPHQL_STRAVA_ENCRYPTION_KEY', 'your-64-character-hex-key' );</code>
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
				<p style="font-size: 13px; color: #646970;"><?php esc_html_e( 'Click "Copy" to copy a query to your clipboard, then paste it into WPGraphiQL.', 'graphql-strava-activities' ); ?></p>

				<h3><?php esc_html_e( 'Fetch all activities with full data', 'graphql-strava-activities' ); ?></h3>
				<div style="position: relative;">
					<pre class="wpgraphql-strava-query" style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities {
	title distance duration date type unit speedUnit
	svgMap stravaUrl photoUrl
	elevationGain averageSpeed maxSpeed
	averageHeartrate maxHeartrate calories
	kudosCount commentCount city country isPrivate
  }
}</pre>
					<button type="button" class="button button-small wpgraphql-strava-copy" style="position:absolute;top:8px;right:8px;"><?php esc_html_e( 'Copy', 'graphql-strava-activities' ); ?></button>
				</div>

				<h3><?php esc_html_e( 'Fetch latest 5 rides with performance data', 'graphql-strava-activities' ); ?></h3>
				<div style="position: relative;">
					<pre class="wpgraphql-strava-query" style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities(first: 5, type: "Ride") {
	title distance duration svgMap
	elevationGain averageSpeed calories
  }
}</pre>
					<button type="button" class="button button-small wpgraphql-strava-copy" style="position:absolute;top:8px;right:8px;"><?php esc_html_e( 'Copy', 'graphql-strava-activities' ); ?></button>
				</div>

				<h3><?php esc_html_e( 'Fetch runs with heart rate and location', 'graphql-strava-activities' ); ?></h3>
				<div style="position: relative;">
					<pre class="wpgraphql-strava-query" style="background: #f0f0f1; padding: 12px; overflow-x: auto; font-size: 13px;">{
  stravaActivities(type: "Run") {
	title distance duration date
	averageHeartrate maxHeartrate
	city country photoUrl stravaUrl
  }
}</pre>
					<button type="button" class="button button-small wpgraphql-strava-copy" style="position:absolute;top:8px;right:8px;"><?php esc_html_e( 'Copy', 'graphql-strava-activities' ); ?></button>
				</div>
			</div>

			<script>
			document.addEventListener( 'click', function( e ) {
				if ( ! e.target.classList.contains( 'wpgraphql-strava-copy' ) ) return;
				var pre = e.target.parentElement.querySelector( '.wpgraphql-strava-query' );
				if ( ! pre ) return;
				navigator.clipboard.writeText( pre.textContent ).then( function() {
					var btn = e.target;
					btn.textContent = '<?php echo esc_js( __( 'Copied!', 'graphql-strava-activities' ) ); ?>';
					setTimeout( function() { btn.textContent = '<?php echo esc_js( __( 'Copy', 'graphql-strava-activities' ) ); ?>'; }, 2000 );
				} );
			} );
			</script>

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

			<!-- Strava Brand Attribution -->
			<div class="card" style="max-width: 800px; padding: 16px 24px;">
				<h2 style="margin-top: 8px;"><?php esc_html_e( 'Strava Brand Attribution', 'graphql-strava-activities' ); ?></h2>
				<p><?php esc_html_e( 'Per Strava brand guidelines, any frontend displaying Strava data must include the following:', 'graphql-strava-activities' ); ?></p>
				<ol style="font-size: 14px; line-height: 1.8;">
					<li>
						<strong><?php esc_html_e( '"Powered by Strava" attribution', 'graphql-strava-activities' ); ?></strong>
						— <?php esc_html_e( 'Display this text on any page that shows Strava activity data. It must not be larger or more prominent than your application name.', 'graphql-strava-activities' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( '"View on Strava" link styling', 'graphql-strava-activities' ); ?></strong>
						— <?php esc_html_e( 'Links to Strava must be bold, underlined, or use Strava orange (#FC5200) to be identifiable.', 'graphql-strava-activities' ); ?>
					</li>
				</ol>
				<p style="font-size: 13px; color: #646970;">
					<?php esc_html_e( 'The poweredByStrava field is available in the GraphQL response for convenience. This plugin handles attribution on admin pages automatically — you are responsible for your public-facing frontend.', 'graphql-strava-activities' ); ?>
				</p>
				<p style="font-size: 13px;">
					<a href="https://developers.strava.com/guidelines/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Strava Brand Guidelines', 'graphql-strava-activities' ); ?> &rarr;</a>
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
					<?php echo wp_kses( $activity['svgMap'], wpgraphql_strava_allowed_svg_tags() ); ?>
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
						<a href="<?php echo esc_url( $activity['stravaUrl'] ); ?>" target="_blank" rel="noopener noreferrer" style="color: #FC5200; font-weight: bold; text-decoration: none; font-size: 13px;">
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

		<?php if ( (int) $total > 0 ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpgraphql-strava-activities&wpgraphql_strava_export_csv=1' ), 'wpgraphql_strava_export_csv' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'graphql-strava-activities' ); ?>
			</a>
		<?php endif; ?>

		<hr class="wp-header-end" />

		<form method="get">
			<input type="hidden" name="page" value="wpgraphql-strava-activities" />
			<?php $table->display(); ?>
		</form>

		<?php wpgraphql_strava_render_admin_footer(); ?>
	</div>
	<?php
}

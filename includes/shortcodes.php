<?php
/**
 * WordPress shortcodes for displaying Strava activities.
 *
 * Provides shortcodes so non-headless WordPress sites can display
 * Strava data in posts and pages without GraphQL queries.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpgraphql_strava_register_shortcodes' );

/**
 * Register all Strava shortcodes.
 *
 * @return void
 */
function wpgraphql_strava_register_shortcodes(): void {
	add_shortcode( 'strava_activities', 'wpgraphql_strava_shortcode_activities' );
	add_shortcode( 'strava_activity', 'wpgraphql_strava_shortcode_activity' );
	add_shortcode( 'strava_map', 'wpgraphql_strava_shortcode_map' );
	add_shortcode( 'strava_stats', 'wpgraphql_strava_shortcode_stats' );
	add_shortcode( 'strava_latest', 'wpgraphql_strava_shortcode_latest' );
	add_shortcode( 'strava_heatmap', 'wpgraphql_strava_shortcode_heatmap' );
	add_shortcode( 'strava_year_review', 'wpgraphql_strava_shortcode_year_review' );
}

/**
 * Render an activity card as HTML.
 *
 * Shared by multiple shortcodes.
 *
 * @param array<string, mixed> $activity Activity data.
 * @return string HTML markup.
 */
function wpgraphql_strava_render_shortcode_card( array $activity ): string {
	$date = '';
	if ( ! empty( $activity['date'] ) ) {
		$ts   = strtotime( $activity['date'] );
		$date = $ts ? wp_date( 'M j, Y', $ts ) : '';
	}

	$html  = '<div class="strava-activity-card" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:16px;font-family:sans-serif;">';

	if ( ! empty( $activity['svgMap'] ) ) {
		$html .= '<div class="strava-activity-map" style="margin-bottom:12px;">';
		$html .= wp_kses( $activity['svgMap'], wpgraphql_strava_allowed_svg_tags() );
		$html .= '</div>';
	}

	$html .= '<div class="strava-activity-details">';
	$html .= '<strong style="font-size:16px;">' . esc_html( $activity['title'] ?? '' ) . '</strong>';

	if ( ! empty( $date ) ) {
		$html .= '<div style="color:#6b7280;font-size:13px;margin-top:2px;">' . esc_html( $date ) . '</div>';
	}

	$html .= '<div style="display:flex;gap:16px;margin-top:8px;font-size:14px;">';
	$html .= '<span><strong>' . esc_html( (string) ( $activity['distance'] ?? '0' ) ) . '</strong> ' . esc_html( $activity['unit'] ?? 'mi' ) . '</span>';
	$html .= '<span><strong>' . esc_html( $activity['duration'] ?? '' ) . '</strong></span>';
	$html .= '<span>' . esc_html( $activity['type'] ?? '' ) . '</span>';
	$html .= '</div>';

	if ( ! empty( $activity['photoUrl'] ) ) {
		$html .= '<div style="margin-top:12px;"><img src="' . esc_url( $activity['photoUrl'] ) . '" alt="' . esc_attr__( 'Activity photo', 'graphql-strava-activities' ) . '" style="max-width:100%;border-radius:6px;" /></div>';
	}

	if ( ! empty( $activity['stravaUrl'] ) ) {
		$html .= '<div style="margin-top:8px;"><a href="' . esc_url( $activity['stravaUrl'] ) . '" target="_blank" rel="noopener noreferrer" style="color:#FC5200;font-weight:bold;text-decoration:none;font-size:13px;">' . esc_html__( 'View on Strava', 'graphql-strava-activities' ) . ' &rarr;</a></div>';
	}

	$html .= '</div></div>';

	return $html;
}

/**
 * [strava_activities] — Render a list of activity cards.
 *
 * Attributes: count (int), type (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_activities( $atts ): string {
	$atts = shortcode_atts(
		[
			'count' => '10',
			'type'  => '',
		],
		$atts,
		'strava_activities'
	);

	$count      = max( 1, (int) $atts['count'] );
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! empty( $atts['type'] ) ) {
		$type_filter = sanitize_text_field( $atts['type'] );
		$activities  = array_values(
			array_filter(
				$activities,
				static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type_filter
			)
		);
	}

	$activities = array_slice( $activities, 0, $count );

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	$html = '<div class="strava-activities">';
	foreach ( $activities as $activity ) {
		$html .= wpgraphql_strava_render_shortcode_card( $activity );
	}
	$html .= '</div>';

	return $html;
}

/**
 * [strava_activity] — Render a single activity card by index.
 *
 * Attributes: index (int, 0-based).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_activity( $atts ): string {
	$atts = shortcode_atts(
		[ 'index' => '0' ],
		$atts,
		'strava_activity'
	);

	$index      = max( 0, (int) $atts['index'] );
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! isset( $activities[ $index ] ) ) {
		return '<p class="strava-empty">' . esc_html__( 'Activity not found.', 'graphql-strava-activities' ) . '</p>';
	}

	return wpgraphql_strava_render_shortcode_card( $activities[ $index ] );
}

/**
 * [strava_map] — Render just the SVG route map for an activity.
 *
 * Attributes: index (int), width (int), height (int), color (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string SVG markup.
 */
function wpgraphql_strava_shortcode_map( $atts ): string {
	$atts = shortcode_atts(
		[
			'index'  => '0',
			'width'  => '300',
			'height' => '200',
			'color'  => '',
		],
		$atts,
		'strava_map'
	);

	$activities = wpgraphql_strava_get_cached_activities( 0 );
	$index      = max( 0, (int) $atts['index'] );

	if ( ! isset( $activities[ $index ] ) || empty( $activities[ $index ]['svgMap'] ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No route map available.', 'graphql-strava-activities' ) . '</p>';
	}

	// If custom dimensions or color requested, we need the raw polyline — not available from cache.
	// Return the cached SVG wrapped in a container with the requested dimensions.
	$width  = max( 100, (int) $atts['width'] );
	$height = max( 100, (int) $atts['height'] );

	return '<div class="strava-map" style="width:' . esc_attr( (string) $width ) . 'px;max-width:100%;">'
		. wp_kses( $activities[ $index ]['svgMap'], wpgraphql_strava_allowed_svg_tags() )
		. '</div>';
}

/**
 * [strava_stats] — Render aggregate activity statistics.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_stats( $atts ): string {
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	$total_distance = 0.0;
	$total_count    = count( $activities );
	$unit           = $activities[0]['unit'] ?? 'mi';
	$types          = [];

	foreach ( $activities as $activity ) {
		$total_distance += (float) ( $activity['distance'] ?? 0 );
		$type            = $activity['type'] ?? 'Other';
		if ( ! isset( $types[ $type ] ) ) {
			$types[ $type ] = 0;
		}
		++$types[ $type ];
	}

	$html  = '<div class="strava-stats" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-family:sans-serif;">';
	$html .= '<div style="display:flex;gap:24px;flex-wrap:wrap;">';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Activities', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( (string) $total_count ) . '</div></div>';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Total Distance', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format( $total_distance, 1 ) ) . ' <span style="font-size:14px;font-weight:400;">' . esc_html( $unit ) . '</span></div></div>';
	$html .= '</div>';

	if ( count( $types ) > 0 ) {
		arsort( $types );
		$html .= '<div style="margin-top:12px;font-size:13px;color:#6b7280;">';
		$parts = [];
		foreach ( $types as $type => $type_count ) {
			$parts[] = esc_html( $type ) . ': ' . esc_html( (string) $type_count );
		}
		$html .= implode( ' &middot; ', $parts );
		$html .= '</div>';
	}

	$html .= '</div>';

	return $html;
}

/**
 * [strava_latest] — Render the most recent activity, optionally filtered by type.
 *
 * Attributes: type (string).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_latest( $atts ): string {
	$atts = shortcode_atts(
		[ 'type' => '' ],
		$atts,
		'strava_latest'
	);

	$activities = wpgraphql_strava_get_cached_activities( 0 );

	if ( ! empty( $atts['type'] ) ) {
		$type_filter = sanitize_text_field( $atts['type'] );
		$activities  = array_values(
			array_filter(
				$activities,
				static fn( array $a ): bool => ( $a['type'] ?? '' ) === $type_filter
			)
		);
	}

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No Strava activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	return wpgraphql_strava_render_shortcode_card( $activities[0] );
}

/**
 * [strava_heatmap] — Render all routes overlaid on one SVG.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string SVG markup.
 */
function wpgraphql_strava_shortcode_heatmap( $atts ): string {
	$atts = shortcode_atts(
		[
			'width'  => '400',
			'height' => '300',
		],
		$atts,
		'strava_heatmap'
	);

	$activities = wpgraphql_strava_get_cached_activities( 0 );
	$width      = max( 100, (int) $atts['width'] );
	$height     = max( 100, (int) $atts['height'] );

	if ( empty( $activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No activities found.', 'graphql-strava-activities' ) . '</p>';
	}

	// Collect all SVG maps and overlay them with reduced opacity.
	$maps = [];
	foreach ( $activities as $activity ) {
		if ( ! empty( $activity['svgMap'] ) ) {
			$maps[] = $activity['svgMap'];
		}
	}

	if ( empty( $maps ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No route data available.', 'graphql-strava-activities' ) . '</p>';
	}

	$opacity = min( 1.0, max( 0.05, 1.0 / count( $maps ) * 3 ) );

	$html = '<div class="strava-heatmap" style="position:relative;width:' . esc_attr( (string) $width ) . 'px;height:' . esc_attr( (string) $height ) . 'px;max-width:100%;background:#1a1a2e;border-radius:8px;overflow:hidden;">';
	foreach ( $maps as $map ) {
		$html .= '<div style="position:absolute;inset:0;opacity:' . esc_attr( (string) round( $opacity, 2 ) ) . ';">'
			. wp_kses( $map, wpgraphql_strava_allowed_svg_tags() )
			. '</div>';
	}
	$html .= '</div>';

	return $html;
}

/**
 * [strava_year_review] — Render year-in-review aggregate statistics.
 *
 * Attributes: year (int, defaults to current year).
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string HTML output.
 */
function wpgraphql_strava_shortcode_year_review( $atts ): string {
	$atts = shortcode_atts(
		[ 'year' => (string) wp_date( 'Y' ) ],
		$atts,
		'strava_year_review'
	);

	$year       = (int) $atts['year'];
	$activities = wpgraphql_strava_get_cached_activities( 0 );

	// Filter to requested year.
	$year_activities = array_filter(
		$activities,
		static function ( array $a ) use ( $year ): bool {
			$date = $a['date'] ?? '';
			return ! empty( $date ) && (int) substr( $date, 0, 4 ) === $year;
		}
	);

	if ( empty( $year_activities ) ) {
		return '<p class="strava-empty">' . esc_html__( 'No activities found for this year.', 'graphql-strava-activities' ) . '</p>';
	}

	$total_distance   = 0.0;
	$total_duration_s = 0;
	$total_elevation  = 0.0;
	$total_count      = count( $year_activities );
	$unit             = '';
	$types            = [];
	$monthly          = array_fill( 1, 12, 0.0 );

	foreach ( $year_activities as $a ) {
		$total_distance  += (float) ( $a['distance'] ?? 0 );
		$total_elevation += (float) ( $a['elevationGain'] ?? 0 );
		$unit             = $a['unit'] ?? 'mi';

		$type = $a['type'] ?? 'Other';
		if ( ! isset( $types[ $type ] ) ) {
			$types[ $type ] = 0;
		}
		++$types[ $type ];

		$date = $a['date'] ?? '';
		if ( ! empty( $date ) ) {
			$month = (int) substr( $date, 5, 2 );
			if ( $month >= 1 && $month <= 12 ) {
				$monthly[ $month ] += (float) ( $a['distance'] ?? 0 );
			}
		}
	}

	// Build monthly bar chart SVG.
	$max_monthly = max( $monthly );
	$max_monthly = $max_monthly > 0 ? $max_monthly : 1;
	$bar_width   = 20;
	$chart_w     = 12 * ( $bar_width + 4 );
	$chart_h     = 60;
	$bars        = '';

	for ( $m = 1; $m <= 12; $m++ ) {
		$bar_h = ( $monthly[ $m ] / $max_monthly ) * $chart_h;
		$x     = ( $m - 1 ) * ( $bar_width + 4 );
		$y     = $chart_h - $bar_h;
		$bars .= sprintf(
			'<rect x="%s" y="%s" width="%s" height="%s" rx="2" fill="#0d9488" opacity="0.8"/>',
			esc_attr( (string) $x ),
			esc_attr( (string) round( $y, 1 ) ),
			esc_attr( (string) $bar_width ),
			esc_attr( (string) round( $bar_h, 1 ) )
		);
	}

	$chart_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $chart_w . ' ' . $chart_h . '" width="' . $chart_w . '" height="' . $chart_h . '">' . $bars . '</svg>';

	arsort( $types );

	$html  = '<div class="strava-year-review" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;font-family:sans-serif;">';
	$html .= '<h3 style="margin-top:0;">' . esc_html( (string) $year ) . ' ' . esc_html__( 'Year in Review', 'graphql-strava-activities' ) . '</h3>';
	$html .= '<div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;">';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Activities', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( (string) $total_count ) . '</div></div>';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Distance', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format( $total_distance, 1 ) ) . ' <span style="font-size:14px;">' . esc_html( $unit ) . '</span></div></div>';
	$html .= '<div><div style="font-size:12px;color:#9ca3af;text-transform:uppercase;">' . esc_html__( 'Elevation', 'graphql-strava-activities' ) . '</div><div style="font-size:24px;font-weight:700;">' . esc_html( number_format( $total_elevation, 0 ) ) . ' <span style="font-size:14px;">m</span></div></div>';
	$html .= '</div>';
	$html .= '<div style="margin-bottom:12px;">' . wp_kses( $chart_svg, wpgraphql_strava_allowed_svg_tags() ) . '</div>';

	$parts = [];
	foreach ( $types as $type_name => $type_count ) {
		$parts[] = esc_html( $type_name ) . ': ' . esc_html( (string) $type_count );
	}
	$html .= '<div style="font-size:13px;color:#6b7280;">' . implode( ' &middot; ', $parts ) . '</div>';
	$html .= '</div>';

	return $html;
}

// ------------------------------------------------------------------
// Shortcode generator button for the classic editor.
// ------------------------------------------------------------------

add_action( 'media_buttons', 'wpgraphql_strava_add_shortcode_button' );
add_action( 'admin_footer', 'wpgraphql_strava_shortcode_modal' );

/**
 * Add a "Strava" button next to "Add Media".
 *
 * @return void
 */
function wpgraphql_strava_add_shortcode_button(): void {
	$screen = get_current_screen();
	if ( null === $screen || ! in_array( $screen->base, [ 'post', 'page' ], true ) ) {
		return;
	}

	printf(
		'<button type="button" class="button" id="wpgraphql-strava-shortcode-btn" title="%1$s" style="padding-left:6px;">
			<span class="dashicons dashicons-chart-line" style="margin-top:3px;"></span> %1$s
		</button>',
		esc_attr__( 'Strava', 'graphql-strava-activities' )
	);
}

/**
 * Render the shortcode generator modal (Thickbox).
 *
 * @return void
 */
function wpgraphql_strava_shortcode_modal(): void {
	$screen = get_current_screen();
	if ( null === $screen || ! in_array( $screen->base, [ 'post', 'page' ], true ) ) {
		return;
	}
	?>
	<div id="wpgraphql-strava-shortcode-modal" style="display:none;">
		<div style="padding:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Insert Strava Shortcode', 'graphql-strava-activities' ); ?></h2>

			<p>
				<label for="wpgraphql-strava-sc-type"><strong><?php esc_html_e( 'Shortcode', 'graphql-strava-activities' ); ?></strong></label><br />
				<select id="wpgraphql-strava-sc-type" style="width:100%;">
					<option value="strava_activities"><?php esc_html_e( '[strava_activities] — Activity list', 'graphql-strava-activities' ); ?></option>
					<option value="strava_activity"><?php esc_html_e( '[strava_activity] — Single activity', 'graphql-strava-activities' ); ?></option>
					<option value="strava_map"><?php esc_html_e( '[strava_map] — Route map only', 'graphql-strava-activities' ); ?></option>
					<option value="strava_stats"><?php esc_html_e( '[strava_stats] — Aggregate stats', 'graphql-strava-activities' ); ?></option>
					<option value="strava_latest"><?php esc_html_e( '[strava_latest] — Most recent activity', 'graphql-strava-activities' ); ?></option>
				</select>
			</p>

			<div id="wpgraphql-strava-sc-opts">
				<p class="wpgraphql-strava-opt" data-for="strava_activities strava_latest">
					<label for="wpgraphql-strava-sc-act-type"><strong><?php esc_html_e( 'Activity Type (optional)', 'graphql-strava-activities' ); ?></strong></label><br />
					<input type="text" id="wpgraphql-strava-sc-act-type" placeholder="<?php esc_attr_e( 'e.g. Ride, Run, Walk', 'graphql-strava-activities' ); ?>" style="width:100%;" />
				</p>
				<p class="wpgraphql-strava-opt" data-for="strava_activities">
					<label for="wpgraphql-strava-sc-count"><strong><?php esc_html_e( 'Count', 'graphql-strava-activities' ); ?></strong></label><br />
					<input type="number" id="wpgraphql-strava-sc-count" value="10" min="1" max="200" style="width:100%;" />
				</p>
				<p class="wpgraphql-strava-opt" data-for="strava_activity strava_map">
					<label for="wpgraphql-strava-sc-index"><strong><?php esc_html_e( 'Activity Index (0-based)', 'graphql-strava-activities' ); ?></strong></label><br />
					<input type="number" id="wpgraphql-strava-sc-index" value="0" min="0" style="width:100%;" />
				</p>
			</div>

			<p style="margin-top:16px;">
				<label><strong><?php esc_html_e( 'Preview', 'graphql-strava-activities' ); ?></strong></label><br />
				<code id="wpgraphql-strava-sc-preview" style="display:block;padding:8px;background:#f0f0f1;">[strava_activities count="10"]</code>
			</p>

			<p style="text-align:right;margin-top:16px;">
				<button type="button" class="button button-primary" id="wpgraphql-strava-sc-insert">
					<?php esc_html_e( 'Insert Shortcode', 'graphql-strava-activities' ); ?>
				</button>
			</p>
		</div>
	</div>

	<script>
	(function() {
		var btn   = document.getElementById( 'wpgraphql-strava-shortcode-btn' );
		if ( ! btn ) return;

		btn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			tb_show( '<?php echo esc_js( __( 'Insert Strava Shortcode', 'graphql-strava-activities' ) ); ?>', '#TB_inline?inlineId=wpgraphql-strava-shortcode-modal&width=420&height=380' );
			updatePreview();
		} );

		var scType  = document.getElementById( 'wpgraphql-strava-sc-type' );
		var actType = document.getElementById( 'wpgraphql-strava-sc-act-type' );
		var count   = document.getElementById( 'wpgraphql-strava-sc-count' );
		var index   = document.getElementById( 'wpgraphql-strava-sc-index' );
		var preview = document.getElementById( 'wpgraphql-strava-sc-preview' );

		function showHideOpts() {
			var val = scType.value;
			document.querySelectorAll( '.wpgraphql-strava-opt' ).forEach( function( el ) {
				el.style.display = el.getAttribute( 'data-for' ).indexOf( val ) !== -1 ? '' : 'none';
			} );
		}

		function updatePreview() {
			showHideOpts();
			var tag   = scType.value;
			var attrs = [];

			if ( ( tag === 'strava_activities' ) && count.value && count.value !== '10' ) {
				attrs.push( 'count="' + count.value + '"' );
			}
			if ( ( tag === 'strava_activities' || tag === 'strava_latest' ) && actType.value ) {
				attrs.push( 'type="' + actType.value + '"' );
			}
			if ( ( tag === 'strava_activity' || tag === 'strava_map' ) && index.value && index.value !== '0' ) {
				attrs.push( 'index="' + index.value + '"' );
			}

			preview.textContent = '[' + tag + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';
		}

		scType.addEventListener( 'change', updatePreview );
		actType.addEventListener( 'input', updatePreview );
		count.addEventListener( 'input', updatePreview );
		index.addEventListener( 'input', updatePreview );

		document.getElementById( 'wpgraphql-strava-sc-insert' ).addEventListener( 'click', function() {
			window.send_to_editor( preview.textContent );
			tb_remove();
		} );
	})();
	</script>
	<?php
}

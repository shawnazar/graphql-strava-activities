<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Elementor widget file.
/**
 * Elementor widget for Strava activities.
 *
 * Only loaded when Elementor is active.
 *
 * @package WPGraphQL\Strava
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strava Activities Elementor Widget.
 */
class WPGRAPHQL_Strava_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'wpgraphql-strava-activities';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Strava Activities', 'graphql-strava-activities' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-chart-line';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Strava Settings', 'graphql-strava-activities' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'shortcode',
			[
				'label'   => __( 'Display', 'graphql-strava-activities' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'strava_activities',
				'options' => [
					'strava_activities'   => __( 'Activity List', 'graphql-strava-activities' ),
					'strava_activity'     => __( 'Single Activity', 'graphql-strava-activities' ),
					'strava_map'          => __( 'Route Map', 'graphql-strava-activities' ),
					'strava_stats'        => __( 'Stats', 'graphql-strava-activities' ),
					'strava_latest'       => __( 'Latest Activity', 'graphql-strava-activities' ),
					'strava_heatmap'      => __( 'Heatmap', 'graphql-strava-activities' ),
					'strava_year_review'  => __( 'Year in Review', 'graphql-strava-activities' ),
				],
			]
		);

		$this->add_control(
			'count',
			[
				'label'     => __( 'Count', 'graphql-strava-activities' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 10,
				'min'       => 1,
				'max'       => 200,
				'condition' => [ 'shortcode' => 'strava_activities' ],
			]
		);

		$this->add_control(
			'type',
			[
				'label'       => __( 'Activity Type', 'graphql-strava-activities' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'Ride, Run, Walk',
				'condition'   => [ 'shortcode' => [ 'strava_activities', 'strava_latest' ] ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings  = $this->get_settings_for_display();
		$shortcode = sanitize_text_field( $settings['shortcode'] ?? 'strava_activities' );
		$atts      = '';

		if ( 'strava_activities' === $shortcode && ! empty( $settings['count'] ) ) {
			$atts .= ' count="' . absint( $settings['count'] ) . '"';
		}

		if ( ! empty( $settings['type'] ) ) {
			$atts .= ' type="' . esc_attr( $settings['type'] ) . '"';
		}

		echo do_shortcode( '[' . $shortcode . $atts . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode handles escaping.
	}
}

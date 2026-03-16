( function ( wp ) {
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'wpgraphql-strava/activities', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Strava Settings', initialOpen: true },
						el( SelectControl, {
							label: 'Display',
							value: attributes.shortcode,
							options: [
								{ label: 'Activity List', value: 'strava_activities' },
								{ label: 'Single Activity', value: 'strava_activity' },
								{ label: 'Route Map', value: 'strava_map' },
								{ label: 'Stats', value: 'strava_stats' },
								{ label: 'Latest Activity', value: 'strava_latest' },
							],
							onChange: function ( val ) {
								setAttributes( { shortcode: val } );
							},
						} ),
						( attributes.shortcode === 'strava_activities' ) &&
							el( RangeControl, {
								label: 'Count',
								value: attributes.count,
								onChange: function ( val ) {
									setAttributes( { count: val } );
								},
								min: 1,
								max: 200,
							} ),
						( attributes.shortcode === 'strava_activities' ||
							attributes.shortcode === 'strava_latest' ) &&
							el( TextControl, {
								label: 'Activity Type',
								value: attributes.type,
								onChange: function ( val ) {
									setAttributes( { type: val } );
								},
								help: 'e.g. Ride, Run, Walk',
							} ),
						( attributes.shortcode === 'strava_activity' ||
							attributes.shortcode === 'strava_map' ) &&
							el( RangeControl, {
								label: 'Activity Index',
								value: attributes.index,
								onChange: function ( val ) {
									setAttributes( { index: val } );
								},
								min: 0,
								max: 199,
							} )
					)
				),
				el( ServerSideRender, {
					block: 'wpgraphql-strava/activities',
					attributes: attributes,
				} )
			);
		},
	} );
} )( window.wp );

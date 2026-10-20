/* global wp */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	// Server-rendered on the front; the editor shows a 9:16 placeholder of the configured width.
	blocks.registerBlockType( 'forwp-social/post-video', {
		apiVersion: 3,
		edit: function ( props ) {
			var max = props.attributes.maxWidth || 400;
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						'div',
						{ style: { padding: '16px' } },
						el( wp.components.RangeControl, {
							label: __( 'Max width (px)', '4wp-social' ),
							value: max,
							min: 200,
							max: 720,
							step: 10,
							onChange: function ( value ) {
								props.setAttributes( { maxWidth: value || 400 } );
							},
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( {
						className: 'forwp-social-post-video-placeholder',
						style: { '--forwp-post-video-max': max + 'px' },
					} ),
					el( 'span', null, __( 'Post video', '4wp-social' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.i18n );

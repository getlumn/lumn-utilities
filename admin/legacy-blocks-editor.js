/**
 * Editor (edit()) registration for the 7 blocks reimplemented in
 * register/legacy-blocks.php, so content built with LUMN-Utilites-OLD
 * ("DCMO Utilities") keeps working in the block editor after that plugin
 * is deleted. Plain vanilla JS using only WordPress core-provided globals
 * (wp.blocks/wp.element/wp.blockEditor/wp.components/wp.serverSideRender) -
 * no build step, no new dependency, matching the rest of this plugin.
 *
 * Fields that were a Genesis Custom Blocks "inner_blocks" control
 * (inner-content, service-hover-content, slick-slide, content) are edited
 * here as raw HTML in a textarea rather than as real nested blocks - GCB
 * stored their value as a plain string attribute (see
 * register/legacy-blocks.php), so this keeps read/write symmetric with
 * exactly what's saved, at the cost of a plain-text editing experience for
 * just those specific fields rather than full visual nested-block editing.
 */
( function( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var Button = wp.components.Button;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	// The wp-server-side-render script has exposed the component both as
	// `wp.serverSideRender` directly and as `wp.serverSideRender.default`
	// across different WP core versions - support both.
	var ServerSideRender = ( wp.serverSideRender && wp.serverSideRender.default ) || wp.serverSideRender;
	var __ = wp.i18n.__;

	function field( type, attributes, setAttributes, def ) {
		var value = attributes[ def.attr ];
		var onChange = function( next ) {
			var update = {};
			update[ def.attr ] = next;
			setAttributes( update );
		};

		if ( def.control === 'select' ) {
			return el( SelectControl, {
				label: def.label,
				help: def.help,
				value: value,
				options: def.options,
				onChange: onChange,
			} );
		}

		if ( def.control === 'number' ) {
			return el( TextControl, {
				type: 'number',
				label: def.label,
				help: def.help,
				value: value,
				onChange: function( next ) {
					onChange( next === '' ? undefined : parseInt( next, 10 ) );
				},
			} );
		}

		if ( def.control === 'textarea' || def.control === 'html' ) {
			return el( TextareaControl, {
				label: def.label,
				help: def.help,
				value: value,
				rows: def.control === 'html' ? 6 : 4,
				onChange: onChange,
			} );
		}

		if ( def.control === 'image' ) {
			return el( 'div', { className: 'lumn-ut-legacy-block-image-field' },
				el( 'p', {}, el( 'strong', {}, def.label ) ),
				el( MediaUploadCheck, {},
					el( MediaUpload, {
						onSelect: function( media ) {
							onChange( media.id );
						},
						allowedTypes: [ 'image' ],
						value: value,
						render: function( obj ) {
							return el( Button, { variant: 'secondary', onClick: obj.open }, value ? __( 'Replace Image', 'lumn-utilities' ) : __( 'Select Image', 'lumn-utilities' ) );
						},
					} )
				),
				value ? el( Button, { variant: 'link', isDestructive: true, onClick: function() { onChange( undefined ); } }, __( 'Remove', 'lumn-utilities' ) ) : null
			);
		}

		// Plain text (covers 'text' and 'color' - a hex/rgba string typed by hand).
		return el( TextControl, {
			label: def.label,
			help: def.help,
			value: value,
			onChange: onChange,
		} );
	}

	function inspectorFields( attributes, setAttributes, defs ) {
		return defs.map( function( def ) {
			return el( 'div', { key: def.attr, style: { marginBottom: '12px' } }, field( def.control, attributes, setAttributes, def ) );
		} );
	}

	function registerLegacyBlock( name, config ) {
		registerBlockType( name, {
			apiVersion: 3,
			title: config.title,
			icon: config.icon,
			category: config.category,
			attributes: config.attributes,
			edit: function( props ) {
				var attributes = props.attributes;
				var setAttributes = props.setAttributes;
				var blockProps = useBlockProps( { className: 'lumn-ut-legacy-block-editor' } );

				return el( 'div', blockProps,
					el( InspectorControls, {}, el( PanelBody, { title: __( 'Settings', 'lumn-utilities' ), initialOpen: true }, inspectorFields( attributes, setAttributes, config.fields ) ) ),
					config.editorBody ? config.editorBody( attributes, setAttributes ) : null,
					el( ServerSideRender, { block: name, attributes: attributes } )
				);
			},
			save: function() {
				return null;
			},
		} );
	}

	// [genesis-custom-blocks/dcmo-ut-hyperlink]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-hyperlink', {
		title: __( 'DCMO UT Hyperlink', 'lumn-utilities' ),
		icon: 'link',
		category: 'text',
		attributes: { className: { type: 'string' }, link: { type: 'string' }, 'link-target': { type: 'string' }, 'inner-content': { type: 'string' } },
		fields: [
			{ attr: 'link', label: __( 'Link URL', 'lumn-utilities' ), control: 'text' },
			{ attr: 'link-target', label: __( 'Link Target', 'lumn-utilities' ), control: 'select', options: [ { label: '_self', value: '_self' }, { label: '_blank', value: '_blank' }, { label: '_parent', value: '_parent' }, { label: '_top', value: '_top' } ] },
			{ attr: 'inner-content', label: __( 'Inner Content (HTML)', 'lumn-utilities' ), control: 'html' },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-service-highlight]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-service-highlight', {
		title: __( 'DCMO UT Service Highlight', 'lumn-utilities' ),
		icon: 'view-column',
		category: 'text',
		attributes: {
			className: { type: 'string' },
			'heading-color': { type: 'string' },
			'heading-color-hover': { type: 'string' },
			'text-color': { type: 'string' },
			'service-highlight-height': { type: 'integer' },
			'border-radius': { type: 'integer' },
			'background-image': { type: 'integer' },
			'service-text-overlay': { type: 'string' },
			'service-text-overlay-hover': { type: 'string' },
			'service-heading': { type: 'string' },
			'service-hover-content': { type: 'string' },
		},
		fields: [
			{ attr: 'service-heading', label: __( 'Service Heading', 'lumn-utilities' ), control: 'text' },
			{ attr: 'service-hover-content', label: __( 'Hover Content (HTML)', 'lumn-utilities' ), control: 'html' },
			{ attr: 'background-image', label: __( 'Background Image', 'lumn-utilities' ), control: 'image' },
			{ attr: 'service-highlight-height', label: __( 'Min Height (px)', 'lumn-utilities' ), control: 'number' },
			{ attr: 'border-radius', label: __( 'Border Radius (px)', 'lumn-utilities' ), control: 'number' },
			{ attr: 'heading-color', label: __( 'Heading Color', 'lumn-utilities' ), control: 'color' },
			{ attr: 'heading-color-hover', label: __( 'Heading Color Hover', 'lumn-utilities' ), control: 'color' },
			{ attr: 'text-color', label: __( 'Text Color', 'lumn-utilities' ), control: 'color' },
			{ attr: 'service-text-overlay', label: __( 'Overlay', 'lumn-utilities' ), control: 'color' },
			{ attr: 'service-text-overlay-hover', label: __( 'Overlay Hover', 'lumn-utilities' ), control: 'color' },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-su-animation]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-su-animation', {
		title: __( 'DCMO UT SU Animation', 'lumn-utilities' ),
		icon: 'controls-play',
		category: 'media',
		attributes: { className: { type: 'string' }, animation: { type: 'string' }, duration: { type: 'integer' }, delay: { type: 'integer' }, inline: { type: 'string' }, class: { type: 'string' }, 'inner-content': { type: 'string' } },
		fields: [
			{ attr: 'animation', label: __( 'Animation', 'lumn-utilities' ), control: 'text', help: __( 'An animate.css animation name, e.g. fadeIn, bounce, tada.', 'lumn-utilities' ) },
			{ attr: 'duration', label: __( 'Duration (seconds)', 'lumn-utilities' ), control: 'number' },
			{ attr: 'delay', label: __( 'Delay', 'lumn-utilities' ), control: 'number' },
			{ attr: 'inline', label: __( 'Inline', 'lumn-utilities' ), control: 'select', options: [ { label: 'no', value: 'no' }, { label: 'yes', value: 'yes' } ] },
			{ attr: 'class', label: __( 'Extra CSS Class', 'lumn-utilities' ), control: 'text' },
			{ attr: 'inner-content', label: __( 'Inner Content (HTML)', 'lumn-utilities' ), control: 'html' },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-su-lightbox]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-su-lightbox', {
		title: __( 'DCMO UT SU Lightbox', 'lumn-utilities' ),
		icon: 'format-gallery',
		category: 'text',
		attributes: { className: { type: 'string' }, type: { type: 'string' }, src: { type: 'string' }, 'inner-content': { type: 'string' } },
		fields: [
			{ attr: 'type', label: __( 'Type', 'lumn-utilities' ), control: 'select', options: [ { label: '—', value: '' }, { label: 'iframe', value: 'iframe' }, { label: 'image', value: 'image' }, { label: 'inline', value: 'inline' } ] },
			{ attr: 'src', label: __( 'Src (URL, or CSS selector for "inline")', 'lumn-utilities' ), control: 'text' },
			{ attr: 'inner-content', label: __( 'Inner Content (HTML)', 'lumn-utilities' ), control: 'html' },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-su-lightbox-content]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-su-lightbox-content', {
		title: __( 'DCMO UT SU Lightbox Content', 'lumn-utilities' ),
		icon: 'megaphone',
		category: 'text',
		attributes: { className: { type: 'string' }, id: { type: 'string' }, content: { type: 'string' } },
		fields: [
			{ attr: 'id', label: __( 'Id (used by the matching Lightbox block)', 'lumn-utilities' ), control: 'text' },
			{ attr: 'content', label: __( 'Content (HTML)', 'lumn-utilities' ), control: 'html' },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-slick-slider]
	registerLegacyBlock( 'genesis-custom-blocks/dcmo-ut-slick-slider', {
		title: __( 'DCMO UT Slick Slider', 'lumn-utilities' ),
		icon: 'images-alt2',
		category: 'media',
		attributes: {
			className: { type: 'string' },
			'slider-class': { type: 'string' },
			'slider-settings': { type: 'string' },
			'left-arrow': { type: 'integer' },
			'right-arrow': { type: 'integer' },
			'arrow-alignment': { type: 'string' },
			'arrow-topbottom-distance': { type: 'integer' },
			'arrow-topbottom-distance-unit': { type: 'string' },
			'arrow-edge-distance': { type: 'integer' },
			'arrow-edge-distance-unit': { type: 'string' },
			'slider-leftright-margin': { type: 'integer' },
			'slider-leftright-margin-unit': { type: 'string' },
			'slick-slide': { type: 'string' },
		},
		fields: [
			{ attr: 'slick-slide', label: __( 'Slides (HTML)', 'lumn-utilities' ), control: 'html', help: __( 'It is recommended to embed each slide in a Group or Container block.', 'lumn-utilities' ) },
			{ attr: 'slider-class', label: __( 'Slider Class', 'lumn-utilities' ), control: 'text' },
			{ attr: 'slider-settings', label: __( 'Slider Settings (JS object body)', 'lumn-utilities' ), control: 'textarea', help: __( 'e.g. infinite: true, autoplay: true, - see kenwheeler.github.io/slick', 'lumn-utilities' ) },
			{ attr: 'left-arrow', label: __( 'Left Arrow Image', 'lumn-utilities' ), control: 'image' },
			{ attr: 'right-arrow', label: __( 'Right Arrow Image', 'lumn-utilities' ), control: 'image' },
			{ attr: 'arrow-alignment', label: __( 'Arrow Alignment', 'lumn-utilities' ), control: 'select', options: [ { label: 'Middle (default)', value: 'dcmo-slick-block-arrows-middle' }, { label: 'Top', value: 'dcmo-slick-block-arrows-top' }, { label: 'Bottom', value: 'dcmo-slick-block-arrows-bottom' } ] },
			{ attr: 'arrow-topbottom-distance', label: __( 'Arrow Top/Bottom Distance', 'lumn-utilities' ), control: 'number' },
			{ attr: 'arrow-topbottom-distance-unit', label: __( 'Arrow Top/Bottom Distance Unit', 'lumn-utilities' ), control: 'select', options: [ { label: 'px', value: 'px' }, { label: 'em', value: 'em' }, { label: 'rem', value: 'rem' }, { label: 'vh', value: 'vh' }, { label: 'vw', value: 'vw' }, { label: '%', value: '%' } ] },
			{ attr: 'arrow-edge-distance', label: __( 'Arrow Edge Distance', 'lumn-utilities' ), control: 'number' },
			{ attr: 'arrow-edge-distance-unit', label: __( 'Arrow Edge Distance Unit', 'lumn-utilities' ), control: 'select', options: [ { label: 'px', value: 'px' }, { label: 'em', value: 'em' }, { label: 'rem', value: 'rem' }, { label: 'vh', value: 'vh' }, { label: 'vw', value: 'vw' }, { label: '%', value: '%' } ] },
			{ attr: 'slider-leftright-margin', label: __( 'Slider Left/Right Margin', 'lumn-utilities' ), control: 'number' },
			{ attr: 'slider-leftright-margin-unit', label: __( 'Slider Left/Right Margin Unit', 'lumn-utilities' ), control: 'select', options: [ { label: 'px', value: 'px' }, { label: 'em', value: 'em' }, { label: 'rem', value: 'rem' }, { label: 'vh', value: 'vh' }, { label: 'vw', value: 'vw' }, { label: '%', value: '%' } ] },
		],
	} );

	// [genesis-custom-blocks/dcmo-ut-su-accordion] - has a repeater field
	// (spoiler: array of {title, content}), handled with a small custom
	// list UI in the block body rather than the generic inspector fields.
	registerBlockType( 'genesis-custom-blocks/dcmo-ut-su-accordion', {
		apiVersion: 3,
		title: __( 'DCMO UT SU Accordion', 'lumn-utilities' ),
		icon: 'list-view',
		category: 'text',
		attributes: {
			className: { type: 'string' },
			style: { type: 'string' },
			icon: { type: 'string' },
			spoiler: { type: 'array', default: [] },
		},
		edit: function( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'lumn-ut-legacy-block-editor' } );
			var rows = attributes.spoiler || [];

			function updateRow( index, key, value ) {
				var next = rows.slice();
				next[ index ] = Object.assign( {}, next[ index ], ( function() {
					var o = {};
					o[ key ] = value;
					return o;
				} )() );
				setAttributes( { spoiler: next } );
			}

			function removeRow( index ) {
				var next = rows.slice();
				next.splice( index, 1 );
				setAttributes( { spoiler: next } );
			}

			function addRow() {
				setAttributes( { spoiler: rows.concat( [ { title: '', content: '' } ] ) } );
			}

			var rowEls = rows.map( function( row, index ) {
				return el( 'div', { key: index, style: { border: '1px solid #ddd', padding: '8px', marginBottom: '8px' } },
					el( TextControl, { label: __( 'Title', 'lumn-utilities' ), value: row.title || '', onChange: function( v ) { updateRow( index, 'title', v ); } } ),
					el( TextareaControl, { label: __( 'Content', 'lumn-utilities' ), value: row.content || '', onChange: function( v ) { updateRow( index, 'content', v ); } } ),
					el( Button, { variant: 'link', isDestructive: true, onClick: function() { removeRow( index ); } }, __( 'Remove Spoiler', 'lumn-utilities' ) )
				);
			} );

			return el( 'div', blockProps,
				el( InspectorControls, {}, el( PanelBody, { title: __( 'Settings', 'lumn-utilities' ), initialOpen: true },
					inspectorFields( attributes, setAttributes, [
						{ attr: 'style', label: __( 'Style', 'lumn-utilities' ), control: 'select', options: [ { label: 'default', value: 'default' }, { label: 'fancy', value: 'fancy' }, { label: 'simple', value: 'simple' } ] },
						{ attr: 'icon', label: __( 'Icon', 'lumn-utilities' ), control: 'text' },
					] )
				) ),
				el( 'div', {}, rowEls ),
				el( Button, { variant: 'secondary', onClick: addRow }, __( 'Add Spoiler', 'lumn-utilities' ) ),
				el( ServerSideRender, { block: 'genesis-custom-blocks/dcmo-ut-su-accordion', attributes: attributes } )
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp );

/**
 * The block's editor side, written against the globals WordPress already loads.
 *
 * No build step on purpose: the block renders on the server, so the editor only
 * needs to collect a handful of settings. A toolchain to produce that would be
 * more to install, more to keep current, and more to review.
 */
(function (blocks, element, blockEditor, components, i18n) {
	const el = element.createElement;
	const __ = i18n.__;

	function field(props, key, label, help) {
		return el(components.TextControl, {
			label: label,
			help: help,
			value: props.attributes[key],
			onChange: function (value) {
				const next = {};
				next[key] = value;
				props.setAttributes(next);
			},
		});
	}

	blocks.registerBlockType('hikari-flipbook/book', {
		edit: function (props) {
			const path = props.attributes.path;

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __('Book', 'hikari-flipbook') },
						field(props, 'book', __('Saved book', 'hikari-flipbook'),
							__('The id of a book from the Flipbooks screen. Leave empty to use a path.', 'hikari-flipbook')),
						field(props, 'path', __('PDF or image folder', 'hikari-flipbook'),
							__('Relative to the site, for example wp-content/uploads/catalogue.pdf', 'hikari-flipbook')),
						el(components.SelectControl, {
							label: __('Pages shown', 'hikari-flipbook'),
							value: props.attributes.mode,
							options: [
								{ label: __('Site default', 'hikari-flipbook'), value: '' },
								{ label: __('One or two, depending on the screen', 'hikari-flipbook'), value: 'auto' },
								{ label: __('One page', 'hikari-flipbook'), value: 'single' },
								{ label: __('Two pages', 'hikari-flipbook'), value: 'double' },
							],
							onChange: function (value) { props.setAttributes({ mode: value }); },
						}),
						field(props, 'maxHeight', __('Largest height (px)', 'hikari-flipbook'),
							__('Leave empty to use the site default.', 'hikari-flipbook')),
						el(components.SelectControl, {
							label: __('Report to analytics', 'hikari-flipbook'),
							value: props.attributes.analytics,
							options: [
								{ label: __('Site default', 'hikari-flipbook'), value: '' },
								{ label: __('Only the page event', 'hikari-flipbook'), value: 'none' },
								{ label: __('Google Tag Manager (dataLayer)', 'hikari-flipbook'), value: 'dataLayer' },
								{ label: __('Google Analytics (gtag)', 'hikari-flipbook'), value: 'gtag' },
							],
							onChange: function (value) { props.setAttributes({ analytics: value }); },
						}),
						field(props, 'seo', __('Text in the page for search engines', 'hikari-flipbook'),
							__('1 or 0. Leave empty to use the site default.', 'hikari-flipbook')),
						field(props, 'hotspotsShown', __('Outline the hotspots', 'hikari-flipbook'),
							__('1 to outline every region as soon as the book opens. Leave empty to use the site default.', 'hikari-flipbook'))
					)
				),
				el(
					'div',
					blockEditor.useBlockProps({ className: 'hikari-flipbook-placeholder' }),
					el(
						components.Placeholder,
						{ icon: 'book-alt', label: __('Flipbook', 'hikari-flipbook') },
						path
							? el('p', null, path)
							: field(props, 'path', __('PDF or image folder', 'hikari-flipbook'),
								__('Relative to the site, for example wp-content/uploads/catalogue.pdf', 'hikari-flipbook'))
					)
				)
			);
		},
		// Rendered by PHP, so the saved post holds the block and nothing else.
		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);

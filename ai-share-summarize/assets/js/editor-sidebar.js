/**
 * AI Share & Summarize — Block editor sidebar panel
 *
 * Registers a PluginDocumentSettingPanel in the post sidebar that
 * replaces the classic meta box. Uses useSelect to read meta from
 * the data store (required for real-time collaboration sync) and
 * controlled inputs so the UI updates when another user changes
 * the value during a collaborative session.
 *
 * Translations are passed from PHP via wp_localize_script()
 * using the global aissI18n object, so this script does not
 * depend on wp-i18n or JSON translation files.
 *
 * @since 1.7.3
 * @package AiShareSummarize
 */
( function () {
	'use strict';

	var el              = wp.element.createElement;
	var registerPlugin  = wp.plugins.registerPlugin;
	var useSelect       = wp.data.useSelect;
	var useDispatch     = wp.data.useDispatch;
	var CheckboxControl = wp.components.CheckboxControl;

	/* PluginDocumentSettingPanel moved to wp.editor in WP 6.6+. */
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return; // Gutenberg not available or very old WP.
	}

	/* Translations from PHP via wp_localize_script(). */
	var i18n = window.aissI18n || {};

	var META_KEY = '_ayudawp_aiss_exclude';

	/**
	 * Sidebar panel component
	 *
	 * Reads and writes the exclusion meta through the data store so
	 * changes sync automatically between collaborative sessions.
	 */
	function AissExcludePanel() {
		/* Read meta from the data store — never copy to local state. */
		var isExcluded = useSelect( function ( select ) {
			var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return meta && meta[ META_KEY ] ? true : false;
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		var meta = {};

		return el(
			PluginDocumentSettingPanel,
			{
				name:  'aiss-exclude-panel',
				title: i18n.panelTitle || 'AI Share & Summarize',
				icon:  'format-status',
			},
			el( CheckboxControl, {
				label:    i18n.label || 'Hide share buttons on this content',
				help:     i18n.help || 'Check this box to prevent the share and AI buttons from appearing on this specific content.',
				checked:  isExcluded,
				onChange: function ( value ) {
					meta[ META_KEY ] = value;
					editPost( { meta: meta } );
				},
			} )
		);
	}

	registerPlugin( 'aiss-exclude', {
		render: AissExcludePanel,
		icon:   null,
	} );
} )();

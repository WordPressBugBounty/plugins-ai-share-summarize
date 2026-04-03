/**
 * AI Share & Summarize — Block editor sidebar panel
 *
 * Registers a PluginDocumentSettingPanel in the post sidebar that
 * replaces the classic meta box. Uses useSelect to read meta from
 * the data store (required for real-time collaboration sync) and
 * controlled inputs so the UI updates when another user changes
 * the value during a collaborative session.
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
	var __              = wp.i18n.__;

	/* PluginDocumentSettingPanel moved to wp.editor in WP 6.6+. */
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return; // Gutenberg not available or very old WP.
	}

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
				title: __( 'AI Share & Summarize', 'ai-share-summarize' ),
				icon:  'format-status',
			},
			el( CheckboxControl, {
				label:    __( 'Hide share buttons on this content', 'ai-share-summarize' ),
				help:     __( 'Check this box to prevent the share and AI buttons from appearing on this specific content.', 'ai-share-summarize' ),
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
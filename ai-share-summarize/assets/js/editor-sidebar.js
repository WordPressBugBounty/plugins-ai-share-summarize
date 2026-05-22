/**
 * AI Share & Summarize — Block editor sidebar panel
 *
 * Registers a single PluginDocumentSettingPanel in the post sidebar that
 * holds both controls (exclusion + AI summary). Uses useSelect to read meta
 * from the data store (required for real-time collaboration sync) and
 * controlled inputs so the UI updates when another user changes the value
 * during a collaborative session.
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
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var useEffect       = wp.element.useEffect;
	var useRef          = wp.element.useRef;
	var registerPlugin  = wp.plugins.registerPlugin;
	var useSelect       = wp.data.useSelect;
	var useDispatch     = wp.data.useDispatch;
	var CheckboxControl = wp.components.CheckboxControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button          = wp.components.Button;
	var Spinner         = wp.components.Spinner;
	var apiFetch        = wp.apiFetch;

	/* PluginDocumentSettingPanel moved to wp.editor in WP 6.6+. */
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return; // Gutenberg not available or very old WP.
	}

	/* Translations from PHP via wp_localize_script(). */
	var i18n = window.aissI18n || {};

	var META_EXCLUDE  = '_ayudawp_aiss_exclude';
	var META_SUMMARY  = '_ayudawp_aiss_summary';
	var META_PROVIDER = '_ayudawp_aiss_summary_provider';
	var META_HASH     = '_ayudawp_aiss_summary_hash';

	/**
	 * Combined sidebar panel: exclusion + AI summary
	 *
	 * Reads and writes meta through the data store so changes sync
	 * automatically between collaborative sessions.
	 */
	function AissPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );

		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var isSaving = useSelect( function ( select ) {
			return select( 'core/editor' ).isSavingPost() && ! select( 'core/editor' ).isAutosavingPost();
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		var isExcluded = meta[ META_EXCLUDE ] ? true : false;
		var summary    = meta[ META_SUMMARY ]  || '';
		var provider   = meta[ META_PROVIDER ] || '';

		var stateBusy     = useState( false );
		var stateError    = useState( '' );
		var stateNotice   = useState( '' );
		var stateWaiting  = useState( false );
		var busy          = stateBusy[ 0 ];
		var setBusy       = stateBusy[ 1 ];
		var error         = stateError[ 0 ];
		var setError      = stateError[ 1 ];
		var notice        = stateNotice[ 0 ];
		var setNotice     = stateNotice[ 1 ];
		var waitingForGen = stateWaiting[ 0 ];
		var setWaiting    = stateWaiting[ 1 ];

		var wasSavingRef = useRef( false );

		/*
		 * Auto-refresh the summary meta after a post save.
		 *
		 * The auto-generation runs asynchronously via WP-Cron, so by the
		 * time the editor finishes saving the meta is still empty in the
		 * store — the cron event hasn't fired yet. Without this effect the
		 * editor user has to reload the page to see the new summary, and
		 * may end up burning API tokens by re-clicking Update.
		 *
		 * Strategy: detect the isSavingPost true→false transition, wait
		 * ~4s for the cron heartbeat, then re-fetch the post via REST and
		 * push the fresh server state into core's entity cache. This is
		 * receiveEntityRecords (not editPost) so it doesn't dirty the
		 * post — any pending user edits stay intact.
		 */
		useEffect( function () {
			if ( wasSavingRef.current && ! isSaving && postId && postType ) {
				setWaiting( true );
				var timer = window.setTimeout( function () {
					refreshSummaryFromServer( postId, postType, function () {
						setWaiting( false );
					} );
				}, 4000 );
				wasSavingRef.current = isSaving;
				return function () { window.clearTimeout( timer ); };
			}
			wasSavingRef.current = isSaving;
		}, [ isSaving, postId, postType ] );

		function refreshSummaryFromServer( id, type, done ) {
			apiFetch( { path: '/wp/v2/types/' + type } )
				.then( function ( typeData ) {
					var restBase = ( typeData && typeData.rest_base ) ? typeData.rest_base : ( type + 's' );
					return apiFetch( { path: '/wp/v2/' + restBase + '/' + id + '?context=edit' } );
				} )
				.then( function ( postData ) {
					if ( postData && wp.data.dispatch( 'core' ).receiveEntityRecords ) {
						wp.data.dispatch( 'core' ).receiveEntityRecords( 'postType', type, [ postData ] );
					}
				} )
				.catch( function () { /* silent — the user can still reload manually */ } )
				.then( function () { if ( done ) { done(); } } );
		}

		function setExclusion( value ) {
			var update = {};
			update[ META_EXCLUDE ] = value;
			editPost( { meta: update } );
		}

		function onEditSummary( value ) {
			var update = {};
			update[ META_SUMMARY ]  = value;
			update[ META_PROVIDER ] = 'manual';
			editPost( { meta: update } );
		}

		function onRegenerate() {
			if ( ! postId || busy ) {
				return;
			}
			setBusy( true );
			setError( '' );
			setNotice( '' );
			apiFetch( {
				path:   '/aiss/v1/summary/regenerate',
				method: 'POST',
				data:   { post_id: postId },
			} ).then( function ( response ) {
				var update = {};
				update[ META_SUMMARY ]  = response.summary  || '';
				update[ META_PROVIDER ] = response.provider || '';
				update[ META_HASH ]     = response.hash     || '';
				editPost( { meta: update } );

				// Confirm success — without this the user sees no visible
				// change when the regenerated text matches the previous one.
				var providerName = 'wp_ai_client' === response.provider
					? ( i18n.providerWpAi || 'WP AI Client' )
					: ( 'extractive' === response.provider
						? ( i18n.providerExtractive || 'Basic summary (extractive)' )
						: response.provider );
				setNotice(
					( i18n.regenerateSuccess || 'Summary regenerated.' )
					+ ( providerName ? ' (' + providerName + ')' : '' )
				);
			} ).catch( function ( err ) {
				var msg = '';
				if ( err && err.message ) {
					msg = err.message;
				}
				if ( err && err.code && err.code !== msg ) {
					msg = msg ? ( msg + ' [' + err.code + ']' ) : err.code;
				}
				setError( msg || ( i18n.regenerateError || 'Regeneration failed.' ) );
			} ).then( function () {
				setBusy( false );
			} );
		}

		var providerLabel = '';
		if ( 'wp_ai_client' === provider ) {
			providerLabel = i18n.providerWpAi || 'Generated by WP AI Client';
		} else if ( 'extractive' === provider ) {
			providerLabel = i18n.providerExtractive || 'Basic summary (extractive)';
		} else if ( 'manual' === provider ) {
			providerLabel = i18n.providerManual || 'Manually edited';
		}

		var children = [];

		// 1) Exclusion checkbox.
		children.push( el( CheckboxControl, {
			key:      'exclude',
			label:    i18n.label || 'Hide share buttons on this content',
			help:     i18n.help  || 'Check this box to prevent the share and AI buttons from appearing on this specific content.',
			checked:  isExcluded,
			onChange: setExclusion,
		} ) );

		// 2) Visual separator between the two control groups.
		children.push( el( 'hr', {
			key:   'separator',
			style: { margin: '16px 0', border: 'none', borderTop: '1px solid #ddd' },
		} ) );

		// 3) AI Summary heading + provider badge.
		children.push( el( 'p', {
			key:   'summary-heading',
			style: { margin: '0 0 4px', fontWeight: 600 },
		}, i18n.summaryPanelTitle || 'AI Summary' ) );

		if ( providerLabel ) {
			children.push( el( 'p', {
				key:   'provider',
				style: { margin: '0 0 8px', fontSize: '12px', color: '#646970' },
			}, providerLabel ) );
		} else {
			children.push( el( 'p', {
				key:   'empty',
				style: { margin: '0 0 8px', fontSize: '12px', color: '#646970' },
			}, i18n.noSummaryYet || 'Summary will be generated when this post is published.' ) );
		}

		// 4) Editable textarea.
		children.push( el( TextareaControl, {
			key:      'textarea',
			label:    i18n.summaryEditLabel || 'Summary',
			help:     i18n.manualOverrideHelp || 'Editing this text marks the summary as manual — it will not be regenerated automatically.',
			value:    summary,
			rows:     4,
			onChange: onEditSummary,
		} ) );

		// 5) Regenerate button + spinner.
		var actionRow = [];
		actionRow.push( el(
			Button,
			{
				key:      'regenerate',
				variant:  'secondary',
				onClick:  onRegenerate,
				disabled: busy || waitingForGen || ! postId,
			},
			busy ? ( i18n.regenerating || 'Regenerating…' ) : ( i18n.regenerateLabel || 'Regenerate now' )
		) );
		if ( busy ) {
			actionRow.push( el( Spinner, { key: 'spinner' } ) );
		}
		children.push( el( 'div', {
			key:   'actions',
			style: { display: 'flex', alignItems: 'center', gap: '8px' },
		}, actionRow ) );

		// 6) Background-generation indicator (post just saved, cron pending).
		if ( waitingForGen ) {
			children.push( el( 'div', {
				key:   'waiting',
				style: { display: 'flex', alignItems: 'center', gap: '8px', margin: '8px 0 0', color: '#646970', fontSize: '12px' },
			}, [
				el( Spinner, { key: 'wait-spinner' } ),
				el( 'span', { key: 'wait-text' }, i18n.waitingForGen || 'Generating summary in background…' ),
			] ) );
		}

		// 7) Success / error messages.
		if ( notice ) {
			children.push( el( 'p', {
				key:   'notice',
				style: { margin: '8px 0 0', color: '#00a32a', fontSize: '12px' },
			}, notice ) );
		}
		if ( error ) {
			children.push( el( 'p', {
				key:   'error',
				style: { margin: '8px 0 0', color: '#dc3232', fontSize: '12px' },
			}, error ) );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name:  'aiss-panel',
				title: i18n.panelTitle || 'AI Share & Summarize',
				icon:  'format-status',
			},
			el( Fragment, null, children )
		);
	}

	registerPlugin( 'aiss-panel', {
		render: AissPanel,
		icon:   null,
	} );
} )();

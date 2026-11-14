<?php
/**
 * Admin functionality for AI Summaries & Share Buttons plugin
 *
 * @package AiShareSummarize
 * @since 1.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle admin functionality
 */
class AyudaWP_AISS_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'ayudawp_admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'ayudawp_add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'ayudawp_admin_init' ) );
		add_action( 'admin_notices', array( $this, 'ayudawp_activation_notice' ) );
		add_action( 'wp_ajax_ayudawp_dismiss_activation_notice', array( $this, 'ayudawp_dismiss_activation_notice' ) );
		add_filter( 'plugin_action_links_' . AYUDAWP_AISS_PLUGIN_BASENAME, array( $this, 'ayudawp_add_settings_link' ) );
		add_action( 'wp_ajax_ayudawp_aiss_delete_all_data', array( $this, 'ayudawp_ajax_delete_all_data' ) );
		add_action( 'wp_ajax_ayudawp_aiss_dismiss_ai_error', array( $this, 'ayudawp_ajax_dismiss_ai_error' ) );
		add_action( 'wp_ajax_ayudawp_aiss_block_preview', array( $this, 'ayudawp_ajax_block_preview' ) );
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function ayudawp_admin_enqueue_scripts( $hook ) {

		// Dashboard widget styles on the main dashboard page.
		if ( 'index.php' === $hook ) {
			wp_enqueue_style(
				'aiss-admin-styles',
				AYUDAWP_AISS_PLUGIN_URL . 'assets/css/admin-styles.css',
				array(),
				AYUDAWP_AISS_VERSION
			);
			return;
		}

		if ( 'settings_page_ai-share-summarize' !== $hook ) {
			return;
		}

		// Load thickbox for plugin install modals.
		add_thickbox();

		/*
		 * The front-end stylesheet, so the button picker below can show each
		 * button exactly as visitors will see it instead of a bare SVG. Loaded
		 * as a dependency of the admin sheet, which then neutralises the block
		 * margins and separator lines inside the picker.
		 */
		wp_enqueue_style(
			'ayudawp-aiss-styles',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/ai-share-summarize.css',
			array(),
			AYUDAWP_AISS_VERSION
		);

		// The bar's own look and its "More" panel (v2.8.0), for the preview in
		// its section. Its placement rules need .ayudawp-aiss-floating, which
		// the preview does not carry, so nothing here gets pinned to the screen.
		wp_enqueue_style(
			'ayudawp-aiss-floating-bar',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/floating-bar.css',
			array( 'ayudawp-aiss-styles' ),
			AYUDAWP_AISS_VERSION
		);

		// Admin CSS.
		wp_enqueue_style(
			'aiss-admin-styles',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/admin-styles.css',
			array( 'ayudawp-aiss-styles', 'ayudawp-aiss-floating-bar' ),
			AYUDAWP_AISS_VERSION
		);

		// Settings page inline styles (form-specific, not needed globally).
		wp_add_inline_style( 'aiss-admin-styles', '
			.ayudawp-info-link {
				font-style: italic;
				color: #646970;
			}
			.form-table th {
				width: 200px;
			}
			.ayudawp-icon-preview {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				margin: 10px 0;
				padding: 8px 12px;
				background: #f8f9fa;
				border: 1px solid #ddd;
				border-radius: 4px;
			}
			.ayudawp-icon-preview svg {
				width: 16px;
				height: 16px;
			}
			.ayudawp-seo-integration {
				background: #f9f9f9;
				padding: 15px;
				margin: 10px 0;
				border-radius: 4px;
			}
			.ayudawp-seo-detected {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				color: #00a32a;
				font-weight: 500;
				margin-left: 15px;
			}
			.ayudawp-seo-detected .dashicons {
				color: #00a32a;
			}
			.ayudawp-seo-not-detected {
				color: #646970;
				font-style: italic;
				margin-left: 15px;
			}
			.ayudawp-color-picker-row {
				display: flex;
				gap: 20px;
				flex-wrap: wrap;
				align-items: flex-start;
				margin-top: 10px;
			}
			.ayudawp-color-picker-row label {
				display: block;
				margin-bottom: 5px;
				font-weight: 500;
			}
			.ayudawp-sortable-placeholder {
				background: #e8f0fe;
				border: 2px dashed #4285f4;
				border-radius: 3px;
				height: 36px;
				margin-bottom: 4px;
			}
		' );

		// Determine active tab.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'analytics'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which settings tab to paint, not acting on anything; the value is only compared against a fixed list of tab slugs.

		// Settings + AI Summary tabs: both have custom-color pickers; only
		// the buttons tab has the drag-and-drop ordering lists.
		if ( 'settings' === $tab || 'ai-summary' === $tab ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		if ( 'settings' === $tab ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		// Admin scripts loaded on all tabs (settings + analytics).
		$script_deps = array( 'jquery' );

		// Analytics tab: also load Chart.js (bundled locally).
		if ( 'analytics' === $tab ) {
			wp_enqueue_script(
				'chartjs',
				AYUDAWP_AISS_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js',
				array(),
				'4.4.7',
				true
			);
			$script_deps[] = 'chartjs';
		}

		wp_enqueue_script(
			'aiss-admin-scripts',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/js/admin-scripts.js',
			$script_deps,
			AYUDAWP_AISS_VERSION,
			true
		);

		wp_localize_script( 'aiss-admin-scripts', 'aissAdminData', array(
			'restUrl'    => esc_url_raw( rest_url( 'aiss/v1/' ) ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'adminNonce' => wp_create_nonce( 'aiss_admin_nonce' ),
			'nonce'      => wp_create_nonce( 'aiss_admin_nonce' ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'adminUrl'   => admin_url(),
			'siteName'   => get_bloginfo( 'name' ),
			'vigiaActive' => is_plugin_active( 'vigia/vigia.php' ) ? '1' : '0',
			'platforms'  => ayudawp_aiss_get_platforms_meta(),
			'strings'    => array(
				'loading'          => __( 'Loading...', 'ai-share-summarize' ),
				'noData'           => __( 'No data yet.', 'ai-share-summarize' ),
				'loadMore'         => __( 'Load more', 'ai-share-summarize' ),
				/* translators: %1$s = current count, %2$s = total count */
				'showingOf'        => __( 'Showing %1$s of %2$s', 'ai-share-summarize' ),
				'clicks'           => __( 'Clicks', 'ai-share-summarize' ),
				'aiCrawls'         => __( 'AI Crawls', 'ai-share-summarize' ),
				'edit'             => __( 'Edit', 'ai-share-summarize' ),
				'allTime'          => __( 'All time', 'ai-share-summarize' ),
				'today'            => __( 'Today', 'ai-share-summarize' ),
				/* translators: %d = number of days */
				'lastDays'         => __( 'Last %d days', 'ai-share-summarize' ),
				'confirmDeleteAll' => __( 'Are you sure you want to delete ALL analytics data? This action cannot be undone.', 'ai-share-summarize' ),
				'deleting'         => __( 'Deleting...', 'ai-share-summarize' ),
				'deleteSuccess'    => __( 'All analytics data has been deleted.', 'ai-share-summarize' ),
				'deleteError'      => __( 'Error deleting data. Please try again.', 'ai-share-summarize' ),
				/* Translatable type labels for platforms table (v1.7.0) */
				'typeAI'           => __( 'AI', 'ai-share-summarize' ),
				'typeSocial'       => __( 'Social', 'ai-share-summarize' ),
				/* Comparison and export strings (v1.7.0) */
				'previousPeriod'   => __( 'Previous period', 'ai-share-summarize' ),
				'currentPeriod'    => __( 'Current period', 'ai-share-summarize' ),
				'exportError'      => __( 'Export failed. Please try again.', 'ai-share-summarize' ),
				/* translators: %1$s = range start, %2$s = range end, %3$s = total */
				'pagerRange'       => __( '%1$s–%2$s of %3$s', 'ai-share-summarize' ),
				'vs'               => __( 'vs', 'ai-share-summarize' ),
				/* Per-button breakdown in the platforms table (v2.6.0) */
				'showPosts'        => __( 'Show the posts it was clicked from', 'ai-share-summarize' ),
				'postsForButton'   => __( 'Posts it was clicked from', 'ai-share-summarize' ),
				'topPostsOnly'     => __( 'Only the 20 posts with the most clicks are listed. The CSV export has all of them.', 'ai-share-summarize' ),
				'loadError'        => __( 'Could not load the data. Please try again.', 'ai-share-summarize' ),
				/* Floating bar preview (v2.8.0) */
				/* translators: %1$s = buttons in sight, %2$s = buttons in the bar */
				'barVisible'       => __( 'In sight: %1$s of %2$s', 'ai-share-summarize' ),
				/* translators: %s = buttons inside the panel that the bar opens */
				'barMore'          => __( 'in the panel: %s', 'ai-share-summarize' ),
				/* translators: %s = height of the bar in pixels */
				'barTall'          => __( '%s px tall', 'ai-share-summarize' ),
				/* translators: %s = width of the bar in pixels */
				'barWide'          => __( '%s px wide', 'ai-share-summarize' ),
				'barNone'          => __( 'No buttons: the bar will not show.', 'ai-share-summarize' ),
				/* translators: %d = number of buttons inside the panel */
				'barMoreLabel'     => __( 'More (%d)', 'ai-share-summarize' ),
				/* translators: %d = number of buttons inside the panel */
				'barShareLabel'    => __( 'Share (%d)', 'ai-share-summarize' ),
				/* Block preview (v2.8.0). Same strings as the front-end toggle. */
				'compactLess'      => __( 'Less', 'ai-share-summarize' ),
				'compactClose'     => __( 'Close', 'ai-share-summarize' ),
				'blockUpdating'    => __( 'Updating…', 'ai-share-summarize' ),
				'blockError'       => __( 'Could not update the preview.', 'ai-share-summarize' ),
				'blockExpired'     => __( 'The session has expired. Reload the page to update the preview.', 'ai-share-summarize' ),
				'blockEmpty'       => __( 'No buttons to show: the block will not appear.', 'ai-share-summarize' ),
			),
			// Block preview (v2.8.0): the request that draws it, and the
			// front-end stylesheet its frame loads.
			'blockPreviewNonce' => wp_create_nonce( 'aiss_block_preview' ),
			'frontCss'          => esc_url_raw( add_query_arg( 'ver', AYUDAWP_AISS_VERSION, AYUDAWP_AISS_PLUGIN_URL . 'assets/css/ai-share-summarize.css' ) ),
		) );

		// Inline script for activation notice dismissal.
		wp_add_inline_script( 'jquery', '
			jQuery(document).ready(function($) {
				$(document).on("click", ".notice[data-notice=\"ayudawp-aiss-activation\"] .notice-dismiss", function() {
					$.post(ajaxurl, {
						action: "ayudawp_dismiss_activation_notice",
						nonce: "' . esc_js( wp_create_nonce( 'ayudawp_dismiss_notice' ) ) . '"
					});
				});

				$(document).on("click", ".aiss-dismiss-ai-error", function() {
					var btn = $(this);
					var notice = btn.closest(".aiss-ai-error-notice");
					$.post(ajaxurl, {
						action: "ayudawp_aiss_dismiss_ai_error",
						nonce:  btn.data("nonce")
					}, function(response) {
						if (response && response.success) {
							notice.slideUp(180, function() { notice.remove(); });
						}
					});
				});
			});
		' );
	}

	/**
	 * Add settings link to plugins page
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function ayudawp_add_settings_link( $links ) {
		// Add Analytics link.
		$analytics_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-share-summarize&tab=analytics' ) ) . '">' . esc_html__( 'Analytics', 'ai-share-summarize' ) . '</a>';
		array_unshift( $links, $analytics_link );

		// Add Settings link (appears first / leftmost).
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-share-summarize&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'ai-share-summarize' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Show activation notice
	 */
	public function ayudawp_activation_notice() {
		if ( get_current_screen()->id !== 'plugins' || get_option( 'ayudawp_aiss_activation_notice_dismissed' ) ) {
			return;
		}

		if ( get_transient( 'ayudawp_aiss_just_activated' ) ) {
			?>
			<div class="notice notice-success is-dismissible" data-notice="ayudawp-aiss-activation">
				<p>
					<strong><?php esc_html_e( 'AI Summaries & Share Buttons is active!', 'ai-share-summarize' ); ?></strong>
					<?php esc_html_e( 'You can now start getting traffic from the main social networks and artificial intelligences!', 'ai-share-summarize' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-share-summarize' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e( 'Go to settings page', 'ai-share-summarize' ); ?>
					</a>
				</p>
			</div>
			<?php
			delete_transient( 'ayudawp_aiss_just_activated' );
		}
	}

	/**
	 * Handle activation notice dismissal
	 */
	public function ayudawp_dismiss_activation_notice() {
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ayudawp_dismiss_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		// Defense in depth: the nonce is only printed on the settings screen
		// (gated by manage_options), but verify the capability too so the
		// option can never be flipped by a lower-privileged user.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Security check failed' );
		}

		update_option( 'ayudawp_aiss_activation_notice_dismissed', true );
		wp_die();
	}

	/**
	 * Add admin menu
	 */
	public function ayudawp_add_admin_menu() {
		add_options_page(
			esc_html__( 'AI Summaries & Share Buttons', 'ai-share-summarize' ),
			esc_html__( 'AI Summaries & Share Buttons', 'ai-share-summarize' ),
			'manage_options',
			'ai-share-summarize',
			array( $this, 'ayudawp_admin_page' )
		);
	}

	/**
	 * Initialize admin settings
	 */
	public function ayudawp_admin_init() {
		register_setting( 'ayudawp_aiss_settings', 'ayudawp_aiss_options', 'ayudawp_aiss_validate_options' );

		// The AI Summary tab posts to its own option with its own sanitizer
		// (v2.3.0), so each tab's form saves independently and can never
		// clobber the other tab's keys.
		register_setting( 'ayudawp_aiss_summary_settings', 'ayudawp_aiss_summary_options', 'ayudawp_aiss_validate_summary_options' );

		/*
		 * The buttons tab since 2.8.0: the catalog, then the two places where
		 * buttons appear, set up the same way. "In-content buttons" and
		 * "Floating bar" have the same six rows in the same order (where, which
		 * buttons and their order, how many in sight, appearance, what only that
		 * place has, and a preview), with the same controls and the same words.
		 * Until 2.7.x the bar was added on with its own words and controls, and
		 * the screen read like two plugins mixed together. They all post to the
		 * same option, so there is still a single Save button.
		 */
		add_settings_section(
			'ayudawp_aiss_main',
			esc_html__( 'Available buttons', 'ai-share-summarize' ),
			array( $this, 'ayudawp_catalog_section_callback' ),
			'ayudawp_aiss_settings'
		);

		add_settings_section(
			'ayudawp_aiss_placement',
			esc_html__( 'In-content buttons', 'ai-share-summarize' ),
			array( $this, 'ayudawp_section_callback' ),
			'ayudawp_aiss_settings'
		);

		add_settings_section(
			'ayudawp_aiss_floating',
			esc_html__( 'Floating bar', 'ai-share-summarize' ),
			array( $this, 'ayudawp_floating_section_callback' ),
			'ayudawp_aiss_settings',
			array(
				'before_section' => '<div class="%s">',
				'after_section'  => '</div>',
				'section_class'  => 'aiss-bar-section',
			)
		);

		add_settings_section(
			'ayudawp_aiss_prompt',
			esc_html__( 'What is sent to the AI assistants', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_settings'
		);

		add_settings_section(
			'ayudawp_aiss_advanced',
			esc_html__( 'Accounts and advanced', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_settings'
		);

		/*
		 * The AI Summary tab gets the same treatment as the buttons one
		 * (v2.5.0): grouped headings instead of thirteen flat rows, related
		 * settings merged into two-column rows, and a live preview of the block
		 * so the visual choices can be judged without saving.
		 */
		add_settings_section(
			'ayudawp_aiss_ai_summary',
			esc_html__( 'Generation', 'ai-share-summarize' ),
			array( $this, 'ayudawp_ai_summary_section_callback' ),
			'ayudawp_aiss_summary_settings'
		);

		add_settings_section(
			'ayudawp_aiss_summary_where',
			esc_html__( 'Where it appears', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_summary_settings'
		);

		add_settings_section(
			'ayudawp_aiss_summary_block',
			esc_html__( 'The summary block', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_summary_settings'
		);

		add_settings_section(
			'ayudawp_aiss_summary_visitor',
			esc_html__( 'Generation button for visitors', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_summary_settings'
		);

		add_settings_section(
			'ayudawp_aiss_summary_custom',
			esc_html__( 'Custom content inside the box', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_summary_settings'
		);

		$this->ayudawp_register_settings_fields();
	}

	/**
	 * Register settings fields
	 */
	private function ayudawp_register_settings_fields() {
		// field id => [ label, section, optional row class ].
		/*
		 * The two places where buttons appear get the same six rows, in the
		 * same order (v2.8.0). The label of "In sight" carries where it applies,
		 * which is what tells the two apart: phones only in the content,
		 * always in the bar.
		 */
		$on_phones = esc_html__( 'In sight', 'ai-share-summarize' ) . ' <span class="aiss-scope">' . esc_html__( 'On phones', 'ai-share-summarize' ) . '</span>';
		$always    = esc_html__( 'In sight', 'ai-share-summarize' ) . ' <span class="aiss-scope">' . esc_html__( 'Always', 'ai-share-summarize' ) . '</span>';

		$fields = array(
			'enabled_buttons' => array( esc_html__( 'Available buttons', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),

			'placements'      => array( esc_html__( 'Where they appear', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'content_buttons' => array( esc_html__( 'Buttons and order', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'mobile_compact'  => array( $on_phones, 'ayudawp_aiss_placement' ),
			'content_look'    => array( esc_html__( 'Appearance', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'layout'          => array( esc_html__( 'Layout', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'content_preview' => array( esc_html__( 'Preview', 'ai-share-summarize' ), 'ayudawp_aiss_placement', 'aiss-preview-row' ),

			// Every row but the first folds away while the bar is off, so a
			// site without it sees one table.
			'floating_bar'          => array( esc_html__( 'Where it appears', 'ai-share-summarize' ), 'ayudawp_aiss_floating' ),
			'floating_bar_buttons'  => array( esc_html__( 'Buttons and order', 'ai-share-summarize' ), 'ayudawp_aiss_floating', 'aiss-bar-extra' ),
			'floating_bar_compact'  => array( $always, 'ayudawp_aiss_floating', 'aiss-bar-extra' ),
			'floating_bar_look'     => array( esc_html__( 'Appearance', 'ai-share-summarize' ), 'ayudawp_aiss_floating', 'aiss-bar-extra' ),
			'floating_bar_behavior' => array( esc_html__( 'Behavior', 'ai-share-summarize' ), 'ayudawp_aiss_floating', 'aiss-bar-extra' ),
			'floating_bar_preview'  => array( esc_html__( 'Preview', 'ai-share-summarize' ), 'ayudawp_aiss_floating', 'aiss-bar-extra aiss-preview-row' ),

			'prompt'          => array( esc_html__( 'Prompt sent with every AI button', 'ai-share-summarize' ), 'ayudawp_aiss_prompt' ),

			'accounts'        => array( esc_html__( 'Accounts', 'ai-share-summarize' ), 'ayudawp_aiss_advanced' ),
			'output'          => array( esc_html__( 'Markup and campaign tracking', 'ai-share-summarize' ), 'ayudawp_aiss_advanced' ),
			'seo_integration' => array( esc_html__( 'SEO integration', 'ai-share-summarize' ), 'ayudawp_aiss_advanced' ),
			'data_retention'  => array( esc_html__( 'Analytics data', 'ai-share-summarize' ), 'ayudawp_aiss_advanced' ),
		);

		foreach ( $fields as $field_id => $field ) {
			add_settings_field(
				$field_id,
				$field[0],
				array( $this, 'ayudawp_' . $field_id . '_callback' ),
				'ayudawp_aiss_settings',
				$field[1],
				isset( $field[2] ) ? array( 'class' => $field[2] ) : array()
			);
		}

		// AI Summary fields, grouped since 2.5.0. field id => [ label, section ].
		$summary_fields = array(
			'ai_summary_mode'      => array( esc_html__( 'How summaries are generated', 'ai-share-summarize' ), 'ayudawp_aiss_ai_summary' ),
			'ai_summary_model'     => array( esc_html__( 'AI model', 'ai-share-summarize' ), 'ayudawp_aiss_ai_summary' ),

			'ai_summary_placement' => array( esc_html__( 'Content types and position', 'ai-share-summarize' ), 'ayudawp_aiss_summary_where' ),

			'ai_summary_look'      => array( esc_html__( 'Appearance', 'ai-share-summarize' ), 'ayudawp_aiss_summary_block' ),
			'ai_summary_wording'   => array( esc_html__( 'Label and format', 'ai-share-summarize' ), 'ayudawp_aiss_summary_block' ),

			'ai_summary_visitor'   => array( esc_html__( 'Let visitors generate it', 'ai-share-summarize' ), 'ayudawp_aiss_summary_visitor' ),

			'ai_summary_custom'    => array( esc_html__( 'Content below the summary', 'ai-share-summarize' ), 'ayudawp_aiss_summary_custom' ),
		);

		foreach ( $summary_fields as $field_id => $field ) {
			add_settings_field(
				$field_id,
				$field[0],
				array( $this, 'ayudawp_' . $field_id . '_callback' ),
				'ayudawp_aiss_summary_settings',
				$field[1]
			);
		}
	}

	/**
	 * Admin page content with tabs
	 */
	public function ayudawp_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'analytics'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which settings tab to paint, not acting on anything; the value is only compared against a fixed list of tab slugs.
		$page_url   = admin_url( 'options-general.php?page=ai-share-summarize' );

		// Dynamic page title based on active tab. The buttons tab keeps the
		// legacy 'settings' slug so bookmarked/external links don't break;
		// only its visible label says "Share Buttons".
		$page_title = 'AI Summaries & Share Buttons';
		if ( 'analytics' === $active_tab ) {
			$page_title .= ' — ' . esc_html__( 'Analytics', 'ai-share-summarize' );
		} elseif ( 'ai-summary' === $active_tab ) {
			$page_title .= ' — ' . esc_html__( 'AI Summary', 'ai-share-summarize' );
		} elseif ( 'settings' === $active_tab ) {
			$page_title .= ' — ' . esc_html__( 'Share Buttons', 'ai-share-summarize' );
		}
		?>
		<div class="wrap aiss-wrap">
			<h1><span class="dashicons dashicons-format-status aiss-page-icon"></span> <?php echo esc_html( $page_title ); ?></h1>

			<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $page_url ); ?>" class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Analytics', 'ai-share-summarize' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $page_url ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Share Buttons', 'ai-share-summarize' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'ai-summary', $page_url ) ); ?>" class="nav-tab <?php echo 'ai-summary' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'AI Summary', 'ai-share-summarize' ); ?>
			</a>
			</nav>

			<?php
			/*
			 * The settings tabs keep content and promo side by side, as since
			 * 2.5.0: the shape Vigilant and Widget Visibility Control use, a
			 * column of content and a 300px rail that folds underneath below
			 * 1200px. Analytics goes full width with the banner as a strip at
			 * the bottom (v2.7.1), as VigIA's data panel does: the rail left the
			 * charts and the two tables in a narrow column. The main column stays
			 * in both, because the analytics tables decide whether they fit side
			 * by side with a container query on it.
			 */
			$is_analytics = 'analytics' === $active_tab;
			$layout_class = $is_analytics ? 'aiss-layout aiss-layout--full' : 'aiss-layout';
			$promo_banner = new AyudaWP_AISS_Promo_Banner( 'aiss' );
			?>
			<div class="<?php echo esc_attr( $layout_class ); ?>">
				<div class="aiss-layout-main">
					<?php
					if ( $is_analytics ) {
						AyudaWP_AISS_Analytics_Page::ayudawp_render();
					} elseif ( 'ai-summary' === $active_tab ) {
						$this->ayudawp_render_summary_tab();
					} else {
						$this->ayudawp_render_settings_tab();
					}
					?>
				</div>
				<?php if ( ! $is_analytics ) : ?>
				<aside class="aiss-layout-side">
					<?php
					// Promo banner - random rotation of AyudaWP services.
					$promo_banner->render( 'vertical' );
					?>
				</aside>
				<?php endif; ?>
			</div>
			<?php
			if ( $is_analytics ) {
				$promo_banner->render( 'horizontal' );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab content (share buttons + general)
	 */
	private function ayudawp_render_settings_tab() {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'ayudawp_aiss_settings' );
			do_settings_sections( 'ayudawp_aiss_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the AI Summary tab content (v2.3.0)
	 *
	 * Dedicated tab with its own option and Save button, so summary settings
	 * save independently from the buttons/general form. Deep-linkable at
	 * options-general.php?page=ai-share-summarize&tab=ai-summary (replaces
	 * the pre-2.3.0 #ayudawp-aiss-ai-summary in-page anchor).
	 */
	private function ayudawp_render_summary_tab() {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'ayudawp_aiss_summary_settings' );
			do_settings_sections( 'ayudawp_aiss_summary_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	// ==========================================
	// Buttons tab (redefined in v2.8.0)
	// ==========================================

	/**
	 * "Available buttons" section: what it is for (v2.8.0)
	 */
	public function ayudawp_catalog_section_callback() {
		echo '<p>' . esc_html__( 'The ones your site offers. The two sections below pick from here.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * "In-content buttons" section: where they go, the shortcode included (v2.8.0)
	 */
	public function ayudawp_section_callback() {
		/* translators: Plugin page URL. Change wordpress.org to your locale if needed (e.g., es.wordpress.org) */
		$plugin_url = __( 'https://wordpress.org/plugins/ai-share-summarize/', 'ai-share-summarize' );

		echo '<p>' . sprintf(
			/* translators: %s is the shortcode. */
			esc_html__( 'Before or after the text of each post, and wherever you place the %s shortcode.', 'ai-share-summarize' ),
			'<code>[ayudawp_share_buttons]</code>'
		);
		echo ' <span class="ayudawp-info-link">(' . esc_html__( 'You can also customize display using shortcodes as described in the', 'ai-share-summarize' ) . ' <a href="' . esc_url( $plugin_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'plugin page', 'ai-share-summarize' ) . '</a>)</span>';
		echo '</p>';
	}

	/**
	 * A button as a small chip: its colour dot with the icon, and its name (v2.8.0)
	 *
	 * No style of its own: the catalog and the order lists are lists of
	 * buttons, not samples, and the preview at the end of each section is where
	 * the look shows. Until 2.7.x each one drew the real button in the block's
	 * style, so the catalog changed size and colour with every style choice.
	 *
	 * @param string $key   Button key.
	 * @param string $label Button name.
	 * @param int    $size  Icon size in pixels.
	 */
	private function ayudawp_render_chip_face( $key, $label, $size = 12 ) {
		$colors = ayudawp_aiss_get_platform_colors();
		$color  = isset( $colors[ $key ] ) ? $colors[ $key ] : '#50575e';

		echo '<span class="aiss-chip-dot" style="--aiss-chip:' . esc_attr( $color ) . '" aria-hidden="true">'
			. wp_kses( AyudaWP_AISS_Icons::ayudawp_get_button_icon( $key, $size ), ayudawp_aiss_kses_svg() ) . '</span>';
		echo '<span class="aiss-chip-name">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Available buttons: the site's catalog (v2.8.0)
	 *
	 * Narrow chips grouped by kind, with two short notes below for the buttons
	 * that behave unlike the rest. What is ticked here shows up at once in the
	 * order lists of both sections, before saving.
	 */
	public function ayudawp_enabled_buttons_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$enabled = isset( $options['enabled_buttons'] ) && is_array( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		$buttons = ayudawp_aiss_get_available_buttons();
		$actions = ayudawp_aiss_get_action_buttons();
		$google  = array( 'google_preferred' );

		$groups = array(
			array(
				'label' => __( 'Social networks', 'ai-share-summarize' ),
				'keys'  => array_values( array_diff( ayudawp_aiss_get_social_buttons(), $actions, $google ) ),
			),
			array(
				'label' => __( 'AI assistants', 'ai-share-summarize' ),
				'keys'  => ayudawp_aiss_get_ai_buttons(),
			),
			array(
				'label' => __( 'Browser actions', 'ai-share-summarize' ),
				'keys'  => $actions,
			),
			array(
				'label' => __( 'Google', 'ai-share-summarize' ),
				'keys'  => $google,
			),
		);

		echo '<div class="aiss-catalog">';
		foreach ( $groups as $group ) {
			echo '<div class="aiss-catalog-group"><p class="aiss-catalog-title">' . esc_html( $group['label'] ) . '</p><div class="aiss-chips">';
			foreach ( $group['keys'] as $key ) {
				if ( ! isset( $buttons[ $key ] ) ) {
					continue;
				}
				echo '<label class="aiss-chip" data-key="' . esc_attr( $key ) . '">';
				echo '<input type="checkbox" name="ayudawp_aiss_options[enabled_buttons][]" value="' . esc_attr( $key ) . '" '
					. checked( in_array( $key, $enabled, true ), true, false ) . '>';
				$this->ayudawp_render_chip_face( $key, $buttons[ $key ] );
				echo '</label>';
			}
			echo '</div></div>';
		}
		echo '</div>';

		/*
		 * The two buttons that behave unlike the rest get one line each, side
		 * by side, as plain descriptions. Until 2.7.x they were blue notice
		 * boxes of a paragraph each.
		 */
		echo '<div class="aiss-catalog-notes">';

		echo '<p class="description aiss-note">';
		$this->ayudawp_render_note_dot( 'web_share' );
		echo '<span><strong>' . esc_html__( 'Native share', 'ai-share-summarize' ) . '</strong> '
			. esc_html__( 'opens the share menu of the device. It only shows over HTTPS and in browsers that support it; where it cannot, it copies the link.', 'ai-share-summarize' )
			. '</span></p>';

		echo '<p class="description aiss-note">';
		$this->ayudawp_render_note_dot( 'google_preferred' );
		echo '<span><strong>' . esc_html__( 'Google preferred source', 'ai-share-summarize' ) . '</strong> '
			. esc_html__( 'takes readers to the Google tool that marks your site as a preferred source.', 'ai-share-summarize' ) . ' ';

		$google_domain = ayudawp_aiss_get_google_preferred_domain();
		if ( '' === $google_domain ) {
			echo '<strong>' . esc_html__( 'This site cannot use it, so it will not be shown:', 'ai-share-summarize' ) . '</strong> ';
			$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
			if ( (int) get_option( 'blog_public', 1 ) < 1 ) {
				echo esc_html__( 'the site asks search engines not to index it (Settings > Reading).', 'ai-share-summarize' );
			} elseif ( is_string( $home_path ) && '' !== trim( $home_path, '/' ) ) {
				echo esc_html__( 'Google only accepts a domain or a subdomain, and the address of this site has a path.', 'ai-share-summarize' );
			} else {
				echo esc_html__( 'a filter on this site hides it.', 'ai-share-summarize' );
			}
		} else {
			/* translators: %s is the domain sent to Google, e.g. example.com */
			echo esc_html( sprintf( __( 'Domain: %s', 'ai-share-summarize' ), $google_domain ) );
		}
		echo '</span></p>';

		echo '</div>';
	}

	/**
	 * The small icon of a button in front of its note (v2.8.0)
	 *
	 * @param string $key Button key.
	 */
	private function ayudawp_render_note_dot( $key ) {
		$colors = ayudawp_aiss_get_platform_colors();
		$color  = isset( $colors[ $key ] ) ? $colors[ $key ] : '#50575e';

		echo '<span class="aiss-chip-dot is-small" style="--aiss-chip:' . esc_attr( $color ) . '" aria-hidden="true">'
			. wp_kses( AyudaWP_AISS_Icons::ayudawp_get_button_icon( $key, 10 ), ayudawp_aiss_kses_svg() ) . '</span>';
	}

	/**
	 * A group of native radios, stacked with <br> or in a row (v2.8.0)
	 *
	 * Core makes labels inside a fieldset of .form-table inline-block
	 * (wp-admin/css/forms.css:966) and stacks them with a <br>, as on Settings >
	 * Reading; the stacked lists here do the same, and the rows of the
	 * appearance leave them side by side. The fieldset carries the name of the
	 * group for screen readers: a legend, or the id of a visible label.
	 *
	 * @param string $field   Option key.
	 * @param array  $choices Value => label, the default first.
	 * @param string $current Current value.
	 * @param string $legend  Name of the group, for screen readers.
	 * @param bool   $inline  Side by side instead of one per line.
	 * @param string $labelledby Id of a visible label, instead of the legend.
	 */
	private function ayudawp_render_radios( $field, $choices, $current, $legend, $inline = false, $labelledby = '' ) {
		echo '<fieldset class="aiss-radios' . ( $inline ? ' is-inline' : '' ) . '" data-field="' . esc_attr( $field ) . '"'
			. ( '' !== $labelledby ? ' aria-labelledby="' . esc_attr( $labelledby ) . '"' : '' ) . '>';
		if ( '' === $labelledby ) {
			echo '<legend class="screen-reader-text">' . esc_html( $legend ) . '</legend>';
		}
		$first = true;
		foreach ( $choices as $value => $label ) {
			if ( ! $inline && ! $first ) {
				echo '<br>';
			}
			$first = false;
			echo '<label><input type="radio" name="ayudawp_aiss_options[' . esc_attr( $field ) . ']" value="' . esc_attr( (string) $value ) . '" '
				. checked( (string) $current, (string) $value, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset>';
	}

	/**
	 * The order of the groups, one line (v2.8.0)
	 *
	 * @param string $field   Option key.
	 * @param array  $choices Value => label.
	 * @param string $current Current value.
	 */
	private function ayudawp_render_groups( $field, $choices, $current ) {
		$id = 'aiss-groups-' . str_replace( '_', '-', $field );
		echo '<div class="aiss-groups"><span class="aiss-sub" id="' . esc_attr( $id ) . '">' . esc_html__( 'Groups:', 'ai-share-summarize' ) . '</span>';
		$this->ayudawp_render_radios( $field, $choices, $current, __( 'Order of the groups', 'ai-share-summarize' ), true, $id );
		echo '</div>';
	}

	/**
	 * Two columns of buttons to tick and drag within their group (v2.8.0)
	 *
	 * One chip per button of the group, available or not, so ticking one in
	 * the catalog makes it appear here before saving; the ones not available
	 * are hidden. Every chip posts its place in the order, ticked or not, so
	 * unticking one does not lose it, and a box that says whether it is chosen;
	 * the box only shows while the section is set to choose.
	 *
	 * @param string     $section 'content' or 'bar'.
	 * @param array      $enabled Available buttons.
	 * @param array      $orders  Group => saved custom order of that group.
	 * @param array      $picked  Buttons ticked.
	 * @param bool       $picking Whether the section is set to choose.
	 * @param string     $google_place Where the Google button goes.
	 */
	private function ayudawp_render_order_board( $section, $enabled, $orders, $picked, $picking, $google_place ) {
		$all     = ayudawp_aiss_get_available_buttons();
		$is_bar  = 'bar' === $section;
		$names   = array(
			'picked' => $is_bar ? 'floating_bar_picked' : 'content_buttons_picked',
			'ai'     => $is_bar ? 'floating_bar_order_ai' : 'button_custom_order_ai',
			'social' => $is_bar ? 'floating_bar_order_social' : 'button_custom_order_social',
		);
		$columns = array(
			'ai'     => array(
				'label' => __( 'AI assistants', 'ai-share-summarize' ),
				'keys'  => ayudawp_aiss_get_ai_buttons(),
			),
			'social' => array(
				'label' => __( 'Social networks, actions and Google', 'ai-share-summarize' ),
				'keys'  => ayudawp_aiss_get_social_buttons(),
			),
		);

		echo '<div class="aiss-order-board' . ( $picking ? ' is-picking' : '' ) . '" data-section="' . esc_attr( $section ) . '">';

		foreach ( $columns as $group => $column ) {
			// The saved order, then the available ones as the front end adds
			// them, then the rest of the group.
			$order = self::ayudawp_build_sorted_list( isset( $orders[ $group ] ) ? (array) $orders[ $group ] : array(), $enabled, $column['keys'], $all );
			$order = array_merge( $order, array_diff( $column['keys'], $order ) );

			echo '<div class="aiss-order-col"><p class="aiss-order-title">' . esc_html( $column['label'] ) . '</p>';
			echo '<ul class="ayudawp-sortable-list aiss-order-chips" data-group="' . esc_attr( $group ) . '">';

			foreach ( $order as $key ) {
				if ( ! isset( $all[ $key ] ) ) {
					continue;
				}
				$available = in_array( $key, $enabled, true );
				echo '<li class="aiss-order-chip" data-key="' . esc_attr( $key ) . '"' . ( $available ? '' : ' hidden' ) . '>';
				echo '<span class="dashicons dashicons-menu aiss-chip-grip" aria-hidden="true"></span>';
				echo '<input type="checkbox" class="aiss-order-pick" name="ayudawp_aiss_options[' . esc_attr( $names['picked'] ) . '][]" value="' . esc_attr( $key ) . '" '
					. checked( in_array( $key, $picked, true ), true, false ) . ' aria-label="' . esc_attr( $all[ $key ] ) . '">';
				$this->ayudawp_render_chip_face( $key, $all[ $key ] );
				echo '<input type="hidden" name="ayudawp_aiss_options[' . esc_attr( $names[ $group ] ) . '][]" value="' . esc_attr( $key ) . '">';
				echo '</li>';
			}

			echo '</ul>';

			if ( 'social' === $group ) {
				$google_notes = array(
					'section' => __( 'Google preferred source goes in a section of its own, at the end of the block, whatever its place here.', 'ai-share-summarize' ),
					'outside' => __( 'Google preferred source goes outside the block, only where you place its shortcode.', 'ai-share-summarize' ),
				);
				foreach ( $google_notes as $place => $note ) {
					echo '<p class="description aiss-google-order-note" data-placement="' . esc_attr( $place ) . '"'
						. ( $place === $google_place && in_array( 'google_preferred', $enabled, true ) ? '' : ' hidden' ) . '>' . esc_html( $note ) . '</p>';
				}
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * The order of the groups, by the words of each section (v2.8.0)
	 *
	 * @return array Value => label for the content's `button_order`.
	 */
	private static function ayudawp_group_choices() {
		return array(
			'ai'     => __( 'AI first (default)', 'ai-share-summarize' ),
			'social' => __( 'Social networks first', 'ai-share-summarize' ),
			'mixed'  => __( 'Mixed, one from each', 'ai-share-summarize' ),
		);
	}

	/**
	 * In-content buttons: which ones and their order (v2.8.0)
	 *
	 * One control for both, live: what is ticked in the catalog shows up here
	 * at once to be ordered, which until 2.7.x needed a save first.
	 */
	public function ayudawp_content_buttons_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$enabled = isset( $options['enabled_buttons'] ) && is_array( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		$mode    = isset( $options['content_buttons_mode'] ) && 'pick' === $options['content_buttons_mode'] ? 'pick' : 'all';
		$picked  = isset( $options['content_buttons_picked'] ) && is_array( $options['content_buttons_picked'] ) ? $options['content_buttons_picked'] : $enabled;
		$order   = isset( $options['button_order'] ) ? (string) $options['button_order'] : 'ayudawp_ai_first';

		$this->ayudawp_render_radios(
			'content_buttons_mode',
			array(
				'all'  => __( 'All the available ones (default)', 'ai-share-summarize' ),
				'pick' => __( 'Choose', 'ai-share-summarize' ),
			),
			$mode,
			__( 'Buttons and order', 'ai-share-summarize' )
		);

		// The content keeps the values `button_order` always had.
		$groups = array();
		foreach ( self::ayudawp_group_choices() as $value => $label ) {
			$groups[ 'ayudawp_' . $value . ( 'mixed' === $value ? '' : '_first' ) ] = $label;
		}
		$this->ayudawp_render_groups( 'button_order', $groups, $order );

		$this->ayudawp_render_order_board(
			'content',
			$enabled,
			array(
				'ai'     => isset( $options['button_custom_order_ai'] ) ? $options['button_custom_order_ai'] : array(),
				'social' => isset( $options['button_custom_order_social'] ) ? $options['button_custom_order_social'] : array(),
			),
			$picked,
			'pick' === $mode,
			ayudawp_aiss_get_google_preferred_placement( $options )
		);

		echo '<p class="description">' . esc_html__( 'Drag to order within each group. What you tick under "Available buttons" shows up here at once, before saving.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * How many in sight: the three choices of both sections (v2.8.0)
	 *
	 * @param string $field   Mode key.
	 * @param string $count   Key of the number of buttons in sight.
	 * @param string $mode    Current mode.
	 * @param int    $visible Current number.
	 * @param string $legend  Name of the group for screen readers.
	 */
	private function ayudawp_render_compact( $field, $count, $mode, $visible, $legend ) {
		echo '<fieldset class="aiss-radios" data-field="' . esc_attr( $field ) . '"><legend class="screen-reader-text">' . esc_html( $legend ) . '</legend>';
		echo '<label><input type="radio" name="ayudawp_aiss_options[' . esc_attr( $field ) . ']" value="partial" ' . checked( $mode, 'partial', false ) . '> '
			. esc_html__( 'A few and More:', 'ai-share-summarize' ) . '</label> ';
		echo '<input type="number" class="small-text aiss-compact-count" min="1" max="5" step="1" name="ayudawp_aiss_options[' . esc_attr( $count ) . ']" value="' . esc_attr( (string) $visible ) . '" aria-label="' . esc_attr__( 'Buttons in sight before More', 'ai-share-summarize' ) . '"> '
			. esc_html__( '(default)', 'ai-share-summarize' ) . '<br>';
		echo '<label><input type="radio" name="ayudawp_aiss_options[' . esc_attr( $field ) . ']" value="single" ' . checked( $mode, 'single', false ) . '> '
			. esc_html__( 'A single Share button that opens the rest', 'ai-share-summarize' ) . '</label><br>';
		echo '<label><input type="radio" name="ayudawp_aiss_options[' . esc_attr( $field ) . ']" value="disabled" ' . checked( $mode, 'disabled', false ) . '> '
			. esc_html__( 'All', 'ai-share-summarize' ) . '</label>';
		echo '</fieldset>';
	}

	/**
	 * In-content buttons: how many in sight on phones (v2.5.0, v2.8.0)
	 */
	public function ayudawp_mobile_compact_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$mode    = isset( $options['mobile_compact'] ) && in_array( $options['mobile_compact'], ayudawp_aiss_get_floating_bar_choices( 'compact' ), true ) ? $options['mobile_compact'] : 'partial';
		$visible = isset( $options['mobile_compact_visible'] ) ? max( 1, min( 5, (int) $options['mobile_compact_visible'] ) ) : 3;

		$this->ayudawp_render_compact( 'mobile_compact', 'mobile_compact_visible', $mode, $visible, __( 'In sight on phones', 'ai-share-summarize' ) );

		echo '<p class="description">' . esc_html__( 'On desktop all of them show. The ones in sight are the first in the order above.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * The rows of an appearance: one line per setting (v2.8.0)
	 *
	 * @param array  $rows   Field => [ label, choices, current ].
	 * @param string $prefix Prefix of the label ids.
	 */
	private function ayudawp_render_look_rows( $rows, $prefix ) {
		foreach ( $rows as $field => $row ) {
			$id = $prefix . '-' . str_replace( '_', '-', $field );
			echo '<div class="aiss-look-row"><span class="aiss-look-label" id="' . esc_attr( $id ) . '">' . esc_html( $row[0] ) . '</span>';
			$this->ayudawp_render_radios( $field, $row[1], $row[2], $row[0], true, $id );
			echo '</div>';
		}
	}

	/**
	 * The choices of each setting of the look, the default first (v2.8.0)
	 *
	 * @param string $setting 'colors', 'corners', 'size' or 'dark'.
	 * @param bool   $bar     Whether the list is for the floating bar.
	 * @return array Value => label.
	 */
	private static function ayudawp_look_labels( $setting, $bar = false ) {
		$labels = array(
			'colors'  => array(
				'minimal' => __( 'Minimal (default)', 'ai-share-summarize' ),
				'brand'   => __( 'Brand colors', 'ai-share-summarize' ),
				'outline' => __( 'Outline', 'ai-share-summarize' ),
				'dark'    => __( 'For dark backgrounds', 'ai-share-summarize' ),
				'custom'  => __( 'Custom colors', 'ai-share-summarize' ),
			),
			'corners' => array(
				'rounded' => __( 'Rounded (default)', 'ai-share-summarize' ),
				'circle'  => __( 'Circular', 'ai-share-summarize' ),
				'square'  => __( 'Square', 'ai-share-summarize' ),
			),
			'size'    => array(
				'normal'  => __( 'Normal (default)', 'ai-share-summarize' ),
				'compact' => __( 'Compact', 'ai-share-summarize' ),
				'large'   => __( 'Large', 'ai-share-summarize' ),
				'fluid'   => __( 'Fluid: fill the available width', 'ai-share-summarize' ),
			),
			'dark'    => array(
				'disabled' => __( 'Disabled (default)', 'ai-share-summarize' ),
				'auto'     => __( 'Automatic', 'ai-share-summarize' ),
			),
		);

		$list = isset( $labels[ $setting ] ) ? $labels[ $setting ] : array();
		if ( $bar && 'size' === $setting ) {
			unset( $list['fluid'] );
		}

		return $list;
	}

	/**
	 * In-content buttons: appearance (v2.8.0)
	 *
	 * One line per setting, label on the left and the radios in a row. "Icons
	 * only" is now what a button carries, not a colour, and the corners apply
	 * to every button.
	 */
	public function ayudawp_content_look_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$look    = ayudawp_aiss_get_button_look( $options );

		echo '<div class="aiss-look">';
		$this->ayudawp_render_look_rows(
			array(
				'button_content' => array(
					__( 'Button', 'ai-share-summarize' ),
					array(
						'text' => __( 'Text only (default)', 'ai-share-summarize' ),
						'both' => __( 'Icon and text', 'ai-share-summarize' ),
						'icon' => __( 'Icon only', 'ai-share-summarize' ),
					),
					$look['content'],
				),
				'button_colors'  => array( __( 'Color', 'ai-share-summarize' ), self::ayudawp_look_labels( 'colors' ), $look['colors'] ),
			),
			'aiss-look'
		);

		// Custom colour pickers: shown while the content or the bar uses them.
		$custom_bg   = isset( $options['custom_color_bg'] ) ? $options['custom_color_bg'] : '#333333';
		$custom_text = isset( $options['custom_color_text'] ) ? $options['custom_color_text'] : '#ffffff';
		$bar_look    = ayudawp_aiss_get_floating_bar_look( $options );
		$show_custom = 'custom' === $look['colors'] || 'custom' === $bar_look['colors'];
		?>
		<div class="ayudawp-color-picker-row" id="ayudawp-custom-colors"<?php echo $show_custom ? '' : ' hidden'; ?>>
			<div>
				<label for="ayudawp_custom_bg"><?php esc_html_e( 'Background color:', 'ai-share-summarize' ); ?></label>
				<input type="text" id="ayudawp_custom_bg" name="ayudawp_aiss_options[custom_color_bg]" value="<?php echo esc_attr( $custom_bg ); ?>" class="ayudawp-color-field" data-default-color="#333333">
			</div>
			<div>
				<label for="ayudawp_custom_text"><?php esc_html_e( 'Text color:', 'ai-share-summarize' ); ?></label>
				<input type="text" id="ayudawp_custom_text" name="ayudawp_aiss_options[custom_color_text]" value="<?php echo esc_attr( $custom_text ); ?>" class="ayudawp-color-field" data-default-color="#ffffff">
			</div>
		</div>
		<?php
		$this->ayudawp_render_look_rows(
			array(
				'button_corners'       => array( __( 'Corners', 'ai-share-summarize' ), self::ayudawp_look_labels( 'corners' ), $look['corners'] ),
				'button_size'          => array( __( 'Size', 'ai-share-summarize' ), self::ayudawp_look_labels( 'size' ), $look['size'] ),
				'dark_mode_adaptation' => array( __( 'Dark backgrounds', 'ai-share-summarize' ), self::ayudawp_look_labels( 'dark' ), $look['dark'] ),
			),
			'aiss-look'
		);
		echo '</div>';

		echo '<p class="description">' . esc_html__( 'Automatic measures the real background behind the buttons and adapts the ones that would disappear on it. It costs a small script on the page.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * In-content buttons: what only the content has (v2.8.0)
	 *
	 * Alignment, separator lines, the titles and where the Google button goes,
	 * which until 2.7.x were spread over three rows of two sections.
	 */
	public function ayudawp_layout_callback() {
		$options          = get_option( 'ayudawp_aiss_options' );
		$alignment        = isset( $options['button_alignment'] ) ? $options['button_alignment'] : 'left';
		$separator_top    = isset( $options['separator_top'] ) ? $options['separator_top'] : true;
		$separator_bottom = isset( $options['separator_bottom'] ) ? $options['separator_bottom'] : true;
		$text             = isset( $options['title_text'] ) ? $options['title_text'] : '';
		$style            = isset( $options['title_style'] ) ? $options['title_style'] : 'span';
		$ai_title         = isset( $options['ai_section_title'] ) ? $options['ai_section_title'] : '';
		$social_title     = isset( $options['social_section_title'] ) ? $options['social_section_title'] : '';
		$google_place     = ayudawp_aiss_get_google_preferred_placement( $options );
		$google_title     = isset( $options['google_section_title'] ) ? (string) $options['google_section_title'] : '';

		$styles = array(
			'span' => __( 'Span, plain text (default)', 'ai-share-summarize' ),
			'h3'   => __( 'Heading 3 (h3)', 'ai-share-summarize' ),
			'h4'   => __( 'Heading 4 (h4)', 'ai-share-summarize' ),
			'h5'   => __( 'Heading 5 (h5)', 'ai-share-summarize' ),
			'h6'   => __( 'Heading 6 (h6)', 'ai-share-summarize' ),
		);
		$google_opts = array(
			'social'  => __( 'With the social networks', 'ai-share-summarize' ),
			'section' => __( 'In a section of its own, at the end of the block', 'ai-share-summarize' ),
			'outside' => __( 'Outside the block, only where you place the shortcode', 'ai-share-summarize' ),
		);
		?>
		<div class="aiss-two-col">
			<div>
				<span class="aiss-sub" id="aiss-layout-align"><?php esc_html_e( 'Alignment', 'ai-share-summarize' ); ?></span>
				<?php
				$this->ayudawp_render_radios(
					'button_alignment',
					array(
						'left'   => __( 'Left (default)', 'ai-share-summarize' ),
						'center' => __( 'Centered', 'ai-share-summarize' ),
					),
					$alignment,
					__( 'Alignment', 'ai-share-summarize' ),
					false,
					'aiss-layout-align'
				);
				?>
				<p class="description"><?php esc_html_e( 'Alignment does not apply to phones, where the buttons adapt on their own.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<span class="aiss-sub"><?php esc_html_e( 'Separator lines', 'ai-share-summarize' ); ?></span>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_options[separator_top]" value="1" <?php checked( $separator_top, true ); ?>>
					<?php esc_html_e( 'Line above the block', 'ai-share-summarize' ); ?>
				</label>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_options[separator_bottom]" value="1" <?php checked( $separator_bottom, true ); ?>>
					<?php esc_html_e( 'Line below the block', 'ai-share-summarize' ); ?>
				</label>
			</div>
			<div>
				<label class="aiss-sub" for="aiss-title-text"><?php esc_html_e( 'Title above the buttons', 'ai-share-summarize' ); ?></label>
				<input type="text" id="aiss-title-text" class="regular-text aiss-full"
					name="ayudawp_aiss_options[title_text]" value="<?php echo esc_attr( $text ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Share this', 'ai-share-summarize' ); ?>">
				<label class="aiss-sub aiss-sub-next" for="aiss-title-style"><?php esc_html_e( 'HTML element', 'ai-share-summarize' ); ?></label>
				<select id="aiss-title-style" class="aiss-full" name="ayudawp_aiss_options[title_style]">
					<?php foreach ( $styles as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $style, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Leave it empty for no title. Ignored when section titles are in use. The element applies to the section titles too.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<label class="aiss-sub" for="ayudawp_ai_section_title"><?php esc_html_e( 'AI section title', 'ai-share-summarize' ); ?></label>
				<input type="text" id="ayudawp_ai_section_title" class="regular-text aiss-full" name="ayudawp_aiss_options[ai_section_title]" value="<?php echo esc_attr( $ai_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Summarize with AI', 'ai-share-summarize' ); ?>">
				<label class="aiss-sub aiss-sub-next" for="ayudawp_social_section_title"><?php esc_html_e( 'Social section title', 'ai-share-summarize' ); ?></label>
				<input type="text" id="ayudawp_social_section_title" class="regular-text aiss-full" name="ayudawp_aiss_options[social_section_title]" value="<?php echo esc_attr( $social_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Share on social media', 'ai-share-summarize' ); ?>">
				<p class="description"><?php esc_html_e( 'Optional. When set, each group gets its own heading and the general title above is hidden. Leave both empty to use only the general title. Does not apply to Mixed order.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<label class="aiss-sub" for="aiss-google-placement"><?php esc_html_e( 'Where the Google button goes', 'ai-share-summarize' ); ?></label>
				<select id="aiss-google-placement" class="aiss-full" name="ayudawp_aiss_options[google_preferred_placement]">
					<?php foreach ( $google_opts as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $google_place, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="aiss-google-title-field"<?php echo 'section' === $google_place ? '' : ' hidden'; ?>>
					<label class="aiss-sub aiss-sub-next" for="aiss-google-section-title"><?php esc_html_e( 'Title of its section (optional)', 'ai-share-summarize' ); ?></label>
					<input type="text" id="aiss-google-section-title" class="regular-text aiss-full" name="ayudawp_aiss_options[google_section_title]" value="<?php echo esc_attr( $google_title ); ?>" placeholder="<?php esc_attr_e( 'Follow us on Google', 'ai-share-summarize' ); ?>">
				</div>
				<p class="description"><?php esc_html_e( 'Without a title, a thin line sets its section apart. Outside the block, place it with this shortcode wherever you want it:', 'ai-share-summarize' ); ?> <code>[ayudawp_share_buttons buttons="google_preferred"]</code></p>
			</div>
		</div>
		<?php
	}

	/**
	 * In-content buttons: the preview, last row of the section (v2.8.0)
	 *
	 * The block is drawn by the server from the unsaved form, inside an iframe
	 * of a real content width, scaled to fit, so a phone is a phone and the
	 * compact mode works as it does on the site. Hidden until the script fills
	 * it: without JS there is nothing to show, and the row folds away.
	 */
	public function ayudawp_content_preview_callback() {
		echo '<div class="aiss-block-dock" hidden>';
		echo '<div class="aiss-block-dock-head">';

		// Not settings: they only pick which sample to draw, and options.php
		// ignores fields outside the plugin's option.
		echo '<span class="aiss-preview-views" role="radiogroup" aria-label="' . esc_attr__( 'Preview', 'ai-share-summarize' ) . '">';
		echo '<label><input type="radio" name="aiss_block_view" value="desktop" checked> ' . esc_html__( 'Desktop', 'ai-share-summarize' ) . '</label>';
		echo '<label><input type="radio" name="aiss_block_view" value="phone"> ' . esc_html__( 'Phone', 'ai-share-summarize' ) . '</label>';
		echo '</span>';
		echo '<label class="aiss-block-dark"><input type="checkbox" name="aiss_block_dark" value="1"> ' . esc_html__( 'Dark background', 'ai-share-summarize' ) . '</label>';
		echo '<span class="aiss-block-status" aria-live="polite"></span>';
		echo '</div>';
		/*
		 * Sandboxed with same origin and no scripts (v2.8.0): nothing inside the
		 * sample runs, and this page can still reach into it to open the compact
		 * mode, add the dark background class and reveal the native share.
		 */
		echo '<div class="aiss-block-frame" aria-hidden="true"><iframe class="aiss-block-iframe" sandbox="allow-same-origin" title="' . esc_attr__( 'Preview of the share buttons', 'ai-share-summarize' ) . '" tabindex="-1"></iframe></div>';
		echo '<p class="description">' . esc_html__( 'The block as these settings draw it, before you save. Your theme may change the fonts and the spacing.', 'ai-share-summarize' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Floating bar section: what it is (v2.8.0)
	 */
	public function ayudawp_floating_section_callback() {
		echo '<p class="aiss-bar-intro">' . esc_html__( 'Pinned to an edge of the screen while reading. It can appear where the content buttons do not, and the other way round.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Floating bar: where it appears (v2.5.0, own section v2.8.0)
	 *
	 * Same table as the buttons, with the positions the bar actually has: an
	 * edge of the viewport, not a place in the content. That is also how it is
	 * switched on — a row ticked is a page where the bar shows — so there is no
	 * separate on/off radio.
	 */
	public function ayudawp_floating_bar_callback() {
		$this->ayudawp_placements_callback( array( 'key' => 'floating_bar_placements' ) );

		echo '<p class="description">' . esc_html__( 'With nothing ticked the bar is off and the rest of this section folds away.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Floating bar: which buttons and their order (v2.8.0)
	 *
	 * As in the content, or its own: then the groups and the chips show up,
	 * starting from the content's list the first time.
	 */
	public function ayudawp_floating_bar_buttons_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$settings = ayudawp_aiss_get_floating_bar_settings( $options );
		$enabled  = isset( $options['enabled_buttons'] ) && is_array( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		$fresh    = null === $settings['picked'];

		$this->ayudawp_render_radios(
			'floating_bar_buttons',
			array(
				'inherit' => __( 'As in the content (default)', 'ai-share-summarize' ),
				'pick'    => __( 'Choose and order its own', 'ai-share-summarize' ),
			),
			$settings['buttons'],
			__( 'Buttons and order', 'ai-share-summarize' )
		);

		/*
		 * Never saved: the bar's own list starts as the content's, with its
		 * groups and its order. The script copies them again from the form as
		 * it is when "Choose" is first picked, so that is what is seen.
		 */
		if ( $fresh ) {
			$content_groups = array(
				'ayudawp_social_first' => 'social',
				'ayudawp_mixed'        => 'mixed',
			);
			$button_order = isset( $options['button_order'] ) ? (string) $options['button_order'] : '';
			$groups       = isset( $content_groups[ $button_order ] ) ? $content_groups[ $button_order ] : 'ai';
			$picked       = ayudawp_aiss_get_content_ordered_buttons( (array) $options );
		} else {
			$groups = $settings['groups'];
			$picked = $settings['picked'];
		}

		echo '<div class="aiss-bar-own"' . ( 'pick' === $settings['buttons'] ? '' : ' hidden' ) . ( $fresh ? ' data-fresh="1"' : '' ) . '>';
		$this->ayudawp_render_groups( 'floating_bar_groups', self::ayudawp_group_choices(), $groups );
		$this->ayudawp_render_order_board(
			'bar',
			$enabled,
			array(
				'ai'     => null !== $settings['order_ai'] ? $settings['order_ai'] : ( isset( $options['button_custom_order_ai'] ) ? $options['button_custom_order_ai'] : array() ),
				'social' => null !== $settings['order_social'] ? $settings['order_social'] : ( isset( $options['button_custom_order_social'] ) ? $options['button_custom_order_social'] : array() ),
			),
			$picked,
			true,
			ayudawp_aiss_get_google_preferred_placement( $options )
		);
		echo '<p class="description">' . esc_html__( 'Tick the ones the bar shows and drag them to order them. They start from the content\'s list.', 'ai-share-summarize' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Floating bar: how many in sight, always (v2.8.0)
	 */
	public function ayudawp_floating_bar_compact_callback() {
		$settings = ayudawp_aiss_get_floating_bar_settings();

		$this->ayudawp_render_compact( 'floating_bar_compact', 'floating_bar_compact_visible', $settings['compact'], $settings['compact_visible'], __( 'In sight in the bar', 'ai-share-summarize' ) );

		echo '<p class="description">' . esc_html__( 'The rest open in a panel beside the bar, which does not grow. When only one would be left over, it is shown.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Floating bar: appearance, the content's or its own (v2.8.0)
	 *
	 * The same rows and words as the content, without "Button" (the bar is
	 * always icons) and without "Fluid".
	 */
	public function ayudawp_floating_bar_look_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$settings = ayudawp_aiss_get_floating_bar_settings( $options );
		$content  = ayudawp_aiss_get_button_look( $options );
		$fresh    = ! isset( $options['floating_bar_color'] );

		$this->ayudawp_render_radios(
			'floating_bar_look',
			array(
				'inherit' => __( 'As in the content (default)', 'ai-share-summarize' ),
				'own'     => __( 'Its own', 'ai-share-summarize' ),
			),
			$settings['look'],
			__( 'Appearance', 'ai-share-summarize' )
		);

		// Never saved: its own look starts as the content's.
		$own = array(
			'color'   => $fresh ? $content['colors'] : $settings['color'],
			'corners' => $fresh ? $content['corners'] : $settings['corners'],
			'size'    => $fresh ? ( 'fluid' === $content['size'] ? 'normal' : $content['size'] ) : $settings['size'],
			'dark'    => $fresh ? $content['dark'] : $settings['dark'],
		);

		echo '<div class="aiss-look aiss-bar-own-look"' . ( 'own' === $settings['look'] ? '' : ' hidden' ) . ( $fresh ? ' data-fresh="1"' : '' ) . '>';
		$this->ayudawp_render_look_rows(
			array(
				'floating_bar_color'   => array( __( 'Color', 'ai-share-summarize' ), self::ayudawp_look_labels( 'colors', true ), $own['color'] ),
				'floating_bar_corners' => array( __( 'Corners', 'ai-share-summarize' ), self::ayudawp_look_labels( 'corners', true ), $own['corners'] ),
				'floating_bar_size'    => array( __( 'Size', 'ai-share-summarize' ), self::ayudawp_look_labels( 'size', true ), $own['size'] ),
				'floating_bar_dark'    => array( __( 'Dark backgrounds', 'ai-share-summarize' ), self::ayudawp_look_labels( 'dark', true ), $own['dark'] ),
			),
			'aiss-bar-look'
		);
		echo '</div>';

		echo '<p class="description">' . esc_html__( 'The bar is always icons. As in the content, it takes the color, the corners, the size and the dark backgrounds setting from there. Custom colors are the two set in the content\'s appearance.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Floating bar: the preview, last row of the section (v2.8.0)
	 *
	 * A sample bar on a page of grey blocks, drawn by the script from the
	 * form as it is now. Hidden until the script fills it.
	 */
	public function ayudawp_floating_bar_preview_callback() {
		$views = array(
			'right'  => __( 'Right edge', 'ai-share-summarize' ),
			'left'   => __( 'Left edge', 'ai-share-summarize' ),
			'bottom' => __( 'Bottom', 'ai-share-summarize' ),
			'phone'  => __( 'Phone', 'ai-share-summarize' ),
		);

		echo '<div class="aiss-bar-dock" hidden'
			. ' data-ai="' . esc_attr( implode( ',', ayudawp_aiss_get_ai_buttons() ) ) . '"'
			. ' data-google-off="' . ( '' === ayudawp_aiss_get_google_preferred_domain() ? '1' : '0' ) . '">';

		echo '<div class="aiss-block-dock-head">';
		// Not settings: they only pick which sample to draw.
		echo '<span class="aiss-preview-views" role="radiogroup" aria-label="' . esc_attr__( 'Preview', 'ai-share-summarize' ) . '">';
		foreach ( $views as $value => $label ) {
			echo '<label><input type="radio" name="aiss_bar_view" value="' . esc_attr( $value ) . '"> ' . esc_html( $label ) . '</label>';
		}
		echo '</span>';
		echo '<label class="aiss-block-dark"><input type="checkbox" name="aiss_bar_dark" value="1"> ' . esc_html__( 'Dark background', 'ai-share-summarize' ) . '</label>';
		echo '</div>';

		echo '<div class="aiss-bar-stage" aria-hidden="true"></div>';
		echo '<p class="aiss-bar-readout" aria-live="polite"></p>';
		echo '<p class="description">' . esc_html__( 'A sample, not your theme. It follows these rows and the content section as you change them, before you save.', 'ai-share-summarize' ) . '</p>';

		// One sample of every button, for the script to copy into the preview.
		echo '<template class="aiss-bar-tpl">';
		foreach ( array_keys( ayudawp_aiss_get_available_buttons() ) as $key ) {
			$classes = array( 'ayudawp-share-btn', str_replace( '_', '-', $key ) );
			if ( in_array( $key, ayudawp_aiss_get_ai_buttons(), true ) ) {
				$classes[] = 'ai';
			}
			echo '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" data-key="' . esc_attr( $key ) . '">'
				. wp_kses( AyudaWP_AISS_Icons::ayudawp_get_button_icon( $key, 20 ), ayudawp_aiss_kses_svg() )
				. '</span>';
		}
		echo '<span class="ayudawp-share-btn" data-key="__share">'
			. wp_kses( AyudaWP_AISS_Icons::ayudawp_get_button_icon( 'web_share', 20 ), ayudawp_aiss_kses_svg() )
			. '</span>';
		echo '</template>';

		echo '</div>';
	}


	// ==========================================
	// Settings field callbacks
	// ==========================================


	/**
	 * SEO Integration callback
	 */
	public function ayudawp_seo_integration_callback() {
		$options         = get_option( 'ayudawp_aiss_options' );
		$exclude_noindex = isset( $options['exclude_noindex'] ) ? $options['exclude_noindex'] : true;
		$seo_plugin      = AyudaWP_AISS_SEO_Integration::ayudawp_detect_seo_plugin();
		$has_noindexer   = class_exists( 'Noindexer_Frontend' );
		$has_visibility  = AyudaWP_AISS_SEO_Integration::ayudawp_has_visibility();
		?>
		<div class="ayudawp-seo-integration">
			<label style="display: flex; align-items: center; gap: 8px;">
				<input type="checkbox" name="ayudawp_aiss_options[exclude_noindex]" value="1" <?php checked( $exclude_noindex, true ); ?>>
				<?php esc_html_e( 'Exclude noindex content', 'ai-share-summarize' ); ?>
			</label>
			<p class="description" style="margin-top: 8px; margin-left: 24px;">
				<?php esc_html_e( 'Automatically exclude content marked as noindex in your SEO plugin.', 'ai-share-summarize' ); ?>
				<?php if ( $seo_plugin || $has_noindexer || $has_visibility ) : ?>
					<span class="ayudawp-seo-detected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php
						$detected = array();
						if ( $seo_plugin ) {
							$detected[] = $seo_plugin['name'];
						}
						if ( $has_visibility ) {
							$detected[] = 'Visibility';
						}
						if ( $has_noindexer ) {
							$detected[] = 'NoIndexer';
						}
						/* translators: %s: detected plugin name(s) */
						printf( esc_html__( 'Detected: %s', 'ai-share-summarize' ), esc_html( implode( ', ', $detected ) ) );
						?>
					</span>
				<?php else : ?>
					<span class="ayudawp-seo-not-detected">
						<?php esc_html_e( 'No compatible SEO plugin detected', 'ai-share-summarize' ); ?>
					</span>
				<?php endif; ?>
			</p>
		</div>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Compatible with:', 'ai-share-summarize' ); ?> Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Visibility, NoIndexer
		</p>
		<?php
	}


	/**
	 * Build a sorted button list from custom order + enabled buttons
	 *
	 * @param array $custom_order Saved custom order.
	 * @param array $enabled      Currently enabled buttons.
	 * @param array $group_keys   Keys belonging to this group (ai or social).
	 * @param array $all_buttons  All available buttons.
	 * @return array Sorted button keys.
	 */
	private static function ayudawp_build_sorted_list( $custom_order, $enabled, $group_keys, $all_buttons ) {
		$sorted = array();
		if ( ! empty( $custom_order ) ) {
			foreach ( $custom_order as $btn ) {
				if ( in_array( $btn, $enabled, true ) && in_array( $btn, $group_keys, true ) && isset( $all_buttons[ $btn ] ) ) {
					$sorted[] = $btn;
				}
			}
		}
		foreach ( $enabled as $btn ) {
			if ( ! in_array( $btn, $sorted, true ) && in_array( $btn, $group_keys, true ) && isset( $all_buttons[ $btn ] ) ) {
				$sorted[] = $btn;
			}
		}
		return $sorted;
	}


	/**
	 * Where something appears: one row per destination (v2.5.0)
	 *
	 * Replaces the three fields that governed this until 2.4.4. Each row is a
	 * checkbox plus its own position, so the "whether" and the "where" travel
	 * together and there is no hidden AND between two overlapping lists. The
	 * same table serves the buttons, the floating bar and the summary, each
	 * with the positions that make sense for it.
	 *
	 * @param array $args Optional. 'key' picks which placements map to paint.
	 */
	public function ayudawp_placements_callback( $args = array() ) {
		$key        = isset( $args['key'] ) ? $args['key'] : 'placements';
		$placements = ayudawp_aiss_get_placements( $key );
		$positions  = ayudawp_aiss_get_placement_positions( $key );
		$targets    = ayudawp_aiss_get_placement_targets( $key );
		$field      = $this->ayudawp_placements_field_name( $key );

		$listing_keys = array_intersect_key( $targets, array( 'home' => 1, 'archive' => 1 ) );
		$singular     = array_diff_key( $targets, $listing_keys );

		$groups = array(
			array(
				'label'   => esc_html__( 'Single content', 'ai-share-summarize' ),
				'targets' => $singular,
			),
		);
		if ( ! empty( $listing_keys ) ) {
			// WordPress calls these archives, so the screen does too.
			$groups[] = array(
				'label'   => esc_html__( 'Archives', 'ai-share-summarize' ),
				'targets' => $listing_keys,
			);
		}

		/*
		 * Two columns: a site with several public post types plus the archives
		 * makes for a tall single column, and this screen paints the table
		 * twice (buttons and floating bar).
		 */
		/*
		 * Marker for the sanitizer (v2.8.0): this table came, so a destination
		 * that is not ticked is off. Without it, a save that does not carry the
		 * table keeps the stored one. The summary's table posts elsewhere.
		 */
		if ( 'ai_summary_placements' !== $key ) {
			echo '<input type="hidden" name="ayudawp_aiss_options[_sections][]" value="' . esc_attr( $key ) . '">';
		}

		echo '<div class="aiss-placements' . ( count( $groups ) > 1 ? ' has-groups' : '' ) . '">';

		foreach ( $groups as $group ) {
			echo '<div class="aiss-placements-col">';
			echo '<p class="aiss-placements-group">' . esc_html( $group['label'] ) . '</p>';

			foreach ( $group['targets'] as $target => $label ) {
				$on       = isset( $placements[ $target ] );
				$position = $on ? $placements[ $target ] : ayudawp_aiss_get_default_placement_position( $key );
				$id       = 'aiss-placement-' . $key . '-' . $target;
				?>
				<div class="aiss-placement-row<?php echo $on ? ' is-on' : ''; ?>">
					<?php
					/*
					 * The checkbox decides whether the destination counts and
					 * the select carries its position; the sanitizer crosses
					 * the two. The select is never disabled, so the form works
					 * with JavaScript off: what JS adds is only the dimming of
					 * a position whose destination is unticked.
					 */
					?>
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>"
						class="aiss-placement-toggle"
						name="<?php echo esc_attr( $field ); ?>[<?php echo esc_attr( $key ); ?>_on][]"
						value="<?php echo esc_attr( $target ); ?>" <?php checked( $on, true ); ?>>
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
					<select name="<?php echo esc_attr( $field ); ?>[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $target ); ?>]"
						class="aiss-placement-position">
						<?php foreach ( $positions as $value => $position_label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position, $value ); ?>>
								<?php echo esc_html( $position_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php
			}
			echo '</div>';
		}

		echo '</div>';

		if ( 'placements' === $key ) {
			echo '<p class="description">' . sprintf(
				/* translators: %s is the shortcode name. */
				esc_html__( 'With nothing ticked, the buttons only appear where you place the %s shortcode.', 'ai-share-summarize' ),
				'<code>[ayudawp_share_buttons]</code>'
			) . '</p>';
		}
	}

	/**
	 * Which option a placements table posts into (v2.5.0)
	 *
	 * The summary keeps its own option since 2.3.0, so its table has to post
	 * there and not into the buttons one.
	 *
	 * @param string $key Placements map key.
	 * @return string Option name.
	 */
	private function ayudawp_placements_field_name( $key ) {
		return ( 'ai_summary_placements' === $key )
			? 'ayudawp_aiss_summary_options'
			: 'ayudawp_aiss_options';
	}


	/**
	 * Floating bar: behaviour, the three checkboxes it had since 2.5.0
	 */
	public function ayudawp_floating_bar_behavior_callback() {
		$options     = get_option( 'ayudawp_aiss_options' );
		$autohide    = ! empty( $options['floating_bar_autohide'] );
		$hide_mobile = ! isset( $options['floating_bar_hide_mobile'] ) || ! empty( $options['floating_bar_hide_mobile'] );
		$hide_inline = ! empty( $options['floating_bar_hide_with_inline'] );
		?>
		<?php // Marker for the sanitizer (v2.8.0): these boxes came, so an unticked one is off. ?>
		<input type="hidden" name="ayudawp_aiss_options[_sections][]" value="floating_bar_behavior">
		<label class="aiss-stacked">
			<input type="checkbox" name="ayudawp_aiss_options[floating_bar_hide_mobile]" value="1" <?php checked( $hide_mobile, true ); ?>>
			<?php esc_html_e( 'Hide it on phones (default)', 'ai-share-summarize' ); ?>
		</label>
		<label class="aiss-stacked">
			<input type="checkbox" name="ayudawp_aiss_options[floating_bar_autohide]" value="1" <?php checked( $autohide, true ); ?>>
			<?php esc_html_e( 'Show it only while scrolling down, hide it when scrolling back up', 'ai-share-summarize' ); ?>
		</label>
		<label class="aiss-stacked">
			<input type="checkbox" name="ayudawp_aiss_options[floating_bar_hide_with_inline]" value="1" <?php checked( $hide_inline, true ); ?>>
			<?php esc_html_e( 'Hide it on pages where the buttons block is already showing', 'ai-share-summarize' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'On phones the side positions turn into a strip along the bottom. The bar loads its own CSS and JS, only on the pages where it is shown.', 'ai-share-summarize' ); ?></p>
		<?php
	}


	/**
	 * Platform accounts (v2.5.0, two settings in one row)
	 */
	public function ayudawp_accounts_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$handle   = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
		$mastodon = isset( $options['mastodon_instance'] ) ? $options['mastodon_instance'] : '';
		?>
		<div class="aiss-two-col">
			<div>
				<label for="aiss-x-handle"><strong><?php esc_html_e( 'X account to mention', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="aiss-x-handle" class="regular-text aiss-full"
					name="ayudawp_aiss_options[twitter_handle]" value="<?php echo esc_attr( $handle ); ?>"
					placeholder="@ayudawp">
				<p class="description"><?php esc_html_e( 'Added to the text of the post being shared.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<label for="aiss-mastodon"><strong><?php esc_html_e( 'Mastodon instance', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="aiss-mastodon" class="regular-text aiss-full"
					name="ayudawp_aiss_options[mastodon_instance]" value="<?php echo esc_attr( $mastodon ); ?>"
					placeholder="mastodon.social">
				<p class="description"><?php esc_html_e( 'Domain only, without https://. Defaults to mastodon.social.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Markup type and UTM tagging (v2.5.0, two settings in one row)
	 */
	public function ayudawp_output_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$seo_type = isset( $options['seo_button_type'] ) ? $options['seo_button_type'] : 'link';
		$utm_on   = ! empty( $options['utm_enabled'] );
		$campaign = isset( $options['utm_campaign'] ) ? $options['utm_campaign'] : '';
		?>
		<div class="aiss-two-col">
			<div>
				<strong><?php esc_html_e( 'Button markup', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="radio" name="ayudawp_aiss_options[seo_button_type]" value="link" <?php checked( $seo_type, 'link' ); ?>>
					<?php esc_html_e( 'Links with nofollow', 'ai-share-summarize' ); ?>
				</label>
				<label class="aiss-stacked">
					<input type="radio" name="ayudawp_aiss_options[seo_button_type]" value="button" <?php checked( $seo_type, 'button' ); ?>>
					<?php esc_html_e( 'Button elements', 'ai-share-summarize' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Links already carry nofollow, so they pass no PageRank. Button elements are not counted as links at all, but they need JavaScript and lose right-click and middle-click.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<strong><?php esc_html_e( 'Campaign tracking (UTM)', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_options[utm_enabled]" value="1" <?php checked( $utm_on, true ); ?>>
					<?php esc_html_e( 'Tag shared links', 'ai-share-summarize' ); ?>
				</label>
				<label for="aiss-utm-campaign"><?php esc_html_e( 'Campaign name', 'ai-share-summarize' ); ?></label>
				<input type="text" id="aiss-utm-campaign" class="regular-text aiss-full"
					name="ayudawp_aiss_options[utm_campaign]" value="<?php echo esc_attr( $campaign ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. share-buttons', 'ai-share-summarize' ); ?>">
				<p class="description"><?php esc_html_e( 'Shared links carry utm_source with the platform and utm_medium with social, ai or share. Fill in a name and they carry utm_campaign too; leave it empty and they do not.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}


	/**
	 * Analytics data: retention, one-off deletion and uninstall behaviour
	 *
	 * Reordered in 2.5.0. The destructive button used to sit at the far right
	 * of the row, pushed there by `margin-left: auto`, which on a wide screen
	 * left it stranded halfway across the screen from the setting it belongs
	 * with. It now sits next to the retention period it complements, with the
	 * uninstall choice, a different decision, below both.
	 */
	public function ayudawp_data_retention_callback() {
		$options     = get_option( 'ayudawp_aiss_options' );
		$retention   = isset( $options['data_retention'] ) ? $options['data_retention'] : 'forever';
		$delete_data = isset( $options['delete_data_on_uninstall'] ) ? $options['delete_data_on_uninstall'] : false;

		$retention_options = array(
			'forever'  => __( 'Forever (default)', 'ai-share-summarize' ),
			'1_month'  => __( '1 month', 'ai-share-summarize' ),
			'3_months' => __( '3 months', 'ai-share-summarize' ),
			'6_months' => __( '6 months', 'ai-share-summarize' ),
			'1_year'   => __( '1 year', 'ai-share-summarize' ),
		);
		?>
		<div class="aiss-two-col">
			<div>
				<label for="ayudawp_data_retention"><strong><?php esc_html_e( 'Keep click data for', 'ai-share-summarize' ); ?></strong></label>
				<select name="ayudawp_aiss_options[data_retention]" id="ayudawp_data_retention" class="aiss-full">
					<?php foreach ( $retention_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $retention, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Anything older is deleted once a day.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<strong><?php esc_html_e( 'Delete it now', 'ai-share-summarize' ); ?></strong>
				<p>
					<button type="button" id="ayudawp-aiss-delete-all-data" class="button aiss-danger-button">
						<?php esc_html_e( 'Delete all analytics data', 'ai-share-summarize' ); ?>
					</button>
				</p>
				<p class="description"><?php esc_html_e( 'Wipes every click recorded so far, for every post and platform. It cannot be undone, and your settings are left alone.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<label class="aiss-stacked" style="margin-top:16px">
			<input type="checkbox" name="ayudawp_aiss_options[delete_data_on_uninstall]" value="1" <?php checked( $delete_data, true ); ?>>
			<?php esc_html_e( 'Also delete everything when the plugin is uninstalled', 'ai-share-summarize' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Off by default, so deactivating and reinstalling keeps your settings and your analytics.', 'ai-share-summarize' ); ?></p>
		<?php
	}


	/**
	 * Where the summary appears (v2.5.0)
	 *
	 * Same table as the buttons, so the position can differ per content type:
	 * next to the buttons on posts and before the content on pages, which one
	 * global position could not express. Archives are not offered here because
	 * a summary belongs to a single post.
	 */
	public function ayudawp_ai_summary_placement_callback() {
		echo '<p class="description">' . esc_html__( 'Tick the content types that get a summary, and where it goes on each. Nothing ticked means no summaries are generated automatically.', 'ai-share-summarize' ) . '</p>';

		$this->ayudawp_placements_callback( array( 'key' => 'ai_summary_placements' ) );

		echo '<p class="description">' . esc_html__( 'The positions relative to the buttons fall back to the start of the content on posts where the buttons are not shown. This list is independent from where the buttons appear.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Appearance of the summary block, with a live preview (v2.5.0)
	 *
	 * Five styles times three icon positions times open or closed is more
	 * combinations than anyone wants to discover by saving and reloading, so the
	 * sample below is rendered with the front-end stylesheet and repainted as
	 * the controls change.
	 */
	public function ayudawp_ai_summary_look_callback() {
		$options   = ayudawp_aiss_get_summary_options();
		$style     = isset( $options['ai_summary_style'] ) ? $options['ai_summary_style'] : 'minimal';
		$icon_pos  = isset( $options['ai_summary_icon_position'] ) ? $options['ai_summary_icon_position'] : 'left';
		$collapsed = isset( $options['ai_summary_collapsed_default'] ) ? $options['ai_summary_collapsed_default'] : true;
		$label     = ! empty( $options['ai_summary_label'] ) ? $options['ai_summary_label'] : __( 'TL;DR (AI Generated)', 'ai-share-summarize' );

		$styles = array(
			'minimal' => esc_html__( 'Minimal (default)', 'ai-share-summarize' ),
			'outline' => esc_html__( 'Outline', 'ai-share-summarize' ),
			'brand'   => esc_html__( 'Brand (filled)', 'ai-share-summarize' ),
			'dark'    => esc_html__( 'Dark', 'ai-share-summarize' ),
			'custom'  => esc_html__( 'Custom colors', 'ai-share-summarize' ),
		);

		$icon_positions = array(
			'left'   => esc_html__( 'Left (default)', 'ai-share-summarize' ),
			'right'  => esc_html__( 'Right', 'ai-share-summarize' ),
			'hidden' => esc_html__( 'Hidden', 'ai-share-summarize' ),
		);

		$custom_bg   = isset( $options['ai_summary_custom_bg'] ) ? $options['ai_summary_custom_bg'] : '#ffffff';
		$custom_text = isset( $options['ai_summary_custom_text'] ) ? $options['ai_summary_custom_text'] : '#1a1a1a';
		?>
		<div class="aiss-two-col">
			<div>
				<strong><?php esc_html_e( 'Style', 'ai-share-summarize' ); ?></strong>
				<?php foreach ( $styles as $key => $style_label ) : ?>
					<label class="aiss-stacked">
						<input type="radio" class="aiss-summary-style-input" name="ayudawp_aiss_summary_options[ai_summary_style]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $style, $key ); ?>>
						<?php echo esc_html( $style_label ); ?>
					</label>
				<?php endforeach; ?>
				<div class="ayudawp-color-picker-row" id="ayudawp-aiss-summary-custom-colors"<?php echo 'custom' !== $style ? ' style="display:none;"' : ''; ?>>
					<div>
						<label for="ayudawp_aiss_summary_bg"><?php esc_html_e( 'Background color:', 'ai-share-summarize' ); ?></label>
						<input type="text" id="ayudawp_aiss_summary_bg" name="ayudawp_aiss_summary_options[ai_summary_custom_bg]" value="<?php echo esc_attr( $custom_bg ); ?>" class="ayudawp-color-field" data-default-color="#ffffff">
					</div>
					<div>
						<label for="ayudawp_aiss_summary_text"><?php esc_html_e( 'Text color:', 'ai-share-summarize' ); ?></label>
						<input type="text" id="ayudawp_aiss_summary_text" name="ayudawp_aiss_summary_options[ai_summary_custom_text]" value="<?php echo esc_attr( $custom_text ); ?>" class="ayudawp-color-field" data-default-color="#1a1a1a">
					</div>
				</div>
			</div>
			<div>
				<strong><?php esc_html_e( 'Icon in the header', 'ai-share-summarize' ); ?></strong>
				<?php foreach ( $icon_positions as $key => $icon_label ) : ?>
					<label class="aiss-stacked">
						<input type="radio" class="aiss-summary-icon-input" name="ayudawp_aiss_summary_options[ai_summary_icon_position]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $icon_pos, $key ); ?>>
						<?php echo esc_html( $icon_label ); ?>
					</label>
				<?php endforeach; ?>
				<label class="aiss-stacked" style="margin-top:12px">
					<input type="checkbox" class="aiss-summary-collapsed-input" name="ayudawp_aiss_summary_options[ai_summary_collapsed_default]" value="1" <?php checked( $collapsed, true ); ?>>
					<?php esc_html_e( 'Start collapsed', 'ai-share-summarize' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Collapsed keeps the post tidy; expanded shows the summary to every reader without a click.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>

		<p class="aiss-preview-caption"><?php esc_html_e( 'Preview', 'ai-share-summarize' ); ?></p>
		<div class="aiss-summary-preview">
			<?php $this->ayudawp_render_summary_preview( $style, $icon_pos, $collapsed, $label, $custom_bg, $custom_text ); ?>
		</div>
		<?php
	}

	/**
	 * Sample of the summary block for the settings preview (v2.5.0)
	 *
	 * Deliberately not AyudaWP_AISS_AI_Summary::get_summary_html(): that one
	 * needs a real post with a stored summary, and the settings screen has to
	 * show something on a brand new site too. Same markup and same class names,
	 * so the front-end stylesheet renders it identically.
	 *
	 * @param string $style       Style key.
	 * @param string $icon_pos    Icon position.
	 * @param bool   $collapsed   Whether it starts collapsed.
	 * @param string $label       Block label.
	 * @param string $custom_bg   Custom background.
	 * @param string $custom_text Custom text colour.
	 */
	private function ayudawp_render_summary_preview( $style, $icon_pos, $collapsed, $label, $custom_bg, $custom_text ) {
		$classes = array( 'ayudawp-aiss-summary' );
		if ( in_array( $style, array( 'outline', 'brand', 'dark', 'custom' ), true ) ) {
			$classes[] = 'style-' . $style;
		}
		if ( 'right' === $icon_pos ) {
			$classes[] = 'icon-right';
		} elseif ( 'hidden' === $icon_pos ) {
			$classes[] = 'icon-hidden';
		}

		$icon = class_exists( 'AyudaWP_AISS_Icons' ) ? AyudaWP_AISS_Icons::ayudawp_get_icon( 'aiss_summary', 16 ) : '';
		?>
		<aside id="aiss-summary-sample" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			style="--ayudawp-aiss-bg:<?php echo esc_attr( $custom_bg ); ?>;--ayudawp-aiss-text:<?php echo esc_attr( $custom_text ); ?>;">
			<details class="ayudawp-aiss-summary-details"<?php echo $collapsed ? '' : ' open'; ?>>
				<summary class="ayudawp-aiss-summary-toggle">
					<span class="ayudawp-aiss-summary-icon" aria-hidden="true"><?php echo wp_kses( $icon, ayudawp_aiss_kses_svg() ); ?></span>
					<span class="ayudawp-aiss-summary-label"><?php echo esc_html( $label ); ?></span>
				</summary>
				<div class="ayudawp-aiss-summary-content">
					<div class="ayudawp-aiss-summary-text">
						<p><?php esc_html_e( 'This is how the summary block will look on your posts.', 'ai-share-summarize' ); ?></p>
						<p><?php esc_html_e( 'Each sentence renders on its own line, or as a bullet when you pick the list format.', 'ai-share-summarize' ); ?></p>
					</div>
				</div>
			</details>
		</aside>
		<?php
	}

	/**
	 * Label, length and format of the summary (v2.5.0, three settings in one row)
	 */
	public function ayudawp_ai_summary_wording_callback() {
		$options   = ayudawp_aiss_get_summary_options();
		$label     = isset( $options['ai_summary_label'] ) ? $options['ai_summary_label'] : '';
		$sentences = isset( $options['ai_summary_sentences'] ) ? (int) $options['ai_summary_sentences'] : 3;
		$bullets   = ! empty( $options['ai_summary_bullets'] );
		?>
		<div class="aiss-two-col">
			<div>
				<label for="aiss-summary-label"><strong><?php esc_html_e( 'Block label', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="aiss-summary-label" class="regular-text aiss-full aiss-summary-label-input"
					name="ayudawp_aiss_summary_options[ai_summary_label]" value="<?php echo esc_attr( $label ); ?>"
					placeholder="<?php esc_attr_e( 'TL;DR (AI Generated)', 'ai-share-summarize' ); ?>">
				<p class="description"><?php esc_html_e( 'The heading readers click to open the block. Leave it empty for the default.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<strong><?php esc_html_e( 'Length and format', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked" for="aiss-summary-sentences">
					<?php esc_html_e( 'Sentences or bullet points:', 'ai-share-summarize' ); ?>
					<input type="number" id="aiss-summary-sentences" min="1" max="5" step="1" style="width:70px"
						name="ayudawp_aiss_summary_options[ai_summary_sentences]" value="<?php echo esc_attr( (string) $sentences ); ?>">
				</label>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_summary_options[ai_summary_bullets]" value="1" <?php checked( $bullets, true ); ?>>
					<?php esc_html_e( 'Render it as a bullet list instead of paragraphs', 'ai-share-summarize' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Summaries already stored are split into items without regenerating them.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Visitor-facing generation button (v2.5.0, three settings in one row)
	 */
	public function ayudawp_ai_summary_visitor_callback() {
		$options    = ayudawp_aiss_get_summary_options();
		$enabled    = ! empty( $options['ai_summary_frontend_button'] );
		$label      = isset( $options['ai_summary_generate_button_label'] ) ? $options['ai_summary_generate_button_label'] : '';
		$extractive = ! isset( $options['ai_summary_frontend_force_extractive'] ) || ! empty( $options['ai_summary_frontend_force_extractive'] );
		?>
		<label class="aiss-stacked">
			<input type="checkbox" name="ayudawp_aiss_summary_options[ai_summary_frontend_button]" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e( 'Show a button on posts that have no summary yet, so a reader can generate it', 'ai-share-summarize' ); ?>
		</label>
		<div class="aiss-two-col" style="margin-top:12px">
			<div>
				<label for="aiss-visitor-label"><strong><?php esc_html_e( 'Button label', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="aiss-visitor-label" class="regular-text aiss-full"
					name="ayudawp_aiss_summary_options[ai_summary_generate_button_label]" value="<?php echo esc_attr( $label ); ?>"
					placeholder="<?php esc_attr_e( 'Generate AI summary', 'ai-share-summarize' ); ?>">
			</div>
			<div>
				<strong><?php esc_html_e( 'Cost control', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_summary_options[ai_summary_frontend_force_extractive]" value="1" <?php checked( $extractive, true ); ?>>
					<?php esc_html_e( 'Visitors only get the built-in summarizer, never the AI provider', 'ai-share-summarize' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Leave this on unless you want reader clicks to spend AI credit. Generation is rate limited per visitor and capped per hour either way.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Custom content inside the summary box (v2.7.0)
	 *
	 * Anything goes in it: text, HTML or scripts, such as an ad, a newsletter
	 * form, a map or a notice. The content field is read-only for anyone
	 * without `unfiltered_html`, who cannot change it anyway (see
	 * ayudawp_aiss_validate_summary_custom_code()). The other settings are
	 * ordinary values and stay editable.
	 */
	public function ayudawp_ai_summary_custom_callback() {
		$options    = ayudawp_aiss_get_summary_options();
		$enabled    = ! empty( $options['ai_summary_custom_content_enabled'] );
		$code       = isset( $options['ai_summary_custom_content_code'] ) && is_string( $options['ai_summary_custom_content_code'] ) ? $options['ai_summary_custom_content_code'] : '';
		$label      = ! empty( $options['ai_summary_custom_content_label'] );
		$label_text = isset( $options['ai_summary_custom_content_label_text'] ) && is_string( $options['ai_summary_custom_content_label_text'] ) ? $options['ai_summary_custom_content_label_text'] : '';
		$min        = isset( $options['ai_summary_custom_content_min_height'] ) ? absint( $options['ai_summary_custom_content_min_height'] ) : 0;
		$max        = isset( $options['ai_summary_custom_content_max_height'] ) ? absint( $options['ai_summary_custom_content_max_height'] ) : 0;
		$can_edit   = current_user_can( 'unfiltered_html' );
		?>
		<label class="aiss-stacked">
			<input type="checkbox" name="ayudawp_aiss_summary_options[ai_summary_custom_content_enabled]" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e( 'Show custom content inside the summary box, below the summary', 'ai-share-summarize' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Anything fits: text, HTML or scripts, such as an ad, a newsletter form, a map or a notice. It only appears when a reader opens the box, and only on the post itself, never in listings.', 'ai-share-summarize' ); ?>
			<?php
			printf(
				/* translators: 1: HTML id of the custom content space, 2: name of the JavaScript event. */
				esc_html__( 'The space is the element with id %1$s, so an ad server or Google Tag Manager can also fill it directly, and the %2$s event (also pushed to the dataLayer) tells them when the box opens.', 'ai-share-summarize' ),
				'<code>ayudawp-aiss-custom</code>',
				'<code>ayudawp_aiss_custom_visible</code>'
			);
			?>
		</p>
		<div style="margin-top:12px">
			<label for="aiss-custom-code"><strong><?php esc_html_e( 'Content: text, HTML or scripts (optional)', 'ai-share-summarize' ); ?></strong></label>
			<textarea id="aiss-custom-code" class="large-text code" rows="8" spellcheck="false"
				name="ayudawp_aiss_summary_options[ai_summary_custom_content_code]" <?php wp_readonly( $can_edit, false ); ?>><?php echo esc_textarea( $code ); ?></textarea>
			<?php if ( $can_edit ) : ?>
				<p class="description"><?php esc_html_e( 'Kept exactly as you paste it and loaded the first time the box is opened. Scripts that queue their work, like AdSense, Google Ad Manager or Tag Manager, work this way; scripts that rely on document.write do not.', 'ai-share-summarize' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Only users allowed to publish unfiltered HTML can change this content (on a network, the super admin). It is shown here read-only.', 'ai-share-summarize' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="aiss-two-col" style="margin-top:12px">
			<div>
				<strong><?php esc_html_e( 'Label', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_summary_options[ai_summary_custom_content_label]" value="1" <?php checked( $label, true ); ?>>
					<?php esc_html_e( 'Show a label above it', 'ai-share-summarize' ); ?>
				</label>
				<label class="aiss-stacked" for="aiss-custom-label-text">
					<?php esc_html_e( 'Label text:', 'ai-share-summarize' ); ?>
					<input type="text" id="aiss-custom-label-text" class="regular-text"
						name="ayudawp_aiss_summary_options[ai_summary_custom_content_label_text]" value="<?php echo esc_attr( $label_text ); ?>"
						placeholder="<?php esc_attr_e( 'Advertisement', 'ai-share-summarize' ); ?>">
				</label>
				<p class="description"><?php esc_html_e( 'Leave it empty for the default. With an ad, the label is usually required.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<strong><?php esc_html_e( 'Height of the space', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked" for="aiss-custom-min-height">
					<?php esc_html_e( 'Minimum (px):', 'ai-share-summarize' ); ?>
					<input type="number" id="aiss-custom-min-height" min="0" max="2000" step="1" style="width:90px"
						name="ayudawp_aiss_summary_options[ai_summary_custom_content_min_height]" value="<?php echo esc_attr( (string) $min ); ?>">
				</label>
				<label class="aiss-stacked" for="aiss-custom-max-height">
					<?php esc_html_e( 'Maximum (px):', 'ai-share-summarize' ); ?>
					<input type="number" id="aiss-custom-max-height" min="0" max="2000" step="1" style="width:90px"
						name="ayudawp_aiss_summary_options[ai_summary_custom_content_max_height]" value="<?php echo esc_attr( (string) $max ); ?>">
				</label>
				<p class="description"><?php esc_html_e( '0 means no limit. The minimum keeps the space while the content loads, so the text below does not jump. Anything taller than the maximum is cut off; with an ad that can break your ad network policies, so better size the ad unit itself.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Default prompt and custom text (v2.5.0, two settings in one row)
	 *
	 * They travel together in the request the AI buttons open: the prompt, then
	 * the custom text, then the URL. Side by side with matching heights, that
	 * relationship is legible; one above the other it was not.
	 */
	public function ayudawp_prompt_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$prompt  = isset( $options['default_prompt'] ) ? $options['default_prompt'] : ayudawp_aiss_get_default_prompt();
		$custom  = isset( $options['custom_text'] ) ? $options['custom_text'] : '';
		?>
		<div class="aiss-two-col">
			<div>
				<label for="ayudawp_default_prompt"><strong><?php esc_html_e( 'Prompt', 'ai-share-summarize' ); ?></strong></label>
				<textarea id="ayudawp_default_prompt" name="ayudawp_aiss_options[default_prompt]" rows="5" class="large-text aiss-full"><?php echo esc_textarea( $prompt ); ?></textarea>
				<p class="description"><?php esc_html_e( 'What the AI is asked to do with your post. Left as it comes, it is translated for each reader; edit it and your own wording is used for everyone.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<label for="ayudawp_custom_text"><strong><?php esc_html_e( 'Extra text', 'ai-share-summarize' ); ?></strong></label>
				<textarea id="ayudawp_custom_text" name="ayudawp_aiss_options[custom_text]" rows="5" class="large-text aiss-full" placeholder="<?php esc_attr_e( 'e.g. Reply in the language of the article.', 'ai-share-summarize' ); ?>"><?php echo esc_textarea( $custom ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Added after the prompt, before the link to your post. Optional.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Delete all analytics data
	 */
	public function ayudawp_ajax_delete_all_data() {
		check_ajax_referer( 'aiss_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		AyudaWP_AISS_Database::ayudawp_delete_all_data();
		wp_send_json_success();
	}

	/**
	 * AJAX handler: the buttons block as a visitor would see it, from the unsaved form (v2.8.0)
	 *
	 * Read-only: it saves nothing and touches nothing. The values go through
	 * the same sanitizer as the Save button and the same renderer as the front
	 * end, so the preview is the block itself and not a copy of its logic that
	 * could drift from it. Only the keys that shape the block are read; the
	 * rest of the form (prompt, accounts, analytics) is ignored, and so are the
	 * shortcode-only keys, which the sanitizer never returns.
	 */
	public function ayudawp_ajax_block_preview() {
		check_ajax_referer( 'aiss_block_preview', 'nonce' );

		// The capability of the settings page that sends it.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$raw = ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) )
			? map_deep( wp_unslash( $_POST['options'] ), 'sanitize_text_field' )
			: array();

		$keys = array(
			'enabled_buttons',
			// Which of them and the look, since 2.8.0.
			'content_buttons_mode',
			'content_buttons_picked',
			'button_content',
			'button_colors',
			'button_corners',
			'button_size',
			'button_alignment',
			'separator_top',
			'separator_bottom',
			'button_order',
			'button_custom_order_ai',
			'button_custom_order_social',
			'title_text',
			'title_style',
			'ai_section_title',
			'social_section_title',
			'google_preferred_placement',
			'google_section_title',
			'mobile_compact',
			'mobile_compact_visible',
			'custom_color_bg',
			'custom_color_text',
			'seo_button_type',
		);

		$options = ayudawp_aiss_validate_options( array_intersect_key( $raw, array_flip( $keys ) ) );

		$html = AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html(
			isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array(),
			$options,
			home_url( '/' ),
			__( 'Sample post', 'ai-share-summarize' ),
			0
		);

		// Same allowlist as the block printed on the front end.
		wp_send_json_success( array( 'html' => wp_kses( $html, ayudawp_aiss_kses_content_blocks() ) ) );
	}

	/**
	 * AJAX handler: Dismiss the "Last AI generation error" notice on Settings (v2.0.0)
	 */
	public function ayudawp_ajax_dismiss_ai_error() {
		check_ajax_referer( 'aiss_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		if ( class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			AyudaWP_AISS_AI_Summary::store_last_ai_error( null );
		} else {
			delete_option( 'ayudawp_aiss_last_ai_error' );
		}

		wp_send_json_success();
	}

	// ==========================================
	// AI Summary section (v2.0.0)
	// ==========================================

	/**
	 * AI Summary section callback — explains the feature and AI client status
	 *
	 * Since 2.3.0 this section lives on its own tab (&tab=ai-summary), which
	 * is the stable deep-link target for the command palette, the Abilities
	 * API and external docs (it replaces the old in-page anchor).
	 */
	public function ayudawp_ai_summary_section_callback() {
		echo '<p>'
			. esc_html__( 'Automatically generate a short summary of each post and display it inline alongside the share buttons.', 'ai-share-summarize' )
			. '</p>';

		// Loud, early warning when automatic generation is effectively off
		// because the content-type list is empty: since the buttons-list
		// fallback was removed (2.3.0), an empty list genuinely means "no
		// content types", and without this notice the only symptom would be
		// summaries silently not appearing.
		if ( ayudawp_aiss_is_summary_active() && array() === ayudawp_aiss_get_summary_post_types() ) {
			echo '<div style="margin:10px 0;padding:10px 14px;background:#fcf9e8;border-left:4px solid #dba617;">'
				. '<p style="margin:0;"><strong>' . esc_html__( 'Automatic summaries are off for every content type.', 'ai-share-summarize' ) . '</strong> '
				. esc_html__( 'No content type is selected in "Summaries by content type" below, so no summary will be generated or displayed automatically. Manual regeneration from the editor keeps working.', 'ai-share-summarize' )
				. '</p>'
				. '</div>';
		}

		echo '<div class="ayudawp-seo-integration" style="background:#f9f9f9;padding:15px;margin:10px 0;border-radius:4px;">';

		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$connectors_url = admin_url( 'options-connectors.php' );
			echo '<p style="margin:0;"><strong style="color:#00a32a;">'
				. esc_html__( 'WP AI Client is available.', 'ai-share-summarize' )
				. '</strong> '
				. sprintf(
					/* translators: %s is a link to the Connectors settings screen. */
					esc_html__( 'Configure your AI provider in %s to enable high-quality summaries.', 'ai-share-summarize' ),
					'<a href="' . esc_url( $connectors_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Settings > Connectors', 'ai-share-summarize' ) . '</a>'
				)
				. '</p>';

			// Surface the last AI Client error (if any) so the admin can act on it.
			// The optional "Possible cause" hint lives inside the same notice
			// so the dismiss button removes both lines together — keeping a
			// floating hint visible after the user has dismissed its parent
			// error would be confusing.
			if ( class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
				$last_error = AyudaWP_AISS_AI_Summary::get_last_ai_error();
				if ( $last_error ) {
					$nonce         = wp_create_nonce( 'aiss_admin_nonce' );
					$show_hint     = ayudawp_aiss_is_canonical_ai_plugin_active();
					$approvals_url = admin_url( 'tools.php?page=ai-connector-approval' );

					echo '<div class="aiss-ai-error-notice" style="margin:8px 0 0;display:flex;align-items:flex-start;gap:10px;">';

					echo '<div style="flex:1;display:flex;flex-direction:column;gap:8px;">';

					echo '<p style="margin:0;padding:8px 12px;background:#fff;border-left:3px solid #dba617;color:#8a6d11;">'
						. '<strong>' . esc_html__( 'Last AI generation error:', 'ai-share-summarize' ) . '</strong> '
						. esc_html( $last_error['message'] )
						. ' <code style="font-size:11px;">' . esc_html( $last_error['code'] ) . '</code>'
						. '</p>';

					// Heuristic hint: when the canonical AI plugin is active,
					// its Connector Approval system can silently block our
					// calls without a clear error code. Point the admin
					// straight at the approvals screen so they can unblock
					// us without us having to ship our own HTTP client.
					if ( $show_hint ) {
						echo '<p style="margin:0;padding:8px 12px;background:#fff;border-left:3px solid #2271b1;color:#1d2327;">'
							. '<strong>' . esc_html__( 'Possible cause:', 'ai-share-summarize' ) . '</strong> '
							. sprintf(
								/* translators: %1$s/%2$s wrap a link to Tools > Connector Approvals. */
								esc_html__( 'The canonical AI plugin is active. Its Connector Approval system can silently block calls from plugins it has not approved yet. Open %1$sTools > Connector Approvals%2$s and enable the toggle next to "AI Summaries & Share Buttons".', 'ai-share-summarize' ),
								'<a href="' . esc_url( $approvals_url ) . '" target="_blank" rel="noopener">',
								'</a>'
							)
							. '</p>';
					}

					echo '</div>'; // .flex column wrapper

					echo '<button type="button" class="button-link aiss-dismiss-ai-error" data-nonce="' . esc_attr( $nonce ) . '" aria-label="' . esc_attr__( 'Dismiss this error', 'ai-share-summarize' ) . '" style="text-decoration:none;color:#8a6d11;font-size:18px;line-height:1;padding:4px 0 0;cursor:pointer;">&times;</button>';

					echo '</div>'; // .aiss-ai-error-notice
				}
			}
		} else {
			echo '<p style="margin:0;"><strong>'
				. esc_html__( 'WP AI Client not detected.', 'ai-share-summarize' )
				. '</strong> '
				. esc_html__( 'Requires WordPress 7.0 or later. Without it, only the basic extractive fallback will be used.', 'ai-share-summarize' )
				. '</p>';
		}

		echo '</div>';

		echo '<p class="description">'
			. esc_html__( 'Summaries are generated asynchronously when a post is published or updated, on the post types selected in "Summaries by content type" below. This tab saves independently from the share buttons settings.', 'ai-share-summarize' )
			. '</p>';
	}

	/**
	 * AI summary generation mode field (v2.2.0)
	 *
	 * Single explicit selector that replaces the former "Enable AI Summary" +
	 * "Use extractive fallback" checkbox pair.
	 */
	public function ayudawp_ai_summary_mode_callback() {
		$mode = ayudawp_aiss_get_summary_mode();

		$modes = array(
			'ai_fallback'     => esc_html__( 'AI with extractive fallback (recommended)', 'ai-share-summarize' ),
			'ai_only'         => esc_html__( 'AI only (no fallback)', 'ai-share-summarize' ),
			'extractive_only' => esc_html__( 'Extractive only (never contacts an AI provider)', 'ai-share-summarize' ),
			'disabled'        => esc_html__( 'Disabled', 'ai-share-summarize' ),
		);

		$descriptions = array(
			'ai_fallback'     => esc_html__( 'Generate with your AI provider; if it is unavailable, fall back to the on-server extractive summary.', 'ai-share-summarize' ),
			'ai_only'         => esc_html__( 'Generate with your AI provider only. If it fails, no summary is produced.', 'ai-share-summarize' ),
			'extractive_only' => esc_html__( 'Always use the on-server extractive summarizer. Zero cost, never contacts an AI provider.', 'ai-share-summarize' ),
			'disabled'        => esc_html__( 'Do not generate summaries at all.', 'ai-share-summarize' ),
		);

		foreach ( $modes as $key => $label ) {
			?>
			<p style="margin:0 0 8px;">
				<label>
					<input type="radio" name="ayudawp_aiss_summary_options[ai_summary_mode]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $mode, $key ); ?>>
					<strong><?php echo esc_html( $label ); ?></strong>
				</label>
				<span class="description" style="display:block;margin-left:24px;"><?php echo esc_html( $descriptions[ $key ] ); ?></span>
			</p>
			<?php
		}
	}

	/**
	 * AI model selector field (v2.2.0)
	 *
	 * Lists the text-generation models exposed by each configured AI provider
	 * (provider plugin active + credentials set). Defaults to "Automatic", which
	 * biases toward a sensible, cheaper model per provider. Shows a hint pointing
	 * to Settings > Connectors when no provider is configured.
	 */
	public function ayudawp_ai_summary_model_callback() {
		$options   = ayudawp_aiss_get_summary_options();
		$selected  = isset( $options['ai_summary_model'] ) ? (string) $options['ai_summary_model'] : '';
		$available = ayudawp_aiss_get_available_models();

		if ( empty( $available ) ) {
			$connectors_url = admin_url( 'options-connectors.php' );
			?>
			<p class="description">
				<?php esc_html_e( 'No AI provider is configured yet, so there are no models to choose from. The summary will use whichever provider you set up.', 'ai-share-summarize' ); ?>
				<a href="<?php echo esc_url( $connectors_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Configure a provider in Settings > Connectors', 'ai-share-summarize' ); ?></a>
			</p>
			<?php
			return;
		}
		?>
		<select name="ayudawp_aiss_summary_options[ai_summary_model]">
			<option value="" <?php selected( $selected, '' ); ?>><?php esc_html_e( 'Automatic (recommended): cheapest model per provider', 'ai-share-summarize' ); ?></option>
			<?php foreach ( $available as $provider_id => $data ) : ?>
				<optgroup label="<?php echo esc_attr( $data['name'] ); ?>">
					<?php
					foreach ( $data['models'] as $model ) :
						$value = $provider_id . ':' . $model['id'];
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $model['name'] ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Model used when AI generation is active. "Automatic" picks the cheapest model of each provider and falls back to another one if it is momentarily unavailable.', 'ai-share-summarize' ); ?></p>
		<?php
	}


}
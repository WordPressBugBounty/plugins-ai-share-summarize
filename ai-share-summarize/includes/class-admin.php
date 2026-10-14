<?php
/**
 * Admin functionality for Share Buttons & AI-powered Summaries plugin
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

		// Admin CSS.
		wp_enqueue_style(
			'aiss-admin-styles',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/admin-styles.css',
			array( 'ayudawp-aiss-styles' ),
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
			),
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
					<strong><?php esc_html_e( 'Share Buttons & AI-powered Summaries is active!', 'ai-share-summarize' ); ?></strong>
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
			esc_html__( 'Share Buttons & AI-powered Summaries', 'ai-share-summarize' ),
			esc_html__( 'Share Buttons & AI-powered Summaries', 'ai-share-summarize' ),
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
		 * Six sections on one page instead of one flat list of 25 rows
		 * (v2.5.0). do_settings_sections() paints them in registration order
		 * with a heading each, and they all post to the same option, so there
		 * is still a single Save button. The grouping puts related settings
		 * next to each other: until now "Enabled buttons" was row 2 and
		 * "Buttons order" row 14, with twelve unrelated rows between two halves
		 * of the same decision.
		 */
		add_settings_section(
			'ayudawp_aiss_placement',
			esc_html__( 'Where they appear', 'ai-share-summarize' ),
			array( $this, 'ayudawp_section_callback' ),
			'ayudawp_aiss_settings'
		);

		/*
		 * Buttons after placement, and with the look settings inside it: the
		 * picker is a live preview of the style, so the style controls have to
		 * be within sight of it. Split into two sections the preview scrolled
		 * off screen exactly when it was useful.
		 */
		add_settings_section(
			'ayudawp_aiss_main',
			esc_html__( 'The buttons', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_settings'
		);

		add_settings_section(
			'ayudawp_aiss_texts',
			esc_html__( 'Wording', 'ai-share-summarize' ),
			'__return_false',
			'ayudawp_aiss_settings'
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

		$this->ayudawp_register_settings_fields();
	}

	/**
	 * Register settings fields
	 */
	private function ayudawp_register_settings_fields() {
		// field id => [ label, section ].
		$fields = array(
			'placements'      => array( esc_html__( 'Where the buttons appear', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'floating_bar'    => array( esc_html__( 'Floating bar', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),
			'mobile_compact'  => array( esc_html__( 'Compact mode on phones', 'ai-share-summarize' ), 'ayudawp_aiss_placement' ),

			'enabled_buttons' => array( esc_html__( 'Enabled buttons', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),
			'button_colors'   => array( esc_html__( 'Style and icons', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),
			'button_size'     => array( esc_html__( 'Size and dark mode', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),
			'layout'          => array( esc_html__( 'Alignment and separators', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),
			'button_order'    => array( esc_html__( 'Order within each group', 'ai-share-summarize' ), 'ayudawp_aiss_main' ),

			'title'           => array( esc_html__( 'Title above the buttons', 'ai-share-summarize' ), 'ayudawp_aiss_texts' ),
			'section_titles'  => array( esc_html__( 'Section titles', 'ai-share-summarize' ), 'ayudawp_aiss_texts' ),

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
				$field[1]
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
		$page_title = 'Share Buttons & AI-powered Summaries';
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
			 * Content and promo side by side since 2.5.0, the same shape
			 * Vigilant and Widget Visibility Control use: a column of content
			 * and a 300px rail that folds underneath below 1200px. The banner
			 * was a full-width strip at the very bottom, which on a wide screen
			 * meant nobody ever scrolled to it and the form was left narrower
			 * than it needed to be for no reason.
			 */
			?>
			<div class="aiss-layout">
				<div class="aiss-layout-main">
					<?php
					if ( 'analytics' === $active_tab ) {
						AyudaWP_AISS_Analytics_Page::ayudawp_render();
					} elseif ( 'ai-summary' === $active_tab ) {
						$this->ayudawp_render_summary_tab();
					} else {
						$this->ayudawp_render_settings_tab();
					}
					?>
				</div>
				<aside class="aiss-layout-side">
					<?php
					// Promo banner - random rotation of AyudaWP services.
					$promo_banner = new AyudaWP_AISS_Promo_Banner( 'aiss' );
					$promo_banner->render( 'vertical' );
					?>
				</aside>
			</div>
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

	/**
	 * Main section callback
	 */
	public function ayudawp_section_callback() {
		/* translators: Plugin page URL. Change wordpress.org to your locale if needed (e.g., es.wordpress.org) */
		$plugin_url = __( 'https://wordpress.org/plugins/ai-share-summarize/', 'ai-share-summarize' );

		echo '<p>' . esc_html__( 'Configure how and where to display the share buttons. The inline AI summary has its own tab: AI Summary.', 'ai-share-summarize' );
		echo ' <span class="ayudawp-info-link">(' . esc_html__( 'You can also customize display using shortcodes as described in the', 'ai-share-summarize' ) . ' <a href="' . esc_url( $plugin_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'plugin page', 'ai-share-summarize' ) . '</a>)</span>';
		echo '</p>';
	}

	// ==========================================
	// Settings field callbacks
	// ==========================================


	/**
	 * Enabled buttons field
	 */
	public function ayudawp_enabled_buttons_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$enabled = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();

		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		$buttons     = ayudawp_aiss_get_available_buttons();
		$social_keys = ayudawp_aiss_get_social_buttons();
		$ai_keys     = ayudawp_aiss_get_ai_buttons();
		$actions     = ayudawp_aiss_get_action_buttons();

		/*
		 * The picker renders each option as the real button (v2.5.0): same
		 * markup, same classes, same stylesheet, so what the admin ticks is
		 * what the visitor sees. It replaces the row of bare SVG glyphs, which
		 * showed the icon but nothing about size, colour or shape.
		 */
		$style       = isset( $options['button_colors'] ) ? $options['button_colors'] : 'minimal';
		$icon_style  = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';
		$custom_bg   = isset( $options['custom_color_bg'] ) ? $options['custom_color_bg'] : '';
		$custom_text = isset( $options['custom_color_text'] ) ? $options['custom_color_text'] : '';

		$wrapper_classes = array( 'ayudawp-share-buttons', 'aiss-picker-preview' );
		if ( 'icons-only' === $style ) {
			$wrapper_classes[] = 'icons-only';
			$wrapper_classes[] = $icon_style;
		} elseif ( 'minimal' !== $style ) {
			$wrapper_classes[] = $style;
		}
		$wrapper_classes[] = 'with-icons';


		$groups = array(
			'social'  => array(
				'label' => esc_html__( 'Social networks', 'ai-share-summarize' ),
				'keys'  => $social_keys,
			),
			'ai'      => array(
				'label' => esc_html__( 'AI assistants', 'ai-share-summarize' ),
				'keys'  => $ai_keys,
			),
		);

		// Browser actions are listed apart: they do not open a destination. So
		// is the Google preferred source button (v2.6.0), which is not a share.
		$google                   = array( 'google_preferred' );
		$groups['social']['keys'] = array_values( array_diff( $groups['social']['keys'], $actions, $google ) );

		echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';

		// Escaped at the point of output rather than built into a variable and
		// echoed: a pre-composed attribute string reads as unescaped output to
		// the security sniff, and it is right to insist.
		if ( 'custom' === $style && ! empty( $custom_bg ) ) {
			printf(
				' style="--ayudawp-custom-bg:%s;--ayudawp-custom-text:%s;"',
				esc_attr( $custom_bg ),
				esc_attr( ! empty( $custom_text ) ? $custom_text : '#ffffff' )
			);
		}

		echo '>';

		foreach ( $groups as $group ) {
			echo '<p class="aiss-picker-group-title">' . esc_html( $group['label'] ) . '</p>';
			echo '<div class="aiss-picker-grid">';
			foreach ( $group['keys'] as $key ) {
				if ( ! isset( $buttons[ $key ] ) ) {
					continue;
				}
				$this->ayudawp_render_button_choice( $key, $buttons[ $key ], $enabled, $style );
			}
			echo '</div>';
		}

		echo '<p class="aiss-picker-group-title">' . esc_html__( 'Browser actions', 'ai-share-summarize' ) . '</p>';
		echo '<div class="aiss-picker-grid">';
		foreach ( $actions as $key ) {
			if ( ! isset( $buttons[ $key ] ) ) {
				continue;
			}
			$this->ayudawp_render_button_choice( $key, $buttons[ $key ], $enabled, $style );
		}
		echo '</div>';

		echo '<p class="aiss-picker-group-title">' . esc_html__( 'Google', 'ai-share-summarize' ) . '</p>';
		echo '<div class="aiss-picker-grid">';
		foreach ( $google as $key ) {
			if ( ! isset( $buttons[ $key ] ) ) {
				continue;
			}
			$this->ayudawp_render_button_choice( $key, $buttons[ $key ], $enabled, $style );
		}
		echo '</div>';

		echo '</div>';

		echo '<p class="description">' . esc_html__( 'Select which buttons to display on the frontend. Each one is shown here with the style you have chosen below.', 'ai-share-summarize' ) . '</p>';

		// The native share button behaves unlike every other one, so it gets
		// its own note right where it is ticked (v2.5.0).
		echo '<p class="description aiss-picker-note"><span class="dashicons dashicons-info-outline"></span> '
			. '<strong>' . esc_html__( 'Native share', 'ai-share-summarize' ) . '</strong>: '
			. esc_html__( 'this one has no destination of its own. It opens the share sheet of the visitor device, the same one a mobile app uses, which reaches WhatsApp, Signal, Instagram, Messages, AirDrop or whatever they have installed, without a button for each. It only appears where the browser supports it and the site is served over HTTPS, so it stays hidden on a local http install and on desktop Firefox. Where it cannot run, clicking it copies the link instead.', 'ai-share-summarize' )
			. '</p>';

		// What the Google button does, and why it would not show (v2.6.0).
		echo '<p class="description aiss-picker-note"><span class="dashicons dashicons-info-outline"></span> '
			. '<strong>' . esc_html__( 'Google preferred source', 'ai-share-summarize' ) . '</strong>: '
			. esc_html__( 'opens the Google tool where readers mark your site as a preferred source, so it shows up more in their Top Stories. It is a plain link: nothing loads from Google until someone clicks it. Clicks are counted in Analytics like any other button.', 'ai-share-summarize' );

		$google_domain = ayudawp_aiss_get_google_preferred_domain();
		if ( '' === $google_domain ) {
			echo ' <strong>' . esc_html__( 'This site cannot use it, so it will not be shown:', 'ai-share-summarize' ) . '</strong> ';
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
			echo ' ' . esc_html( sprintf( __( 'Domain sent to Google: %s', 'ai-share-summarize' ), $google_domain ) );
		}
		echo '</p>';

		// Where it goes (v2.6.0): with the social networks, in a section of
		// its own at the end of the block, or only where a shortcode asks.
		$google_place = ayudawp_aiss_get_google_preferred_placement( $options );
		$google_title = isset( $options['google_section_title'] ) ? (string) $options['google_section_title'] : '';
		$google_opts  = array(
			'social'  => __( 'With the social networks', 'ai-share-summarize' ),
			'section' => __( 'In a section of its own, at the end of the block', 'ai-share-summarize' ),
			'outside' => __( 'Outside the block, only where you place the shortcode', 'ai-share-summarize' ),
		);

		// Side by side; the title is dimmed while it would not be used.
		echo '<div class="aiss-google-placement">';
		echo '<div class="aiss-google-field"><label for="aiss-google-placement"><strong>' . esc_html__( 'Where the Google button goes', 'ai-share-summarize' ) . '</strong></label>';
		echo '<select id="aiss-google-placement" name="ayudawp_aiss_options[google_preferred_placement]">';
		foreach ( $google_opts as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $google_place, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div class="aiss-google-field aiss-google-title-field' . ( 'section' === $google_place ? '' : ' is-off' ) . '"><label for="aiss-google-section-title">' . esc_html__( 'Title of its section (optional)', 'ai-share-summarize' ) . '</label>';
		echo '<input type="text" id="aiss-google-section-title" class="regular-text" name="ayudawp_aiss_options[google_section_title]" value="' . esc_attr( $google_title ) . '" placeholder="' . esc_attr__( 'Follow us on Google', 'ai-share-summarize' ) . '"></div>';
		echo '</div>';

		echo '<p class="description">'
			. esc_html__( 'The title is only used when the button has a section of its own; without one, a thin line sets it apart. Outside the block, place it with this shortcode wherever you want it:', 'ai-share-summarize' )
			. ' <code>[ayudawp_share_buttons buttons="google_preferred"]</code></p>';
	}

	/**
	 * Render one checkbox of the button picker as the button it enables (v2.5.0)
	 *
	 * @param string $key     Button key.
	 * @param string $label   Button label.
	 * @param array  $enabled Enabled buttons.
	 * @param string $style   Current visual style.
	 */
	private function ayudawp_render_button_choice( $key, $label, $enabled, $style ) {
		$classes = array( 'ayudawp-share-btn', str_replace( '_', '-', $key ) );
		if ( in_array( $key, ayudawp_aiss_get_ai_buttons(), true ) ) {
			$classes[] = 'ai';
		}

		$icon = AyudaWP_AISS_Icons::ayudawp_get_button_icon( $key, 16 );

		echo '<label class="aiss-pick' . ( in_array( $key, $enabled, true ) ? ' is-on' : '' ) . '">';
		echo '<input type="checkbox" name="ayudawp_aiss_options[enabled_buttons][]" value="' . esc_attr( $key ) . '" '
			. checked( in_array( $key, $enabled, true ), true, false ) . '>';
		/*
		 * Both labels are always in the markup and CSS shows one or the other,
		 * so switching the style live is a class change on the wrapper and
		 * never a re-render: inside the button for the styles that carry text,
		 * next to it for "Icons only".
		 */
		echo '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-hidden="true">';
		echo wp_kses( $icon, ayudawp_aiss_kses_svg() );
		echo '<span class="ayudawp-button-text">' . esc_html( $label ) . '</span>';
		echo '</span>';
		echo '<span class="aiss-pick-name">' . esc_html( $label ) . '</span>';
		echo '</label>';
	}




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
	 * Button order field
	 */
	public function ayudawp_button_order_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$order   = isset( $options['button_order'] ) ? $options['button_order'] : 'ayudawp_ai_first';

		// Every list of choices on this screen leads with its default (v2.5.0).
		$orders = array(
			'ayudawp_ai_first'     => esc_html__( 'AI assistants first (default)', 'ai-share-summarize' ),
			'ayudawp_social_first' => esc_html__( 'Social networks first', 'ai-share-summarize' ),
			'ayudawp_mixed'        => esc_html__( 'Mixed, one from each in turn', 'ai-share-summarize' ),
		);

		foreach ( $orders as $key => $label ) {
			$checked = ( $order === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[button_order]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

		// Two-column sortable for custom internal order within each group (v1.6.0).
		$custom_order_ai     = isset( $options['button_custom_order_ai'] ) ? $options['button_custom_order_ai'] : array();
		$custom_order_social = isset( $options['button_custom_order_social'] ) ? $options['button_custom_order_social'] : array();
		$enabled             = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		$all_buttons         = ayudawp_aiss_get_available_buttons();
		$social_keys         = ayudawp_aiss_get_social_buttons();
		$ai_keys             = ayudawp_aiss_get_ai_buttons();

		// Build sorted lists per group.
		$sorted_ai     = self::ayudawp_build_sorted_list( $custom_order_ai, $enabled, $ai_keys, $all_buttons );
		$sorted_social = self::ayudawp_build_sorted_list( $custom_order_social, $enabled, $social_keys, $all_buttons );

		/*
		 * Each row shows the real button, same as the picker above (v2.5.0):
		 * the list used to draw a bare SVG next to the platform name, which was
		 * the last place in the screen where a button did not look like itself.
		 */
		$style      = isset( $options['button_colors'] ) ? $options['button_colors'] : 'minimal';
		$icon_style = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';

		$wrapper_classes = array( 'ayudawp-share-buttons', 'aiss-order-preview', 'with-icons' );
		if ( 'icons-only' === $style ) {
			$wrapper_classes[] = 'icons-only';
			$wrapper_classes[] = $icon_style;
		} elseif ( 'minimal' !== $style ) {
			$wrapper_classes[] = $style;
		}

		/*
		 * The Google preferred source button only has a place in this order
		 * while it goes with the social networks (v2.6.0). In a section of its
		 * own it is always last, and outside the block it is not in it. It is
		 * still rendered, hidden, so changing the setting shows it again
		 * without saving first.
		 */
		$google_place = ayudawp_aiss_get_google_preferred_placement( $options );

		$columns = array(
			'ai'     => array(
				'label'   => esc_html__( 'AI platforms', 'ai-share-summarize' ),
				'buttons' => $sorted_ai,
			),
			'social' => array(
				'label'   => esc_html__( 'Social platforms', 'ai-share-summarize' ),
				'buttons' => $sorted_social,
			),
		);

		echo '<div id="ayudawp-sortable-wrapper" class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';
		echo '<p class="description">' . esc_html__( 'Drag and drop to reorder within each group. Only the buttons you enabled above are listed.', 'ai-share-summarize' ) . '</p>';
		echo '<div class="aiss-two-col">';

		foreach ( $columns as $group => $column ) {
			echo '<div>';
			echo '<strong>' . esc_html( $column['label'] ) . '</strong>';
			echo '<ul class="ayudawp-sortable-list aiss-order-list" id="ayudawp-sortable-' . esc_attr( $group ) . '">';

			foreach ( $column['buttons'] as $btn ) {
				$classes = array( 'ayudawp-share-btn', str_replace( '_', '-', $btn ) );
				if ( 'ai' === $group ) {
					$classes[] = 'ai';
				}
				?>
				<li data-button="<?php echo esc_attr( $btn ); ?>"<?php echo ( 'google_preferred' === $btn && 'social' !== $google_place ) ? ' hidden' : ''; ?>>
					<span class="dashicons dashicons-menu aiss-order-grip" aria-hidden="true"></span>
					<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" aria-hidden="true">
						<?php echo wp_kses( AyudaWP_AISS_Icons::ayudawp_get_button_icon( $btn, 16 ), ayudawp_aiss_kses_svg() ); ?>
						<span class="ayudawp-button-text"><?php echo esc_html( $all_buttons[ $btn ] ); ?></span>
					</span>
					<span class="aiss-pick-name"><?php echo esc_html( $all_buttons[ $btn ] ); ?></span>
					<input type="hidden" name="ayudawp_aiss_options[button_custom_order_<?php echo esc_attr( $group ); ?>][]" value="<?php echo esc_attr( $btn ); ?>">
				</li>
				<?php
			}

			echo '</ul>';

			if ( 'social' === $group && in_array( 'google_preferred', $column['buttons'], true ) ) {
				$google_notes = array(
					'section' => __( 'Google preferred source is not in this list: it goes in a section of its own, at the end of the block.', 'ai-share-summarize' ),
					'outside' => __( 'Google preferred source is not in this list: it goes outside the block, only where you place its shortcode.', 'ai-share-summarize' ),
				);
				foreach ( $google_notes as $place => $note ) {
					echo '<p class="description aiss-google-order-note" data-placement="' . esc_attr( $place ) . '"' . ( $place === $google_place ? '' : ' hidden' ) . '>' . esc_html( $note ) . '</p>';
				}
			}

			echo '</div>';
		}

		echo '</div></div>';
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
	 * Mobile compact mode (v2.5.0)
	 */
	public function ayudawp_mobile_compact_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$mode    = isset( $options['mobile_compact'] ) ? $options['mobile_compact'] : 'single';
		$visible = isset( $options['mobile_compact_visible'] ) ? (int) $options['mobile_compact_visible'] : 3;
		$visible = max( 1, min( 5, $visible ) );

		$modes = array(
			'single'   => esc_html__( 'A single Share button that opens the rest (default)', 'ai-share-summarize' ),
			'partial'  => esc_html__( 'A few buttons plus a More button', 'ai-share-summarize' ),
			'disabled' => esc_html__( 'Disabled: show every button', 'ai-share-summarize' ),
		);

		echo '<p class="description">' . esc_html__( 'How much room the buttons take on a phone. The desktop layout is not affected.', 'ai-share-summarize' ) . '</p>';

		foreach ( $modes as $key => $label ) {
			echo '<label class="aiss-stacked">
					<input type="radio" name="ayudawp_aiss_options[mobile_compact]" value="' . esc_attr( $key ) . '" '
					. checked( $mode, $key, false ) . '> ' . esc_html( $label ) . '
				  </label>';
		}
		?>
		<label class="aiss-stacked" style="margin-top:10px">
			<?php esc_html_e( 'Buttons visible before the More button:', 'ai-share-summarize' ); ?>
			<input type="number" min="1" max="5" step="1" style="width: 70px;" name="ayudawp_aiss_options[mobile_compact_visible]" value="<?php echo esc_attr( (string) $visible ); ?>">
		</label>
		<p class="description"><?php esc_html_e( 'The visible ones are the first in the order you set below, so drag the platforms you care about to the top of their group.', 'ai-share-summarize' ); ?></p>
		<?php
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
	 * Floating bar (v2.5.0)
	 *
	 * Same table as the buttons, with the positions the bar actually has: an
	 * edge of the viewport, not a place in the content. That is also how it is
	 * switched on — a row ticked is a page where the bar shows — so there is no
	 * separate on/off radio and no "use its own destinations" checkbox to fold
	 * a second table away.
	 */
	public function ayudawp_floating_bar_callback() {
		$options     = get_option( 'ayudawp_aiss_options' );
		$autohide    = ! empty( $options['floating_bar_autohide'] );
		$hide_mobile = ! isset( $options['floating_bar_hide_mobile'] ) || ! empty( $options['floating_bar_hide_mobile'] );
		$hide_inline = ! empty( $options['floating_bar_hide_with_inline'] );

		echo '<p class="description">' . esc_html__( 'A sticky bar with the same enabled buttons, in icon form, pinned to the edge of the screen. It does not have to appear where the block above appears: the block on posts and the bar on archives works.', 'ai-share-summarize' ) . '</p>';

		$this->ayudawp_placements_callback( array( 'key' => 'floating_bar_placements' ) );

		echo '<p class="description">' . esc_html__( 'With nothing ticked the bar is off, which is how it comes.', 'ai-share-summarize' ) . '</p>';
		?>
		<label class="aiss-stacked" style="margin-top:12px">
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
	 * Alignment and separator lines (v2.5.0, two settings in one row)
	 */
	public function ayudawp_layout_callback() {
		$options          = get_option( 'ayudawp_aiss_options' );
		$alignment        = isset( $options['button_alignment'] ) ? $options['button_alignment'] : 'left';
		$separator_top    = isset( $options['separator_top'] ) ? $options['separator_top'] : true;
		$separator_bottom = isset( $options['separator_bottom'] ) ? $options['separator_bottom'] : true;
		?>
		<div class="aiss-two-col">
			<div>
				<strong><?php esc_html_e( 'Alignment', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="radio" name="ayudawp_aiss_options[button_alignment]" value="left" <?php checked( $alignment, 'left' ); ?>>
					<?php esc_html_e( 'Left (default)', 'ai-share-summarize' ); ?>
				</label>
				<label class="aiss-stacked">
					<input type="radio" name="ayudawp_aiss_options[button_alignment]" value="center" <?php checked( $alignment, 'center' ); ?>>
					<?php esc_html_e( 'Centered', 'ai-share-summarize' ); ?>
				</label>
			</div>
			<div>
				<strong><?php esc_html_e( 'Separator lines', 'ai-share-summarize' ); ?></strong>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_options[separator_top]" value="1" <?php checked( $separator_top, true ); ?>>
					<?php esc_html_e( 'Line above the block', 'ai-share-summarize' ); ?>
				</label>
				<label class="aiss-stacked">
					<input type="checkbox" name="ayudawp_aiss_options[separator_bottom]" value="1" <?php checked( $separator_bottom, true ); ?>>
					<?php esc_html_e( 'Line below the block', 'ai-share-summarize' ); ?>
				</label>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Alignment does not apply to phones, where the buttons adapt on their own.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Title text and its HTML element (v2.5.0, two settings in one row)
	 */
	public function ayudawp_title_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$text    = isset( $options['title_text'] ) ? $options['title_text'] : '';
		$style   = isset( $options['title_style'] ) ? $options['title_style'] : 'span';

		$styles = array(
			'span' => esc_html__( 'Span, plain text (default)', 'ai-share-summarize' ),
			'h3'   => esc_html__( 'Heading 3 (h3)', 'ai-share-summarize' ),
			'h4'   => esc_html__( 'Heading 4 (h4)', 'ai-share-summarize' ),
			'h5'   => esc_html__( 'Heading 5 (h5)', 'ai-share-summarize' ),
			'h6'   => esc_html__( 'Heading 6 (h6)', 'ai-share-summarize' ),
		);
		?>
		<div class="aiss-two-col">
			<div>
				<label for="aiss-title-text"><strong><?php esc_html_e( 'Text', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="aiss-title-text" class="regular-text aiss-full"
					name="ayudawp_aiss_options[title_text]" value="<?php echo esc_attr( $text ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. Share this', 'ai-share-summarize' ); ?>">
				<p class="description"><?php esc_html_e( 'Leave it empty for no title. Ignored when section titles are in use.', 'ai-share-summarize' ); ?></p>
			</div>
			<div>
				<label for="aiss-title-style"><strong><?php esc_html_e( 'HTML element', 'ai-share-summarize' ); ?></strong></label>
				<select id="aiss-title-style" class="aiss-full" name="ayudawp_aiss_options[title_style]">
					<?php foreach ( $styles as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $style, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Applies to the section titles too.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
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
	 * Button colors field
	 */
	public function ayudawp_button_colors_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$style   = isset( $options['button_colors'] ) ? $options['button_colors'] : 'minimal';

		$styles = array(
			'minimal'    => esc_html__( 'Minimal (default)', 'ai-share-summarize' ),
			'brand'      => esc_html__( 'Brand colours', 'ai-share-summarize' ),
			'outline'    => esc_html__( 'Outline (brand borders)', 'ai-share-summarize' ),
			'dark'       => esc_html__( 'For dark backgrounds', 'ai-share-summarize' ),
			'custom'     => esc_html__( 'Custom colors', 'ai-share-summarize' ),
			'icons-only' => esc_html__( 'Icons only', 'ai-share-summarize' ),
		);

		$show_icons = isset( $options['show_icons'] ) ? $options['show_icons'] : false;
		$icon_style = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';

		echo '<div class="aiss-two-col"><div>';
		foreach ( $styles as $key => $label ) {
			echo '<label class="aiss-stacked">
					<input type="radio" class="aiss-style-input" name="ayudawp_aiss_options[button_colors]" value="' . esc_attr( $key ) . '" '
					. checked( $style, $key, false ) . '> ' . esc_html( $label ) . '
				  </label>';
		}
		echo '</div><div>';
		?>
			<label class="aiss-stacked">
				<input type="checkbox" class="aiss-icons-input" name="ayudawp_aiss_options[show_icons]" value="1" <?php checked( $show_icons, true ); ?>>
				<?php esc_html_e( 'Add icons next to the text', 'ai-share-summarize' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Not used by the "Icons only" style, which is all icon.', 'ai-share-summarize' ); ?></p>
			<label for="aiss-icon-style" class="aiss-stacked"><strong><?php esc_html_e( 'Icon corners', 'ai-share-summarize' ); ?></strong></label>
			<select id="aiss-icon-style" class="aiss-icon-style-input" name="ayudawp_aiss_options[icon_style]">
				<option value="circular" <?php selected( $icon_style, 'circular' ); ?>><?php esc_html_e( 'Circular', 'ai-share-summarize' ); ?></option>
				<option value="square" <?php selected( $icon_style, 'square' ); ?>><?php esc_html_e( 'Square corners', 'ai-share-summarize' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Only affects the "Icons only" style.', 'ai-share-summarize' ); ?></p>
		<?php
		echo '</div></div>';

		// Custom color picker fields.
		$custom_bg   = isset( $options['custom_color_bg'] ) ? $options['custom_color_bg'] : '#333333';
		$custom_text = isset( $options['custom_color_text'] ) ? $options['custom_color_text'] : '#ffffff';
		?>
		<div class="ayudawp-color-picker-row" id="ayudawp-custom-colors" style="<?php echo 'custom' !== $style ? 'display:none;' : ''; ?>">
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
		echo '<p class="description">' . esc_html__( 'The preview in "Enabled buttons" above updates as you change these, before saving.', 'ai-share-summarize' ) . '</p>';
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
	 * Section titles (v1.6.0)
	 *
	 * On a grid since 2.5.0: the flex row it used before paired `flex: 1` with
	 * the fixed width of `regular-text`, so on a wide screen the inputs kept
	 * their size and the second column drifted to the far right instead of
	 * lining up with the first.
	 */
	public function ayudawp_section_titles_callback() {
		$options      = get_option( 'ayudawp_aiss_options' );
		$ai_title     = isset( $options['ai_section_title'] ) ? $options['ai_section_title'] : '';
		$social_title = isset( $options['social_section_title'] ) ? $options['social_section_title'] : '';
		?>
		<div class="aiss-two-col">
			<div>
				<label for="ayudawp_ai_section_title"><strong><?php esc_html_e( 'AI section title', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="ayudawp_ai_section_title" class="regular-text aiss-full" name="ayudawp_aiss_options[ai_section_title]" value="<?php echo esc_attr( $ai_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Summarize with AI', 'ai-share-summarize' ); ?>">
			</div>
			<div>
				<label for="ayudawp_social_section_title"><strong><?php esc_html_e( 'Social section title', 'ai-share-summarize' ); ?></strong></label>
				<input type="text" id="ayudawp_social_section_title" class="regular-text aiss-full" name="ayudawp_aiss_options[social_section_title]" value="<?php echo esc_attr( $social_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Share on social media', 'ai-share-summarize' ); ?>">
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Optional. When set, each group gets its own heading and the general title above is hidden. Leave both empty to use only the general title. Does not apply to Mixed order.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Button size and dark mode adaptation (v2.5.0, two settings in one row)
	 *
	 * Both lists lead with their default, which the size one did not: "Compact"
	 * sat first and "Normal" third, so the option in force looked like a change
	 * from what was above it.
	 */
	public function ayudawp_button_size_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$size    = isset( $options['button_size'] ) ? $options['button_size'] : 'normal';
		$dark    = isset( $options['dark_mode_adaptation'] ) ? $options['dark_mode_adaptation'] : 'disabled';

		$sizes = array(
			'normal'  => esc_html__( 'Normal (default)', 'ai-share-summarize' ),
			'compact' => esc_html__( 'Compact', 'ai-share-summarize' ),
			'large'   => esc_html__( 'Large', 'ai-share-summarize' ),
			'fluid'   => esc_html__( 'Fluid: fill the available width', 'ai-share-summarize' ),
		);

		$dark_modes = array(
			'disabled' => esc_html__( 'Disabled (default)', 'ai-share-summarize' ),
			'auto'     => esc_html__( 'Automatic', 'ai-share-summarize' ),
		);
		?>
		<div class="aiss-two-col">
			<div>
				<strong><?php esc_html_e( 'Size', 'ai-share-summarize' ); ?></strong>
				<?php foreach ( $sizes as $key => $label ) : ?>
					<label class="aiss-stacked">
						<input type="radio" name="ayudawp_aiss_options[button_size]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $size, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<div>
				<strong><?php esc_html_e( 'Dark backgrounds', 'ai-share-summarize' ); ?></strong>
				<?php foreach ( $dark_modes as $key => $label ) : ?>
					<label class="aiss-stacked">
						<input type="radio" name="ayudawp_aiss_options[dark_mode_adaptation]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $dark, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Automatic measures the real background behind the block and adapts the buttons that would disappear on it. It costs a small script on the page.', 'ai-share-summarize' ); ?></p>
			</div>
		</div>
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
								esc_html__( 'The canonical AI plugin is active. Its Connector Approval system can silently block calls from plugins it has not approved yet. Open %1$sTools > Connector Approvals%2$s and enable the toggle next to "Share Buttons & AI-powered Summaries".', 'ai-share-summarize' ),
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
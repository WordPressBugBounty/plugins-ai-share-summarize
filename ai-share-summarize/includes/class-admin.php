<?php
/**
 * Admin functionality for AI Share & Summarize plugin
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

		// Admin CSS.
		wp_enqueue_style(
			'aiss-admin-styles',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/admin-styles.css',
			array(),
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
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'analytics'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Settings tab: load color picker and sortable.
		if ( 'settings' === $tab ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
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
					<strong><?php esc_html_e( 'AI Share & Summarize is active!', 'ai-share-summarize' ); ?></strong>
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

		update_option( 'ayudawp_aiss_activation_notice_dismissed', true );
		wp_die();
	}

	/**
	 * Add admin menu
	 */
	public function ayudawp_add_admin_menu() {
		add_options_page(
			esc_html__( 'AI Share & Summarize', 'ai-share-summarize' ),
			esc_html__( 'AI Share & Summarize', 'ai-share-summarize' ),
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

		add_settings_section(
			'ayudawp_aiss_main',
			esc_html__( 'General Settings', 'ai-share-summarize' ),
			array( $this, 'ayudawp_section_callback' ),
			'ayudawp_aiss_settings'
		);

		$this->ayudawp_register_settings_fields();
	}

	/**
	 * Register settings fields
	 */
	private function ayudawp_register_settings_fields() {
		$fields = array(
			'default_prompt'             => esc_html__( 'AIs Default Prompt', 'ai-share-summarize' ),
			'enabled_buttons'            => esc_html__( 'Enabled buttons', 'ai-share-summarize' ),
			'display_locations'          => esc_html__( 'Where to display the buttons', 'ai-share-summarize' ),
			'auto_insert_position'       => esc_html__( 'Automatic insertion position', 'ai-share-summarize' ),
			'auto_insert_content_types'  => esc_html__( 'Automatic insertion by content type', 'ai-share-summarize' ),
			'seo_integration'            => esc_html__( 'SEO Integration', 'ai-share-summarize' ),
			'twitter_handle'             => esc_html__( 'X handle to mention', 'ai-share-summarize' ),
			'mastodon_instance'          => esc_html__( 'Mastodon instance', 'ai-share-summarize' ),
			'custom_text'                => esc_html__( 'Custom text in prompts', 'ai-share-summarize' ),
			'title_text'                 => esc_html__( 'Title text before buttons', 'ai-share-summarize' ),
			'section_titles'             => esc_html__( 'Section titles', 'ai-share-summarize' ),
			'title_style'                => esc_html__( 'Title style', 'ai-share-summarize' ),
			'button_order'               => esc_html__( 'Buttons order', 'ai-share-summarize' ),
			'button_alignment'           => esc_html__( 'Button alignment', 'ai-share-summarize' ),
			'button_colors'              => esc_html__( 'Button style', 'ai-share-summarize' ),
			'button_size'                => esc_html__( 'Button size', 'ai-share-summarize' ),
			'show_icons'                 => esc_html__( 'Show icons', 'ai-share-summarize' ),
			'icon_style'                 => esc_html__( 'Icons only style', 'ai-share-summarize' ),
			'seo_button_type'            => esc_html__( 'Button HTML Type', 'ai-share-summarize' ),
			'data_retention'             => esc_html__( 'Data retention', 'ai-share-summarize' ),
		);

		foreach ( $fields as $field_id => $field_title ) {
			add_settings_field(
				$field_id,
				$field_title,
				array( $this, 'ayudawp_' . $field_id . '_callback' ),
				'ayudawp_aiss_settings',
				'ayudawp_aiss_main'
			);
		}
	}

	/**
	 * Admin page content with tabs
	 */
	public function ayudawp_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'analytics'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_url   = admin_url( 'options-general.php?page=ai-share-summarize' );

		// Dynamic page title based on active tab.
		$page_title = 'AI Share & Summarize';
		if ( 'analytics' === $active_tab ) {
			$page_title .= ' — ' . esc_html__( 'Analytics', 'ai-share-summarize' );
		}
		?>
		<div class="wrap aiss-wrap">
			<h1><span class="dashicons dashicons-format-status aiss-page-icon"></span> <?php echo esc_html( $page_title ); ?></h1>

			<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $page_url ); ?>" class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Analytics', 'ai-share-summarize' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $page_url ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'ai-share-summarize' ); ?>
			</a>
			</nav>

			<?php
			if ( 'analytics' === $active_tab ) {
				AyudaWP_AISS_Analytics_Page::ayudawp_render();
			} else {
				$this->ayudawp_render_settings_tab();
			}
			?>

			<?php
			// Promo banner - dynamic rotation from AyudaWP catalog.
			$promo_banner = new AyudaWP_AISS_Promo_Banner(
				'ai-share-summarize',
				'ai-share-summarize',
				'aiss'
			);
			$promo_banner->render( 'horizontal' );
			?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab content
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
	 * Main section callback
	 */
	public function ayudawp_section_callback() {
		/* translators: Plugin page URL. Change wordpress.org to your locale if needed (e.g., es.wordpress.org) */
		$plugin_url = __( 'https://wordpress.org/plugins/ai-share-summarize/', 'ai-share-summarize' );

		echo '<p>' . esc_html__( 'Configure how and where to display share buttons and generate summaries with AI.', 'ai-share-summarize' );
		echo ' <span class="ayudawp-info-link">(' . esc_html__( 'You can also customize display using shortcodes as described in the', 'ai-share-summarize' ) . ' <a href="' . esc_url( $plugin_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'plugin page', 'ai-share-summarize' ) . '</a>)</span>';
		echo '</p>';
	}

	// ==========================================
	// Settings field callbacks
	// ==========================================

	/**
	 * Default prompt field
	 */
	public function ayudawp_default_prompt_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$value   = isset( $options['default_prompt'] ) ? $options['default_prompt'] : ayudawp_aiss_get_default_prompt();
		?>
		<textarea name="ayudawp_aiss_options[default_prompt]" rows="5" cols="70" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Prompt to be sent to AIs to generate the summary with citation.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Enabled buttons field
	 */
	public function ayudawp_enabled_buttons_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$enabled = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();

		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		$buttons = ayudawp_aiss_get_available_buttons();

		echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">';

		foreach ( $buttons as $key => $label ) {
			$checked = in_array( $key, $enabled, true ) ? 'checked="checked"' : '';
			$icon_preview = AyudaWP_AISS_Icons::ayudawp_get_icon( $key, 16 );

			echo '<label style="display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="ayudawp_aiss_options[enabled_buttons][]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					<span class="ayudawp-icon-preview">' . wp_kses( $icon_preview, array( 'svg' => array( 'width' => array(), 'height' => array(), 'viewBox' => array(), 'fill' => array(), 'role' => array(), 'class' => array(), 'aria-hidden' => array() ), 'path' => array( 'd' => array(), 'fill' => array(), 'stroke' => array() ), 'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array(), 'stroke' => array() ), 'rect' => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'rx' => array(), 'ry' => array() ), 'polyline' => array( 'points' => array() ), 'span' => array( 'class' => array() ) ) ) . ' <span>' . esc_html( $label ) . '</span></span>
				  </label>';
		}

		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Select which buttons to display on the frontend.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Display locations field
	 */
	public function ayudawp_display_locations_callback() {
		$options   = get_option( 'ayudawp_aiss_options' );
		$locations = isset( $options['display_locations'] ) ? $options['display_locations'] : array();

		if ( ! is_array( $locations ) ) {
			$locations = array();
		}

		$available_locations = array(
			'posts'    => esc_html__( 'Posts', 'ai-share-summarize' ),
			'pages'    => esc_html__( 'Pages', 'ai-share-summarize' ),
			'archives' => esc_html__( 'Archives', 'ai-share-summarize' ),
			'home'     => esc_html__( 'Home', 'ai-share-summarize' ),
			'cpt'      => esc_html__( 'Custom Post Types', 'ai-share-summarize' ),
		);

		echo '<p class="description">' . esc_html__( 'Select in which types of content to display the buttons:', 'ai-share-summarize' ) . '</p>';

		foreach ( $available_locations as $key => $label ) {
			$checked = in_array( $key, $locations, true ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="ayudawp_aiss_options[display_locations][]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}
	}

	/**
	 * Auto insert position field
	 */
	public function ayudawp_auto_insert_position_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';

		$positions = array(
			'disabled'       => esc_html__( 'Disabled (shortcode only mode)', 'ai-share-summarize' ),
			'before_content' => esc_html__( 'Before the content', 'ai-share-summarize' ),
			'after_content'  => esc_html__( 'After the content', 'ai-share-summarize' ),
			'both'           => esc_html__( 'Before and after the content', 'ai-share-summarize' ),
		);

		echo '<p class="description">' . esc_html__( 'Select where to automatically insert the buttons:', 'ai-share-summarize' ) . '</p>';

		foreach ( $positions as $key => $label ) {
			$checked = ( $position === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[auto_insert_position]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

		echo '<p class="description" style="margin-top: 10px;"><strong>' . esc_html__( 'Note:', 'ai-share-summarize' ) . '</strong> ' . esc_html__( 'When disabled, it will only be displayed using the shortcode [ayudawp_share_buttons].', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Auto insert content types field
	 */
	public function ayudawp_auto_insert_content_types_callback() {
		$options       = get_option( 'ayudawp_aiss_options' );
		$content_types = isset( $options['auto_insert_content_types'] ) ? $options['auto_insert_content_types'] : array();

		if ( ! is_array( $content_types ) ) {
			$content_types = array();
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<p class="description">' . esc_html__( 'Select the types of content in which to automatically insert the buttons:', 'ai-share-summarize' ) . '</p>';
		echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 5px;">';

		foreach ( $post_types as $post_type ) {
			$checked = in_array( $post_type->name, $content_types, true ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="ayudawp_aiss_options[auto_insert_content_types][]" value="' . esc_attr( $post_type->name ) . '" ' . esc_attr( $checked ) . '>
					<strong>' . esc_html( $post_type->label ) . '</strong> (' . esc_html( $post_type->name ) . ')
				  </label>';
		}

		echo '</div>';
		echo '<p class="description" style="margin-top: 10px;">' . esc_html__( 'It will only be applied to singular content (not to lists or files).', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * SEO Integration callback
	 */
	public function ayudawp_seo_integration_callback() {
		$options         = get_option( 'ayudawp_aiss_options' );
		$exclude_noindex = isset( $options['exclude_noindex'] ) ? $options['exclude_noindex'] : true;
		$seo_plugin      = AyudaWP_AISS_SEO_Integration::ayudawp_detect_seo_plugin();
		$has_noindexer   = class_exists( 'Noindexer_Frontend' );
		?>
		<div class="ayudawp-seo-integration">
			<label style="display: flex; align-items: center; gap: 8px;">
				<input type="checkbox" name="ayudawp_aiss_options[exclude_noindex]" value="1" <?php checked( $exclude_noindex, true ); ?>>
				<?php esc_html_e( 'Exclude noindex content', 'ai-share-summarize' ); ?>
			</label>
			<p class="description" style="margin-top: 8px; margin-left: 24px;">
				<?php esc_html_e( 'Automatically exclude content marked as noindex in your SEO plugin.', 'ai-share-summarize' ); ?>
				<?php if ( $seo_plugin || $has_noindexer ) : ?>
					<span class="ayudawp-seo-detected">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php
						$detected = array();
						if ( $seo_plugin ) {
							$detected[] = $seo_plugin['name'];
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
			<?php esc_html_e( 'Compatible with:', 'ai-share-summarize' ); ?> Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, NoIndexer
		</p>
		<?php
	}

	/**
	 * Twitter handle field
	 */
	public function ayudawp_twitter_handle_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$value   = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
		?>
		<input type="text" name="ayudawp_aiss_options[twitter_handle]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( '@yourname', 'ai-share-summarize' ); ?>">
		<p class="description"><?php esc_html_e( 'X (Twitter) user to mention (include the @)', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Custom text field
	 */
	public function ayudawp_custom_text_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$value   = isset( $options['custom_text'] ) ? $options['custom_text'] : '';
		?>
		<textarea name="ayudawp_aiss_options[custom_text]" rows="3" cols="70" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Additional text to be added at the end of AI prompts, before the link to the source.', 'ai-share-summarize' ); ?>
			<br>
			<strong><?php esc_html_e( 'Tip:', 'ai-share-summarize' ); ?></strong>
			<?php esc_html_e( 'Google AI Mode responds in English by default. To get responses in your language, add: "Deliver the response in [your language]"', 'ai-share-summarize' ); ?>
		</p>
		<?php
	}

	/**
	 * Title text field
	 */
	public function ayudawp_title_text_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$value   = isset( $options['title_text'] ) ? $options['title_text'] : '';
		?>
		<input type="text" name="ayudawp_aiss_options[title_text]" value="<?php echo esc_attr( $value ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Social Share or Summarize with AI', 'ai-share-summarize' ); ?>">
		<p class="description"><?php esc_html_e( 'Text to display before the buttons. Leave empty to hide the title completely.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Title style field
	 */
	public function ayudawp_title_style_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$style   = isset( $options['title_style'] ) ? $options['title_style'] : 'span';

		$styles = array(
			'h3'   => esc_html__( 'Heading 3 (h3)', 'ai-share-summarize' ),
			'h4'   => esc_html__( 'Heading 4 (h4)', 'ai-share-summarize' ),
			'h5'   => esc_html__( 'Heading 5 (h5)', 'ai-share-summarize' ),
			'h6'   => esc_html__( 'Heading 6 (h6)', 'ai-share-summarize' ),
			'span' => esc_html__( 'Span (plain text)', 'ai-share-summarize' ),
		);

		echo '<p class="description">' . esc_html__( 'Select the HTML element for the title:', 'ai-share-summarize' ) . '</p>';

		foreach ( $styles as $key => $label ) {
			$checked = ( $style === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[title_style]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}
	}

	/**
	 * Button order field
	 */
	public function ayudawp_button_order_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$order   = isset( $options['button_order'] ) ? $options['button_order'] : 'ayudawp_ai_first';

		$orders = array(
			'ayudawp_social_first' => esc_html__( 'Social media first', 'ai-share-summarize' ),
			'ayudawp_ai_first'     => esc_html__( 'AIs first', 'ai-share-summarize' ),
			'ayudawp_mixed'        => esc_html__( 'Mixed', 'ai-share-summarize' ),
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

		$svg_kses = array(
			'svg'    => array( 'width' => array(), 'height' => array(), 'viewBox' => array(), 'fill' => array(), 'role' => array(), 'class' => array(), 'aria-hidden' => array() ),
			'path'   => array( 'd' => array(), 'fill' => array(), 'stroke' => array() ),
			'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array(), 'fill' => array() ),
			'rect'   => array( 'x' => array(), 'y' => array(), 'width' => array(), 'height' => array(), 'rx' => array(), 'ry' => array() ),
			'span'   => array( 'class' => array() ),
		);

		echo '<div id="ayudawp-sortable-wrapper" style="margin-top:12px;">';
		echo '<p class="description" style="margin-bottom:8px;">' . esc_html__( 'Drag and drop to reorder within each group:', 'ai-share-summarize' ) . '</p>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:600px;">';

		// AI column.
		echo '<div>';
		echo '<strong style="display:block;margin-bottom:6px;">' . esc_html__( 'AI platforms', 'ai-share-summarize' ) . '</strong>';
		echo '<ul class="ayudawp-sortable-list" id="ayudawp-sortable-ai" style="margin:0;padding:0;list-style:none;min-height:40px;">';
		foreach ( $sorted_ai as $btn ) {
			$icon = AyudaWP_AISS_Icons::ayudawp_get_icon( $btn, 14 );
			echo '<li data-button="' . esc_attr( $btn ) . '" style="display:flex;align-items:center;gap:6px;padding:4px 8px;margin-bottom:3px;background:#f8f9fa;border:1px solid #ddd;border-radius:3px;cursor:grab;font-size:13px;">';
			echo '<span class="dashicons dashicons-menu" style="color:#bbb;font-size:14px;width:14px;height:14px;line-height:14px;"></span>';
			echo wp_kses( $icon, $svg_kses );
			echo '<span>' . esc_html( $all_buttons[ $btn ] ) . '</span>';
			echo '<input type="hidden" name="ayudawp_aiss_options[button_custom_order_ai][]" value="' . esc_attr( $btn ) . '">';
			echo '</li>';
		}
		echo '</ul></div>';

		// Social column.
		echo '<div>';
		echo '<strong style="display:block;margin-bottom:6px;">' . esc_html__( 'Social platforms', 'ai-share-summarize' ) . '</strong>';
		echo '<ul class="ayudawp-sortable-list" id="ayudawp-sortable-social" style="margin:0;padding:0;list-style:none;min-height:40px;">';
		foreach ( $sorted_social as $btn ) {
			$icon = AyudaWP_AISS_Icons::ayudawp_get_icon( $btn, 14 );
			echo '<li data-button="' . esc_attr( $btn ) . '" style="display:flex;align-items:center;gap:6px;padding:4px 8px;margin-bottom:3px;background:#f8f9fa;border:1px solid #ddd;border-radius:3px;cursor:grab;font-size:13px;">';
			echo '<span class="dashicons dashicons-menu" style="color:#bbb;font-size:14px;width:14px;height:14px;line-height:14px;"></span>';
			echo wp_kses( $icon, $svg_kses );
			echo '<span>' . esc_html( $all_buttons[ $btn ] ) . '</span>';
			echo '<input type="hidden" name="ayudawp_aiss_options[button_custom_order_social][]" value="' . esc_attr( $btn ) . '">';
			echo '</li>';
		}
		echo '</ul></div>';

		echo '</div>'; // grid.
		echo '</div>'; // wrapper.
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
	 * Button alignment field
	 */
	public function ayudawp_button_alignment_callback() {
		$options   = get_option( 'ayudawp_aiss_options' );
		$alignment = isset( $options['button_alignment'] ) ? $options['button_alignment'] : 'left';

		$alignments = array(
			'left'   => esc_html__( 'Left (default)', 'ai-share-summarize' ),
			'center' => esc_html__( 'Centered', 'ai-share-summarize' ),
		);

		foreach ( $alignments as $key => $label ) {
			$checked = ( $alignment === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[button_alignment]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

		echo '<p class="description" style="margin-top: 10px;">' . esc_html__( 'This setting does not affect mobile devices, where buttons adapt automatically for better usability.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Button colors field
	 */
	public function ayudawp_button_colors_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$style   = isset( $options['button_colors'] ) ? $options['button_colors'] : 'brand';

		$styles = array(
			'minimal'    => esc_html__( 'Minimal (default)', 'ai-share-summarize' ),
			'brand'      => esc_html__( 'Brand colors', 'ai-share-summarize' ),
			'outline'    => esc_html__( 'Outline (brand borders)', 'ai-share-summarize' ),
			'dark'       => esc_html__( 'For dark backgrounds', 'ai-share-summarize' ),
			'custom'     => esc_html__( 'Custom colors', 'ai-share-summarize' ),
			'icons-only' => esc_html__( 'Icons only', 'ai-share-summarize' ),
		);

		foreach ( $styles as $key => $label ) {
			$checked = ( $style === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[button_colors]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

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
		echo '<p class="description">' . esc_html__( 'Select the visual style for the buttons. Icons only style shows only icons without text.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * Show icons field
	 */
	public function ayudawp_show_icons_callback() {
		$options    = get_option( 'ayudawp_aiss_options' );
		$show_icons = isset( $options['show_icons'] ) ? $options['show_icons'] : false;
		?>
		<label>
			<input type="checkbox" name="ayudawp_aiss_options[show_icons]" value="1" <?php checked( $show_icons, true ); ?>>
			<?php esc_html_e( 'Add icons to buttons', 'ai-share-summarize' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Show icons alongside text in buttons (does not apply to "Icons only" style).', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Icon style field
	 */
	public function ayudawp_icon_style_callback() {
		$options    = get_option( 'ayudawp_aiss_options' );
		$icon_style = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';

		$styles = array(
			'square'   => esc_html__( 'Square corners', 'ai-share-summarize' ),
			'circular' => esc_html__( 'Circular', 'ai-share-summarize' ),
		);

		echo '<p class="description">' . esc_html__( 'Select the corner style for icons-only buttons:', 'ai-share-summarize' ) . '</p>';

		foreach ( $styles as $key => $label ) {
			$checked = ( $icon_style === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[icon_style]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

		echo '<p class="description" style="margin-top: 10px;">' . esc_html__( 'This setting only affects the "Icons only" button style.', 'ai-share-summarize' ) . '</p>';
	}

	/**
	 * SEO button type field
	 */
	public function ayudawp_seo_button_type_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$seo_type = isset( $options['seo_button_type'] ) ? $options['seo_button_type'] : 'link';
		?>
		<label style="display: block; margin-bottom: 5px;">
			<input type="radio" name="ayudawp_aiss_options[seo_button_type]" value="link" <?php checked( $seo_type, 'link' ); ?>>
			<?php esc_html_e( 'Default <a> links with nofollow', 'ai-share-summarize' ); ?>
		</label>
		<label style="display: block; margin-bottom: 5px;">
			<input type="radio" name="ayudawp_aiss_options[seo_button_type]" value="button" <?php checked( $seo_type, 'button' ); ?>>
			<?php esc_html_e( 'SEO optimized <button> elements', 'ai-share-summarize' ); ?>
		</label>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Default links already include "nofollow", so they do not pass PageRank. Button elements are not counted as links by search engines but require JavaScript and lose right-click/middle-click functionality.', 'ai-share-summarize' ); ?>
		</p>
		<?php
	}

	/**
	 * Data retention settings: retention period, delete all button, and uninstall cleanup
	 */
	public function ayudawp_data_retention_callback() {
		$options         = get_option( 'ayudawp_aiss_options' );
		$retention       = isset( $options['data_retention'] ) ? $options['data_retention'] : 'forever';
		$delete_data     = isset( $options['delete_data_on_uninstall'] ) ? $options['delete_data_on_uninstall'] : false;

		$retention_options = array(
			'1_month'  => __( '1 month', 'ai-share-summarize' ),
			'3_months' => __( '3 months', 'ai-share-summarize' ),
			'6_months' => __( '6 months', 'ai-share-summarize' ),
			'1_year'   => __( '1 year', 'ai-share-summarize' ),
			'forever'  => __( 'Forever', 'ai-share-summarize' ),
		);
		?>

		<div class="aiss-retention-row" style="display: flex; align-items: center; gap: 30px; flex-wrap: wrap;">
			<div>
				<label for="ayudawp_data_retention">
					<strong><?php esc_html_e( 'Retention period:', 'ai-share-summarize' ); ?></strong>
				</label>
				<br>
				<select name="ayudawp_aiss_options[data_retention]" id="ayudawp_data_retention">
					<?php foreach ( $retention_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $retention, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Older analytics data will be automatically deleted daily.', 'ai-share-summarize' ); ?></p>
			</div>

			<div>
				<label>
					<input type="checkbox" name="ayudawp_aiss_options[delete_data_on_uninstall]" value="1" <?php checked( $delete_data, true ); ?>>
					<?php esc_html_e( 'Delete all data when plugin is deleted (uninstalled)', 'ai-share-summarize' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If unchecked, data will be preserved in case of reinstallation.', 'ai-share-summarize' ); ?></p>
			</div>

			<div style="margin-left: auto;">
				<button type="button" id="ayudawp-aiss-delete-all-data" class="button" style="color: #dc3232; border-color: #dc3232;">
					<?php esc_html_e( 'Delete all data', 'ai-share-summarize' ); ?>
				</button>
			</div>
		</div>

		<?php
	}

	/**
	 * Mastodon instance field (v1.6.0)
	 */
	public function ayudawp_mastodon_instance_callback() {
		$options  = get_option( 'ayudawp_aiss_options' );
		$value    = isset( $options['mastodon_instance'] ) ? $options['mastodon_instance'] : '';
		?>
		<input type="text" name="ayudawp_aiss_options[mastodon_instance]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="mastodon.social">
		<p class="description"><?php esc_html_e( 'Your Mastodon instance domain (without https://). Required for the Mastodon share button.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Section titles field (v1.6.0)
	 */
	public function ayudawp_section_titles_callback() {
		$options       = get_option( 'ayudawp_aiss_options' );
		$ai_title      = isset( $options['ai_section_title'] ) ? $options['ai_section_title'] : '';
		$social_title  = isset( $options['social_section_title'] ) ? $options['social_section_title'] : '';
		?>
		<div style="display: flex; gap: 20px; flex-wrap: wrap;">
			<div style="flex: 1; min-width: 200px;">
				<label for="ayudawp_ai_section_title"><strong><?php esc_html_e( 'AI section title:', 'ai-share-summarize' ); ?></strong></label><br>
				<input type="text" id="ayudawp_ai_section_title" name="ayudawp_aiss_options[ai_section_title]" value="<?php echo esc_attr( $ai_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Summarize with AI', 'ai-share-summarize' ); ?>">
			</div>
			<div style="flex: 1; min-width: 200px;">
				<label for="ayudawp_social_section_title"><strong><?php esc_html_e( 'Social section title:', 'ai-share-summarize' ); ?></strong></label><br>
				<input type="text" id="ayudawp_social_section_title" name="ayudawp_aiss_options[social_section_title]" value="<?php echo esc_attr( $social_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Share on social media', 'ai-share-summarize' ); ?>">
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Optional. When set, each group gets its own heading and the general title above is hidden. Leave both empty to use only the general title. Does not apply to Mixed order.', 'ai-share-summarize' ); ?></p>
		<?php
	}

	/**
	 * Button size field (v1.6.0)
	 */
	public function ayudawp_button_size_callback() {
		$options = get_option( 'ayudawp_aiss_options' );
		$size    = isset( $options['button_size'] ) ? $options['button_size'] : 'normal';

		$sizes = array(
			'compact' => esc_html__( 'Compact', 'ai-share-summarize' ),
			'normal'  => esc_html__( 'Normal (default)', 'ai-share-summarize' ),
			'large'   => esc_html__( 'Large', 'ai-share-summarize' ),
			'fluid'   => esc_html__( 'Fluid (fill available width)', 'ai-share-summarize' ),
		);

		foreach ( $sizes as $key => $label ) {
			$checked = ( $size === $key ) ? 'checked="checked"' : '';
			echo '<label style="display: block; margin-bottom: 5px;">
					<input type="radio" name="ayudawp_aiss_options[button_size]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . '>
					' . esc_html( $label ) . '
				  </label>';
		}

		echo '<p class="description">' . esc_html__( 'Fluid mode makes buttons expand to fill the available space instead of using a fixed width.', 'ai-share-summarize' ) . '</p>';
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
}
<?php
/**
 * Plugin Name: AI Share & Summarize
 * Plugin URI: https://servicios.ayudawp.com/
 * Description: Inline AI summary on every post + one-click sharing to social networks and 11 AI assistants (Claude, ChatGPT, Gemini, Grok, Perplexity, DeepSeek, Mistral, Copilot, Qwen, Meta AI). Powered by the new WordPress 7.0 AI Connectors.
 * Version: 2.0.2
 * Author: Fernando Tellado
 * Author URI: https://ayudawp.com/
 * Text Domain: ai-share-summarize
 * Requires at least: 5.6
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package AiShareSummarize
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AYUDAWP_AISS_VERSION', '2.0.2' );
define( 'AYUDAWP_AISS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AYUDAWP_AISS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AYUDAWP_AISS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class AyudaWP_AI_Share_Summarize {

	/**
	 * Single instance of the plugin
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get single instance (Singleton)
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->ayudawp_load_includes();
		$this->ayudawp_init_hooks();
	}

	/**
	 * Load include files
	 */
	private function ayudawp_load_includes() {
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/functions-helpers.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-icons.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-buttons.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-frontend.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-admin.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-seo-integration.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-meta-box.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-ai-summary.php';

		// Analytics system (v1.5.0).
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-database.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-analytics-page.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-aiss-promo-banner.php';
	}

	/**
	 * Initialize hooks
	 */
	private function ayudawp_init_hooks() {
		add_action( 'init', array( $this, 'ayudawp_init' ) );

		register_activation_hook( __FILE__, array( $this, 'ayudawp_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'ayudawp_deactivate' ) );
		register_uninstall_hook( __FILE__, array( 'AyudaWP_AI_Share_Summarize', 'ayudawp_uninstall' ) );

		// Daily cron for data retention purge.
		add_action( 'ayudawp_aiss_daily_purge', array( $this, 'ayudawp_run_data_purge' ) );

		// Initialize classes.
		new AyudaWP_AISS_Admin();
		new AyudaWP_AISS_Frontend();
		new AyudaWP_AISS_Shortcode();
		new AyudaWP_AISS_Meta_Box();
		new AyudaWP_AISS_AI_Summary();

		// REST API endpoints.
		add_action( 'rest_api_init', array( 'AyudaWP_AISS_REST_API', 'ayudawp_register_routes' ) );

		// Dashboard widget.
		add_action( 'wp_dashboard_setup', array( 'AyudaWP_AISS_Dashboard_Widget', 'ayudawp_register' ) );

		// AJAX handler for VigIA tip dismiss.
		add_action( 'wp_ajax_aiss_dismiss_vigia_tip', array( $this, 'ayudawp_dismiss_vigia_tip' ) );
	}

	/**
	 * Plugin initialization
	 */
	public function ayudawp_init() {
		$this->ayudawp_check_version_update();
	}

	/**
	 * Check for version update and migrate settings if needed
	 */
	private function ayudawp_check_version_update() {
		$current_version = get_option( 'ayudawp_aiss_version', '1.0.9' );

		if ( version_compare( $current_version, AYUDAWP_AISS_VERSION, '<' ) ) {
			$this->ayudawp_migrate_settings( $current_version );
			update_option( 'ayudawp_aiss_version', AYUDAWP_AISS_VERSION );

			// Create or update database tables on version upgrade.
			AyudaWP_AISS_Database::ayudawp_create_tables();

			// Ensure daily purge cron is scheduled.
			if ( ! wp_next_scheduled( 'ayudawp_aiss_daily_purge' ) ) {
				wp_schedule_event( time(), 'daily', 'ayudawp_aiss_daily_purge' );
			}
		}
	}

	/**
	 * Migrate settings from older versions
	 *
	 * @param string $from_version Version migrating from.
	 */
	private function ayudawp_migrate_settings( $from_version ) {
		$options = get_option( 'ayudawp_aiss_options' );

		if ( ! $options ) {
			return;
		}

		// Migration for v1.1.0: Add seo_button_type.
		if ( version_compare( $from_version, '1.1.0', '<' ) ) {
			if ( ! isset( $options['seo_button_type'] ) ) {
				$options['seo_button_type'] = 'link';
			}
		}

		// Migration for v1.4.0: Add exclude_noindex option.
		if ( version_compare( $from_version, '1.4.0', '<' ) ) {
			if ( ! isset( $options['exclude_noindex'] ) ) {
				$options['exclude_noindex'] = true;
			}
		}

		// Migration for v1.6.0: Add new options, merge default/minimal styles.
		if ( version_compare( $from_version, '1.6.0', '<' ) ) {
			// Merge old 'default' style into 'minimal'.
			if ( isset( $options['button_colors'] ) && 'default' === $options['button_colors'] ) {
				$options['button_colors'] = 'minimal';
			}
			if ( ! isset( $options['data_retention'] ) ) {
				$options['data_retention'] = 'forever';
			}
			if ( ! isset( $options['button_size'] ) ) {
				$options['button_size'] = 'normal';
			}
			if ( ! isset( $options['ai_section_title'] ) ) {
				$options['ai_section_title'] = '';
			}
			if ( ! isset( $options['social_section_title'] ) ) {
				$options['social_section_title'] = '';
			}
			if ( ! isset( $options['mastodon_instance'] ) ) {
				$options['mastodon_instance'] = 'mastodon.social';
			}
			if ( ! isset( $options['custom_color_bg'] ) ) {
				$options['custom_color_bg'] = '#333333';
			}
			if ( ! isset( $options['custom_color_text'] ) ) {
				$options['custom_color_text'] = '#ffffff';
			}
			if ( ! isset( $options['button_custom_order'] ) ) {
				$options['button_custom_order'] = array();
			}
		}

		// Migration for v2.0.0: AI Summary feature defaults.
		if ( version_compare( $from_version, '2.0.0', '<' ) ) {
			$summary_defaults = array(
				'ai_summary_enabled'                 => true,
				'ai_summary_use_extractive_fallback' => true,
				'ai_summary_sentences'               => 3,
				'ai_summary_position'                => 'before_buttons',
				'ai_summary_collapsed_default'       => true,
				'ai_summary_frontend_button'         => false,
				'ai_summary_frontend_force_extractive' => true,
			);
			foreach ( $summary_defaults as $key => $value ) {
				if ( ! isset( $options[ $key ] ) ) {
					$options[ $key ] = $value;
				}
			}
			// Seed the summary post-type list from the buttons list so the
			// feature works out of the box on the same content where buttons
			// are already enabled, without forcing the admin to re-pick.
			if ( ! isset( $options['ai_summary_content_types'] ) ) {
				$options['ai_summary_content_types'] = isset( $options['auto_insert_content_types'] ) && is_array( $options['auto_insert_content_types'] )
					? $options['auto_insert_content_types']
					: array( 'post' );
			}
		}

		update_option( 'ayudawp_aiss_options', $options );
	}

	/**
	 * Plugin activation
	 */
	public function ayudawp_activate() {
		$default_options = array(
			'default_prompt'             => ayudawp_aiss_get_default_prompt(),
			'enabled_buttons'            => array( 'chatgpt', 'claude', 'twitter', 'linkedin' ),
			'display_locations'          => array( 'posts' ),
			'auto_insert_position'       => 'after_content',
			'auto_insert_content_types'  => array( 'post' ),
			'twitter_handle'             => '',
			'mastodon_instance'          => '',
			'custom_text'                => '',
			'title_text'                 => '',
			'title_style'                => 'span',
			'ai_section_title'           => '',
			'social_section_title'       => '',
			'button_order'               => 'ayudawp_ai_first',
			'button_alignment'           => 'left',
			'button_colors'              => 'minimal',
			'button_size'                => 'normal',
			'custom_color_bg'            => '#333333',
			'custom_color_text'          => '#ffffff',
			'dark_mode_adaptation'       => 'disabled',
			'show_icons'                 => false,
			'icon_style'                 => 'circular',
			'delete_data_on_uninstall'   => false,
			'data_retention'             => 'forever',
			'seo_button_type'            => 'link',
			'exclude_noindex'            => true,
			'button_custom_order'        => array(),
			'ai_summary_enabled'                 => true,
			'ai_summary_use_extractive_fallback' => true,
			'ai_summary_sentences'               => 3,
			'ai_summary_position'                => 'before_buttons',
			'ai_summary_collapsed_default'       => true,
			'ai_summary_frontend_button'         => false,
			'ai_summary_frontend_force_extractive' => true,
			'ai_summary_content_types'           => array( 'post' ),
		);

		add_option( 'ayudawp_aiss_options', $default_options );
		update_option( 'ayudawp_aiss_version', AYUDAWP_AISS_VERSION );
		set_transient( 'ayudawp_aiss_just_activated', true, 60 );

		// Create analytics database tables.
		AyudaWP_AISS_Database::ayudawp_create_tables();

		// Schedule daily data retention purge.
		if ( ! wp_next_scheduled( 'ayudawp_aiss_daily_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'ayudawp_aiss_daily_purge' );
		}
	}

	/**
	 * Plugin deactivation: unschedule cron
	 */
	public function ayudawp_deactivate() {
		$timestamp = wp_next_scheduled( 'ayudawp_aiss_daily_purge' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ayudawp_aiss_daily_purge' );
		}

		// Clear any pending async summary generation events.
		wp_clear_scheduled_hook( 'ayudawp_aiss_generate_summary_async' );
	}

	/**
	 * Cron callback: purge old analytics data based on retention setting
	 */
	public function ayudawp_run_data_purge() {
		AyudaWP_AISS_Database::ayudawp_purge_old_data();
	}

	/**
	 * Plugin uninstallation
	 */
	public static function ayudawp_uninstall() {
		$options     = get_option( 'ayudawp_aiss_options' );
		$delete_data = isset( $options['delete_data_on_uninstall'] ) ? $options['delete_data_on_uninstall'] : false;

		// Always unschedule cron on uninstall.
		$timestamp = wp_next_scheduled( 'ayudawp_aiss_daily_purge' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ayudawp_aiss_daily_purge' );
		}

		if ( $delete_data ) {
			delete_option( 'ayudawp_aiss_options' );
			delete_option( 'ayudawp_aiss_version' );
			delete_option( 'ayudawp_aiss_db_version' );
			delete_option( 'ayudawp_aiss_activation_notice_dismissed' );
			delete_option( 'ayudawp_aiss_vigia_tip_dismissed' );
			delete_option( 'ayudawp_aiss_last_ai_error' );
			delete_transient( 'ayudawp_aiss_just_activated' );

			// Clean up post meta.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- postmeta cleanup on uninstall, not cached by design.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ayudawp_aiss_exclude' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- postmeta cleanup on uninstall, not cached by design.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ayudawp_aiss_summary' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- postmeta cleanup on uninstall, not cached by design.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ayudawp_aiss_summary_provider' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- postmeta cleanup on uninstall, not cached by design.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ayudawp_aiss_summary_hash' ) );

			// Drop analytics table.
			$table_name      = $wpdb->prefix . 'aiss_clicks';
			$table_name_safe = esc_sql( $table_name );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DROP on own table at uninstall, name is escaped.
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name_safe}" );
		}
	}

	/**
	 * AJAX handler: Dismiss VigIA tip notice
	 */
	public function ayudawp_dismiss_vigia_tip() {
		check_ajax_referer( 'aiss_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_option( 'ayudawp_aiss_vigia_tip_dismissed', true );
		wp_send_json_success();
	}
}

// Initialize the plugin.
AyudaWP_AI_Share_Summarize::get_instance();
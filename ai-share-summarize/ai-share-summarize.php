<?php
/**
 * Plugin Name: AI Summaries & Share Buttons
 * Plugin URI: https://servicios.ayudawp.com/
 * Description: Inline AI summary on every post + one-click sharing to social networks and 11 AI assistants (Claude, ChatGPT, Gemini, Grok, Perplexity, DeepSeek, Mistral, Copilot, Qwen, Meta AI). Powered by the new WordPress 7.x AI Connectors.
 * Version: 2.8.0
 * Author: Fernando Tellado
 * Author URI: https://ayudawp.com/
 * Text Domain: ai-share-summarize
 * Requires at least: 6.1
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
define( 'AYUDAWP_AISS_VERSION', '2.8.0' );
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
		require_once AYUDAWP_AISS_PLUGIN_DIR . 'includes/class-floating-bar.php';
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
		new AyudaWP_AISS_Floating_Bar();
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

		// Migration for v2.2.0: collapse the two AI-summary booleans into a single
		// explicit mode, then drop the legacy keys.
		if ( version_compare( $from_version, '2.2.0', '<' ) ) {
			if ( ! isset( $options['ai_summary_mode'] ) ) {
				$was_enabled  = ! isset( $options['ai_summary_enabled'] ) || ! empty( $options['ai_summary_enabled'] );
				$was_fallback = ! isset( $options['ai_summary_use_extractive_fallback'] ) || ! empty( $options['ai_summary_use_extractive_fallback'] );
				if ( ! $was_enabled ) {
					$options['ai_summary_mode'] = 'disabled';
				} else {
					$options['ai_summary_mode'] = $was_fallback ? 'ai_fallback' : 'ai_only';
				}
			}
			unset( $options['ai_summary_enabled'], $options['ai_summary_use_extractive_fallback'] );
		}

		/*
		 * Migration for v2.4.1: drop a prompt that only holds a copy of the
		 * bundled default, so it goes back to being resolved from the language
		 * pack on every request and the AI is asked for the summary in the
		 * language of each reader. Activation used to store it, and the settings
		 * field came pre-filled, so the first save froze it in the language of
		 * the admin. A prompt the site wrote itself never matches any of the
		 * bundled defaults and is left untouched, and so is an empty one, which
		 * means send no instructions at all.
		 */
		if ( version_compare( $from_version, '2.4.1', '<' ) ) {
			if ( isset( $options['default_prompt'] ) && ayudawp_aiss_is_default_prompt( $options['default_prompt'] ) ) {
				unset( $options['default_prompt'] );
			}
		}

		/*
		 * Migration for v2.5.0: the three settings that governed where the
		 * buttons appear collapse into one map of destination => position.
		 *
		 * Until 2.4.4 a post needed `display_locations` to contain "posts" AND
		 * `auto_insert_content_types` to contain "post" AND
		 * `auto_insert_position` to be something other than "disabled". The
		 * first two listed overlapping things and nothing on screen said the
		 * condition was an AND, so the usual outcome of ticking one of them was
		 * no buttons and no explanation.
		 *
		 * What is migrated is the VISIBLE RESULT, not the literal settings: a
		 * site with "Pages" ticked under locations but not under content types
		 * was showing nothing on pages, so `page` is not written. Migrating the
		 * settings literally would have made buttons appear where that site
		 * never had them. The old keys are left in place as a rollback snapshot
		 * (see the carry-over in ayudawp_aiss_validate_options()).
		 */
		if ( version_compare( $from_version, '2.5.0', '<' ) && ! isset( $options['placements'] ) ) {
			$options['placements'] = self::ayudawp_derive_placements_from_legacy( $options );
		}

		/*
		 * Same move for the summary: its content-type list and its single
		 * position become one map, so a site can put the summary before the
		 * content on pages and next to the buttons on posts.
		 */
		if ( version_compare( $from_version, '2.5.0', '<' ) ) {
			$summary_options = get_option( 'ayudawp_aiss_summary_options' );
			if ( is_array( $summary_options ) && ! isset( $summary_options['ai_summary_placements'] ) ) {
				$summary_options['ai_summary_placements'] = self::ayudawp_derive_summary_placements_from_legacy( $summary_options );
				update_option( 'ayudawp_aiss_summary_options', $summary_options );
			}
		}

		// Migration for v2.8.0: the look and the floating bar, see the method.
		if ( version_compare( $from_version, '2.8.0', '<' ) ) {
			$options = self::ayudawp_migrate_look_280( $options );
		}

		// Persist before the 2.3.0 block: its derivation helper reads this
		// option back from the database, so earlier in-memory migrations
		// (mode materialization above) must already be stored.
		update_option( 'ayudawp_aiss_options', $options );

		/*
		 * Migration for v2.3.0: the AI-summary settings move to their own
		 * option, `ayudawp_aiss_summary_options`, behind the new AI Summary
		 * settings tab. COPY, do not move: the `ai_summary_*` keys stay in
		 * `ayudawp_aiss_options` as a frozen snapshot so a rollback to 2.2.x
		 * finds its configuration intact (deleted by the 2.6.0 migration
		 * below). The derivation also materializes the
		 * old read-time inheritance of the buttons' content-type list, which
		 * 2.3.0 removes from the runtime resolver.
		 */
		if ( version_compare( $from_version, '2.3.0', '<' ) ) {
			if ( false === get_option( 'ayudawp_aiss_summary_options' ) ) {
				add_option( 'ayudawp_aiss_summary_options', ayudawp_aiss_derive_summary_options_from_legacy() );
			}
			$this->ayudawp_migrate_summary_exclusion_meta();
		}

		/*
		 * Migration for v2.6.0: delete that frozen `ai_summary_*` snapshot.
		 * After eight releases of the 2.3+ series with no migration problem
		 * reported, going back to 2.2.x is not worth the weight these keys add
		 * to an autoloaded option. Runs after the 2.3.0 block, so a site
		 * coming straight from 2.2.x gets its summary option first, and only
		 * when that option exists: until then ayudawp_aiss_get_summary_options()
		 * still reads these keys.
		 */
		if ( version_compare( $from_version, '2.6.0', '<' ) && is_array( get_option( 'ayudawp_aiss_summary_options' ) ) ) {
			$current = get_option( 'ayudawp_aiss_options' );
			if ( is_array( $current ) ) {
				$kept = array();
				foreach ( $current as $key => $value ) {
					if ( 0 !== strpos( (string) $key, 'ai_summary' ) ) {
						$kept[ $key ] = $value;
					}
				}
				if ( count( $kept ) !== count( $current ) ) {
					update_option( 'ayudawp_aiss_options', $kept );
				}
			}
		}
	}

	/**
	 * The 2.8.0 migration: the new look keys and the floating bar of 2.7.x (v2.8.0)
	 *
	 * 2.8.0 sets up the buttons in the content and the floating bar the same
	 * way. "Icons only" stops being a colour and becomes what the buttons carry,
	 * the corners apply to every button, and the bar takes its buttons and look
	 * from the content unless it has its own. What is migrated is what each site
	 * SEES, so nobody sees a change (decided by Fernando on 14 Nov 2026):
	 *
	 * - "Icons only" becomes icons, brand colours and the corners of
	 *   `icon_style`. Any other style keeps its colour, carries text and icon or
	 *   text only as `show_icons` said, and gets rounded corners, the 4px every
	 *   text button has always had.
	 * - A site that never saved the compact mode rendered every button on
	 *   phones; "a few and More" is the default from 2.8.0, so that site gets
	 *   "all" written instead.
	 * - A site with the bar on gets the bar of 2.7.x written: the content's
	 *   buttons (the same list, since the content is migrated to "all"), every
	 *   one in sight, and its own look in brand colours, with the corners of
	 *   `icon_style`, the content's size ("fluid" was drawn as normal) and the
	 *   content's dark backgrounds setting. A site with the bar off gets nothing
	 *   for it and sees the defaults the day it turns it on.
	 *
	 * Nothing 2.7.x reads is changed: `button_colors`, `show_icons` and
	 * `icon_style` are left as they were, and "icons-only" keeps meaning icons
	 * in brand colours with the corners of `icon_style` for the 2.8.0 reader
	 * too (ayudawp_aiss_get_button_look()). The new keys are only added, and
	 * `mobile_compact` is written with the value 2.7.x already rendered for a
	 * missing key. So going back to 2.7.x right after updating, before saving,
	 * shows the same buttons. After the first save in 2.8.0 it no longer does,
	 * and cannot: the form posts "brand" instead of "icons-only", drops
	 * `show_icons`, and 2.7.x knows nothing of the new settings (found by the
	 * cross review of 14 Nov 2026, H3). Static and side-effect free, so the
	 * release harness can call it with fabricated inputs and compare what both
	 * versions draw.
	 *
	 * @since 2.8.0
	 * @param array $options Stored options, pre-migration.
	 * @return array Options with the 2.8.0 keys written.
	 */
	public static function ayudawp_migrate_look_280( $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		// Worked out exactly as the renderer works out a missing key.
		$look = ayudawp_aiss_get_button_look( $options );

		// `button_colors` stays as it was, "icons-only" included: see above.
		if ( ! isset( $options['button_content'] ) ) {
			$options['button_content'] = $look['content'];
		}
		if ( ! isset( $options['button_corners'] ) ) {
			$options['button_corners'] = $look['corners'];
		}

		if ( ! isset( $options['mobile_compact'] ) ) {
			$options['mobile_compact'] = 'disabled';
		}

		if ( ! empty( $options['floating_bar_placements'] ) && ! isset( $options['floating_bar_look'] ) ) {
			$icon_style = isset( $options['icon_style'] ) ? $options['icon_style'] : null;

			$options['floating_bar_buttons'] = 'inherit';
			$options['floating_bar_compact'] = 'disabled';
			$options['floating_bar_look']    = 'own';
			$options['floating_bar_color']   = 'brand';
			// The bar of 2.7.x drew a value it did not know as a circle.
			$options['floating_bar_corners'] = ayudawp_aiss_corners_from_icon_style( $icon_style, 'circle' );
			$options['floating_bar_size']    = in_array( $look['size'], array( 'compact', 'large' ), true ) ? $look['size'] : 'normal';
			$options['floating_bar_dark']    = $look['dark'];
		}

		return $options;
	}

	/**
	 * Turn the pre-2.5.0 location settings into the unified placements map
	 *
	 * Static and side-effect free so the release harness can call it with
	 * fabricated inputs and check every combination.
	 *
	 * @since 2.5.0
	 * @param array $options Stored options, pre-migration.
	 * @return array Destination key => position.
	 */
	public static function ayudawp_derive_placements_from_legacy( $options ) {
		$position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';

		// "Shortcode only" becomes an empty map: no destination is ticked.
		if ( 'disabled' === $position ) {
			return array();
		}

		if ( ! in_array( $position, array( 'before_content', 'after_content', 'both' ), true ) ) {
			$position = 'after_content';
		}

		$locations = isset( $options['display_locations'] ) && is_array( $options['display_locations'] )
			? $options['display_locations']
			: array();
		$types     = isset( $options['auto_insert_content_types'] ) && is_array( $options['auto_insert_content_types'] )
			? $options['auto_insert_content_types']
			: array();

		$placements = array();

		foreach ( $types as $type ) {
			$type = (string) $type;

			if ( 'post' === $type ) {
				$gate = in_array( 'posts', $locations, true );
			} elseif ( 'page' === $type ) {
				$gate = in_array( 'pages', $locations, true );
			} else {
				$gate = in_array( 'cpt', $locations, true );
			}

			if ( $gate ) {
				$placements[ $type ] = $position;
			}
		}

		if ( in_array( 'home', $locations, true ) ) {
			$placements['home'] = $position;
		}
		if ( in_array( 'archives', $locations, true ) ) {
			$placements['archive'] = $position;
		}

		return $placements;
	}

	/**
	 * Turn the pre-2.5.0 summary settings into its placements map
	 *
	 * @since 2.5.0
	 * @param array $options Stored summary options, pre-migration.
	 * @return array Post type => position.
	 */
	public static function ayudawp_derive_summary_placements_from_legacy( $options ) {
		$types = isset( $options['ai_summary_content_types'] ) && is_array( $options['ai_summary_content_types'] )
			? $options['ai_summary_content_types']
			: array();

		if ( empty( $types ) ) {
			return array();
		}

		$position = isset( $options['ai_summary_position'] ) ? $options['ai_summary_position'] : 'before_buttons';

		// "Do not display" kept generating summaries, and so does its
		// replacement, which is a position of its own rather than an off switch.
		if ( 'disabled' === $position ) {
			$position = 'hidden';
		}
		if ( ! in_array( $position, array( 'before_buttons', 'after_buttons', 'before_content', 'hidden' ), true ) ) {
			$position = 'before_buttons';
		}

		$placements = array();
		foreach ( $types as $type ) {
			$placements[ (string) $type ] = $position;
		}

		return $placements;
	}

	/**
	 * Materialize the per-post summary exclusion for posts excluded pre-2.3.0
	 *
	 * Before 2.3.0 the single "Hide share buttons" checkbox silently hid the
	 * AI summary too. 2.3.0 splits that into two independent controls, so
	 * every post that had the old meta gets the new summary-exclusion meta
	 * stamped once here — existing exclusions keep behaving exactly as they
	 * did, while both controls become editable independently from now on.
	 *
	 * Runs once from the 2.3.0 migration. add_post_meta() with $unique keeps
	 * it idempotent and refreshes each post's meta cache as it goes.
	 *
	 * @since 2.3.0
	 */
	private function ayudawp_migrate_summary_exclusion_meta() {
		global $wpdb;

		// Direct lookup on purpose: it must cover every post status and every
		// post type (WP_Query would need fragile any-status/any-type gymnastics
		// for a one-time upgrade sweep this small).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time 2.3.0 upgrade sweep over our own meta key, result consumed immediately.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'",
				'_ayudawp_aiss_exclude'
			)
		);

		foreach ( $post_ids as $post_id ) {
			add_post_meta( (int) $post_id, '_ayudawp_aiss_exclude_summary', '1', true );
		}
	}

	/**
	 * Plugin activation
	 */
	public function ayudawp_activate() {
		// `default_prompt` is left out on purpose: it is resolved at read time
		// from the language pack, so storing it here would freeze it in one
		// language. Translations are not even loaded during activation, which
		// is what made the old code store plain English.
		$default_options = array(
			'enabled_buttons'            => array( 'chatgpt', 'claude', 'twitter', 'linkedin' ),
			// One map since 2.5.0: destination => position.
			'placements'                 => array( 'post' => 'after_content' ),
			'twitter_handle'             => '',
			'mastodon_instance'          => '',
			'custom_text'                => '',
			'title_text'                 => '',
			'title_style'                => 'span',
			'ai_section_title'           => '',
			'social_section_title'       => '',
			'button_order'               => 'ayudawp_ai_first',
			'button_alignment'           => 'left',
			// The look since 2.8.0: text only, minimal, rounded corners.
			'button_content'             => 'text',
			'button_colors'              => 'minimal',
			'button_corners'             => 'rounded',
			'button_size'                => 'normal',
			'custom_color_bg'            => '#333333',
			'custom_color_text'          => '#ffffff',
			'dark_mode_adaptation'       => 'disabled',
			// v2.8.0: all the available buttons in the content.
			'content_buttons_mode'       => 'all',
			'delete_data_on_uninstall'   => false,
			'data_retention'             => 'forever',
			'seo_button_type'            => 'link',
			'exclude_noindex'            => true,
			'button_custom_order'        => array(),
			// v2.5.0. All three off by default, so a fresh install looks like
			// every previous version until the admin turns them on.
			'floating_bar_placements'    => array(),
			'floating_bar_autohide'      => true,
			'floating_bar_hide_mobile'   => true,
			'floating_bar_hide_with_inline' => false,
			// v2.8.0: the bar takes its buttons and look from the content and
			// keeps four in sight. Same values the reader falls back to, written
			// here only so the defaults are in one view.
			'floating_bar_buttons'       => 'inherit',
			'floating_bar_compact'       => 'partial',
			'floating_bar_compact_visible' => 4,
			'floating_bar_look'          => 'inherit',
			// Compact mode on by default: a phone screen with fifteen buttons
			// is the complaint this setting exists to answer. "A few and More"
			// with three since 2.8.0 (until then, a single Share button).
			'mobile_compact'             => 'partial',
			'mobile_compact_visible'     => 3,
			'utm_enabled'                => false,
			'utm_campaign'               => '',
		);

		// AI-summary settings live in their own option since 2.3.0.
		$default_summary_options = array(
			'ai_summary_mode'                    => 'ai_fallback',
			'ai_summary_sentences'               => 3,
			'ai_summary_position'                => 'before_buttons',
			'ai_summary_collapsed_default'       => true,
			'ai_summary_frontend_button'         => false,
			'ai_summary_frontend_force_extractive' => true,
			'ai_summary_content_types'           => array( 'post' ),
		);

		/*
		 * The version is only written on a fresh install (v2.8.0). A site that
		 * already has settings keeps the version they were saved with, so the
		 * migrations run on the next load: until now, updating the plugin while
		 * it was deactivated and then activating it wrote the new version here
		 * and skipped them. Found by the cross review of 14 Nov 2026 (H2).
		 */
		$fresh_install = false === get_option( 'ayudawp_aiss_options' );

		add_option( 'ayudawp_aiss_options', $default_options );
		add_option( 'ayudawp_aiss_summary_options', $default_summary_options );
		if ( $fresh_install ) {
			update_option( 'ayudawp_aiss_version', AYUDAWP_AISS_VERSION );
		}
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
			delete_option( 'ayudawp_aiss_summary_options' );
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
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ayudawp_aiss_exclude_summary' ) );
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
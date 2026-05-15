<?php
/**
 * Frontend functionality for AI Share & Summarize plugin
 *
 * Handles selective asset loading and button insertion.
 * CSS/JS only loads where buttons will actually display,
 * respecting plugin settings and exclusion rules.
 *
 * @package AiShareSummarize
 * @since 1.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle frontend display and functionality
 */
class AyudaWP_AISS_Frontend {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'ayudawp_enqueue_scripts' ) );
		add_filter( 'the_content', array( $this, 'ayudawp_add_share_buttons' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * Only loads assets where buttons will actually display,
	 * using the same conditional logic as button rendering.
	 * Falls back to shortcode detection for disabled auto-insert.
	 */
	public function ayudawp_enqueue_scripts() {
		$options         = get_option( 'ayudawp_aiss_options' );
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';

		// Determine if assets are needed on this page.
		$should_load = false;

		if ( 'disabled' !== $insert_position ) {
			// Auto-insert mode: use the same display logic as button rendering.
			$should_load = ayudawp_aiss_should_display_buttons();
		} else {
			// Shortcode-only mode: load on singular and archives
			// where shortcodes might be used in content or widgets.
			$should_load = is_singular() || is_archive() || is_home();
		}

		if ( ! $should_load ) {
			return;
		}

		/*
		 * Fix #3: if buttons are disabled for this specific post via the meta box,
		 * skip loading assets entirely on singular pages.
		 * get_queried_object_id() is used instead of get_the_ID() because it is
		 * reliable at wp_enqueue_scripts time (before the_content loop runs).
		 */
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && ayudawp_aiss_is_post_excluded( $post_id ) ) {
				return;
			}
		}

		ayudawp_aiss_enqueue_frontend_assets();
	}

	/**
	 * Add share buttons to content
	 *
	 * @param string $content Post content.
	 * @return string Modified content with buttons.
	 */
	public function ayudawp_add_share_buttons( $content ) {
		// Track which post IDs already received the buttons so the same post
		// can never get them twice on the same request — protects against
		// builders/widgets that apply the_content filter more than once and
		// against widgets/sidebars running the_content() on arbitrary text
		// while the singular post is being rendered.
		static $processed = array();

		// Backend or feeds: never inject.
		if ( is_admin() || is_feed() ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( is_singular() ) {
			// On singular pages the only valid target is the queried post.
			// Note: in_the_loop() is intentionally NOT checked here — Divi
			// Theme Builder (Divi 4 and Divi 5), FSE block templates and
			// Bricks Builder render the post content via the_content filter
			// WITHOUT setting up a standard WP loop, so in_the_loop() is
			// false even when the singular post is being rendered legitimately.
			// Comparing IDs + the $processed guard below is enough to keep
			// the buttons out of widgets and footer content.
			$queried_id = get_queried_object_id();
			if ( ! $post_id || ! $queried_id || $post_id !== $queried_id ) {
				return $content;
			}
		} else {
			// On archives/home, require the main loop so secondary loops and
			// widgets running the_content() are not picked up.
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}
		}

		if ( $post_id && in_array( $post_id, $processed, true ) ) {
			return $content;
		}

		if ( ! ayudawp_aiss_should_display_buttons() ) {
			return $content;
		}

		// Apply per-post exclusion set via the meta box.
		if ( $post_id && ayudawp_aiss_is_post_excluded( $post_id ) ) {
			return $content;
		}

		$options         = get_option( 'ayudawp_aiss_options' );
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';

		if ( 'disabled' === $insert_position ) {
			return $content;
		}

		$buttons_html = AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html();

		if ( $post_id ) {
			$processed[] = $post_id;
		}

		switch ( $insert_position ) {
			case 'before_content':
				return $buttons_html . $content;
			case 'after_content':
				return $content . $buttons_html;
			case 'both':
				return $buttons_html . $content . $buttons_html;
			default:
				return $content . $buttons_html;
		}
	}
}
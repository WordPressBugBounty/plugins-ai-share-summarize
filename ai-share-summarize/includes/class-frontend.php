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
		// Only inject in the main loop to prevent buttons in footers,
		// sidebars, or other areas using the_content filter.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! ayudawp_aiss_should_display_buttons() ) {
			return $content;
		}

		// Apply per-post exclusion set via the meta box.
		$post_id = get_the_ID();
		if ( $post_id && ayudawp_aiss_is_post_excluded( $post_id ) ) {
			return $content;
		}

		$options         = get_option( 'ayudawp_aiss_options' );
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';

		if ( 'disabled' === $insert_position ) {
			return $content;
		}

		$buttons_html = AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html();

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
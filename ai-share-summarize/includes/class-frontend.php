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

		wp_enqueue_style(
			'ayudawp-aiss-styles',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/ai-share-summarize.css',
			array(),
			AYUDAWP_AISS_VERSION
		);

		wp_enqueue_script(
			'ayudawp-aiss-scripts',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/js/ai-share-summarize.js',
			array( 'jquery' ),
			AYUDAWP_AISS_VERSION,
			true
		);

		wp_localize_script( 'ayudawp-aiss-scripts', 'ayudawpAissL10n', array(
			'promptCopied'       => __( 'Prompt copied to clipboard!', 'ai-share-summarize' ),
			// Generic copy-prompt tooltips.
			'copyPromptShort'    => __( 'Copy prompt & open', 'ai-share-summarize' ),
			'copyPromptLong'     => __( 'Copy prompt and open', 'ai-share-summarize' ),
			// Gemini specific tooltips.
			'geminiTooltipShort' => __( 'Copy prompt & open', 'ai-share-summarize' ),
			'geminiTooltipLong'  => __( 'Copy prompt and open Gemini', 'ai-share-summarize' ),
			// DeepSeek specific tooltips.
			'deepseekTooltipShort' => __( 'Copy prompt & open', 'ai-share-summarize' ),
			'deepseekTooltipLong'  => __( 'Copy prompt and open DeepSeek', 'ai-share-summarize' ),
			// Copilot specific tooltips.
			'copilotTooltipShort' => __( 'Copy prompt & open', 'ai-share-summarize' ),
			'copilotTooltipLong'  => __( 'Copy prompt and open Copilot', 'ai-share-summarize' ),
			// Platform names for tooltips.
			'platformNames'      => array(
				'twitter'    => 'X (Twitter)',
				'linkedin'   => 'LinkedIn',
				'facebook'   => 'Facebook',
				'telegram'   => 'Telegram',
				'whatsapp'   => 'WhatsApp',
				'email'      => __( 'Email', 'ai-share-summarize' ),
				'raindrop'   => 'Raindrop',
				'reddit'     => 'Reddit',
				'bluesky'    => 'Bluesky',
				'line'       => 'LINE',
				'claude'     => 'Claude AI',
				'chatgpt'    => 'ChatGPT',
				'google_ai'  => 'Google AI',
				'gemini'     => 'Gemini',
				'grok'       => 'Grok',
				'perplexity' => 'Perplexity',
				'deepseek'   => 'DeepSeek',
				'mistral'    => 'Mistral AI',
				'copilot'    => 'Microsoft Copilot',
			),
			// Analytics click tracking (v1.5.0) - no nonce needed for public counter.
			'trackUrl'           => esc_url_raw( rest_url( 'aiss/v1/track' ) ),
		) );
	}

	/**
	 * Add share buttons to content
	 *
	 * @param string $content Post content.
	 * @return string Modified content with buttons.
	 */
	public function ayudawp_add_share_buttons( $content ) {
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
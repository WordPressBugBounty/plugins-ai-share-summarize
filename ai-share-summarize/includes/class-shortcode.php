<?php
/**
 * Shortcode functionality for AI Summaries & Share Buttons plugin
 *
 * @package AiShareSummarize
 * @since 1.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle shortcode functionality
 */
class AyudaWP_AISS_Shortcode {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'ayudawp_share_buttons', array( $this, 'ayudawp_shortcode_share_buttons' ) );
		add_shortcode( 'ayudawp_aiss_summary', array( $this, 'ayudawp_shortcode_summary' ) );
	}

	/**
	 * Shortcode to display buttons manually
	 *
	 * Usage: [ayudawp_share_buttons]
	 * or:    [ayudawp_share_buttons buttons="twitter,claude,google_ai"]
	 * or:    [ayudawp_share_buttons ai_title="Summarize with AI" social_title="Share"]
	 * or:    [ayudawp_share_buttons buttons="google_preferred" title_text="Follow us on Google"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function ayudawp_shortcode_share_buttons( $atts ) {
		// Ensure assets are loaded when shortcode is processed late
		// (page builders may render after wp_enqueue_scripts).
		ayudawp_aiss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'buttons'      => '',
				'style'        => '',
				'size'         => '',
				'show_title'   => 'true',
				'title_text'   => '',
				'title_style'  => '',
				'show_icons'   => '',
				'icon_style'   => '',
				'ai_title'     => '',
				'social_title' => '',
				'google_title' => '',
			),
			$atts,
			'ayudawp_share_buttons'
		);

		$options = get_option( 'ayudawp_aiss_options' );

		// Override options with shortcode attributes.
		$shortcode_options = is_array( $options ) ? $options : array();

		// Handle buttons parameter. A list given here is the one wanted, so a
		// button set to go outside the block still shows when named (v2.6.0).
		if ( ! empty( $atts['buttons'] ) ) {
			$enabled_buttons                           = array_map( 'trim', explode( ',', $atts['buttons'] ) );
			$shortcode_options['_sc_explicit_buttons'] = true;
		} else {
			$enabled_buttons = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		}

		if ( empty( $enabled_buttons ) || ! is_array( $enabled_buttons ) ) {
			return '';
		}

		/*
		 * Handle style parameter. Since 2.8.0 "icons-only" is not a colour but
		 * what the buttons carry; it still means icons in brand colours, with
		 * the corners of the old icon_style when there is one.
		 */
		if ( ! empty( $atts['style'] ) ) {
			$style = sanitize_key( $atts['style'] );
			if ( 'icons-only' === $style ) {
				// A site whose buttons already are icons keeps its corners;
				// one with text buttons gets the icon corners it had.
				$stored_look = ayudawp_aiss_get_button_look( $shortcode_options );
				if ( 'icon' !== $stored_look['content'] ) {
					unset( $shortcode_options['button_corners'] );
				}
				$shortcode_options['button_content'] = 'icon';
				$shortcode_options['button_colors']  = 'brand';
			} else {
				/*
				 * Until 2.7.x "Icons only" was a style too, so naming any other
				 * style on a site with icon buttons gave buttons with text: with
				 * the icon if show_icons said so, and the corners of text
				 * buttons. Found by the cross review of 14 Nov 2026 (H1).
				 */
				$stored_look = ayudawp_aiss_get_button_look( $shortcode_options );
				if ( 'icon' === $stored_look['content'] ) {
					$shortcode_options['button_content'] = ! empty( $shortcode_options['show_icons'] ) ? 'both' : 'text';
					$shortcode_options['button_corners'] = 'rounded';
				}
				$shortcode_options['button_colors'] = $style;
			}
		}

		// Handle size parameter (v1.6.0).
		if ( ! empty( $atts['size'] ) ) {
			$shortcode_options['button_size'] = sanitize_text_field( $atts['size'] );
		}

		// Handle title parameters.
		if ( ! empty( $atts['title_text'] ) ) {
			$shortcode_options['title_text'] = $atts['title_text'];
		}

		if ( ! empty( $atts['title_style'] ) ) {
			$shortcode_options['title_style'] = ayudawp_aiss_sanitize_title_style( $atts['title_style'] );
		}

		// Handle show_title parameter.
		if ( 'false' === $atts['show_title'] ) {
			$shortcode_options['title_text'] = '';
		}

		/*
		 * Handle icons parameters, kept from before 2.8.0. show_icons adds or
		 * takes away the icon next to the text, never the text of a button that
		 * is only an icon. icon_style keeps its meaning too: the corners of
		 * buttons that are only an icon, leaving text buttons as they are.
		 */
		$sc_look = ayudawp_aiss_get_button_look( $shortcode_options );

		if ( ! empty( $atts['show_icons'] ) && 'icon' !== $sc_look['content'] ) {
			$shortcode_options['button_content'] = ( 'true' === $atts['show_icons'] ) ? 'both' : 'text';
		}

		if ( ! empty( $atts['icon_style'] ) && 'icon' === $sc_look['content'] ) {
			$shortcode_options['button_corners'] = ayudawp_aiss_corners_from_icon_style( sanitize_key( $atts['icon_style'] ) );
		}

		// Handle section titles (v1.6.0).
		if ( ! empty( $atts['ai_title'] ) ) {
			$shortcode_options['_sc_ai_title'] = sanitize_text_field( $atts['ai_title'] );
		}
		if ( ! empty( $atts['social_title'] ) ) {
			$shortcode_options['_sc_social_title'] = sanitize_text_field( $atts['social_title'] );
		}
		if ( ! empty( $atts['google_title'] ) ) {
			$shortcode_options['_sc_google_title'] = sanitize_text_field( $atts['google_title'] );
		}

		$current_url   = get_permalink();
		$current_title = get_the_title();

		$html = AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html(
			$enabled_buttons,
			$shortcode_options,
			$current_url,
			$current_title
		);

		// Add shortcode-specific class.
		$html = str_replace( 'class="ayudawp-share-buttons', 'class="ayudawp-share-buttons shortcode-buttons', $html );

		return $html;
	}

	/**
	 * Shortcode to display the AI summary for a post (v2.0.0)
	 *
	 * Usage: [ayudawp_aiss_summary]
	 * or:    [ayudawp_aiss_summary post_id="123"]
	 *
	 * Outputs nothing when the post has no stored summary, so it is safe
	 * to drop into templates without conditional wrappers.
	 *
	 * @since 2.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Summary HTML or empty string.
	 */
	public function ayudawp_shortcode_summary( $atts ) {
		if ( ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'post_id' => get_the_ID(),
			),
			$atts,
			'ayudawp_aiss_summary'
		);

		$post_id = (int) $atts['post_id'];
		if ( ! $post_id ) {
			return '';
		}

		// Ensure assets are loaded when shortcode is processed late
		// (page builders may render after wp_enqueue_scripts).
		ayudawp_aiss_enqueue_frontend_assets();

		return AyudaWP_AISS_AI_Summary::get_summary_html( $post_id );
	}
}
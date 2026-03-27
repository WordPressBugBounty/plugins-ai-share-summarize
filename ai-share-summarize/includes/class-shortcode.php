<?php
/**
 * Shortcode functionality for AI Share & Summarize plugin
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
	}

	/**
	 * Shortcode to display buttons manually
	 *
	 * Usage: [ayudawp_share_buttons]
	 * or:    [ayudawp_share_buttons buttons="twitter,claude,google_ai"]
	 * or:    [ayudawp_share_buttons ai_title="Summarize with AI" social_title="Share"]
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
			),
			$atts,
			'ayudawp_share_buttons'
		);

		$options = get_option( 'ayudawp_aiss_options' );

		// Override options with shortcode attributes.
		$shortcode_options = $options;

		// Handle buttons parameter.
		if ( ! empty( $atts['buttons'] ) ) {
			$enabled_buttons = array_map( 'trim', explode( ',', $atts['buttons'] ) );
		} else {
			$enabled_buttons = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		}

		if ( empty( $enabled_buttons ) || ! is_array( $enabled_buttons ) ) {
			return '';
		}

		// Handle style parameter.
		if ( ! empty( $atts['style'] ) ) {
			$shortcode_options['button_colors'] = sanitize_text_field( $atts['style'] );
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
			$shortcode_options['title_style'] = $atts['title_style'];
		}

		// Handle show_title parameter.
		if ( 'false' === $atts['show_title'] ) {
			$shortcode_options['title_text'] = '';
		}

		// Handle icons parameters.
		if ( ! empty( $atts['show_icons'] ) ) {
			$shortcode_options['show_icons'] = ( 'true' === $atts['show_icons'] );
		}

		if ( ! empty( $atts['icon_style'] ) ) {
			$shortcode_options['icon_style'] = $atts['icon_style'];
		}

		// Handle section titles (v1.6.0).
		if ( ! empty( $atts['ai_title'] ) ) {
			$shortcode_options['_sc_ai_title'] = sanitize_text_field( $atts['ai_title'] );
		}
		if ( ! empty( $atts['social_title'] ) ) {
			$shortcode_options['_sc_social_title'] = sanitize_text_field( $atts['social_title'] );
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
}
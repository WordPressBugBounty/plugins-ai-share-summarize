<?php
/**
 * Floating share bar (v2.5.0)
 *
 * Optional sticky bar with the same enabled buttons as the inline block, in
 * icons-only form, pinned to the left edge, the right edge or the bottom of
 * the viewport. Requested by users as an alternative display location, not as
 * a replacement: it can coexist with the inline block or be the only place the
 * buttons show (set "Automatic insertion position" to disabled and turn the
 * bar on).
 *
 * Assets are separate on purpose: a site with the bar off never downloads a
 * byte of its CSS or JS.
 *
 * @package AiShareSummarize
 * @since 2.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to render the floating share bar
 */
class AyudaWP_AISS_Floating_Bar {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'ayudawp_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'ayudawp_render' ), 20 );
	}

	/**
	 * Enqueue the bar's own CSS and JS, only where the bar renders
	 */
	public function ayudawp_enqueue() {
		if ( ! ayudawp_aiss_should_display_floating_bar() ) {
			return;
		}

		// The shared button styles and the click/tracking script live in the
		// main assets; make sure they are present even in shortcode-only mode,
		// where the inline block is not inserted anywhere.
		ayudawp_aiss_enqueue_frontend_assets();

		wp_enqueue_style(
			'ayudawp-aiss-floating-bar',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/css/floating-bar.css',
			array( 'ayudawp-aiss-styles' ),
			AYUDAWP_AISS_VERSION
		);

		wp_enqueue_script(
			'ayudawp-aiss-floating-bar',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/js/floating-bar.js',
			array(),
			AYUDAWP_AISS_VERSION,
			true
		);
	}

	/**
	 * Print the bar in the footer
	 *
	 * Rendered at the end of the document instead of inside the_content: it is
	 * positioned against the viewport, so its place in the flow is irrelevant,
	 * and staying out of the content filter keeps it away from the builders and
	 * DOM-rewriting plugins that the inline block has to defend against.
	 */
	public function ayudawp_render() {
		if ( ! ayudawp_aiss_should_display_floating_bar() ) {
			return;
		}

		$options = get_option( 'ayudawp_aiss_options' );

		$buttons = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		if ( empty( $buttons ) || ! is_array( $buttons ) ) {
			return;
		}

		// Where it sits comes from its own table, one position per destination.
		$position = ayudawp_aiss_get_placement_for_context( 'floating_bar_placements' );
		if ( ! in_array( $position, array( 'left', 'right', 'bottom' ), true ) ) {
			return;
		}

		$context = ayudawp_aiss_get_shareable_context();
		$post_id = $context['post_id'];

		$icon_style  = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';
		$button_size = isset( $options['button_size'] ) ? $options['button_size'] : 'normal';
		$order       = isset( $options['button_order'] ) ? $options['button_order'] : 'ayudawp_ai_first';
		$autohide    = ! empty( $options['floating_bar_autohide'] );
		/*
		 * Hidden on phones unless the admin says otherwise: a bar pinned over a
		 * small screen is the most intrusive thing this plugin can do, so the
		 * default errs on the side of not doing it.
		 */
		$hide_mobile = ! isset( $options['floating_bar_hide_mobile'] ) || ! empty( $options['floating_bar_hide_mobile'] );

		if ( ! in_array( $icon_style, array( 'circular', 'rounded', 'square' ), true ) ) {
			$icon_style = 'circular';
		}

		$buttons = ayudawp_aiss_remove_unavailable_buttons( AyudaWP_AISS_Buttons::ayudawp_order_buttons( $buttons, $order, $options ) );

		// Set to go outside the block (v2.6.0): it stays out of the bar too.
		if ( 'outside' === ayudawp_aiss_get_google_preferred_placement( $options ) ) {
			$buttons = array_values( array_diff( $buttons, array( 'google_preferred' ) ) );
		}

		/**
		 * Filter the buttons shown in the floating bar.
		 *
		 * Defaults to the same enabled buttons as the inline block, in the same
		 * order. A site that wants a shorter bar can trim the list here without
		 * touching the inline block.
		 *
		 * @since 2.5.0
		 * @param array $buttons Ordered button keys.
		 * @param int   $post_id Post being rendered.
		 */
		$buttons = apply_filters( 'ayudawp_aiss_floating_bar_buttons', $buttons, $post_id );

		if ( empty( $buttons ) || ! is_array( $buttons ) ) {
			return;
		}

		$url   = $context['url'];
		$title = $context['title'];

		$classes = array(
			'ayudawp-aiss-floating',
			'ayudawp-aiss-floating--' . $position,
			// Reusing the inline block's class brings the brand color custom
			// properties and the icons-only button rules; floating-bar.css only
			// has to neutralise the block's own margins and place the bar.
			'ayudawp-share-buttons',
			'icons-only',
			$icon_style,
		);

		if ( 'normal' !== $button_size && in_array( $button_size, array( 'compact', 'large' ), true ) ) {
			$classes[] = 'size-' . $button_size;
		}
		if ( $autohide ) {
			$classes[] = 'has-autohide';
		}
		if ( $hide_mobile ) {
			$classes[] = 'hide-on-mobile';
		}

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '"'
			. ( $autohide ? ' data-autohide="1"' : '' )
			. ' role="complementary" aria-label="' . esc_attr__( 'Share this content', 'ai-share-summarize' ) . '">';

		$html .= '<div class="ayudawp-buttons-container" role="group" aria-label="'
			. esc_attr__( 'Share this content', 'ai-share-summarize' ) . '">';

		foreach ( $buttons as $button ) {
			$html .= AyudaWP_AISS_Buttons::ayudawp_get_button_html(
				$button,
				$url,
				$title,
				$options,
				true,
				'icons-only',
				'link'
			);
		}

		$html .= '</div></div>';

		/*
		 * Every attribute above and inside ayudawp_get_button_html() is escaped
		 * where it is written. The wp_kses() pass on top is defence in depth
		 * and keeps the file free of security-sniff suppressions: the allowlist
		 * in ayudawp_aiss_kses_buttons() covers exactly the markup the button
		 * builder produces, so anything else would be dropped here instead of
		 * printed.
		 */
		echo wp_kses( $html, ayudawp_aiss_kses_buttons() );
	}
}

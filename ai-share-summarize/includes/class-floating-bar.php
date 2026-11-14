<?php
/**
 * Floating share bar (v2.5.0)
 *
 * Optional sticky bar of icon buttons pinned to the left edge, the right edge
 * or the bottom of the viewport. Requested by users as an alternative display
 * location, not as a replacement: it can coexist with the inline block or be
 * the only place the buttons show.
 *
 * Since 2.8.0 it is set up like the buttons in the content, with the same rows
 * and words: its buttons and their order, how many stay in sight (the rest go
 * in a panel that opens beside it) and its look, each taken from the content
 * by default or its own. Until then it showed every enabled button in brand
 * colours, which with ten buttons on was a 488px column.
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

		// Where it sits comes from its own table, one position per destination.
		$position = ayudawp_aiss_get_placement_for_context( 'floating_bar_placements' );
		$context  = ayudawp_aiss_get_shareable_context();

		$html = self::ayudawp_build_html( get_option( 'ayudawp_aiss_options' ), $position, $context['url'], $context['title'], $context['post_id'] );

		/*
		 * Every attribute in the bar and inside ayudawp_get_button_html() is
		 * escaped where it is written. The wp_kses() pass on top is defence in
		 * depth and keeps the file free of security-sniff suppressions: the
		 * allowlist covers exactly the markup the button builder produces plus
		 * the "More" disclosure, so anything else would be dropped here instead
		 * of printed.
		 */
		echo wp_kses( $html, self::ayudawp_allowed_html() );
	}

	/**
	 * The bar's markup for some options, a position and a page (v2.8.0)
	 *
	 * Split out of ayudawp_render() so the release harness can draw the bar
	 * for fabricated options and compare it with what 2.7.x drew. Returns an
	 * empty string when there is nothing to show.
	 *
	 * @param array|false $options  Plugin options.
	 * @param string      $position 'left', 'right' or 'bottom'.
	 * @param string      $url      Shared URL.
	 * @param string      $title    Shared title.
	 * @param int         $post_id  Post being rendered, 0 on listings.
	 * @return string HTML, not yet passed through wp_kses().
	 */
	public static function ayudawp_build_html( $options, $position, $url, $title, $post_id ) {
		if ( ! is_array( $options ) ) {
			return '';
		}

		$enabled = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		if ( empty( $enabled ) || ! is_array( $enabled ) ) {
			return '';
		}

		if ( ! in_array( $position, array( 'left', 'right', 'bottom' ), true ) ) {
			return '';
		}

		$autohide = ! empty( $options['floating_bar_autohide'] );
		/*
		 * Hidden on phones unless the admin says otherwise: a bar pinned over a
		 * small screen is the most intrusive thing this plugin can do, so the
		 * default errs on the side of not doing it.
		 */
		$hide_mobile = ! isset( $options['floating_bar_hide_mobile'] ) || ! empty( $options['floating_bar_hide_mobile'] );

		/*
		 * The buttons (v2.8.0): the content's, as it shows them, or the bar's
		 * own choice with its own order. Either way without the buttons the
		 * site cannot show and without the Google one when it goes outside.
		 */
		$buttons = ayudawp_aiss_get_floating_bar_buttons( $options );

		/**
		 * Filter the buttons shown in the floating bar.
		 *
		 * Receives the bar's buttons in order: the content's (since 2.8.0; until
		 * then, every enabled button in the block's order), or the ones chosen
		 * for the bar in its own order. Runs before the list is split into the
		 * ones in sight and the ones behind the toggle, so what it returns is
		 * split too.
		 *
		 * @since 2.5.0
		 * @param array $buttons Ordered button keys.
		 * @param int   $post_id Post being rendered.
		 */
		$buttons = apply_filters( 'ayudawp_aiss_floating_bar_buttons', $buttons, $post_id );

		if ( empty( $buttons ) || ! is_array( $buttons ) ) {
			return '';
		}

		$settings             = ayudawp_aiss_get_floating_bar_settings( $options );
		list( $shown, $rest ) = ayudawp_aiss_split_floating_bar_buttons( $buttons, $settings['compact'], $settings['compact_visible'] );

		// The look (v2.8.0): the content's or the bar's own, always icons.
		$look = ayudawp_aiss_get_floating_bar_look( $options );

		$classes = array_merge(
			array(
				'ayudawp-aiss-floating',
				'ayudawp-aiss-floating--' . $position,
				// The inline block's class brings the brand colour custom
				// properties and the button rules; floating-bar.css only has to
				// neutralise the block's own margins and place the bar.
				'ayudawp-share-buttons',
			),
			ayudawp_aiss_get_look_classes( $look ),
			// Hook for the bar's own rules, shared with the preview in Settings.
			array( 'ayudawp-aiss-bar' )
		);

		if ( ! empty( $rest ) ) {
			$classes[] = 'has-more';
		}
		if ( empty( $shown ) ) {
			$classes[] = 'is-single';
		}
		if ( $autohide ) {
			$classes[] = 'has-autohide';
		}
		if ( $hide_mobile ) {
			$classes[] = 'hide-on-mobile';
		}

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-post-id="' . esc_attr( (string) $post_id ) . '"'
			. ( $autohide ? ' data-autohide="1"' : '' )
			. ayudawp_aiss_get_custom_colors_attr( $look, $options )
			. ' role="complementary" aria-label="' . esc_attr__( 'Share this content', 'ai-share-summarize' ) . '">';

		$html .= '<div class="ayudawp-buttons-container" role="group" aria-label="'
			. esc_attr__( 'Share this content', 'ai-share-summarize' ) . '">';

		foreach ( $shown as $button ) {
			$html .= self::ayudawp_button( $button, $url, $title, $options );
		}

		if ( ! empty( $rest ) ) {
			$html .= self::ayudawp_more_html( $rest, $position, $url, $title, $options, empty( $shown ) );
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * One button of the bar
	 *
	 * @param string $button  Button key.
	 * @param string $url     Shared URL.
	 * @param string $title   Shared title.
	 * @param array  $options Plugin options.
	 * @return string Button HTML.
	 */
	private static function ayudawp_button( $button, $url, $title, $options ) {
		return AyudaWP_AISS_Buttons::ayudawp_get_button_html(
			$button,
			$url,
			$title,
			$options,
			true,
			'icons-only',
			'link'
		);
	}

	/**
	 * The toggle and the panel it opens (v2.8.0)
	 *
	 * A native <details>: it opens and closes with no script, is announced as
	 * an expandable button, and a site whose JS fails keeps every button
	 * reachable. floating-bar.js only adds closing on Escape and on a click
	 * outside. The panel opens beside the bar, not below the last button, so
	 * the bar keeps its height however many buttons are behind it.
	 *
	 * Two faces. With buttons in sight it is "More", a + that turns into an x.
	 * With none ("A single Share button") it is the share icon and the panel
	 * holds every button.
	 *
	 * The summary carries .ayudawp-share-btn to take the size and shape of its
	 * neighbours from the shared rules; it has no data-url or href, so the
	 * click and tracking handler in the main script lets it through.
	 *
	 * @param array  $rest     Button keys behind the toggle.
	 * @param string $position 'left', 'right' or 'bottom'.
	 * @param string $url      Shared URL.
	 * @param string $title    Shared title.
	 * @param array  $options  Plugin options.
	 * @param bool   $single   Whether the toggle is the only thing in sight.
	 * @return string HTML.
	 */
	private static function ayudawp_more_html( $rest, $position, $url, $title, $options, $single = false ) {
		$count = count( $rest );

		// Columns: a side panel grows sideways before it grows taller than
		// the bar; along the bottom one row holds up to six. On phones every
		// position is a strip along the bottom, five to a row.
		if ( 'bottom' === $position ) {
			$columns = min( $count, 6 );
		} elseif ( $count <= 4 ) {
			$columns = 1;
		} elseif ( $count <= 10 ) {
			$columns = 2;
		} else {
			$columns = 3;
		}
		$columns_phone = min( $count, 5 );

		if ( $single ) {
			/* translators: %d: number of share buttons inside the panel. */
			$label = sprintf( __( 'Share (%d)', 'ai-share-summarize' ), $count );
			$icon  = AyudaWP_AISS_Icons::ayudawp_get_button_icon( 'web_share', 20 );
		} else {
			/* translators: %d: number of share buttons inside the "More" panel. */
			$label = sprintf( __( 'More (%d)', 'ai-share-summarize' ), $count );
			$icon  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>';
		}

		$html  = '<details class="ayudawp-aiss-bar-more' . ( $single ? ' is-single' : '' ) . '">';
		$html .= '<summary class="ayudawp-share-btn ayudawp-aiss-bar-more-btn" aria-label="' . esc_attr( $label ) . '">'
			. $icon
			. '</summary>';
		$html .= '<div class="ayudawp-aiss-bar-panel" role="group" aria-label="' . esc_attr__( 'More ways to share', 'ai-share-summarize' ) . '"'
			. ' style="--aiss-cols:' . esc_attr( (string) $columns ) . ';--aiss-cols-phone:' . esc_attr( (string) $columns_phone ) . '">';

		foreach ( $rest as $button ) {
			$html .= self::ayudawp_button( $button, $url, $title, $options );
		}

		$html .= '</div></details>';

		return $html;
	}

	/**
	 * Allowed markup for the bar: the buttons plus the "More" disclosure (v2.8.0)
	 *
	 * @return array Allowlist for wp_kses().
	 */
	public static function ayudawp_allowed_html() {
		$allowed            = ayudawp_aiss_kses_buttons();
		$allowed['details'] = array( 'class' => array() );
		$allowed['summary'] = array(
			'class'      => array(),
			'aria-label' => array(),
		);

		return $allowed;
	}
}

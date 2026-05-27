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
		$summary_enabled = ! empty( $options['ai_summary_enabled'] );
		$summary_pos     = isset( $options['ai_summary_position'] ) ? $options['ai_summary_position'] : 'before_buttons';

		// Determine if assets are needed on this page.
		$should_load = false;

		$buttons_active = ( 'disabled' !== $insert_position );
		$summary_active = ( $summary_enabled && 'disabled' !== $summary_pos );

		if ( $buttons_active || $summary_active ) {
			// Auto-insert mode (buttons or summary): use the same display logic.
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

		/*
		 * Skip when running inside the excerpt pipeline. WordPress's
		 * wp_trim_excerpt() applies the_content filter internally to
		 * auto-truncate posts that have no manual excerpt. Themes or
		 * plugins that call the_excerpt() / get_the_excerpt() from the
		 * <head> (Open Graph meta, Twitter Card description, JSON-LD,
		 * etc.) would otherwise trigger the_content twice per request:
		 * once for the excerpt (result discarded) and once for the real
		 * template render. The 1.9.2+ id-match guard accepts the excerpt
		 * call as legitimate, marks the post as processed and the
		 * subsequent real call returns $content unchanged — so the
		 * buttons never reach the rendered HTML.
		 *
		 * Returning early here keeps the buttons out of any excerpt
		 * (where they don't belong anyway) and, crucially, prevents the
		 * post from being added to the $processed guard, so the real
		 * the_content invocation from the post template can still inject
		 * normally.
		 */
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ) ) {
			return $content;
		}

		$post_id = null;

		if ( is_singular() ) {
			// On singular pages the only valid target is the queried post.
			// Note: in_the_loop() is intentionally NOT checked here — Divi
			// Theme Builder (Divi 4 and Divi 5), FSE block templates and
			// Bricks Builder render the post content via the_content filter
			// WITHOUT setting up a standard WP loop, so in_the_loop() is
			// false even when the singular post is being rendered legitimately.
			$queried_id = get_queried_object_id();
			if ( ! $queried_id ) {
				return $content;
			}

			$current_id = get_the_ID();

			if ( $current_id && $current_id === $queried_id ) {
				/*
				 * Fast path: sane globals. Either standard WP loop or a
				 * modern builder (Divi / Bricks / FSE) rendering the
				 * queried post with the global $post properly set up.
				 * This is the 1.9.2 behaviour.
				 */
				$post_id = $queried_id;
			} else {
				/*
				 * Slow path: tainted globals. Typically a theme/plugin
				 * calling setup_postdata() from a header/sidebar/widget
				 * without a matching wp_reset_postdata(), which makes
				 * get_the_ID() return the wrong id everywhere. The strict
				 * 1.9.2 guard would then block the buttons on every entry
				 * except the one that happens to coincide with the tainted
				 * id (the typical "buttons only show on the latest post"
				 * symptom).
				 *
				 * We verify that the $content this filter received actually
				 * belongs to the queried post by matching a short stripped
				 * fragment of its post_content against the (already
				 * processed) $content. If it matches, trust the queried id.
				 * If it doesn't, this is genuine widget/footer content with
				 * arbitrary text and we skip — preserving the original
				 * 1.9.2 intent.
				 */
				static $queried_samples = array();
				if ( ! isset( $queried_samples[ $queried_id ] ) ) {
					$queried_post = get_post( $queried_id );
					$raw          = $queried_post ? strip_shortcodes( (string) $queried_post->post_content ) : '';
					$clean        = trim( wp_strip_all_tags( $raw ) );
					$queried_samples[ $queried_id ] = function_exists( 'mb_substr' )
						? mb_substr( $clean, 0, 50 )
						: substr( $clean, 0, 50 );
				}
				$sample = $queried_samples[ $queried_id ];

				// Sample too short to be reliable (very short or
				// image-only post): fall back to the strict behaviour
				// rather than risk a false positive on widget content.
				if ( strlen( $sample ) < 20 ) {
					return $content;
				}

				$haystack = wp_strip_all_tags( $content );
				$found    = function_exists( 'mb_strpos' )
					? mb_strpos( $haystack, $sample )
					: strpos( $haystack, $sample );

				if ( false === $found ) {
					return $content;
				}

				$post_id = $queried_id;
			}
		} else {
			// On archives/home, require the main loop so secondary loops and
			// widgets running the_content() are not picked up.
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}
			$post_id = get_the_ID();
		}

		if ( ! $post_id || in_array( $post_id, $processed, true ) ) {
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
		$summary_enabled = ! empty( $options['ai_summary_enabled'] );
		$frontend_button = ! empty( $options['ai_summary_frontend_button'] );
		$summary_pos     = isset( $options['ai_summary_position'] ) ? $options['ai_summary_position'] : 'before_buttons';

		// The summary slot is active when its position is not "disabled" AND
		// either auto-generation is on OR the visitor-driven button is on.
		// get_summary_html() applies the per-feature gating internally.
		$buttons_active = ( 'disabled' !== $insert_position );
		$summary_active = ( 'disabled' !== $summary_pos ) && ( $summary_enabled || $frontend_button );

		// Both pieces disabled: leave content untouched.
		if ( ! $buttons_active && ! $summary_active ) {
			return $content;
		}

		$buttons_html = $buttons_active
			? AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html()
			: '';

		$summary_html = (
			$summary_active
			&& $post_id
			&& class_exists( 'AyudaWP_AISS_AI_Summary' )
			&& ayudawp_aiss_should_apply_summary( $post_id )
		)
			? AyudaWP_AISS_AI_Summary::get_summary_html( $post_id )
			: '';

		if ( $post_id ) {
			$processed[] = $post_id;
		}

		/*
		 * Effective summary position falls back to 'before_content' when the
		 * summary is anchored to the buttons but the buttons are not being
		 * inserted (auto_insert_position = 'disabled'). Otherwise the summary
		 * would have nothing to anchor against and would never render.
		 */
		$effective_summary_pos = $summary_pos;
		if ( $summary_active && ! $buttons_active && in_array( $summary_pos, array( 'before_buttons', 'after_buttons' ), true ) ) {
			$effective_summary_pos = 'before_content';
		}

		return $this->ayudawp_compose_output( $content, $buttons_html, $summary_html, $insert_position, $effective_summary_pos );
	}

	/**
	 * Compose the final post output from content, buttons and summary parts
	 *
	 * Implements the position matrix:
	 * - `ai_summary_position === 'before_content'` → summary always at the start.
	 * - `ai_summary_position === 'before_buttons'` / `'after_buttons'` → summary
	 *   is glued to the buttons block (first occurrence when buttons are
	 *   inserted twice via `auto_insert_position === 'both'`).
	 *
	 * Summary is rendered exactly once regardless of `auto_insert_position`,
	 * because the summary itself is a single piece of per-post data.
	 *
	 * @param string $content         Original post content.
	 * @param string $buttons_html    Pre-built buttons HTML (may be '').
	 * @param string $summary_html    Pre-built summary HTML (may be '').
	 * @param string $insert_position Buttons position option.
	 * @param string $summary_pos     Effective summary position option.
	 * @return string Composed content.
	 */
	private function ayudawp_compose_output( $content, $buttons_html, $summary_html, $insert_position, $summary_pos ) {
		$has_buttons = '' !== $buttons_html;
		$has_summary = '' !== $summary_html;

		/*
		 * Build a single "buttons block with optional inline summary" decided
		 * by $summary_pos. The buttons-relative position ('before_buttons' /
		 * 'after_buttons') anchors the summary to the buttons regardless of
		 * where the buttons themselves get inserted ('before_content',
		 * 'after_content', 'both').
		 */
		if ( $has_buttons && $has_summary && 'before_buttons' === $summary_pos ) {
			$buttons_block = $summary_html . $buttons_html;
		} elseif ( $has_buttons && $has_summary && 'after_buttons' === $summary_pos ) {
			$buttons_block = $buttons_html . $summary_html;
		} else {
			$buttons_block = $buttons_html;
		}

		// Pre-content summary: only rendered separately when the position is before_content.
		$pre_summary = ( $has_summary && 'before_content' === $summary_pos ) ? $summary_html : '';

		switch ( $insert_position ) {
			case 'before_content':
				return $pre_summary . $buttons_block . $content;
			case 'both':
				// Summary appears once with the first buttons block; the second block stays plain.
				return $pre_summary . $buttons_block . $content . $buttons_html;
			case 'after_content':
			default:
				return $pre_summary . $content . $buttons_block;
		}
	}
}
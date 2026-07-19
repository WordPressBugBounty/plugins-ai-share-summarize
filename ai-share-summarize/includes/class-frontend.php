<?php
/**
 * Frontend functionality for Share Buttons & AI-powered Summaries plugin
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
		add_action( 'wp_head', array( $this, 'ayudawp_print_summary_critical_css' ) );
		add_filter( 'the_content', array( $this, 'ayudawp_add_share_buttons' ) );

		/*
		 * The "before content" summary is injected by a separate filter at a
		 * deliberately high priority so it runs AFTER content-rewriting filters
		 * (image optimizers, lazy-load, nofollow, table-of-contents, etc.) that
		 * reserialize the_content through DOMDocument. Those filters hoist a
		 * leading <style>/<aside> into the implicit <head> and drop it when they
		 * keep only the <body> children, which made the summary vanish when it
		 * was prepended at the default priority 10. Buttons stay at priority 10
		 * so their position relative to other injected content is unchanged.
		 */
		$before_content_priority = (int) apply_filters( 'ayudawp_aiss_summary_before_content_priority', 1000 );
		add_filter( 'the_content', array( $this, 'ayudawp_prepend_before_content_summary' ), $before_content_priority );
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
		$summary_options = ayudawp_aiss_get_summary_options();
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';
		$summary_enabled = ayudawp_aiss_is_summary_active();
		$summary_pos     = isset( $summary_options['ai_summary_position'] ) ? $summary_options['ai_summary_position'] : 'before_buttons';

		// Determine if assets are needed on this page.
		$should_load = false;

		$buttons_active = ( 'disabled' !== $insert_position );
		$summary_active = ( $summary_enabled && 'disabled' !== $summary_pos );

		if ( $buttons_active || $summary_active ) {
			// Auto-insert mode (buttons or summary): the buttons' locations
			// setting gates archives/home; singular pages get a second chance
			// below through the summary's own content-type list.
			$should_load = ayudawp_aiss_should_display_buttons();
		} else {
			// Shortcode-only mode: load on singular and archives
			// where shortcodes might be used in content or widgets.
			$should_load = is_singular() || is_archive() || is_home();
		}

		/*
		 * Decoupled summary gate (v2.3.0): on singular pages the summary no
		 * longer piggybacks on the buttons' locations/content-type settings —
		 * its own content-type list decides. This is what lets summaries show
		 * on post types the buttons are not configured for.
		 */
		if ( ! $should_load && $summary_active && is_singular() ) {
			$queried_id  = get_queried_object_id();
			$should_load = $queried_id && ayudawp_aiss_should_apply_summary( $queried_id );
		}

		if ( ! $should_load ) {
			return;
		}

		/*
		 * Per-post exclusions — independent for buttons and summary since
		 * 2.3.0. Skip loading assets on singular pages only when NEITHER
		 * feature will render for this post.
		 * get_queried_object_id() is used instead of get_the_ID() because it is
		 * reliable at wp_enqueue_scripts time (before the_content loop runs).
		 */
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$buttons_shown = $buttons_active
					&& ayudawp_aiss_should_display_buttons()
					&& ! ayudawp_aiss_is_post_excluded( $post_id );
				$summary_shown = $summary_active
					&& ayudawp_aiss_should_apply_summary( $post_id );
				if ( ! $buttons_shown && ! $summary_shown ) {
					return;
				}
			}
		}

		ayudawp_aiss_enqueue_frontend_assets();
	}

	/**
	 * Add the share buttons (and the buttons-anchored summary) to the content
	 *
	 * Runs at the default the_content priority (10). The standalone
	 * "before content" summary is injected separately by
	 * ayudawp_prepend_before_content_summary() at a high priority so it is not
	 * stripped by content-rewriting filters. This method therefore only injects
	 * the buttons and, for the before_buttons / after_buttons positions, the
	 * summary glued to the buttons block.
	 *
	 * @param string $content Post content.
	 * @return string Modified content with buttons.
	 */
	public function ayudawp_add_share_buttons( $content ) {
		// Track which post IDs already received the buttons so the same post
		// can never get them twice on the same request.
		static $processed = array();

		$post_id = $this->ayudawp_resolve_target_post_id( $content );
		if ( ! $post_id || in_array( $post_id, $processed, true ) ) {
			return $content;
		}

		if ( ! ayudawp_aiss_should_display_buttons() ) {
			return $content;
		}

		// Apply per-post exclusion set via the meta box.
		if ( ayudawp_aiss_is_post_excluded( $post_id ) ) {
			return $content;
		}

		$options         = get_option( 'ayudawp_aiss_options' );
		$summary_options = ayudawp_aiss_get_summary_options();
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';
		$summary_enabled = ayudawp_aiss_is_summary_active();
		$frontend_button = ! empty( $summary_options['ai_summary_frontend_button'] );
		$summary_pos     = isset( $summary_options['ai_summary_position'] ) ? $summary_options['ai_summary_position'] : 'before_buttons';

		// Buttons are this filter's job. When auto-insert is disabled there is
		// nothing to do here — a standalone summary (if any) is rendered by
		// ayudawp_prepend_before_content_summary().
		if ( 'disabled' === $insert_position ) {
			return $content;
		}

		$buttons_html = AyudaWP_AISS_Buttons::ayudawp_generate_buttons_html();

		// Glue the summary to the buttons only for the buttons-relative
		// positions. 'before_content' is rendered standalone at a later
		// priority and must not be added here.
		$glue_summary = ( $summary_enabled || $frontend_button )
			&& in_array( $summary_pos, array( 'before_buttons', 'after_buttons' ), true )
			&& class_exists( 'AyudaWP_AISS_AI_Summary' )
			&& ayudawp_aiss_should_apply_summary( $post_id );

		$summary_html = $glue_summary
			? AyudaWP_AISS_AI_Summary::get_summary_html( $post_id )
			: '';

		$processed[] = $post_id;

		return $this->ayudawp_compose_output( $content, $buttons_html, $summary_html, $insert_position, $summary_pos );
	}

	/**
	 * Resolve which post the current the_content call belongs to (v2.1.0)
	 *
	 * Shared by ayudawp_add_share_buttons() and
	 * ayudawp_prepend_before_content_summary() so both filters agree on the
	 * target. Returns the post ID to inject into, or null when this the_content
	 * call must be left untouched (backend, feed, excerpt pipeline, secondary
	 * loop / widget content, or tainted globals that don't match the queried
	 * post). Each caller keeps its own "already processed" guard.
	 *
	 * @param string $content Content passed to the_content.
	 * @return int|null Target post ID, or null to skip injection.
	 */
	private function ayudawp_resolve_target_post_id( $content ) {
		// Backend or feeds: never inject.
		if ( is_admin() || is_feed() ) {
			return null;
		}

		/*
		 * Skip when running inside the excerpt pipeline. WordPress's
		 * wp_trim_excerpt() applies the_content internally to auto-truncate
		 * posts with no manual excerpt. Themes/plugins that call the_excerpt()
		 * / get_the_excerpt() from the <head> (Open Graph, Twitter Card,
		 * JSON-LD, etc.) would otherwise trigger the_content twice: once for
		 * the excerpt (discarded) and once for the real render. Returning null
		 * keeps the injection out of excerpts and, crucially, prevents the
		 * caller from marking the post as processed, so the real the_content
		 * invocation still injects normally.
		 */
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ) ) {
			return null;
		}

		if ( is_singular() ) {
			// On singular pages the only valid target is the queried post.
			// Note: in_the_loop() is intentionally NOT checked here — Divi
			// Theme Builder (Divi 4 and Divi 5), FSE block templates and
			// Bricks Builder render the post content via the_content filter
			// WITHOUT setting up a standard WP loop, so in_the_loop() is
			// false even when the singular post is being rendered legitimately.
			$queried_id = get_queried_object_id();
			if ( ! $queried_id ) {
				return null;
			}

			$current_id = get_the_ID();

			if ( $current_id && $current_id === $queried_id ) {
				/*
				 * Fast path: sane globals. Either standard WP loop or a modern
				 * builder (Divi / Bricks / FSE) rendering the queried post with
				 * the global $post properly set up.
				 */
				return $queried_id;
			}

			/*
			 * Slow path: tainted globals. Typically a theme/plugin calling
			 * setup_postdata() from a header/sidebar/widget without a matching
			 * wp_reset_postdata(), which makes get_the_ID() return the wrong id
			 * everywhere. We verify that the $content this filter received
			 * actually belongs to the queried post by matching a short stripped
			 * fragment of its post_content against $content. If it matches,
			 * trust the queried id; otherwise this is genuine widget/footer
			 * content with arbitrary text and we skip.
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

			// Sample too short to be reliable (very short or image-only post):
			// fall back to the strict behaviour rather than risk a false
			// positive on widget content.
			if ( strlen( $sample ) < 20 ) {
				return null;
			}

			$haystack = wp_strip_all_tags( $content );
			$found    = function_exists( 'mb_strpos' )
				? mb_strpos( $haystack, $sample )
				: strpos( $haystack, $sample );

			return ( false === $found ) ? null : $queried_id;
		}

		// On archives/home, require the main loop so secondary loops and
		// widgets running the_content() are not picked up.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return null;
		}
		$post_id = get_the_ID();

		return $post_id ? $post_id : null;
	}

	/**
	 * Prepend the standalone "before content" summary at a late priority (v2.1.0)
	 *
	 * Runs after content-rewriting filters (image optimizers, lazy-load,
	 * nofollow, table-of-contents, etc.) so a block injected at the start of
	 * the content is not dropped when those filters reserialize the_content
	 * through DOMDocument. Handles the 'before_content' position and the
	 * fallback where the summary is anchored to the buttons but the buttons are
	 * not being auto-inserted (nothing to glue to). The buttons-glued positions
	 * are handled in ayudawp_add_share_buttons().
	 *
	 * @param string $content Post content.
	 * @return string Content with the summary prepended when applicable.
	 */
	public function ayudawp_prepend_before_content_summary( $content ) {
		static $processed = array();

		if ( ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return $content;
		}

		$post_id = $this->ayudawp_resolve_target_post_id( $content );
		if ( ! $post_id || in_array( $post_id, $processed, true ) ) {
			return $content;
		}

		/*
		 * The buttons' locations setting only gates non-singular contexts
		 * since 2.3.0: on singular pages the summary is governed by its own
		 * content-type list (checked below via should_apply_summary), so it
		 * can render on post types the buttons are not configured for.
		 */
		if ( ! is_singular() && ! ayudawp_aiss_should_display_buttons() ) {
			return $content;
		}

		$options         = get_option( 'ayudawp_aiss_options' );
		$summary_options = ayudawp_aiss_get_summary_options();
		$insert_position = isset( $options['auto_insert_position'] ) ? $options['auto_insert_position'] : 'after_content';
		$summary_enabled = ayudawp_aiss_is_summary_active();
		$frontend_button = ! empty( $summary_options['ai_summary_frontend_button'] );
		$summary_pos     = isset( $summary_options['ai_summary_position'] ) ? $summary_options['ai_summary_position'] : 'before_buttons';

		$summary_active = ( 'disabled' !== $summary_pos ) && ( $summary_enabled || $frontend_button );
		if ( ! $summary_active ) {
			return $content;
		}

		/*
		 * Render standalone at the start of the content when the position is
		 * 'before_content', or when the summary is anchored to the buttons but
		 * the buttons are not visible on THIS post (globally disabled, this
		 * post type outside the buttons' locations, or excluded per post), so
		 * there is nothing to glue it to. The glued cases live in
		 * ayudawp_add_share_buttons(). The per-post summary exclusion itself
		 * is enforced inside should_apply_summary().
		 */
		$buttons_visible = ( 'disabled' !== $insert_position )
			&& ayudawp_aiss_should_display_buttons()
			&& ! ayudawp_aiss_is_post_excluded( $post_id );
		$standalone      = ( 'before_content' === $summary_pos )
			|| ( ! $buttons_visible && in_array( $summary_pos, array( 'before_buttons', 'after_buttons' ), true ) );

		if ( ! $standalone || ! ayudawp_aiss_should_apply_summary( $post_id ) ) {
			return $content;
		}

		/*
		 * Guard against a nested/secondary the_content render stealing the slot.
		 * Because this filter runs late (after priority-999 plugins), a plugin
		 * that fires a nested the_content of the same post during its own
		 * priority-999 pass (e.g. while building related content) reaches this
		 * filter BEFORE the outer/visible render does. Without a discriminator it
		 * would take the summary and mark the post processed, leaving the real
		 * content without it (buttons survive because they inject at priority 10,
		 * before any nesting). When the buttons are visible on this post, the
		 * visible main content already carries the share-buttons block, while the
		 * skipped nested render does not — so require that marker to make sure we
		 * prepend to the stream the buttons filter already claimed.
		 */
		if ( $buttons_visible && false === strpos( $content, 'ayudawp-share-buttons' ) ) {
			return $content;
		}

		$summary_html = AyudaWP_AISS_AI_Summary::get_summary_html( $post_id );
		if ( '' === $summary_html ) {
			return $content;
		}

		$processed[] = $post_id;

		return $summary_html . $content;
	}

	/**
	 * Print the summary block's critical inline CSS in <head> (v2.1.0)
	 *
	 * Previously the critical CSS was emitted inside the_content next to the
	 * summary block. Content-rewriting filters that reserialize via DOMDocument
	 * hoist a mid-content <style> into the implicit <head> and then drop it when
	 * they keep only the <body> children, leaving the block unstyled. Printing
	 * it in the real <head> makes it immune to those filters.
	 * get_summary_html() / get_generate_button_html() keep a self-guarded inline
	 * fallback for late shortcode renders that happen after wp_head has fired.
	 */
	public function ayudawp_print_summary_critical_css() {
		if ( ! is_singular() || ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		// No buttons-locations gate here (v2.3.0): whether the summary shows
		// on this singular page is decided by its own rules alone.
		$summary_options = ayudawp_aiss_get_summary_options();
		$summary_pos     = isset( $summary_options['ai_summary_position'] ) ? $summary_options['ai_summary_position'] : 'before_buttons';
		$summary_enabled = ayudawp_aiss_is_summary_active();
		$frontend_button = ! empty( $summary_options['ai_summary_frontend_button'] );

		if ( 'disabled' === $summary_pos || ( ! $summary_enabled && ! $frontend_button ) ) {
			return;
		}

		if ( ! ayudawp_aiss_should_apply_summary( $post_id ) ) {
			return;
		}

		AyudaWP_AISS_AI_Summary::print_critical_inline_style();
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
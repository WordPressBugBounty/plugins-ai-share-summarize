<?php
/**
 * AI Summary class for AI Share & Summarize plugin
 *
 * Generates an inline summary of post content using the WordPress 7.0
 * AI Client (wp_ai_client_prompt()) when available, with a PHP extractive
 * fallback for older WordPress versions or sites without an AI provider
 * configured in Settings > Connectors.
 *
 * Generation runs asynchronously via wp_schedule_single_event() to avoid
 * blocking the save_post response — the AI provider call can take 5-30s.
 *
 * @package AiShareSummarize
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle AI summary generation, storage and rendering
 */
class AyudaWP_AISS_AI_Summary {

	const META_SUMMARY    = '_ayudawp_aiss_summary';
	const META_PROVIDER   = '_ayudawp_aiss_summary_provider';
	const META_HASH       = '_ayudawp_aiss_summary_hash';
	const CRON_HOOK       = 'ayudawp_aiss_generate_summary_async';
	const LOCK_PREFIX     = '_ayudawp_aiss_generating_';
	const LAST_ERROR_OPT  = 'ayudawp_aiss_last_ai_error';

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'wp_after_insert_post', array( $this, 'on_save_post' ), 20, 4 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ), 10, 1 );
	}

	/**
	 * Register the three summary meta keys for REST exposure
	 *
	 * Only registers on the post types selected in auto_insert_content_types
	 * so the block editor sidebar gets the meta in its data store for those
	 * types only.
	 */
	public function register_meta() {
		$post_types = ayudawp_aiss_get_summary_post_types();
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		$auth_callback = function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_SUMMARY,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '',
					'auth_callback' => $auth_callback,
				)
			);
			register_post_meta(
				$post_type,
				self::META_PROVIDER,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '',
					'auth_callback' => $auth_callback,
				)
			);
			register_post_meta(
				$post_type,
				self::META_HASH,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '',
					'auth_callback' => $auth_callback,
				)
			);
		}
	}

	/**
	 * Fire on every post save — decide whether to schedule async generation
	 *
	 * Uses wp_after_insert_post (WP 5.6+) which fires after both the post
	 * row and its meta are committed, so the manual-edit provider flag is
	 * already in place when this runs.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post        Post object.
	 * @param bool    $update      Whether this is an existing post being updated.
	 * @param mixed   $post_before The previous version of the post or null on insert.
	 */
	public function on_save_post( $post_id, $post, $update, $post_before ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$options = get_option( 'ayudawp_aiss_options', array() );

		if ( empty( $options['ai_summary_enabled'] ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! ayudawp_aiss_should_apply_summary( $post_id ) ) {
			return;
		}

		// Respect manual edits — user override always wins.
		if ( 'manual' === get_post_meta( $post_id, self::META_PROVIDER, true ) ) {
			return;
		}

		if ( ! $this->should_regenerate( $post_id, $post ) ) {
			return;
		}

		// Re-entrancy lock: avoid duplicate cron events for the same post.
		$lock_key = self::LOCK_PREFIX . $post_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

		wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( (int) $post_id ) );
	}

	/**
	 * Check whether the stored summary is still valid for the current content
	 *
	 * Compares the current content+title hash against the stored hash.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Optional post object to avoid an extra fetch.
	 * @return bool True when the summary should be regenerated.
	 */
	public function should_regenerate( $post_id, $post = null ) {
		if ( null === $post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post ) {
			return false;
		}
		$current_hash = self::compute_hash( $post->post_content, $post->post_title );
		$stored_hash  = get_post_meta( $post_id, self::META_HASH, true );
		return $current_hash !== $stored_hash;
	}

	/**
	 * Compute the content hash used to detect significant content changes
	 *
	 * @param string $content Post content.
	 * @param string $title   Post title.
	 * @return string SHA1 hash.
	 */
	public static function compute_hash( $content, $title ) {
		return sha1( wp_strip_all_tags( (string) $content ) . '|' . (string) $title );
	}

	/**
	 * Orchestrate the provider cascade: A (WP AI Client) → C (extractive)
	 *
	 * Called by:
	 * - the cron hook (async, after save_post)
	 * - the REST endpoint (sync, when the editor user clicks "Regenerate now")
	 *
	 * Always releases the lock transient on exit so a future save can
	 * schedule a new generation even when the call errored.
	 *
	 * @param int  $post_id          Post ID to summarize.
	 * @param bool $force            When true, regenerate even if the hash matches.
	 * @param bool $force_extractive When true, skip Level A (AI) and go straight to extractive.
	 *                               Used by the public frontend endpoint when the admin has
	 *                               opted out of letting visitors trigger paid API calls.
	 * @return string|WP_Error Summary text on success, WP_Error on failure.
	 */
	public static function run( $post_id, $force = false, $force_extractive = false ) {
		$post_id  = (int) $post_id;
		$lock_key = self::LOCK_PREFIX . $post_id;

		try {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'no_post', __( 'Post not found.', 'ai-share-summarize' ) );
			}

			$content = $post->post_content;
			$title   = $post->post_title;
			$hash    = self::compute_hash( $content, $title );

			if ( ! $force ) {
				$stored_hash = get_post_meta( $post_id, self::META_HASH, true );
				if ( $hash === $stored_hash ) {
					return get_post_meta( $post_id, self::META_SUMMARY, true );
				}
			}

			$options = get_option( 'ayudawp_aiss_options', array() );

			if ( $force_extractive ) {
				// Skip Level A entirely — caller wants zero-cost generation
				// (typically the public frontend endpoint).
				$summary  = '';
				$provider = '';
			} else {
				// Level A — WP AI Client (WordPress 7.0+).
				$summary  = self::generate_with_wp_ai_client( $content, $title );
				$provider = 'wp_ai_client';
			}

			if ( is_wp_error( $summary ) || '' === $summary ) {
				$ai_error = is_wp_error( $summary ) ? $summary : null;

				// Persist the last AI error for diagnostics (visible from the
				// sidebar, the meta box and an admin notice — much more useful
				// than relying on error_log + WP_DEBUG). Skip when the caller
				// forced extractive — it isn't a real failure.
				if ( ! $force_extractive ) {
					self::store_last_ai_error( $ai_error );
				}

				// Forced-extractive callers always run the fallback regardless
				// of the admin's ai_summary_use_extractive_fallback setting,
				// since the caller is explicitly opting into it.
				if ( ! $force_extractive && empty( $options['ai_summary_use_extractive_fallback'] ) ) {
					if ( $ai_error ) {
						// Surface the real error code/message so the user sees
						// what really happened, not a generic "AI failed".
						return new WP_Error(
							'no_fallback',
							sprintf(
								/* translators: %s is the underlying error message from the AI client. */
								__( 'AI generation failed and extractive fallback is disabled: %s', 'ai-share-summarize' ),
								$ai_error->get_error_message()
							)
						);
					}
					return new WP_Error( 'no_fallback', __( 'AI generation failed and extractive fallback is disabled.', 'ai-share-summarize' ) );
				}

				// Level C — PHP extractive fallback.
				$n_sentences = isset( $options['ai_summary_sentences'] ) ? (int) $options['ai_summary_sentences'] : 3;
				$summary     = ayudawp_aiss_extractive_summary( $content, $title, $n_sentences );
				$provider    = 'extractive';
			} else {
				// Successful AI run — clear any stale error.
				self::store_last_ai_error( null );
			}

			if ( '' === $summary ) {
				return new WP_Error( 'empty_summary', __( 'Could not generate a summary for this content.', 'ai-share-summarize' ) );
			}

			update_post_meta( $post_id, self::META_SUMMARY, wp_kses_post( $summary ) );
			update_post_meta( $post_id, self::META_PROVIDER, $provider );
			update_post_meta( $post_id, self::META_HASH, $hash );

			return $summary;
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Generate the summary via the WordPress 7.0 AI Client
	 *
	 * Returns WP_Error when wp_ai_client_prompt() is unavailable, when
	 * the configured Connector cannot handle text generation, or when
	 * the call itself fails.
	 *
	 * The builder chain is wrapped in try/catch \Throwable to survive
	 * any future signature drift in the WP AI Client API.
	 *
	 * Note: the builder uses PHP __call() magic to proxy snake_case to
	 * camelCase methods, so `method_exists()` cannot reliably detect
	 * support-check methods like `is_supported_for_text_generation`.
	 * We skip that check and just let `generate_text()` fail loudly
	 * if the provider can't handle the request — its WP_Error already
	 * carries the precise reason (no provider configured, model lacks
	 * text capability, etc.).
	 *
	 * @param string $content Post content.
	 * @param string $title   Post title.
	 * @return string|WP_Error Summary text on success.
	 */
	public static function generate_with_wp_ai_client( $content, $title ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'no_wp_ai_client', __( 'WP AI Client is not available (requires WordPress 7.0+).', 'ai-share-summarize' ) );
		}

		$options     = get_option( 'ayudawp_aiss_options', array() );
		$n_sentences = isset( $options['ai_summary_sentences'] ) ? max( 1, min( 5, (int) $options['ai_summary_sentences'] ) ) : 3;

		$system  = 'You are a content summarizer. Return ONLY the summary in plain text, no markdown, no quotes, no preamble. Match the language of the input.';
		$clean   = ayudawp_aiss_clean_html_for_summary( (string) $content );
		$excerpt = function_exists( 'mb_substr' )
			? mb_substr( $clean, 0, 3000 )
			: substr( $clean, 0, 3000 );

		$prompt = sprintf(
			"Summarize the following content in exactly %d sentences:\n\n%s\n\n%s",
			$n_sentences,
			$title,
			$excerpt
		);

		try {
			$result = wp_ai_client_prompt( $prompt )
				->using_system_instruction( $system )
				->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'wp_ai_client_exception',
				sprintf( '%s (%s)', $e->getMessage(), get_class( $e ) )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$text = trim( wp_strip_all_tags( (string) $result ) );

		if ( '' === $text ) {
			return new WP_Error( 'wp_ai_client_empty', __( 'WP AI Client returned an empty response.', 'ai-share-summarize' ) );
		}

		return $text;
	}

	/**
	 * Persist (or clear) the last AI Client error for diagnostics
	 *
	 * The option holds the most recent failure only — successful generations
	 * clear it. Surfaced in the editor sidebar, the classic meta box and an
	 * admin notice so the admin can act on the actual error without enabling
	 * WP_DEBUG to read PHP logs.
	 *
	 * @param WP_Error|null $error Error to store, or null to clear.
	 */
	public static function store_last_ai_error( $error ) {
		if ( null === $error ) {
			delete_option( self::LAST_ERROR_OPT );
			return;
		}

		update_option( self::LAST_ERROR_OPT, array(
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
			'time'    => time(),
		), false );
	}

	/**
	 * Read the last AI Client error
	 *
	 * Auto-expires after 7 days so a long-resolved transient failure does
	 * not stick to the settings page forever — sites running on extractive
	 * only would never clear it otherwise, because the clear-on-success
	 * path requires a successful AI generation to fire.
	 *
	 * @return array|null Array with code/message/time, or null when no error is stored.
	 */
	public static function get_last_ai_error() {
		$stored = get_option( self::LAST_ERROR_OPT );
		if ( ! is_array( $stored ) || empty( $stored['message'] ) ) {
			return null;
		}
		$age = isset( $stored['time'] ) ? ( time() - (int) $stored['time'] ) : 0;
		if ( $age > 7 * DAY_IN_SECONDS ) {
			delete_option( self::LAST_ERROR_OPT );
			return null;
		}
		return $stored;
	}

	/**
	 * Critical inline CSS for the summary block (v2.0.0)
	 *
	 * Printed once per page right before the first summary or placeholder
	 * is rendered. Performance plugins (wpo-tweaks, WP Rocket, LiteSpeed,
	 * etc.) often defer the main stylesheet with `rel="preload"`, which
	 * can leave the block visually broken until the deferred CSS finishes
	 * loading — or fail entirely on browsers/CSPs that block onload
	 * handlers. These few rules guarantee the summary looks acceptable
	 * even when the external stylesheet never applies.
	 *
	 * The full stylesheet still wins because it ships richer rules
	 * (dark-mode adaptation, caret rotation, attribution color), but the
	 * baseline survives without it.
	 *
	 * Echoes directly (rather than returning a string the caller would echo)
	 * so the output is a plain literal-string echo with no escaping concerns —
	 * `echo helper()` of pre-built markup is a wordpress.org review red flag.
	 * Idempotent: the static guard prints at most once per request, whether
	 * the first call comes from wp_head (auto-insert) or from the summary
	 * renderer (late shortcode fallback after wp_head has fired).
	 *
	 * @return void
	 */
	public static function print_critical_inline_style() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		// All-literal CSS; echoing string literals needs no output escaping.
		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- one-liner CSS for minimal payload.
		echo '<style id="ayudawp-aiss-critical-inline">'
			. '.ayudawp-aiss-summary{margin:24px 0;padding:12px 16px;border:1px solid #e0e0e0;border-radius:6px}'
			. '.ayudawp-aiss-summary-details{margin:0}'
			. '.ayudawp-aiss-summary-details>summary{cursor:pointer;font-weight:600;list-style:none;display:inline-flex;align-items:center;gap:8px}'
			. '.ayudawp-aiss-summary-details>summary::-webkit-details-marker{display:none}'
			. '.ayudawp-aiss-summary-content{margin-top:8px}'
			. '.ayudawp-aiss-summary-content p{margin:0;line-height:1.5}'
			. '.ayudawp-aiss-summary--placeholder{padding:0;border:none;background:transparent}'
			. '.ayudawp-aiss-summary-generate-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:transparent;border:1px solid #e0e0e0;border-radius:6px;font-weight:600;font-size:.95em;color:inherit;cursor:pointer}'
			. '.ayudawp-aiss-summary-icon{display:inline-flex;align-items:center}'
			. '.ayudawp-aiss-summary-icon svg{width:16px;height:16px}'
			. '</style>';
		// phpcs:enable
	}

	/**
	 * Build the CSS class string for the summary <aside> (v2.1.0)
	 *
	 * Encapsulates the visual-style preset (minimal/outline/brand/dark/custom)
	 * and the icon-position modifier so get_summary_html() and
	 * get_generate_button_html() stay in sync.
	 *
	 * @param array  $options Plugin options.
	 * @param string $extra   Extra base class to append (e.g. the placeholder modifier).
	 * @return string Space-separated class list.
	 */
	private static function get_summary_block_classes( $options, $extra = '' ) {
		$style    = isset( $options['ai_summary_style'] ) ? $options['ai_summary_style'] : 'minimal';
		$icon_pos = isset( $options['ai_summary_icon_position'] ) ? $options['ai_summary_icon_position'] : 'left';

		$classes = array( 'ayudawp-aiss-summary' );
		if ( '' !== $extra ) {
			$classes[] = $extra;
		}
		if ( in_array( $style, array( 'outline', 'brand', 'dark', 'custom' ), true ) ) {
			$classes[] = 'style-' . $style;
		}
		if ( 'right' === $icon_pos ) {
			$classes[] = 'icon-right';
		} elseif ( 'hidden' === $icon_pos ) {
			$classes[] = 'icon-hidden';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Resolve the custom background/text colors for the summary block (v2.1.0)
	 *
	 * Only returns values when the style is "custom"; mirrors the share buttons'
	 * custom color CSS-variable pattern (see class-buttons.php). The caller
	 * escapes each value with esc_attr() at the point of output, so escaping is
	 * visible at the echo site (no `echo $prebuilt_attr` to suppress).
	 *
	 * @param array $options Plugin options.
	 * @return array|null array{bg:string,text:string} when custom, else null.
	 */
	private static function get_summary_block_custom_colors( $options ) {
		$style = isset( $options['ai_summary_style'] ) ? $options['ai_summary_style'] : 'minimal';
		if ( 'custom' !== $style ) {
			return null;
		}

		return array(
			'bg'   => ! empty( $options['ai_summary_custom_bg'] ) ? $options['ai_summary_custom_bg'] : '#ffffff',
			'text' => ! empty( $options['ai_summary_custom_text'] ) ? $options['ai_summary_custom_text'] : '#1a1a1a',
		);
	}

	/**
	 * Get the summary HTML to render in the frontend or shortcode
	 *
	 * Returns an empty string when there is no stored summary so callers
	 * can simply concatenate without checking.
	 *
	 * @param int $post_id Post ID.
	 * @return string HTML for the collapsible summary block, or ''.
	 */
	public static function get_summary_html( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return '';
		}

		$summary = get_post_meta( $post_id, self::META_SUMMARY, true );

		if ( '' === $summary ) {
			// No stored summary — render the public "Generate" button when
			// the admin has enabled visitor-driven generation. Otherwise
			// stay silent so the post layout isn't disturbed.
			return self::get_generate_button_html( $post_id );
		}

		$provider = get_post_meta( $post_id, self::META_PROVIDER, true );
		$options  = get_option( 'ayudawp_aiss_options', array() );
		$open     = empty( $options['ai_summary_collapsed_default'] ) ? ' open' : '';
		$is_basic = 'extractive' === $provider;

		$label = ! empty( $options['ai_summary_label'] )
			? $options['ai_summary_label']
			: __( 'AI Summary', 'ai-share-summarize' );

		$class_attr = self::get_summary_block_classes( $options );
		$custom     = self::get_summary_block_custom_colors( $options );

		$icon_pos = isset( $options['ai_summary_icon_position'] ) ? $options['ai_summary_icon_position'] : 'left';
		$icon     = ( 'hidden' !== $icon_pos && class_exists( 'AyudaWP_AISS_Icons' ) )
			? AyudaWP_AISS_Icons::ayudawp_get_icon( 'aiss_summary', 16 )
			: '';

		// Split into sentences so each renders on its own line (v2.1.0). The
		// AI provider usually returns sentences glued with a single space, so
		// the split happens at render time and also fixes already-stored
		// summaries without regenerating them.
		$sentences = ayudawp_aiss_split_sentences( $summary );
		if ( empty( $sentences ) ) {
			$sentences = array( $summary );
		}

		ob_start();
		self::print_critical_inline_style();
		?>
		<aside class="<?php echo esc_attr( $class_attr ); ?>"<?php if ( $custom ) : ?> style="--ayudawp-aiss-bg:<?php echo esc_attr( $custom['bg'] ); ?>;--ayudawp-aiss-text:<?php echo esc_attr( $custom['text'] ); ?>;"<?php endif; ?>
		       role="complementary"
		       aria-label="<?php echo esc_attr( $label ); ?>"
		       data-provider="<?php echo esc_attr( $provider ); ?>"
		       data-nosnippet
		       itemscope itemtype="https://schema.org/CreativeWork">
			<meta itemprop="genre" content="summary">
			<details class="ayudawp-aiss-summary-details"<?php echo esc_attr( $open ); ?>>
				<summary class="ayudawp-aiss-summary-toggle">
					<?php if ( $icon ) : ?>
						<span class="ayudawp-aiss-summary-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by AyudaWP_AISS_Icons. ?></span>
					<?php endif; ?>
					<span class="ayudawp-aiss-summary-label"><?php echo esc_html( $label ); ?></span>
				</summary>
				<div class="ayudawp-aiss-summary-content">
					<div class="ayudawp-aiss-summary-text" itemprop="abstract">
						<?php foreach ( $sentences as $sentence ) : ?>
							<p><?php echo esc_html( $sentence ); ?></p>
						<?php endforeach; ?>
					</div>
					<?php if ( $is_basic ) : ?>
						<small class="ayudawp-aiss-summary-attribution"><?php esc_html_e( 'Basic summary', 'ai-share-summarize' ); ?></small>
					<?php endif; ?>
				</div>
			</details>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the "Generate summary" placeholder HTML for visitors (v2.0.0)
	 *
	 * Returns empty when the admin has not enabled the frontend button or
	 * when the post is excluded — keeping the markup empty so the post
	 * layout isn't disturbed for ineligible posts.
	 *
	 * @param int $post_id Post ID.
	 * @return string Button HTML or ''.
	 */
	public static function get_generate_button_html( $post_id ) {
		$options = get_option( 'ayudawp_aiss_options', array() );

		if ( empty( $options['ai_summary_frontend_button'] ) ) {
			return '';
		}

		if ( ayudawp_aiss_is_post_excluded( $post_id ) ) {
			return '';
		}

		$label = ! empty( $options['ai_summary_generate_button_label'] )
			? $options['ai_summary_generate_button_label']
			: __( 'Generate AI summary', 'ai-share-summarize' );

		$class_attr = self::get_summary_block_classes( $options, 'ayudawp-aiss-summary--placeholder' );
		$custom     = self::get_summary_block_custom_colors( $options );

		$icon_pos = isset( $options['ai_summary_icon_position'] ) ? $options['ai_summary_icon_position'] : 'left';
		$icon     = ( 'hidden' !== $icon_pos && class_exists( 'AyudaWP_AISS_Icons' ) )
			? AyudaWP_AISS_Icons::ayudawp_get_icon( 'aiss_summary', 16 )
			: '';

		ob_start();
		self::print_critical_inline_style();
		?>
		<aside class="<?php echo esc_attr( $class_attr ); ?>"<?php if ( $custom ) : ?> style="--ayudawp-aiss-bg:<?php echo esc_attr( $custom['bg'] ); ?>;--ayudawp-aiss-text:<?php echo esc_attr( $custom['text'] ); ?>;"<?php endif; ?>
		       role="complementary"
		       aria-label="<?php echo esc_attr( $label ); ?>"
		       data-post-id="<?php echo esc_attr( $post_id ); ?>"
		       data-nosnippet>
			<button type="button" class="ayudawp-aiss-summary-generate-btn">
				<?php if ( $icon ) : ?>
					<span class="ayudawp-aiss-summary-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by AyudaWP_AISS_Icons. ?></span>
				<?php endif; ?>
				<span class="ayudawp-aiss-summary-label"><?php echo esc_html( $label ); ?></span>
			</button>
		</aside>
		<?php
		return (string) ob_get_clean();
	}
}

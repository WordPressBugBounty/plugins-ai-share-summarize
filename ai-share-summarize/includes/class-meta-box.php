<?php
/**
 * Meta Box class for Share Buttons & AI-powered Summaries plugin
 *
 * Handles the meta box for excluding individual posts/pages.
 * Since v1.7.3: block editor uses a sidebar panel (PluginDocumentSettingPanel)
 * instead of a classic meta box, for compatibility with WordPress 7.0
 * real-time collaboration (RTC).
 *
 * @package AiShareSummarize
 * @since 1.4.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle meta box functionality
 */
class AyudaWP_AISS_Meta_Box {

	/**
	 * Meta key for the share-buttons exclusion
	 */
	const META_KEY = '_ayudawp_aiss_exclude';

	/**
	 * Meta key for the AI-summary exclusion (v2.3.0)
	 *
	 * Independent from META_KEY since 2.3.0: before that, the single
	 * buttons checkbox silently hid the summary too. The 2.3.0 migration
	 * stamps this meta on every post that had the old one checked.
	 */
	const META_KEY_SUMMARY = '_ayudawp_aiss_exclude_summary';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'ayudawp_add_meta_box' ) );
		add_action( 'save_post', array( $this, 'ayudawp_save_meta_box' ), 10, 2 );

		// Support for block editor (Gutenberg).
		add_action( 'init', array( $this, 'ayudawp_register_meta' ) );

		// Sidebar panel for the block editor (v1.7.3+).
		add_action( 'enqueue_block_editor_assets', array( $this, 'ayudawp_enqueue_editor_sidebar' ) );

		// Whether the post is too short for a summary, for that panel (v2.7.0).
		add_action( 'rest_api_init', array( $this, 'ayudawp_register_rest_fields' ) );
	}

	/**
	 * Tell the block editor when a post is too short for a summary (v2.7.0)
	 *
	 * Since 2.6.0 a post under the minimum gets no summary and a stored AI one
	 * is not shown, but both editors kept showing that stored text, so the
	 * author only found out by pressing Regenerate. The classic box asks
	 * is_below_minimum() while it renders. The block panel cannot, because
	 * the text changes as it is typed, and counting in JavaScript would mean a
	 * second copy of the rule (the cleaner drops tables, embeds, captions and
	 * shortcodes). So the server answers in a read-only field of the edit
	 * context: the editor gets it with the post and again in the response of
	 * every save, and the notice follows the saved text.
	 *
	 * Registered on the same post types as the panel's meta. It is true only
	 * where the summary applies, so a post type without summaries never shows
	 * the notice. It also shows over a summary written by hand, which is
	 * displayed: the notice says that only such a summary reaches the site.
	 *
	 * @since 2.7.0
	 * @return void
	 */
	public function ayudawp_register_rest_fields() {
		if ( ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return;
		}

		register_rest_field(
			get_post_types( array( 'public' => true ), 'names' ),
			'ayudawp_aiss_too_short',
			array(
				'get_callback' => static function ( $data ) {
					$post_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
					return $post_id
						&& ayudawp_aiss_should_apply_summary( $post_id )
						&& AyudaWP_AISS_AI_Summary::is_below_minimum( $post_id );
				},
				'schema'       => array(
					'description' => __( 'Whether the post is too short for an automatic summary.', 'ai-share-summarize' ),
					'type'        => 'boolean',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * The notice both editors show on a post too short for a summary (v2.7.0)
	 *
	 * @since 2.7.0
	 * @return string Translated notice with the current minimum.
	 */
	public static function ayudawp_too_short_notice() {
		$min      = ayudawp_aiss_get_summary_min_words();
		$by_chars = function_exists( 'wp_get_word_count_type' ) && 0 === strpos( wp_get_word_count_type(), 'characters' );

		return $by_chars
			? sprintf(
				/* translators: %s: minimum number of characters of text a post needs for an automatic summary. */
				_n( 'This post is too short for an automatic summary (it needs at least %s character of text). The site only shows a summary you write here.', 'This post is too short for an automatic summary (it needs at least %s characters of text). The site only shows a summary you write here.', $min, 'ai-share-summarize' ),
				number_format_i18n( $min )
			)
			: sprintf(
				/* translators: %s: minimum number of words of text a post needs for an automatic summary. */
				_n( 'This post is too short for an automatic summary (it needs at least %s word of text). The site only shows a summary you write here.', 'This post is too short for an automatic summary (it needs at least %s words of text). The site only shows a summary you write here.', $min, 'ai-share-summarize' ),
				number_format_i18n( $min )
			);
	}

	/**
	 * Register meta for REST API (block editor support)
	 */
	public function ayudawp_register_meta() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		/*
		 * Permission is checked over the post being written, not over the
		 * "can this user edit posts at all" generic capability (v2.5.0).
		 * Until 2.4.4 these two callbacks took no arguments and asked for
		 * `edit_posts`, so any contributor could flip the exclusion flags on
		 * somebody else's published post through the REST meta endpoint.
		 * `edit_post` runs through map_meta_cap, which resolves ownership and
		 * post status, and matches what the summary meta keys already did in
		 * class-ai-summary.php.
		 */
		$auth_callback = function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'boolean',
					'default'           => false,
					'auth_callback'     => $auth_callback,
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
			register_post_meta(
				$post_type,
				self::META_KEY_SUMMARY,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'boolean',
					'default'           => false,
					'auth_callback'     => $auth_callback,
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
		}
	}

	/**
	 * Add meta box to post types
	 *
	 * In the block editor, the sidebar panel (editor-sidebar.js) replaces
	 * this meta box. Registering a classic meta box in the block editor
	 * disables WordPress 7.0 real-time collaboration for the post.
	 *
	 * Fallback: if the sidebar JS file is missing, the classic meta box
	 * is registered even in the block editor so the user is never left
	 * without the exclusion control.
	 */
	public function ayudawp_add_meta_box() {
		$screen = get_current_screen();

		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			$js_file = AYUDAWP_AISS_PLUGIN_DIR . 'assets/js/editor-sidebar.js';
			if ( file_exists( $js_file ) ) {
				// Sidebar JS handles the UI in the block editor.
				return;
			}
		}

		// Classic editor (or block editor fallback if JS is missing).
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ayudawp_aiss_exclude_metabox',
				__( 'Share Buttons & AI-powered Summaries', 'ai-share-summarize' ),
				array( $this, 'ayudawp_render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box content (classic editor only)
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function ayudawp_render_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'ayudawp_aiss_meta_box', 'ayudawp_aiss_meta_box_nonce' );

		// Get current value.
		$excluded = get_post_meta( $post->ID, self::META_KEY, true );

		?>
		<p>
			<label>
				<input type="checkbox"
					   name="ayudawp_aiss_exclude"
					   id="ayudawp_aiss_exclude"
					   value="1"
					   <?php checked( $excluded, '1' ); ?>>
				<?php esc_html_e( 'Hide share buttons on this content', 'ai-share-summarize' ); ?>
			</label>
		</p>
		<p class="description" style="margin-top: 8px; color: #646970;">
			<?php esc_html_e( 'Check this box to prevent the share and AI buttons from appearing on this specific content.', 'ai-share-summarize' ); ?>
		</p>

		<?php
		// AI Summary fields (v2.0.0).
		$summary_enabled = ayudawp_aiss_is_summary_active();

		if ( $summary_enabled && class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			$summary  = get_post_meta( $post->ID, AyudaWP_AISS_AI_Summary::META_SUMMARY, true );
			$provider = get_post_meta( $post->ID, AyudaWP_AISS_AI_Summary::META_PROVIDER, true );

			$provider_label = '';
			if ( 'wp_ai_client' === $provider ) {
				$provider_label = __( 'Generated by WP AI Client', 'ai-share-summarize' );
			} elseif ( 'extractive' === $provider ) {
				$provider_label = __( 'Basic summary (extractive)', 'ai-share-summarize' );
			} elseif ( 'manual' === $provider ) {
				$provider_label = __( 'Manually edited', 'ai-share-summarize' );
			}
			?>
			<hr style="margin: 12px 0; border: none; border-top: 1px solid #ddd;">
			<p style="margin: 0 0 8px;">
				<strong><?php esc_html_e( 'AI Summary', 'ai-share-summarize' ); ?></strong>
				<?php if ( $provider_label ) : ?>
					<span style="display:block;font-size:11px;color:#646970;margin-top:2px;"><?php echo esc_html( $provider_label ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( ayudawp_aiss_should_apply_summary( $post->ID ) && AyudaWP_AISS_AI_Summary::is_below_minimum( $post ) ) : ?>
				<p class="ayudawp-aiss-too-short" style="margin:0 0 8px;color:#8a6100;"><?php echo esc_html( self::ayudawp_too_short_notice() ); ?></p>
			<?php endif; ?>
			<p>
				<label>
					<?php
					// The hidden marker tells the save handler this control was
					// present in the submitted form, so an unchecked box can be
					// told apart from a form rendered without the summary block.
					?>
					<input type="hidden" name="ayudawp_aiss_exclude_summary_present" value="1">
					<input type="checkbox"
						   name="ayudawp_aiss_exclude_summary"
						   id="ayudawp_aiss_exclude_summary"
						   value="1"
						   <?php checked( self::ayudawp_is_summary_excluded( $post->ID ) ); ?>>
					<?php esc_html_e( 'Hide AI summary on this content', 'ai-share-summarize' ); ?>
				</label>
			</p>
			<p class="description" style="margin-top: 0; color: #646970;">
				<?php esc_html_e( 'Prevents the summary from being generated and displayed for this specific content. Independent from the share buttons checkbox above.', 'ai-share-summarize' ); ?>
			</p>
			<p>
				<label for="ayudawp_aiss_summary_manual" class="screen-reader-text"><?php esc_html_e( 'AI summary text', 'ai-share-summarize' ); ?></label>
				<textarea name="ayudawp_aiss_summary_manual"
				          id="ayudawp_aiss_summary_manual"
				          rows="4"
				          style="width:100%;"
				          placeholder="<?php esc_attr_e( 'Summary will be generated when this post is published.', 'ai-share-summarize' ); ?>"><?php echo esc_textarea( $summary ); ?></textarea>
			</p>
			<p>
				<label>
					<input type="checkbox" name="ayudawp_aiss_regenerate_on_save" value="1">
					<?php esc_html_e( 'Regenerate summary on next save', 'ai-share-summarize' ); ?>
				</label>
			</p>
			<p class="description" style="color:#646970;">
				<?php esc_html_e( 'Editing the text above marks the summary as manual and disables auto-regeneration. Check the box to force a new generation on the next save.', 'ai-share-summarize' ); ?>
			</p>
			<?php
		}
	}

	/**
	 * Save meta box data (classic editor only)
	 *
	 * In the block editor the meta is saved via REST API using
	 * register_post_meta(), so this handler only fires from the
	 * classic editor form submission.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function ayudawp_save_meta_box( $post_id, $post ) {
		// Check if nonce is set (only present in classic editor submissions).
		if ( ! isset( $_POST['ayudawp_aiss_meta_box_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ayudawp_aiss_meta_box_nonce'] ) ), 'ayudawp_aiss_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save or delete meta.
		$exclude = isset( $_POST['ayudawp_aiss_exclude'] ) ? '1' : '';

		if ( $exclude ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}

		// Summary exclusion (v2.3.0). Only touch the meta when its control was
		// actually in the form (the hidden marker): the checkbox is rendered
		// inside the AI Summary block, which is absent when the feature is
		// globally disabled, and an absent control must not clear the meta.
		if ( isset( $_POST['ayudawp_aiss_exclude_summary_present'] ) ) {
			if ( isset( $_POST['ayudawp_aiss_exclude_summary'] ) ) {
				update_post_meta( $post_id, self::META_KEY_SUMMARY, '1' );
			} else {
				delete_post_meta( $post_id, self::META_KEY_SUMMARY );
			}
		}

		// Handle AI Summary fields (v2.0.0).
		if ( class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			$regenerate = isset( $_POST['ayudawp_aiss_regenerate_on_save'] );

			if ( $regenerate ) {
				/*
				 * Force regeneration synchronously — this is an explicit
				 * user request, so we want the result (or the error) to be
				 * visible immediately rather than relying on the WP-Cron
				 * heartbeat. The summary cascade still falls back to the
				 * extractive summarizer when the AI provider is unavailable.
				 *
				 * On success the post page reloads, the meta box re-renders
				 * and the provider label + summary textarea reflect the new
				 * state — no extra notice is needed. AI errors are stored
				 * globally by run() via store_last_ai_error() and surfaced
				 * on the plugin's settings page, so we don't duplicate that
				 * here.
				 */
				AyudaWP_AISS_AI_Summary::run( $post_id, true );
			} elseif ( isset( $_POST['ayudawp_aiss_summary_manual'] ) ) {
				/*
				 * sanitize_textarea_field() rather than wp_kses_post() since
				 * 2.5.0, to match the sanitize_callback the meta key itself
				 * carries (class-ai-summary.php:112). The summary is printed
				 * one sentence per line through esc_html(), so markup never
				 * rendered as markup anyway; keeping wp_kses_post() here would
				 * promise HTML that the meta sanitizer strips on save. The
				 * textarea variant is the right one because the bullet-list
				 * format stores one item per line.
				 */
				$submitted = sanitize_textarea_field( wp_unslash( $_POST['ayudawp_aiss_summary_manual'] ) );
				$stored    = (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_SUMMARY, true );

				if ( $submitted !== $stored ) {
					if ( '' === $submitted ) {
						delete_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_SUMMARY );
						delete_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_PROVIDER );
						// Clear the content hash too. Left behind, it still matches
						// the current content, so should_regenerate() sees no change
						// and automatic generation never refills the emptied summary.
						delete_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_HASH );
					} else {
						update_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_SUMMARY, $submitted );
						update_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_PROVIDER, 'manual' );
					}
				}
			}
		}
	}

	/**
	 * Enqueue sidebar panel script for the block editor
	 *
	 * Registers a PluginDocumentSettingPanel that replaces the classic
	 * meta box in the block editor, keeping RTC compatibility.
	 *
	 * Translations are passed from PHP via wp_localize_script() so
	 * the JS does not depend on wp-i18n or JSON translation files.
	 *
	 * @since 1.7.3
	 */
	public function ayudawp_enqueue_editor_sidebar() {
		$asset_file = AYUDAWP_AISS_PLUGIN_DIR . 'assets/js/editor-sidebar.js';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		wp_enqueue_script(
			'ayudawp-aiss-editor-sidebar',
			AYUDAWP_AISS_PLUGIN_URL . 'assets/js/editor-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-data', 'wp-components', 'wp-element', 'wp-api-fetch' ),
			AYUDAWP_AISS_VERSION,
			true
		);

		// Pass translated strings from PHP to JS.
		wp_localize_script( 'ayudawp-aiss-editor-sidebar', 'aissI18n', array(
			'panelTitle'         => __( 'Share Buttons & AI-powered Summaries', 'ai-share-summarize' ),
			'label'              => __( 'Hide share buttons on this content', 'ai-share-summarize' ),
			'help'               => __( 'Check this box to prevent the share and AI buttons from appearing on this specific content.', 'ai-share-summarize' ),
			'excludeSummaryLabel' => __( 'Hide AI summary on this content', 'ai-share-summarize' ),
			'excludeSummaryHelp'  => __( 'Prevents the summary from being generated and displayed for this specific content. Independent from the share buttons toggle.', 'ai-share-summarize' ),
			'summaryPanelTitle'  => __( 'AI Summary', 'ai-share-summarize' ),
			'summaryEditLabel'   => __( 'Summary', 'ai-share-summarize' ),
			'regenerateLabel'    => __( 'Regenerate now', 'ai-share-summarize' ),
			'regenerating'       => __( 'Regenerating…', 'ai-share-summarize' ),
			'regenerateSuccess'  => __( 'Summary regenerated.', 'ai-share-summarize' ),
			'regenerateError'    => __( 'Regeneration failed.', 'ai-share-summarize' ),
			'waitingForGen'      => __( 'Generating summary in background…', 'ai-share-summarize' ),
			'noSummaryYet'       => __( 'Summary will be generated when this post is published.', 'ai-share-summarize' ),
			'providerWpAi'       => __( 'Generated by WP AI Client', 'ai-share-summarize' ),
			'providerExtractive' => __( 'Basic summary (extractive)', 'ai-share-summarize' ),
			'providerManual'     => __( 'Manually edited', 'ai-share-summarize' ),
			'manualOverrideHelp' => __( 'Editing this text marks the summary as manual — it will not be regenerated automatically.', 'ai-share-summarize' ),
			'tooShort'           => self::ayudawp_too_short_notice(),
		) );
	}

	/**
	 * Check if a post is excluded via meta box
	 *
	 * Works with both classic editor (stores '1' as string) and
	 * block editor (stores true as boolean via REST API).
	 *
	 * @param int $post_id Post ID to check.
	 * @return bool True if excluded, false otherwise.
	 */
	public static function ayudawp_is_post_excluded( $post_id ) {
		return (bool) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * Check if a post has the AI summary excluded via meta box (v2.3.0)
	 *
	 * Same value semantics as ayudawp_is_post_excluded(): '1' from the
	 * classic editor, boolean true through the REST meta.
	 *
	 * @since 2.3.0
	 * @param int $post_id Post ID to check.
	 * @return bool True if the summary is excluded, false otherwise.
	 */
	public static function ayudawp_is_summary_excluded( $post_id ) {
		return (bool) get_post_meta( $post_id, self::META_KEY_SUMMARY, true );
	}
}

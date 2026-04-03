<?php
/**
 * Meta Box class for AI Share & Summarize plugin
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
	 * Meta key for exclusion
	 */
	const META_KEY = '_ayudawp_aiss_exclude';

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
	}

	/**
	 * Register meta for REST API (block editor support)
	 */
	public function ayudawp_register_meta() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'default'       => false,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
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
				__( 'AI Share & Summarize', 'ai-share-summarize' ),
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
	}

	/**
	 * Enqueue sidebar panel script for the block editor
	 *
	 * Registers a PluginDocumentSettingPanel that replaces the classic
	 * meta box in the block editor, keeping RTC compatibility.
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
			array( 'wp-plugins', 'wp-edit-post', 'wp-data', 'wp-components', 'wp-element', 'wp-i18n' ),
			AYUDAWP_AISS_VERSION,
			true
		);

		wp_set_script_translations( 'ayudawp-aiss-editor-sidebar', 'ai-share-summarize' );
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
}
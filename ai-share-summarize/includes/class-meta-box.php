<?php
/**
 * Meta Box class for AI Share & Summarize plugin
 *
 * Handles the meta box for excluding individual posts/pages.
 *
 * @package AiShareSummarize
 * @since 1.4.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
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
        add_action('add_meta_boxes', array($this, 'ayudawp_add_meta_box'));
        add_action('save_post', array($this, 'ayudawp_save_meta_box'), 10, 2);
        
        // Support for block editor (Gutenberg)
        add_action('init', array($this, 'ayudawp_register_meta'));
    }
    
    /**
     * Register meta for REST API (block editor support)
     */
    public function ayudawp_register_meta() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            register_post_meta($post_type, self::META_KEY, array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));
        }
    }
    
    /**
     * Add meta box to post types
     */
    public function ayudawp_add_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'ayudawp_aiss_exclude_metabox',
                __('AI Share & Summarize', 'ai-share-summarize'),
                array($this, 'ayudawp_render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render meta box content
     *
     * @param WP_Post $post Current post object
     */
    public function ayudawp_render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('ayudawp_aiss_meta_box', 'ayudawp_aiss_meta_box_nonce');
        
        // Get current value
        $excluded = get_post_meta($post->ID, self::META_KEY, true);
        
        ?>
        <p>
            <label>
                <input type="checkbox" 
                       name="ayudawp_aiss_exclude" 
                       id="ayudawp_aiss_exclude" 
                       value="1" 
                       <?php checked($excluded, '1'); ?>>
                <?php esc_html_e('Hide share buttons on this content', 'ai-share-summarize'); ?>
            </label>
        </p>
        <p class="description" style="margin-top: 8px; color: #646970;">
            <?php esc_html_e('Check this box to prevent the share and AI buttons from appearing on this specific content.', 'ai-share-summarize'); ?>
        </p>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function ayudawp_save_meta_box($post_id, $post) {
        // Check if nonce is set
        if (!isset($_POST['ayudawp_aiss_meta_box_nonce'])) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ayudawp_aiss_meta_box_nonce'])), 'ayudawp_aiss_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save or delete meta
        $exclude = isset($_POST['ayudawp_aiss_exclude']) ? '1' : '';
        
        if ($exclude) {
            update_post_meta($post_id, self::META_KEY, '1');
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }
    
    /**
     * Check if a post is excluded via meta box
     *
     * @param int $post_id Post ID to check
     * @return bool True if excluded, false otherwise
     */
    public static function ayudawp_is_post_excluded($post_id) {
        return (bool) get_post_meta($post_id, self::META_KEY, true);
    }
}

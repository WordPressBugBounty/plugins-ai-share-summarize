<?php
/**
 * SEO Integration class for AI Share & Summarize plugin
 *
 * Handles detection of SEO plugins and noindex status checking.
 *
 * @package AiShareSummarize
 * @since 1.4.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle SEO plugin integration
 */
class AyudaWP_AISS_SEO_Integration {
    
    /**
     * Supported SEO plugins configuration
     *
     * @var array
     */
    private static $seo_plugins = array(
        'yoast' => array(
            'name'     => 'Yoast SEO',
            'file'     => 'wordpress-seo/wp-seo.php',
            'meta_key' => '_yoast_wpseo_meta-robots-noindex', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fixed key, not user input.
            'noindex'  => '1',
        ),
        'rankmath' => array(
            'name'     => 'Rank Math',
            'file'     => 'seo-by-rank-math/rank-math.php',
            'meta_key' => 'rank_math_robots', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fixed key, not user input.
            'noindex'  => 'noindex',
            'is_array' => true,
        ),
        'aioseo' => array(
            'name'     => 'All in One SEO',
            'file'     => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
            'custom'   => true, // Uses custom table, requires special handling.
        ),
        'seopress' => array(
            'name'     => 'SEOPress',
            'file'     => 'wp-seopress/seopress.php',
            'meta_key' => '_seopress_robots_index', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fixed key, not user input.
            'noindex'  => 'yes',
        ),
        'seoframework' => array(
            'name'     => 'The SEO Framework',
            'file'     => 'autodescription/autodescription.php',
            'meta_key' => '_genesis_noindex', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fixed key, not user input.
            'noindex'  => '1',
        ),
    );
    
    /**
     * Detect active SEO plugin
     *
     * @return array|false Array with slug and name if found, false otherwise
     */
    public static function ayudawp_detect_seo_plugin() {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach (self::$seo_plugins as $slug => $plugin) {
            if (is_plugin_active($plugin['file'])) {
                return array(
                    'slug' => $slug,
                    'name' => $plugin['name'],
                );
            }
        }
        
        return false;
    }
    
    /**
     * Check if a post is marked as noindex
     *
     * @param int $post_id Post ID to check
     * @return bool True if post is noindex, false otherwise
     */
    public static function ayudawp_is_post_noindex($post_id) {
        // Check NoIndexer plugin first (preferred static method).
        if ( class_exists( 'Noindexer_Frontend' ) && Noindexer_Frontend::is_noindex( $post_id ) ) {
            return true;
        }

        $seo = self::ayudawp_detect_seo_plugin();
        
        if (!$seo) {
            return false;
        }
        
        $config = self::$seo_plugins[$seo['slug']];
        
        // Handle AIOSEO separately (uses custom table in v4+)
        if (!empty($config['custom']) && $seo['slug'] === 'aioseo') {
            return self::ayudawp_check_aioseo_noindex($post_id);
        }
        
        // Standard meta key check for other plugins
        $meta = get_post_meta($post_id, $config['meta_key'], true);
        
        if (empty($meta)) {
            return false;
        }
        
        // Handle array-based meta (like Rank Math)
        if (!empty($config['is_array'])) {
            $meta = is_array($meta) ? $meta : maybe_unserialize($meta);
            return is_array($meta) && in_array($config['noindex'], $meta, true);
        }
        
        return $meta === $config['noindex'];
    }
    
    /**
     * Check noindex status for All in One SEO (v4+)
     * 
     * AIOSEO v4+ stores data in wp_aioseo_posts table
     *
     * @param int $post_id Post ID to check
     * @return bool True if post is noindex, false otherwise
     */
    private static function ayudawp_check_aioseo_noindex($post_id) {
        global $wpdb;
        
        // Try AIOSEO v4+ API first (most reliable method)
        if (function_exists('aioseo') && method_exists(aioseo(), 'helpers')) {
            // Check if the post model exists and has robots data
            if (class_exists('AIOSEO\Plugin\Models\Post')) {
                $aioseo_post = AIOSEO\Plugin\Models\Post::getPost($post_id);
                if ($aioseo_post && isset($aioseo_post->robots_noindex)) {
                    return (bool) $aioseo_post->robots_noindex;
                }
            }
        }
        
        // Fallback: Query the custom table directly.
        $table_name      = $wpdb->prefix . 'aioseo_posts';
        $table_name_safe = esc_sql( $table_name );

        // Check if table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time structural check.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table_name
            )
        );

        if ( $table_exists ) {
            $cache_key = 'aioseo_noindex_' . (int) $post_id;
            $cached    = wp_cache_get( $cache_key, 'ayudawp_aiss' );
            if ( false !== $cached ) {
                return (int) $cached === 1;
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- AIOSEO custom table, name is escaped with esc_sql().
            $result = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT robots_noindex FROM {$table_name_safe} WHERE post_id = %d",
                    $post_id
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            if ( null !== $result ) {
                wp_cache_set( $cache_key, (int) $result, 'ayudawp_aiss', 300 );
                return (int) $result === 1;
            }
        }
        
        // Legacy fallback: Check old meta key (AIOSEO v3 and earlier)
        $legacy_meta = get_post_meta($post_id, '_aioseo_noindex', true);
        if (!empty($legacy_meta)) {
            return $legacy_meta === '1' || $legacy_meta === 1 || $legacy_meta === true;
        }
        
        return false;
    }
    
    /**
     * Get list of supported SEO plugins
     *
     * @return array List of plugin names
     */
    public static function ayudawp_get_supported_plugins() {
        $plugins = array();
        foreach (self::$seo_plugins as $plugin) {
            $plugins[] = $plugin['name'];
        }
        return $plugins;
    }
}
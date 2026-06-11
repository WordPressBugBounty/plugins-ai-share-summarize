<?php
/**
 * Promo Banner class
 *
 * Promotional banners with random plugin and service rotation.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISS Promo Banner class
 */
class AyudaWP_AISS_Promo_Banner {

	/**
	 * Current plugin slug to exclude from recommendations.
	 *
	 * @var string
	 */
	private $current_plugin_slug;

	/**
	 * Text domain for translations.
	 *
	 * @var string
	 */
	private $textdomain;

	/**
	 * CSS class prefix.
	 *
	 * @var string
	 */
	private $css_prefix;

	/**
	 * Constructor.
	 *
	 * @param string $current_plugin_slug Current plugin slug.
	 * @param string $textdomain          Text domain for translations.
	 * @param string $css_prefix          CSS class prefix.
	 */
	public function __construct( $current_plugin_slug, $textdomain, $css_prefix ) {
		$this->current_plugin_slug = $current_plugin_slug;
		$this->textdomain          = $textdomain;
		$this->css_prefix          = $css_prefix;
	}

	/**
	 * Get plugins catalog.
	 *
	 * @return array
	 */
	private function get_plugins_catalog() {
		// Mirror of ayudawp-promo-banner-catalog.md (the single source of
		// truth, alphabetical by slug). The host plugin's own entry is
		// included on purpose: get_random_plugins() unsets it at runtime.
		return array(
			'ai-content-signals'              => array(
				'icon'        => 'dashicons-flag',
				'title'       => __( 'Control AI content usage', 'ai-share-summarize' ),
				'description' => __( 'Cloudflare-endorsed plugin to define how AI systems can use your content: for training, search results, or both.', 'ai-share-summarize' ),
				'button'      => __( 'Install AI Content Signals', 'ai-share-summarize' ),
			),
			'ai-share-summarize'              => array(
				'icon'        => 'dashicons-share',
				'title'       => __( 'Boost your AI presence', 'ai-share-summarize' ),
				'description' => __( 'Add social sharing and AI summarize buttons. Help visitors share your content and let AIs learn from your site while getting backlinks.', 'ai-share-summarize' ),
				'button'      => __( 'Install AI Share & Summarize', 'ai-share-summarize' ),
			),
			'anticache'                       => array(
				'icon'        => 'dashicons-hammer',
				'title'       => __( 'Development toolkit', 'ai-share-summarize' ),
				'description' => __( 'Bypass all caching during development. Auto-detects cache plugins, enables debug mode, and includes maintenance screen.', 'ai-share-summarize' ),
				'button'      => __( 'Install Anti-Cache Kit', 'ai-share-summarize' ),
			),
			'auto-capitalize-names-ayudawp'   => array(
				'icon'        => 'dashicons-editor-textcolor',
				'title'       => __( 'Fix customer names', 'ai-share-summarize' ),
				'description' => __( 'Auto-capitalize names and addresses in WordPress and WooCommerce. Keep invoices and reports professionally formatted.', 'ai-share-summarize' ),
				'button'      => __( 'Install Auto Capitalize', 'ai-share-summarize' ),
			),
			'easy-actions-scheduler-cleaner-ayudawp' => array(
				'icon'        => 'dashicons-database-remove',
				'title'       => __( 'Clean Action Scheduler', 'ai-share-summarize' ),
				'description' => __( 'Remove millions of completed, failed, and old actions from WooCommerce Action Scheduler. Reduce database size instantly.', 'ai-share-summarize' ),
				'button'      => __( 'Install Scheduler Cleaner', 'ai-share-summarize' ),
			),
			'easy-store-management-ayudawp'   => array(
				'icon'        => 'dashicons-store',
				'title'       => __( 'Simplify store management', 'ai-share-summarize' ),
				'description' => __( 'Clean up WordPress admin for Store Managers. Hide unnecessary menus, keep only orders, products, and customers, plus quick access shortcuts.', 'ai-share-summarize' ),
				'button'      => __( 'Install Easy Store', 'ai-share-summarize' ),
			),
			'eu-withdrawal-compliance'        => array(
				'icon'        => 'dashicons-undo',
				'title'       => __( 'EU withdrawal compliance', 'ai-share-summarize' ),
				'description' => __( 'Add the EU online withdrawal function required by Directive 2023/2673 from June 2026. Public form, My Account button, email notice and SHA-256 receipt hash.', 'ai-share-summarize' ),
				'button'      => __( 'Install EU Withdrawal', 'ai-share-summarize' ),
			),
			'gozer'                           => array(
				'icon'        => 'dashicons-admin-network',
				'title'       => __( 'Restrict site access', 'ai-share-summarize' ),
				'description' => __( 'Force visitors to log in before accessing your site with extensive exception controls for pages, posts, and user roles.', 'ai-share-summarize' ),
				'button'      => __( 'Install Gozer', 'ai-share-summarize' ),
			),
			'lightbox-images-for-divi'        => array(
				'icon'        => 'dashicons-format-gallery',
				'title'       => __( 'Lightbox for Divi', 'ai-share-summarize' ),
				'description' => __( 'Add native lightbox functionality to Divi theme images. No jQuery, fast loading, fully customizable.', 'ai-share-summarize' ),
				'button'      => __( 'Install Divi Lightbox', 'ai-share-summarize' ),
			),
			'multiple-sale-prices-scheduler'  => array(
				'icon'        => 'dashicons-calendar-alt',
				'title'       => __( 'Schedule sale prices', 'ai-share-summarize' ),
				'description' => __( 'Set multiple future sale prices for WooCommerce products. Plan promotions in advance with start and end dates.', 'ai-share-summarize' ),
				'button'      => __( 'Install Sale Scheduler', 'ai-share-summarize' ),
			),
			'native-aeo-pack'                 => array(
				'icon'        => 'dashicons-embed-generic',
				'title'       => __( 'All-in-one native SEO', 'ai-share-summarize' ),
				'description' => __( 'Meta tags, Open Graph, JSON-LD schema, robots directives and native sitemap control in one lightweight plugin built on WordPress core features.', 'ai-share-summarize' ),
				'button'      => __( 'Install Native SEO Pack', 'ai-share-summarize' ),
			),
			'no-gutenberg'                    => array(
				'icon'        => 'dashicons-edit-page',
				'title'       => __( 'Back to Classic Editor', 'ai-share-summarize' ),
				'description' => __( 'Completely remove Gutenberg, FSE styles, and block widgets. Restore the classic editing experience with better performance.', 'ai-share-summarize' ),
				'button'      => __( 'Install No Gutenberg', 'ai-share-summarize' ),
			),
			'periscopio'                      => array(
				'icon'        => 'dashicons-rss',
				'title'       => __( 'Custom Dashboard News', 'ai-share-summarize' ),
				'description' => __( 'Add your own custom feeds and links to the news and events dashboard widget and replace WordPress default one.', 'ai-share-summarize' ),
				'button'      => __( 'Install Periscope', 'ai-share-summarize' ),
			),
			'post-visibility-control'         => array(
				'icon'        => 'dashicons-hidden',
				'title'       => __( 'Control post visibility', 'ai-share-summarize' ),
				'description' => __( 'Hide posts from homepage, archives, feeds, or REST API while keeping them accessible via direct URL.', 'ai-share-summarize' ),
				'button'      => __( 'Install Post Visibility', 'ai-share-summarize' ),
			),
			'scheduled-posts-showcase'        => array(
				'icon'        => 'dashicons-clock',
				'title'       => __( 'Show visitors what is coming up next', 'ai-share-summarize' ),
				'description' => __( 'Display your scheduled and future posts on the frontend to gain and retain visits.', 'ai-share-summarize' ),
				'button'      => __( 'Install Scheduled Posts Showcase', 'ai-share-summarize' ),
			),
			'search-replace-text-blocks'      => array(
				'icon'        => 'dashicons-search',
				'title'       => __( 'Search & replace in blocks', 'ai-share-summarize' ),
				'description' => __( 'Find and replace text across all your Gutenberg blocks. Bulk edit content without touching the database directly.', 'ai-share-summarize' ),
				'button'      => __( 'Install Search Replace Blocks', 'ai-share-summarize' ),
			),
			'seo-read-more-buttons-ayudawp'   => array(
				'icon'        => 'dashicons-admin-links',
				'title'       => __( 'Better read more links', 'ai-share-summarize' ),
				'description' => __( 'Customize excerpt "read more" links with buttons, custom text, and nofollow option. Improve CTR and SEO.', 'ai-share-summarize' ),
				'button'      => __( 'Install SEO Read More', 'ai-share-summarize' ),
			),
			'show-only-lowest-prices-in-woocommerce-variable-products' => array(
				'icon'        => 'dashicons-tag',
				'title'       => __( 'Cleaner variable prices', 'ai-share-summarize' ),
				'description' => __( 'Display only the lowest price for WooCommerce variable products instead of confusing price ranges.', 'ai-share-summarize' ),
				'button'      => __( 'Install Lowest Price', 'ai-share-summarize' ),
			),
			'terms-conditions-consent-log'    => array(
				'icon'        => 'dashicons-yes-alt',
				'title'       => __( 'Tamper-evident consent log', 'ai-share-summarize' ),
				'description' => __( 'GDPR art. 7.1 audit trail for any acceptance checkbox: WooCommerce checkout, CF7, WPForms, comments and shortcode. Timestamp, IP, version and SHA-256 sealed text.', 'ai-share-summarize' ),
				'button'      => __( 'Install Consent Log', 'ai-share-summarize' ),
			),
			'vigia'                           => array(
				'icon'        => 'dashicons-visibility',
				'title'       => __( 'Monitor AI crawler activity', 'ai-share-summarize' ),
				'description' => __( 'Track which AI bots visit your site, analyze their behavior, and take control with blocking rules and robots.txt management.', 'ai-share-summarize' ),
				'button'      => __( 'Install VigIA', 'ai-share-summarize' ),
			),
			'vigilante'                       => array(
				'icon'        => 'dashicons-shield',
				'title'       => __( 'Complete WordPress security', 'ai-share-summarize' ),
				'description' => __( 'All-in-one security plugin: firewall, login protection, security headers, 2FA, file integrity monitoring, and activity logging.', 'ai-share-summarize' ),
				'button'      => __( 'Install Vigilant', 'ai-share-summarize' ),
			),
			'widget-visibility-control'       => array(
				'icon'        => 'dashicons-welcome-widgets-menus',
				'title'       => __( 'Smart widget display', 'ai-share-summarize' ),
				'description' => __( 'Show or hide widgets based on pages, post types, categories, user roles, and more. Works with any theme.', 'ai-share-summarize' ),
				'button'      => __( 'Install Widget Visibility', 'ai-share-summarize' ),
			),
			'wpo-tweaks'                      => array(
				'icon'        => 'dashicons-food',
				'title'       => __( 'Put WordPress on a diet', 'ai-share-summarize' ),
				'description' => __( 'Disable bloat and apply 30+ performance tweaks (critical CSS, lazy loading, cache rules) with zero configuration for a leaner, faster site.', 'ai-share-summarize' ),
				'button'      => __( 'Install DietPress', 'ai-share-summarize' ),
			),
		);
	}

	/**
	 * Get services catalog.
	 *
	 * @return array
	 */
	private function get_services_catalog() {
		return array(
			'maintenance' => array(
				'icon'        => 'dashicons-admin-tools',
				'title'       => __( 'Need help with your website?', 'ai-share-summarize' ),
				'description' => __( 'Professional WordPress maintenance: security monitoring, regular backups, performance optimization, and priority support.', 'ai-share-summarize' ),
				'button'      => __( 'Learn more', 'ai-share-summarize' ),
				'url'         => 'https://mantenimiento.ayudawp.com',
			),
			'consultancy' => array(
				'icon'        => 'dashicons-businessman',
				'title'       => __( 'WordPress consultancy', 'ai-share-summarize' ),
				'description' => __( 'One-on-one online sessions to solve your WordPress doubts, get expert advice, and make better decisions for your project.', 'ai-share-summarize' ),
				'button'      => __( 'Book a session', 'ai-share-summarize' ),
				'url'         => 'https://servicios.ayudawp.com/producto/consultoria-online-wordpress/',
			),
			'hacked'      => array(
				'icon'        => 'dashicons-sos',
				'title'       => __( 'Hacked website?', 'ai-share-summarize' ),
				'description' => __( 'Fast recovery service for compromised WordPress sites. We clean malware, fix vulnerabilities, and restore your site security.', 'ai-share-summarize' ),
				'button'      => __( 'Get help now', 'ai-share-summarize' ),
				'url'         => 'https://servicios.ayudawp.com/producto/wordpress-hackeado/',
			),
			'development' => array(
				'icon'        => 'dashicons-editor-code',
				'title'       => __( 'Custom development', 'ai-share-summarize' ),
				'description' => __( 'Need a custom plugin, theme modifications, or specific functionality? We build tailored WordPress solutions for your needs.', 'ai-share-summarize' ),
				'button'      => __( 'Request a quote', 'ai-share-summarize' ),
				'url'         => 'https://servicios.ayudawp.com/producto/desarrollo-wordpress/',
			),
			'hosting'     => array(
				'icon'        => 'dashicons-cloud-saved',
				'title'       => __( 'Hosting built for WordPress', 'ai-share-summarize' ),
				'description' => __( 'Google Cloud servers, automatic geo-located daily backups, and 24/7 expert support. Speed, security, and migration tools included.', 'ai-share-summarize' ),
				'button'      => __( 'Learn more', 'ai-share-summarize' ),
				/* translators: SiteGround affiliate URL. Change this URL in translations to use a localized landing page. */
				'url'         => __( 'https://stgrnd.co/telladowpbox', 'ai-share-summarize' ),
			),
		);
	}

	/**
	 * Get random plugins excluding current.
	 *
	 * @param int $count Number of plugins to return.
	 * @return array
	 */
	private function get_random_plugins( $count = 2 ) {
		$plugins = $this->get_plugins_catalog();

		// Remove current plugin.
		unset( $plugins[ $this->current_plugin_slug ] );

		// Get random keys.
		$random_keys = array_rand( $plugins, min( $count, count( $plugins ) ) );

		if ( ! is_array( $random_keys ) ) {
			$random_keys = array( $random_keys );
		}

		$result = array();
		foreach ( $random_keys as $key ) {
			$result[ $key ] = $plugins[ $key ];
		}

		return $result;
	}

	/**
	 * Get random service.
	 *
	 * @return array
	 */
	private function get_random_service() {
		$services   = $this->get_services_catalog();
		$random_key = array_rand( $services );

		return $services[ $random_key ];
	}

	/**
	 * Render the promotional banner.
	 *
	 * @param string $layout Layout type: 'horizontal' or 'vertical'.
	 */
	public function render( $layout = 'horizontal' ) {
		if ( 'vertical' === $layout ) {
			$this->render_vertical();
		} else {
			$this->render_horizontal();
		}
	}

	/**
	 * Render horizontal layout (3-column grid).
	 */
	private function render_horizontal() {
		$plugins = $this->get_random_plugins( 2 );
		$service = $this->get_random_service();
		$prefix  = $this->css_prefix;
		?>
		<!-- Promotional notice -->
		<div class="<?php echo esc_attr( $prefix ); ?>-promo-notice">
			<h4><?php esc_html_e( 'Starter kit for your site', 'ai-share-summarize' ); ?></h4>
			<div class="<?php echo esc_attr( $prefix ); ?>-promo-columns">
				
				<?php foreach ( $plugins as $slug => $plugin ) : ?>
				<div class="<?php echo esc_attr( $prefix ); ?>-promo-column">
					<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
					<h5><?php echo esc_html( $plugin['title'] ); ?></h5>
					<p><?php echo esc_html( $plugin['description'] ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=772&height=618' ) ); ?>" class="button thickbox">
						<?php echo esc_html( $plugin['button'] ); ?>
					</a>
				</div>
				<?php endforeach; ?>
				
				<div class="<?php echo esc_attr( $prefix ); ?>-promo-column">
					<span class="dashicons <?php echo esc_attr( $service['icon'] ); ?>"></span>
					<h5><?php echo esc_html( $service['title'] ); ?></h5>
					<p><?php echo esc_html( $service['description'] ); ?></p>
					<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
						<?php echo esc_html( $service['button'] ); ?>
					</a>
				</div>
				
			</div>
		</div>
		<?php
	}

	/**
	 * Render vertical layout (sidebar boxes).
	 */
	private function render_vertical() {
		$plugins = $this->get_random_plugins( 2 );
		$service = $this->get_random_service();
		$prefix  = $this->css_prefix;

		foreach ( $plugins as $slug => $plugin ) :
			?>
			<div class="<?php echo esc_attr( $prefix ); ?>-promo-box">
				<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?> <?php echo esc_attr( $prefix ); ?>-promo-icon"></span>
				<h4 class="<?php echo esc_attr( $prefix ); ?>-promo-box-title"><?php echo esc_html( $plugin['title'] ); ?></h4>
				<p class="<?php echo esc_attr( $prefix ); ?>-promo-box-description"><?php echo esc_html( $plugin['description'] ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=772&height=618' ) ); ?>" class="button thickbox">
					<?php echo esc_html( $plugin['button'] ); ?>
				</a>
			</div>
			<?php
		endforeach;

		?>
		<div class="<?php echo esc_attr( $prefix ); ?>-promo-box">
			<span class="dashicons <?php echo esc_attr( $service['icon'] ); ?> <?php echo esc_attr( $prefix ); ?>-promo-icon"></span>
			<h4 class="<?php echo esc_attr( $prefix ); ?>-promo-box-title"><?php echo esc_html( $service['title'] ); ?></h4>
			<p class="<?php echo esc_attr( $prefix ); ?>-promo-box-description"><?php echo esc_html( $service['description'] ); ?></p>
			<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php echo esc_html( $service['button'] ); ?>
			</a>
		</div>
		<?php
	}
}
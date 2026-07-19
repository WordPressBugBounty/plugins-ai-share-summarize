<?php
/**
 * Dashboard widget class
 *
 * Adds a summary widget to the WordPress admin dashboard.
 * Uses a short-lived transient to cache the rendered HTML
 * and avoid running 3 database queries on every dashboard load.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard widget class
 */
class AyudaWP_AISS_Dashboard_Widget {

	/**
	 * Transient key for cached widget HTML
	 */
	const CACHE_KEY = 'aiss_dashboard_widget_html';

	/**
	 * Cache duration in seconds (5 minutes)
	 */
	const CACHE_TTL = 300;

	/**
	 * Register the dashboard widget
	 */
	public static function ayudawp_register() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'aiss_dashboard_widget',
			__( 'Share Buttons & AI-powered Summaries - Click Analytics', 'ai-share-summarize' ),
			array( __CLASS__, 'ayudawp_render_widget' )
		);
	}

	/**
	 * Render the widget content
	 *
	 * Serves cached HTML from a transient when available.
	 * On cache miss, queries the database and stores the result.
	 */
	public static function ayudawp_render_widget() {
		$cached_html = get_transient( self::CACHE_KEY );

		if ( false !== $cached_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped during generation and stored as a safe transient.
			echo $cached_html;
			return;
		}

		// Generate fresh HTML using output buffering.
		ob_start();
		self::ayudawp_build_widget_html();
		$html = ob_get_clean();

		set_transient( self::CACHE_KEY, $html, self::CACHE_TTL );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped during generation in ayudawp_build_widget_html().
		echo $html;
	}

	/**
	 * Build the widget HTML (queries database)
	 *
	 * All output is properly escaped. Called only on cache miss.
	 */
	private static function ayudawp_build_widget_html() {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
		$stats      = AyudaWP_AISS_Database::ayudawp_get_stats( $start_date, $end_date );
		$platforms  = AyudaWP_AISS_Database::ayudawp_get_top_platforms( $start_date, $end_date, 5 );
		$timeline   = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time( $start_date, $end_date );

		// Prepare sparkline data (fill missing days with 0).
		$sparkline_data = self::ayudawp_prepare_sparkline_data( $timeline, $start_date, $end_date );

		// Platform display names.
		$platform_labels = self::ayudawp_get_platform_labels();
		?>
		<div class="aiss-widget">
			<div class="aiss-widget-stats">
				<div class="aiss-widget-stat">
					<span class="aiss-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_clicks'] ) ); ?></span>
					<span class="aiss-widget-stat-label"><?php echo esc_html__( 'clicks', 'ai-share-summarize' ); ?></span>
				</div>
				<div class="aiss-widget-stat">
					<span class="aiss-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['unique_platforms'] ) ); ?></span>
					<span class="aiss-widget-stat-label"><?php echo esc_html__( 'platforms', 'ai-share-summarize' ); ?></span>
				</div>
				<div class="aiss-widget-stat">
					<span class="aiss-widget-stat-value"><?php echo esc_html( number_format_i18n( $stats['unique_posts'] ) ); ?></span>
					<span class="aiss-widget-stat-label"><?php echo esc_html__( 'posts', 'ai-share-summarize' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $sparkline_data ) && array_sum( $sparkline_data ) > 0 ) : ?>
				<div class="aiss-widget-sparkline">
					<h4><?php echo esc_html__( 'Last 7 days', 'ai-share-summarize' ); ?></h4>
					<?php self::ayudawp_render_sparkline( $sparkline_data ); ?>
				</div>
			<?php endif; ?>

			<div style="clear: both; height: 25px;"></div>

			<?php if ( ! empty( $platforms ) ) : ?>
				<h4 class="aiss-widget-section-title"><?php echo esc_html__( 'Top platforms', 'ai-share-summarize' ); ?></h4>
				<ul class="aiss-widget-list">
					<?php foreach ( $platforms as $platform ) : ?>
						<?php
						$name = isset( $platform_labels[ $platform['platform'] ] ) ? $platform_labels[ $platform['platform'] ] : ucfirst( $platform['platform'] );
						?>
						<li>
							<span class="aiss-widget-platform-name"><?php echo esc_html( $name ); ?></span>
							<span class="aiss-widget-platform-count"><?php echo esc_html( number_format_i18n( $platform['click_count'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aiss-widget-empty"><?php echo esc_html__( 'No click activity recorded yet.', 'ai-share-summarize' ); ?></p>
			<?php endif; ?>

			<p class="aiss-widget-footer">
				<?php // Bug #1 fix: correct menu slug for the analytics page. ?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-share-summarize&tab=analytics' ) ); ?>">
					<?php echo esc_html__( 'View full analytics', 'ai-share-summarize' ); ?> &rarr;
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Get human-readable platform labels
	 *
	 * @return array
	 */
	private static function ayudawp_get_platform_labels() {
		return array(
			'twitter'    => 'X (Twitter)',
			'linkedin'   => 'LinkedIn',
			'facebook'   => 'Facebook',
			'telegram'   => 'Telegram',
			'whatsapp'   => 'WhatsApp',
			'email'      => 'Email',
			'raindrop'   => 'Raindrop',
			'reddit'     => 'Reddit',
			'bluesky'    => 'Bluesky',
			'line'       => 'LINE',
			'claude'     => 'Claude',
			'chatgpt'    => 'ChatGPT',
			'google_ai'  => 'Google AI',
			'gemini'     => 'Gemini',
			'grok'       => 'Grok',
			'perplexity' => 'Perplexity',
			'deepseek'   => 'DeepSeek',
			'mistral'    => 'Mistral',
			'copilot'    => 'Copilot',
		);
	}

	/**
	 * Prepare sparkline data with all days filled
	 *
	 * @param array  $timeline   Raw timeline data.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Associative array of date => count.
	 */
	private static function ayudawp_prepare_sparkline_data( $timeline, $start_date, $end_date ) {
		$data    = array();
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );

		while ( $current <= $end ) {
			$date_key          = gmdate( 'Y-m-d', $current );
			$data[ $date_key ] = 0;
			$current           = strtotime( '+1 day', $current );
		}

		foreach ( $timeline as $row ) {
			if ( isset( $data[ $row['date'] ] ) ) {
				$data[ $row['date'] ] = (int) $row['click_count'];
			}
		}

		return $data;
	}

	/**
	 * Render sparkline chart
	 *
	 * @param array $data Sparkline data.
	 */
	private static function ayudawp_render_sparkline( $data ) {
		$values = array_values( $data );
		$dates  = array_keys( $data );
		$max    = max( $values );

		if ( 0 === $max ) {
			$max = 1;
		}

		?>
		<div class="aiss-sparkline-container">
			<?php foreach ( $values as $index => $value ) : ?>
				<?php
				$height  = ( $value / $max ) * 100;
				$date    = isset( $dates[ $index ] ) ? $dates[ $index ] : '';
				$tooltip = sprintf(
					/* translators: 1: date, 2: number of clicks */
					__( '%1$s: %2$s clicks', 'ai-share-summarize' ),
					wp_date( get_option( 'date_format' ), strtotime( $date ) ),
					number_format_i18n( $value )
				);
				?>
				<div 
					class="aiss-sparkline-bar" 
					style="height: <?php echo esc_attr( max( $height, 5 ) ); ?>%;"
					title="<?php echo esc_attr( $tooltip ); ?>"
				></div>
			<?php endforeach; ?>
		</div>
		<div class="aiss-sparkline-labels">
			<span><?php echo esc_html( wp_date( 'M j', strtotime( $dates[0] ) ) ); ?></span>
			<span><?php echo esc_html( wp_date( 'M j', strtotime( end( $dates ) ) ) ); ?></span>
		</div>
		<?php
	}
}
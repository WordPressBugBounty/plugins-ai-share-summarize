<?php
/**
 * Analytics page class
 *
 * Renders the analytics dashboard tab with stats, chart, and tables.
 * Design adapted from VigIA plugin patterns.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics page class
 */
class AyudaWP_AISS_Analytics_Page {

	/**
	 * Render the analytics tab content
	 */
	public static function ayudawp_render() {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$stats      = AyudaWP_AISS_Database::ayudawp_get_stats( $start_date, $end_date );

		// Check if VigIA tip should be shown.
		$show_vigia_tip = ! class_exists( 'VigIA_Database' ) && ! get_option( 'ayudawp_aiss_vigia_tip_dismissed', false );
		?>

		<?php if ( $show_vigia_tip ) : ?>
			<div class="notice notice-info is-dismissible aiss-vigia-tip" id="aiss-vigia-tip">
				<p>
					<strong><?php echo esc_html__( 'Tip:', 'ai-share-summarize' ); ?></strong>
					<?php echo esc_html__( 'Install VigIA to see AI crawler data per post in the Top Content table.', 'ai-share-summarize' ); ?>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=vigia&TB_iframe=true&width=772&height=618' ) ); ?>" class="thickbox">
						<?php echo esc_html__( 'Learn more', 'ai-share-summarize' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<!-- Date range selector with comparison controls and export dropdown -->
		<div class="aiss-date-filter">
			<div class="aiss-date-filter-left">
				<label for="aiss-date-range"><?php echo esc_html__( 'Period:', 'ai-share-summarize' ); ?></label>
				<select id="aiss-date-range">
					<option value="1"><?php echo esc_html__( 'Today', 'ai-share-summarize' ); ?></option>
					<option value="7"><?php echo esc_html__( 'Last 7 days', 'ai-share-summarize' ); ?></option>
					<option value="14"><?php echo esc_html__( 'Last 14 days', 'ai-share-summarize' ); ?></option>
					<option value="30" selected><?php echo esc_html__( 'Last 30 days', 'ai-share-summarize' ); ?></option>
					<option value="60"><?php echo esc_html__( 'Last 60 days', 'ai-share-summarize' ); ?></option>
					<option value="90"><?php echo esc_html__( 'Last 90 days', 'ai-share-summarize' ); ?></option>
					<option value="180"><?php echo esc_html__( 'Last 6 months', 'ai-share-summarize' ); ?></option>
					<option value="365"><?php echo esc_html__( 'Last year', 'ai-share-summarize' ); ?></option>
					<option value="0"><?php echo esc_html__( 'All time', 'ai-share-summarize' ); ?></option>
					<option value="custom"><?php echo esc_html__( 'Custom range', 'ai-share-summarize' ); ?></option>
				</select>

				<span id="aiss-custom-dates" class="aiss-custom-dates" style="display: none;">
					<input type="date" id="aiss-date-from" title="<?php echo esc_attr__( 'From', 'ai-share-summarize' ); ?>">
					<span class="aiss-date-separator">&mdash;</span>
					<input type="date" id="aiss-date-to" title="<?php echo esc_attr__( 'To', 'ai-share-summarize' ); ?>">
					<button type="button" id="aiss-apply-custom-dates" class="button button-small"><?php echo esc_html__( 'Apply', 'ai-share-summarize' ); ?></button>
				</span>

				<?php // Comparison controls (v1.7.0). ?>
				<label for="aiss-compare-toggle" class="aiss-compare-label">
					<input type="checkbox" id="aiss-compare-toggle">
					<?php echo esc_html__( 'Compare with:', 'ai-share-summarize' ); ?>
				</label>
				<select id="aiss-compare-range" disabled>
					<option value="previous"><?php echo esc_html__( 'Previous period', 'ai-share-summarize' ); ?></option>
					<option value="year"><?php echo esc_html__( 'Same period last year', 'ai-share-summarize' ); ?></option>
					<option value="custom"><?php echo esc_html__( 'Custom range', 'ai-share-summarize' ); ?></option>
				</select>

				<span id="aiss-compare-custom-dates" class="aiss-custom-dates" style="display: none;">
					<input type="date" id="aiss-compare-date-from"
						   title="<?php echo esc_attr__( 'From', 'ai-share-summarize' ); ?>">
					<span class="aiss-date-separator">&mdash;</span>
					<input type="date" id="aiss-compare-date-to"
						   title="<?php echo esc_attr__( 'To', 'ai-share-summarize' ); ?>">
					<button type="button" id="aiss-apply-compare-dates"
							class="button button-small">
						<?php echo esc_html__( 'Apply', 'ai-share-summarize' ); ?>
					</button>
				</span>
			</div>

			<?php // Export CSV dropdown (v1.7.0, replaces single button). ?>
			<div class="aiss-date-filter-right">
				<div class="aiss-export-dropdown">
					<button type="button" id="aiss-export-csv" class="button aiss-export-btn">
						<span class="dashicons dashicons-download"></span>
						<?php echo esc_html__( 'Export CSV', 'ai-share-summarize' ); ?>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</button>
					<div class="aiss-export-menu" id="aiss-export-menu">
						<button type="button" class="aiss-export-option" data-export="current">
							<?php echo esc_html__( 'Current period', 'ai-share-summarize' ); ?>
						</button>
						<button type="button" class="aiss-export-option" data-export="timeline">
							<?php echo esc_html__( 'Timeline summary', 'ai-share-summarize' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Stats cards with comparison indicators -->
		<div class="aiss-stats-grid">
			<div class="aiss-stat-card">
				<span class="aiss-stat-icon dashicons dashicons-share"></span>
				<div class="aiss-stat-content">
					<span class="aiss-stat-value" id="aiss-total-clicks"><?php echo esc_html( number_format_i18n( $stats['total_clicks'] ) ); ?></span>
					<span class="aiss-stat-label"><?php echo esc_html__( 'Total clicks', 'ai-share-summarize' ); ?></span>
					<span class="aiss-stat-compare" id="aiss-total-clicks-compare"></span>
				</div>
			</div>
			<div class="aiss-stat-card">
				<span class="aiss-stat-icon dashicons dashicons-networking"></span>
				<div class="aiss-stat-content">
					<span class="aiss-stat-value" id="aiss-unique-platforms"><?php echo esc_html( number_format_i18n( $stats['unique_platforms'] ) ); ?></span>
					<span class="aiss-stat-label"><?php echo esc_html__( 'Platforms used', 'ai-share-summarize' ); ?></span>
					<span class="aiss-stat-compare" id="aiss-unique-platforms-compare"></span>
				</div>
			</div>
			<div class="aiss-stat-card">
				<span class="aiss-stat-icon dashicons dashicons-admin-page"></span>
				<div class="aiss-stat-content">
					<span class="aiss-stat-value" id="aiss-unique-posts"><?php echo esc_html( number_format_i18n( $stats['unique_posts'] ) ); ?></span>
					<span class="aiss-stat-label"><?php echo esc_html__( 'Pages shared', 'ai-share-summarize' ); ?></span>
					<span class="aiss-stat-compare" id="aiss-unique-posts-compare"></span>
				</div>
			</div>
		</div>

		<!-- Chart -->
		<div class="aiss-chart-container">
			<h3>
				<?php echo esc_html__( 'Clicks History', 'ai-share-summarize' ); ?>
				<span class="aiss-period-indicator" id="aiss-timeline-period"></span>
			</h3>
			<div class="aiss-chart-wrapper">
				<canvas id="aiss-timeline-chart"></canvas>
			</div>
		</div>

		<!-- Data tables row -->
		<div class="aiss-tables-row">
			<!-- Top platforms table with paginators -->
			<div class="aiss-table-container">
				<div class="aiss-table-header">
					<h3>
						<?php echo esc_html__( 'Top Platforms', 'ai-share-summarize' ); ?>
						<span class="aiss-period-indicator" id="aiss-platforms-period"></span>
					</h3>
					<div class="aiss-pager" id="aiss-platforms-pager"></div>
				</div>
				<table class="wp-list-table widefat fixed striped" id="aiss-platforms-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Platform', 'ai-share-summarize' ); ?></th>
							<th class="aiss-col-type"><?php echo esc_html__( 'Type', 'ai-share-summarize' ); ?></th>
							<th class="num"><?php echo esc_html__( 'Clicks', 'ai-share-summarize' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr class="aiss-no-data">
							<td colspan="3" class="aiss-loading"><?php echo esc_html__( 'Loading...', 'ai-share-summarize' ); ?></td>
						</tr>
					</tbody>
				</table>
				<div class="aiss-pager-bottom-wrap">
					<div class="aiss-pager" id="aiss-platforms-pager-bottom"></div>
				</div>
			</div>

			<!-- Top content table with paginators -->
			<div class="aiss-table-container">
				<div class="aiss-table-header">
					<h3>
						<?php echo esc_html__( 'Top Content', 'ai-share-summarize' ); ?>
						<span class="aiss-period-indicator" id="aiss-content-period"></span>
					</h3>
					<div class="aiss-pager" id="aiss-content-pager"></div>
				</div>
				<table class="wp-list-table widefat fixed striped" id="aiss-content-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Title', 'ai-share-summarize' ); ?></th>
							<th class="num aiss-col-id"><?php echo esc_html__( 'ID', 'ai-share-summarize' ); ?></th>
							<th class="num"><?php echo esc_html__( 'Clicks', 'ai-share-summarize' ); ?></th>
							<?php if ( class_exists( 'VigIA_Database' ) ) : ?>
								<th class="num"><?php echo esc_html__( 'AI Crawls', 'ai-share-summarize' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<tr class="aiss-no-data">
							<td colspan="<?php echo class_exists( 'VigIA_Database' ) ? '4' : '3'; ?>" class="aiss-loading"><?php echo esc_html__( 'Loading...', 'ai-share-summarize' ); ?></td>
						</tr>
					</tbody>
				</table>
				<div class="aiss-pager-bottom-wrap">
					<div class="aiss-pager" id="aiss-content-pager-bottom"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
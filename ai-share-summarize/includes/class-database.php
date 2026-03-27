<?php
/**
 * Database management class
 *
 * Handles table creation, data storage and retrieval for share button clicks.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class for click analytics
 */
class AyudaWP_AISS_Database {

	/**
	 * Database version for migrations
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Cache group for analytics queries
	 */
	const CACHE_GROUP = 'ayudawp_aiss';

	/**
	 * Cache TTL in seconds (5 minutes)
	 */
	const CACHE_TTL = 300;

	/**
	 * Get table name
	 *
	 * @return string Full table name with prefix.
	 */
	public static function ayudawp_get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aiss_clicks';
	}

	/**
	 * Create database tables on activation
	 *
	 * Note: dbDelta requires raw SQL; table name is safe (prefix + fixed string).
	 */
	public static function ayudawp_create_tables() {
		global $wpdb;

		$table_name      = esc_sql( $wpdb->prefix . 'aiss_clicks' );
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta requires raw SQL, table name is escaped with esc_sql().
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(50) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_title varchar(255) NOT NULL DEFAULT '',
			click_date datetime NOT NULL,
			PRIMARY KEY (id),
			KEY platform (platform),
			KEY post_id (post_id),
			KEY click_date (click_date)
		) {$charset_collate};";
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'ayudawp_aiss_db_version', self::DB_VERSION );
	}

	/**
	 * Insert a click record
	 *
	 * @param array $data Click data with platform, post_id, post_title.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function ayudawp_insert_click( $data ) {
		global $wpdb;

		$defaults = array(
			'platform'   => '',
			'post_id'    => 0,
			'post_title' => '',
			'click_date' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$data['platform']   = sanitize_text_field( $data['platform'] );
		$data['post_id']    = absint( $data['post_id'] );
		$data['post_title'] = sanitize_text_field( $data['post_title'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- required for custom analytics table.
		$result = $wpdb->insert(
			self::ayudawp_get_table_name(),
			array(
				'platform'   => $data['platform'],
				'post_id'    => $data['post_id'],
				'post_title' => $data['post_title'],
				'click_date' => $data['click_date'],
			),
			array( '%s', '%d', '%s', '%s' )
		);

		// Cache expires naturally via TTL (5 min). No need to flush
		// on every insert — analytics data is not real-time critical
		// and flushing on each click is counterproductive under load.

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get aggregated stats for a date range
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Stats with total_clicks, unique_platforms, unique_posts.
	 */
	public static function ayudawp_get_stats( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'stats_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_clicks,
					COUNT(DISTINCT platform) as unique_platforms,
					COUNT(DISTINCT CASE WHEN post_id > 0 THEN post_id END) as unique_posts
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $row ? $row : array(
			'total_clicks'     => 0,
			'unique_platforms' => 0,
			'unique_posts'     => 0,
		);

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get clicks over time for chart
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Array of rows with date, click_count.
	 */
	public static function ayudawp_get_clicks_over_time( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'clicks_time_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(click_date) as date, COUNT(*) as click_count
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s
				GROUP BY DATE(click_date)
				ORDER BY date ASC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $results ? $results : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get clicks over time with zero-filled dates
	 *
	 * Returns one entry per day in the range, filling missing dates with 0.
	 * Used for comparison alignment where both datasets must have
	 * the same number of entries (one per day).
	 *
	 * @since 1.7.0
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Array of rows with date, click_count (every day in range).
	 */
	public static function ayudawp_get_clicks_over_time_filled( $start_date, $end_date ) {
		$cache_key = 'clicks_time_filled_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// Get raw data and index by date.
		$raw     = self::ayudawp_get_clicks_over_time( $start_date, $end_date );
		$indexed = array();
		foreach ( $raw as $row ) {
			$indexed[ $row['date'] ] = (int) $row['click_count'];
		}

		// Fill every date in the range.
		$result  = array();
		$current = new DateTime( $start_date );
		$end_dt  = new DateTime( $end_date );

		while ( $current <= $end_dt ) {
			$date_str = $current->format( 'Y-m-d' );
			$result[] = array(
				'date'        => $date_str,
				'click_count' => isset( $indexed[ $date_str ] ) ? $indexed[ $date_str ] : 0,
			);
			$current->modify( '+1 day' );
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get clicks grouped by date and platform (for chart breakdown)
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Array of rows with date, platform, click_count.
	 */
	public static function ayudawp_get_clicks_by_platform_over_time( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'clicks_platform_time_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(click_date) as date, platform, COUNT(*) as click_count
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s
				GROUP BY DATE(click_date), platform
				ORDER BY date ASC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $results ? $results : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get top platforms by clicks
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @param int    $limit      Max results.
	 * @param int    $offset     Offset for pagination.
	 * @return array Array with platform, click_count.
	 */
	public static function ayudawp_get_top_platforms( $start_date, $end_date, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$cache_key = 'top_platforms_' . md5( $start_date . $end_date . $limit . $offset );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT platform, COUNT(*) as click_count
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s
				GROUP BY platform
				ORDER BY click_count DESC
				LIMIT %d OFFSET %d",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $results ? $results : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get total unique platforms count
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int
	 */
	public static function ayudawp_get_platforms_count( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'platforms_count_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$result = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT platform)
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get top content by clicks
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @param int    $limit      Max results.
	 * @param int    $offset     Offset for pagination.
	 * @return array Array with post_id, post_title, click_count.
	 */
	public static function ayudawp_get_top_content( $start_date, $end_date, $limit = 10, $offset = 0 ) {
		global $wpdb;

		$cache_key = 'top_content_' . md5( $start_date . $end_date . $limit . $offset );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, post_title, COUNT(*) as click_count
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s AND post_id > 0
				GROUP BY post_id, post_title
				ORDER BY click_count DESC
				LIMIT %d OFFSET %d",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59',
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = $results ? $results : array();
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get total unique posts count
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int
	 */
	public static function ayudawp_get_content_count( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'content_count_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom analytics table, name is escaped with esc_sql().
		$result = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id)
				FROM {$table}
				WHERE click_date >= %s AND click_date < %s AND post_id > 0",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Get AI crawl data per post from VigIA (if active)
	 *
	 * @param array  $post_ids   Array of post IDs.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Associative array post_id => crawl_count.
	 */
	public static function ayudawp_get_vigia_crawls_for_posts( $post_ids, $start_date, $end_date ) {
		if ( ! class_exists( 'VigIA_Database' ) || empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		$vigia_table      = $wpdb->prefix . 'vigia_visits';
		$vigia_table_safe = esc_sql( $vigia_table );

		// Check if VigIA table exists (one-time structural check).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time existence check, no caching needed.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$vigia_table
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		$crawl_data = array();
		foreach ( $post_ids as $pid ) {
			$permalink = get_permalink( $pid );
			if ( ! $permalink ) {
				continue;
			}

			$path = wp_parse_url( $permalink, PHP_URL_PATH );
			if ( ! $path ) {
				continue;
			}

			$cache_key = 'vigia_crawls_' . md5( $pid . $start_date . $end_date );
			$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				$crawl_data[ $pid ] = (int) $cached;
				continue;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- VigIA external table, name is escaped with esc_sql().
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$vigia_table_safe}
					WHERE request_path = %s
					AND visit_date >= %s AND visit_date < %s",
					$path,
					$start_date . ' 00:00:00',
					$end_date . ' 23:59:59'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$crawl_data[ $pid ] = (int) $count;
			wp_cache_set( $cache_key, (int) $count, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return $crawl_data;
	}

	/**
	 * Get all click data for CSV export in a date range
	 *
	 * Returns rows grouped by date + platform + post for a full export.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Rows with date, platform, post_title, post_id, clicks.
	 */
	public static function ayudawp_get_export_data( $start_date, $end_date ) {
		global $wpdb;

		$table      = esc_sql( $wpdb->prefix . 'aiss_clicks' );
		$cache_key  = 'export_' . md5( $start_date . $end_date );
		$cached     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, name is escaped with esc_sql().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(click_date) AS date,
					platform,
					post_id,
					post_title,
					COUNT(*) AS clicks
				FROM {$table}
				WHERE click_date >= %s AND click_date <= %s
				GROUP BY DATE(click_date), platform, post_id, post_title
				ORDER BY date DESC, clicks DESC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $results ) {
			$results = array();
		}

		// Add post permalink for each row.
		foreach ( $results as &$row ) {
			$row['post_url'] = $row['post_id'] > 0 ? get_permalink( (int) $row['post_id'] ) : '';
			if ( ! $row['post_url'] ) {
				$row['post_url'] = '';
			}
		}
		unset( $row );

		wp_cache_set( $cache_key, $results, self::CACHE_GROUP, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Delete all analytics data
	 */
	public static function ayudawp_delete_all_data() {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- TRUNCATE on own table, name is escaped with esc_sql().
		$wpdb->query( "TRUNCATE TABLE {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Purge analytics data older than the configured retention period
	 *
	 * Called daily by cron. Skips if retention is set to 'forever'.
	 */
	public static function ayudawp_purge_old_data() {
		$options   = get_option( 'ayudawp_aiss_options' );
		$retention = isset( $options['data_retention'] ) ? $options['data_retention'] : 'forever';

		if ( 'forever' === $retention ) {
			return;
		}

		$intervals = array(
			'1_month'  => '1 month',
			'3_months' => '3 months',
			'6_months' => '6 months',
			'1_year'   => '1 year',
		);

		if ( ! isset( $intervals[ $retention ] ) ) {
			return;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $intervals[ $retention ] ) );

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cron purge on own table, name is escaped with esc_sql().
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE click_date < %s",
				$cutoff_date
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Drop the analytics table (for uninstall)
	 */
	public static function ayudawp_drop_tables() {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'aiss_clicks' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DROP on own table at uninstall, name is escaped with esc_sql().
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( 'ayudawp_aiss_db_version' );
	}
}
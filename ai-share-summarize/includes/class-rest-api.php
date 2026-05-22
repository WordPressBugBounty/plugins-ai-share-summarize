<?php
/**
 * REST API class
 *
 * Provides REST endpoints for analytics data, click tracking,
 * period comparison, and CSV export.
 *
 * @package AiShareSummarize
 * @since 1.5.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class
 */
class AyudaWP_AISS_Rest_API {

	/**
	 * API namespace
	 */
	const API_NAMESPACE = 'aiss/v1';

	/**
	 * Register REST routes
	 */
	public static function ayudawp_register_routes() {
		// Track a click (public, from frontend).
		register_rest_route(
			self::API_NAMESPACE,
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ayudawp_track_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'platform' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Stats endpoint (admin only).
		register_rest_route(
			self::API_NAMESPACE,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_stats' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => self::ayudawp_get_date_args(),
			)
		);

		// Stats comparison endpoint (admin only, v1.7.0).
		register_rest_route(
			self::API_NAMESPACE,
			'/stats/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_stats_compare' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					self::ayudawp_get_compare_args()
				),
			)
		);

		// Timeline endpoint (admin only).
		register_rest_route(
			self::API_NAMESPACE,
			'/timeline',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_timeline' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					array(
						'filled' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					)
				),
			)
		);

		// Timeline comparison endpoint (admin only, v1.7.0).
		register_rest_route(
			self::API_NAMESPACE,
			'/timeline/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_timeline_compare' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					self::ayudawp_get_compare_args()
				),
			)
		);

		// Timeline broken down by platform (admin only).
		register_rest_route(
			self::API_NAMESPACE,
			'/timeline-by-platform',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_timeline_by_platform' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => self::ayudawp_get_date_args(),
			)
		);

		// Top platforms endpoint (admin only).
		register_rest_route(
			self::API_NAMESPACE,
			'/platforms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_platforms' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					self::ayudawp_get_pagination_args()
				),
			)
		);

		// Top content endpoint (admin only).
		register_rest_route(
			self::API_NAMESPACE,
			'/content',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_content' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					self::ayudawp_get_pagination_args()
				),
			)
		);

		// Export endpoint (admin only) - returns all data for the date range.
		register_rest_route(
			self::API_NAMESPACE,
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_export' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => self::ayudawp_get_date_args(),
			)
		);

		// Timeline CSV export endpoint (admin only, v1.7.0).
		register_rest_route(
			self::API_NAMESPACE,
			'/export/timeline',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ayudawp_get_export_timeline' ),
				'permission_callback' => array( __CLASS__, 'ayudawp_check_admin_permission' ),
				'args'                => array_merge(
					self::ayudawp_get_date_args(),
					self::ayudawp_get_compare_args()
				),
			)
		);

		// AI Summary regeneration (v2.0.0) — editor-triggered, sync.
		register_rest_route(
			self::API_NAMESPACE,
			'/summary/regenerate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ayudawp_regenerate_summary' ),
				'permission_callback' => function ( $request ) {
					$post_id = (int) $request->get_param( 'post_id' );
					return $post_id && current_user_can( 'edit_post', $post_id );
				},
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Public-facing summary generation (v2.0.0) — triggered by the
		// "Generate summary" button in the post for visitors.
		register_rest_route(
			self::API_NAMESPACE,
			'/summary/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ayudawp_public_generate_summary' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check admin permission
	 *
	 * @return bool
	 */
	public static function ayudawp_check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Build a REST response with no-cache headers
	 *
	 * Prevents caching plugins (SiteGround Speed Optimizer, etc.)
	 * from serving stale analytics data on admin endpoints.
	 *
	 * @param mixed $data    Response data.
	 * @param int   $status  HTTP status code.
	 * @return WP_REST_Response
	 */
	private static function ayudawp_nocache_response( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );
		return $response;
	}

	/**
	 * Get date args definition
	 *
	 * @return array
	 */
	private static function ayudawp_get_date_args() {
		return array(
			'days'       => array(
				'type'              => 'integer',
				'default'           => 30,
				'sanitize_callback' => 'absint',
			),
			'start_date' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'end_date'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get pagination args definition
	 *
	 * @return array
	 */
	private static function ayudawp_get_pagination_args() {
		return array(
			'limit'  => array(
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'offset' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get comparison args definition (v1.7.0)
	 *
	 * @return array
	 */
	private static function ayudawp_get_compare_args() {
		return array(
			'compare'           => array(
				'type'              => 'string',
				'default'           => 'previous',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'compare_date_from' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'compare_date_to'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Calculate date range from request params
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array With start_date and end_date.
	 */
	private static function ayudawp_get_date_range( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		if ( $start_date && $end_date ) {
			return array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
		}

		$days     = (int) $request->get_param( 'days' );
		// Use wp_date() to respect WordPress timezone setting (data is stored in local time).
		$end_date = wp_date( 'Y-m-d' );

		if ( 0 === $days ) {
			$start_date = '2020-01-01';
		} else {
			$start_date = wp_date( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
		);
	}

	/**
	 * Calculate comparison date range based on current range and compare type (v1.7.0)
	 *
	 * @param WP_REST_Request $request       Request object with compare params.
	 * @param array           $current_range Current period start_date and end_date.
	 * @return array With start_date and end_date for comparison period.
	 */
	private static function ayudawp_get_compare_date_range( $request, $current_range ) {
		$compare = $request->get_param( 'compare' );
		$start   = $current_range['start_date'];
		$end     = $current_range['end_date'];

		// Period length in days.
		$start_dt    = new DateTime( $start );
		$end_dt      = new DateTime( $end );
		$period_days = $end_dt->diff( $start_dt )->days + 1;

		switch ( $compare ) {
			case 'year':
				$comp_start = gmdate( 'Y-m-d', strtotime( $start . ' -1 year' ) );
				$comp_end   = gmdate( 'Y-m-d', strtotime( $end . ' -1 year' ) );
				break;

			case 'custom':
				$comp_start = $request->get_param( 'compare_date_from' );
				$comp_end   = $request->get_param( 'compare_date_to' );
				if ( ! $comp_start || ! $comp_end ) {
					// Fallback to previous period.
					$comp_end   = gmdate( 'Y-m-d', strtotime( $start . ' -1 day' ) );
					$comp_start = gmdate( 'Y-m-d', strtotime( $comp_end . ' -' . ( $period_days - 1 ) . ' days' ) );
				}
				break;

			default: // 'previous'.
				$comp_end   = gmdate( 'Y-m-d', strtotime( $start . ' -1 day' ) );
				$comp_start = gmdate( 'Y-m-d', strtotime( $comp_end . ' -' . ( $period_days - 1 ) . ' days' ) );
				break;
		}

		return array(
			'start_date' => $comp_start,
			'end_date'   => $comp_end,
		);
	}

	/**
	 * Calculate percentage change between current and previous values (v1.7.0)
	 *
	 * @param int $current  Current period value.
	 * @param int $previous Previous period value.
	 * @return float Percentage change.
	 */
	private static function ayudawp_calculate_change( $current, $previous ) {
		if ( 0 === (int) $previous ) {
			return $current > 0 ? 100.0 : 0.0;
		}
		return ( ( $current - $previous ) / $previous ) * 100;
	}

	/**
	 * Escape a value for CSV output
	 *
	 * @param string|int|float $value Value to escape.
	 * @return string Escaped CSV field.
	 */
	private static function ayudawp_csv_escape( $value ) {
		$str = (string) $value;
		if ( false !== strpos( $str, ',' ) || false !== strpos( $str, '"' ) || false !== strpos( $str, "\n" ) ) {
			$str = '"' . str_replace( '"', '""', $str ) . '"';
		}
		return $str;
	}

	/**
	 * Track a button click from frontend
	 *
	 * No nonce verification: this is a public, non-destructive endpoint
	 * (anonymous click counter). Nonces break with full-page caching
	 * (SiteGround, WP Super Cache, etc.) because they are baked into
	 * the cached HTML and expire. The endpoint is protected by platform
	 * whitelist validation and input sanitization instead.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_track_click( $request ) {
		$platform = $request->get_param( 'platform' );
		$post_id  = $request->get_param( 'post_id' );

		// Validate platform against known list.
		$valid_platforms = array_keys( ayudawp_aiss_get_available_buttons() );
		// Also accept hyphenated versions.
		$platform_clean = str_replace( '-', '_', $platform );

		if ( ! in_array( $platform_clean, $valid_platforms, true ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid platform' ), 400 );
		}

		$post_title = '';
		if ( $post_id > 0 ) {
			$post_title = get_the_title( $post_id );
		}

		$result = AyudaWP_AISS_Database::ayudawp_insert_click( array(
			'platform'   => $platform_clean,
			'post_id'    => $post_id,
			'post_title' => $post_title,
		) );

		if ( $result ) {
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		return new WP_REST_Response( array( 'error' => 'Failed to track' ), 500 );
	}

	/**
	 * Get stats for the date range
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_stats( $request ) {
		$range = self::ayudawp_get_date_range( $request );
		$stats = AyudaWP_AISS_Database::ayudawp_get_stats( $range['start_date'], $range['end_date'] );

		return self::ayudawp_nocache_response( $stats );
	}

	/**
	 * Get comparison stats for the date range (v1.7.0)
	 *
	 * Returns previous-period values and percentage change for each metric.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_stats_compare( $request ) {
		$range         = self::ayudawp_get_date_range( $request );
		$current_stats = AyudaWP_AISS_Database::ayudawp_get_stats( $range['start_date'], $range['end_date'] );

		$comp_range = self::ayudawp_get_compare_date_range( $request, $range );
		$comp_stats = AyudaWP_AISS_Database::ayudawp_get_stats( $comp_range['start_date'], $comp_range['end_date'] );

		$response = array(
			'total_clicks_previous'     => (int) $comp_stats['total_clicks'],
			'total_clicks_change'       => round( self::ayudawp_calculate_change( (int) $current_stats['total_clicks'], (int) $comp_stats['total_clicks'] ), 1 ),
			'unique_platforms_previous' => (int) $comp_stats['unique_platforms'],
			'unique_platforms_change'   => round( self::ayudawp_calculate_change( (int) $current_stats['unique_platforms'], (int) $comp_stats['unique_platforms'] ), 1 ),
			'unique_posts_previous'     => (int) $comp_stats['unique_posts'],
			'unique_posts_change'       => round( self::ayudawp_calculate_change( (int) $current_stats['unique_posts'], (int) $comp_stats['unique_posts'] ), 1 ),
		);

		return self::ayudawp_nocache_response( $response );
	}

	/**
	 * Get timeline data
	 *
	 * Supports optional 'filled' param (v1.7.0) to return zero-filled dates
	 * for comparison chart alignment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_timeline( $request ) {
		$range  = self::ayudawp_get_date_range( $request );
		$filled = (int) $request->get_param( 'filled' );

		if ( $filled ) {
			$timeline = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time_filled( $range['start_date'], $range['end_date'] );
		} else {
			$timeline = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time( $range['start_date'], $range['end_date'] );
		}

		return self::ayudawp_nocache_response( $timeline );
	}

	/**
	 * Get comparison timeline data (v1.7.0)
	 *
	 * Returns zero-filled daily clicks for the comparison period.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_timeline_compare( $request ) {
		$range      = self::ayudawp_get_date_range( $request );
		$comp_range = self::ayudawp_get_compare_date_range( $request, $range );

		$timeline = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time_filled(
			$comp_range['start_date'],
			$comp_range['end_date']
		);

		return self::ayudawp_nocache_response( $timeline );
	}

	/**
	 * Get timeline data grouped by platform (for chart per-platform breakdown)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_timeline_by_platform( $request ) {
		$range = self::ayudawp_get_date_range( $request );
		$rows  = AyudaWP_AISS_Database::ayudawp_get_clicks_by_platform_over_time( $range['start_date'], $range['end_date'] );

		return self::ayudawp_nocache_response( $rows );
	}

	/**
	 * Get top platforms
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_platforms( $request ) {
		$range  = self::ayudawp_get_date_range( $request );
		$limit  = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		$platforms = AyudaWP_AISS_Database::ayudawp_get_top_platforms(
			$range['start_date'],
			$range['end_date'],
			$limit,
			$offset
		);

		$total = AyudaWP_AISS_Database::ayudawp_get_platforms_count(
			$range['start_date'],
			$range['end_date']
		);

		return self::ayudawp_nocache_response( array(
			'items' => $platforms,
			'total' => $total,
		) );
	}

	/**
	 * Get top content
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_content( $request ) {
		$range  = self::ayudawp_get_date_range( $request );
		$limit  = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		$content = AyudaWP_AISS_Database::ayudawp_get_top_content(
			$range['start_date'],
			$range['end_date'],
			$limit,
			$offset
		);

		$total = AyudaWP_AISS_Database::ayudawp_get_content_count(
			$range['start_date'],
			$range['end_date']
		);

		// If VigIA is active, add crawl data per post.
		$vigia_active = class_exists( 'VigIA_Database' );
		$crawl_data   = array();

		if ( $vigia_active && ! empty( $content ) ) {
			$post_ids   = wp_list_pluck( $content, 'post_id' );
			$crawl_data = AyudaWP_AISS_Database::ayudawp_get_vigia_crawls_for_posts(
				$post_ids,
				$range['start_date'],
				$range['end_date']
			);
		}

		return self::ayudawp_nocache_response( array(
			'items'        => $content,
			'total'        => $total,
			'vigia_active' => $vigia_active,
			'crawl_data'   => $crawl_data,
		) );
	}

	/**
	 * Get all data for CSV export
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_export( $request ) {
		$range = self::ayudawp_get_date_range( $request );
		$rows  = AyudaWP_AISS_Database::ayudawp_get_export_data( $range['start_date'], $range['end_date'] );

		return self::ayudawp_nocache_response( array(
			'rows'       => $rows,
			'start_date' => $range['start_date'],
			'end_date'   => $range['end_date'],
		) );
	}

	/**
	 * Export timeline data as CSV (v1.7.0)
	 *
	 * Includes comparison columns when compare param is present.
	 * CSV is built server-side and returned as { filename, content }.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_get_export_timeline( $request ) {
		$range    = self::ayudawp_get_date_range( $request );
		$timeline = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time_filled(
			$range['start_date'],
			$range['end_date']
		);

		$compare       = $request->get_param( 'compare' );
		$comp_timeline = null;

		// Only load comparison if compare param is explicitly set and not empty.
		if ( $compare && '' !== $compare ) {
			$comp_range    = self::ayudawp_get_compare_date_range( $request, $range );
			$comp_timeline = AyudaWP_AISS_Database::ayudawp_get_clicks_over_time_filled(
				$comp_range['start_date'],
				$comp_range['end_date']
			);
		}

		// Build CSV content.
		$site_name = get_bloginfo( 'name' );
		$lines     = array();

		$lines[] = self::ayudawp_csv_escape(
			$site_name . ' - AI Share & Summarize Timeline - ' . $range['start_date'] . ' to ' . $range['end_date']
		);
		$lines[] = '';

		if ( $comp_timeline ) {
			// Headers with comparison columns.
			$lines[] = implode( ',', array(
				'Date',
				'Clicks',
				'Comparison date',
				'Comparison clicks',
				'Difference',
				'Change %',
			) );

			$count = max( count( $timeline ), count( $comp_timeline ) );
			for ( $i = 0; $i < $count; $i++ ) {
				$current = isset( $timeline[ $i ] ) ? $timeline[ $i ] : array(
					'date'        => '',
					'click_count' => 0,
				);
				$comp    = isset( $comp_timeline[ $i ] ) ? $comp_timeline[ $i ] : array(
					'date'        => '',
					'click_count' => 0,
				);

				$diff   = (int) $current['click_count'] - (int) $comp['click_count'];
				$change = self::ayudawp_calculate_change(
					(int) $current['click_count'],
					(int) $comp['click_count']
				);

				$lines[] = implode( ',', array(
					$current['date'],
					$current['click_count'],
					$comp['date'],
					$comp['click_count'],
					$diff,
					round( $change, 1 ) . '%',
				) );
			}
		} else {
			// Simple headers without comparison.
			$lines[] = 'Date,Clicks';

			foreach ( $timeline as $row ) {
				$lines[] = $row['date'] . ',' . $row['click_count'];
			}
		}

		// BOM + content for Excel UTF-8 compatibility.
		$content  = "\xEF\xBB\xBF" . implode( "\r\n", $lines );
		$filename = 'aiss-timeline-' . wp_date( 'Y-m-d' ) . '.csv';

		return self::ayudawp_nocache_response( array(
			'filename' => $filename,
			'content'  => $content,
		) );
	}

	/**
	 * Regenerate the AI summary for a post (v2.0.0)
	 *
	 * Synchronous on purpose — the editor user clicked "Regenerate now"
	 * and is actively waiting for the response, so we run the cascade in
	 * the request itself instead of scheduling cron.
	 *
	 * @param WP_REST_Request $request Request object with post_id.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_regenerate_summary( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return self::ayudawp_nocache_response( array( 'error' => 'AI Summary class missing' ), 500 );
		}

		$result = AyudaWP_AISS_AI_Summary::run( $post_id, true );

		if ( is_wp_error( $result ) ) {
			return self::ayudawp_nocache_response(
				array(
					'error'   => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		return self::ayudawp_nocache_response( array(
			'summary'  => $result,
			'provider' => (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_PROVIDER, true ),
			'hash'     => (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_HASH, true ),
		) );
	}

	/**
	 * Public generation endpoint for visitors (v2.0.0)
	 *
	 * Triggered by the "Generate AI summary" button rendered on posts that
	 * don't have a stored summary yet. Rate-limited per IP+post to one
	 * generation per minute to prevent abuse. Returns the rendered summary
	 * HTML ready to swap into the DOM.
	 *
	 * Honors three admin settings:
	 * - ai_summary_frontend_button must be on (otherwise we 403)
	 * - ai_summary_frontend_force_extractive (when on) skips the AI Client
	 *   path and uses the PHP extractive summarizer only — zero cost
	 * - exclusion (per-post meta box + SEO noindex) is respected
	 *
	 * No nonce is enforced because the button is public and page caches
	 * (SiteGround, WP Rocket, etc.) would bake stale nonces into the HTML.
	 * Rate limiting + post-exclusion checks provide the protection layer.
	 *
	 * @param WP_REST_Request $request Request object with post_id.
	 * @return WP_REST_Response
	 */
	public static function ayudawp_public_generate_summary( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return self::ayudawp_nocache_response( array( 'error' => 'invalid_post' ), 400 );
		}

		if ( ! class_exists( 'AyudaWP_AISS_AI_Summary' ) ) {
			return self::ayudawp_nocache_response( array( 'error' => 'class_missing' ), 500 );
		}

		$options = get_option( 'ayudawp_aiss_options', array() );

		if ( empty( $options['ai_summary_frontend_button'] ) ) {
			return self::ayudawp_nocache_response( array( 'error' => 'frontend_disabled' ), 403 );
		}

		if ( ayudawp_aiss_is_post_excluded( $post_id ) ) {
			return self::ayudawp_nocache_response( array( 'error' => 'post_excluded' ), 403 );
		}

		// If a summary already exists, just return it — avoids duplicate work
		// when two visitors click around the same time, and means cached
		// responses degrade gracefully.
		$existing = (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_SUMMARY, true );
		if ( '' !== $existing ) {
			return self::ayudawp_nocache_response( array(
				'html'     => AyudaWP_AISS_AI_Summary::get_summary_html( $post_id ),
				'provider' => (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_PROVIDER, true ),
			) );
		}

		// Rate limit: 1 generation per IP per post per minute.
		$ip       = self::ayudawp_visitor_ip();
		$rate_key = 'ayudawp_aiss_pub_' . md5( $ip . '|' . $post_id );

		if ( get_transient( $rate_key ) ) {
			return self::ayudawp_nocache_response(
				array(
					'error'   => 'rate_limited',
					'message' => __( 'Please wait a moment before trying again.', 'ai-share-summarize' ),
				),
				429
			);
		}
		set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		$force_extractive = ! empty( $options['ai_summary_frontend_force_extractive'] );

		$result = AyudaWP_AISS_AI_Summary::run( $post_id, true, $force_extractive );

		if ( is_wp_error( $result ) ) {
			return self::ayudawp_nocache_response(
				array(
					'error'   => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		return self::ayudawp_nocache_response( array(
			'html'     => AyudaWP_AISS_AI_Summary::get_summary_html( $post_id ),
			'provider' => (string) get_post_meta( $post_id, AyudaWP_AISS_AI_Summary::META_PROVIDER, true ),
		) );
	}

	/**
	 * Best-effort visitor IP detection for rate limiting (v2.0.0)
	 *
	 * Not used for authentication or anything security-sensitive — only as
	 * a rate-limit bucket key, so accuracy under reverse proxies is enough.
	 *
	 * @return string IP address, or '0.0.0.0' as fallback.
	 */
	private static function ayudawp_visitor_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$raw   = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$first = trim( explode( ',', $raw )[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}
		return '0.0.0.0';
	}
}
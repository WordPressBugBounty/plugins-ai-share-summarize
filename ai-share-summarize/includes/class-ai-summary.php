<?php
/**
 * AI Summary class for Share Buttons & AI-powered Summaries plugin
 *
 * Generates an inline summary of post content using the WordPress 7.0
 * AI Client (wp_ai_client_prompt()) when available, with a PHP extractive
 * fallback for older WordPress versions or sites without an AI provider
 * configured in Settings > Connectors.
 *
 * Generation runs asynchronously via wp_schedule_single_event() so the AI
 * provider call (5-30s) never blocks the request that triggered it, whether a
 * web save or the cron process that publishes a scheduled post. Scheduled
 * posts are triggered from future_to_publish, early in wp_publish_post(),
 * because the wp_after_insert_post handler sits at the end of the publish hook
 * chain and is missed whenever a cron publish process is interrupted before
 * reaching it. The only inline path is the daily rescue sweep, a dedicated
 * cron with no publish work to lose. Scheduling is verified after the fact and
 * re-checked at shutdown, with a frontend self-heal plus that daily sweep as
 * safety nets against a single-shot event erased by a concurrent cron runner.
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
	const LOCK_RESCUE_AGE = 180; // Seconds before a lock with no queued event counts as orphaned (v2.2.2).
	const RESCUE_WINDOW_DAYS = 14; // Recovery layers only touch posts newer than this (v2.2.2) — repairing lost events, never backfilling the archive.
	const LAST_ERROR_OPT  = 'ayudawp_aiss_last_ai_error';

	/**
	 * Post IDs whose async event was scheduled during this request and must
	 * be re-verified against the database at shutdown (v2.2.2).
	 *
	 * @var array<int,bool>
	 */
	private static $pending_events = array();

	/**
	 * Whether the shutdown re-verifier has been hooked already (v2.2.2).
	 *
	 * @var bool
	 */
	private static $shutdown_hooked = false;

	/**
	 * Constructor: register hooks
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'wp_after_insert_post', array( $this, 'on_save_post' ), 20, 4 );

		// Scheduled posts publish through wp_publish_post() inside WP-Cron,
		// where the wp_after_insert_post handler above is the very last link of
		// a long hook chain. If anything earlier aborts that chain (a killed
		// cron process, a third-party save_post callback that wp_die()s with no
		// nonce, a fatal in another callback), the post goes live but
		// on_save_post never runs. future_to_publish fires early in
		// wp_publish_post(), before that chain, so generation is triggered
		// reliably on any host. See on_future_publish().
		add_action( 'future_to_publish', array( $this, 'on_future_publish' ), 20, 1 );

		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ), 10, 1 );

		// Rescue sweep (v2.2.2): piggyback on the existing daily cron so
		// posts whose async event was silently lost (a concurrent wp-cron
		// runner clobbering the 'cron' option is a real, observed failure
		// mode) eventually get their summary without any new cron of our own.
		add_action( 'ayudawp_aiss_daily_purge', array( __CLASS__, 'rescue_sweep' ) );
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
	 * Fire on every post save — trigger automatic generation when it applies
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
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}
		self::maybe_generate( (int) $post_id, $post );
	}

	/**
	 * Trigger generation when a scheduled post goes live (v2.2.2)
	 *
	 * future_to_publish fires early inside wp_publish_post(), unlike
	 * wp_after_insert_post which sits at the end of the chain and is skipped
	 * when the cron publish process is interrupted before it. Forces the async
	 * route ($prefer_async) so the provider call runs in a separate cron
	 * request, never inside the publish process itself. maybe_generate()
	 * re-checks every eligibility rule (feature active, post type, exclusions,
	 * the manual-edit flag — already committed when the post was saved as
	 * 'future' — and the content hash), so this stays safe and idempotent next
	 * to on_save_post for a scheduled post published from the editor.
	 *
	 * @since 2.2.2
	 * @param WP_Post $post The post transitioning from future to publish.
	 * @return void
	 */
	public function on_future_publish( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			$post = get_post( $post );
		}
		if ( $post instanceof WP_Post ) {
			self::maybe_generate( (int) $post->ID, $post, true );
		}
	}

	/**
	 * Central gate for automatic generation (v2.2.2)
	 *
	 * Runs every eligibility check (feature active, post published, post type
	 * allowed, not excluded, not manually edited, content actually changed,
	 * no generation already in flight) and then generates by the most robust
	 * route available:
	 *
	 * - The daily rescue sweep (a dedicated cron with no publish work to lose):
	 *   run() INLINE. There is no user waiting and nothing to interrupt.
	 * - Everything else — web saves, the frontend self-heal and scheduled-post
	 *   publishing (which passes $prefer_async): schedule the async event with
	 *   verification and a shutdown re-check against the database, so the
	 *   provider call never runs inside the request that triggered it, and a
	 *   single-shot event erased by a concurrent cron runner still has the
	 *   shutdown re-check, self-heal and sweep behind it (see
	 *   schedule_generation()).
	 *
	 * Shared by the save-post handler, the frontend self-heal and the daily
	 * rescue sweep so all entry points agree on the rules.
	 *
	 * @since 2.2.2
	 * @param int          $post_id      Post ID.
	 * @param WP_Post|null $post         Optional post object to avoid a refetch.
	 * @param bool         $prefer_async When true, schedule the async event even
	 *                                   inside WP-Cron instead of running inline.
	 *                                   Used by the scheduled-post publish path so
	 *                                   the provider call does not run inside the
	 *                                   publish process.
	 * @return bool True when generation ran or was scheduled.
	 */
	public static function maybe_generate( $post_id, $post = null, $prefer_async = false ) {
		$post_id = (int) $post_id;
		if ( ! $post_id || ! ayudawp_aiss_is_summary_active() ) {
			return false;
		}

		if ( null === $post ) {
			$post = get_post( $post_id );
		}
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return false;
		}

		if ( ! ayudawp_aiss_should_apply_summary( $post_id ) ) {
			return false;
		}

		// Respect manual edits — user override always wins.
		if ( 'manual' === get_post_meta( $post_id, self::META_PROVIDER, true ) ) {
			return false;
		}

		if ( ! self::should_regenerate( $post_id, $post ) ) {
			return false;
		}

		// Re-entrancy lock: a generation for this post is already in flight.
		// The lock stores its creation timestamp so an ORPHANED lock can be
		// told apart from a live one: when the async event this lock guards
		// is erased by a concurrent cron runner AFTER the publishing request
		// ended (the classic-editor failure window observed in production),
		// the lock would otherwise block every retry for its full 5 minutes.
		// An old-enough lock with no event in the queue means exactly that,
		// so fall through and reschedule. A duplicate is harmless anyway:
		// run() re-checks the content hash and no-ops when already generated.
		$lock_key = self::LOCK_PREFIX . $post_id;
		$lock     = get_transient( $lock_key );
		if ( $lock ) {
			$lock_age = time() - (int) $lock;
			if ( $lock_age < self::LOCK_RESCUE_AGE
				|| false !== wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
				return false;
			}
			// Orphaned lock (old, with nothing in the queue): rescue below.
		}

		if ( wp_doing_cron() && ! $prefer_async ) {
			// Dedicated cron with nothing to interrupt (the daily rescue
			// sweep): generate right here. run() releases the lock in its
			// finally block whatever happens. The scheduled-post publish path
			// passes $prefer_async = true so its provider call runs in a
			// separate cron request instead of inside wp_publish_post().
			set_transient( $lock_key, time(), 5 * MINUTE_IN_SECONDS );
			self::run( $post_id );
			return true;
		}

		return self::schedule_generation( $post_id );
	}

	/**
	 * Schedule the async generation event, verifying it actually landed (v2.2.2)
	 *
	 * The 'cron' option is rewritten wholesale by every process that touches
	 * the queue (non-atomic read-modify-write), so a single-shot event can be
	 * silently erased by an overlapping wp-cron runner that started with a
	 * pre-event snapshot. Defenses, in order:
	 *
	 * 1. Verify with wp_next_scheduled() right after scheduling; when the
	 *    event is not there (a 'pre_schedule_event' short-circuit from a cron
	 *    manager, a failed write), return false WITHOUT setting the lock so a
	 *    later save or the self-heal can retry.
	 * 2. The lock transient is set only AFTER the event is confirmed — the
	 *    pre-2.3.0 order (lock first, schedule unchecked) turned any silent
	 *    scheduling failure into a 5-minute retry blackout.
	 * 3. At shutdown, re-read the queue FROM THE DATABASE (the in-process
	 *    cache cannot see other processes' writes) and reschedule if the
	 *    event vanished mid-request.
	 *
	 * @since 2.2.2
	 * @param int $post_id Post ID.
	 * @return bool True when the event is confirmed in the queue.
	 */
	public static function schedule_generation( $post_id ) {
		$post_id = (int) $post_id;

		wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $post_id ) );

		if ( false === wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			return false;
		}

		set_transient( self::LOCK_PREFIX . $post_id, time(), 5 * MINUTE_IN_SECONDS );

		self::$pending_events[ $post_id ] = true;
		if ( ! self::$shutdown_hooked ) {
			self::$shutdown_hooked = true;
			add_action( 'shutdown', array( __CLASS__, 'reverify_scheduled_events' ), 5 );
		}

		return true;
	}

	/**
	 * Shutdown re-check: reschedule events erased by concurrent processes (v2.2.2)
	 *
	 * wp_next_scheduled() reads the request-local option cache, which cannot
	 * see a concurrent runner's clobbering write, so the caches for 'cron'
	 * and 'alloptions' are dropped first to force a fresh read from the
	 * database. The request is ending, so the invalidation is free.
	 *
	 * @since 2.2.2
	 * @return void
	 */
	public static function reverify_scheduled_events() {
		if ( empty( self::$pending_events ) ) {
			return;
		}
		$pending              = array_keys( self::$pending_events );
		self::$pending_events = array();

		wp_cache_delete( 'cron', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		foreach ( $pending as $post_id ) {
			if ( false !== wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
				continue;
			}
			// Erased between our verified write and now: put it back.
			wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Daily rescue sweep for published posts left without a summary (v2.2.2)
	 *
	 * Last safety net: even if the async event is lost after the request ends
	 * and no visitor triggers the frontend self-heal, recent posts get their
	 * summary within a day. Piggybacks on the existing daily purge cron (no
	 * new scheduled event) and is tightly bounded: only the newest handful of
	 * posts from the last two weeks. Runs inside cron, so maybe_generate()
	 * takes the inline path.
	 *
	 * @since 2.2.2
	 * @return void
	 */
	public static function rescue_sweep() {
		if ( ! ayudawp_aiss_is_summary_active() ) {
			return;
		}
		$post_types = ayudawp_aiss_get_summary_post_types();
		if ( empty( $post_types ) ) {
			return;
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array( 'after' => self::RESCUE_WINDOW_DAYS . ' days ago' ),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded rescue query (5 posts, 14-day window), runs once a day from cron.
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => self::META_SUMMARY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_SUMMARY,
						'value'   => '',
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			self::maybe_generate( (int) $post_id );
		}
	}

	/**
	 * Frontend self-heal: recover posts whose generation event was lost (v2.2.2)
	 *
	 * Called from get_summary_html() when a post that should have a summary
	 * has none. maybe_generate() re-runs every eligibility check (including
	 * the manual-edit flag and the in-flight lock, so no scheduling storms),
	 * making a lost event self-correct on the next visit instead of never.
	 *
	 * @since 2.2.2
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function maybe_self_heal( $post_id ) {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		static $seen = array();
		if ( isset( $seen[ $post_id ] ) ) {
			return;
		}
		$seen[ $post_id ] = true;

		// Recency window: self-heal repairs posts whose generation event was
		// recently lost. Older summary-less posts (typically pre-dating the
		// plugin) are deliberately left alone — backfilling an archive means
		// paid API calls nobody asked for, and it has its own explicit tools
		// (the per-post Regenerate button, the public frontend button).
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		$published = get_post_time( 'U', true, $post );
		if ( ! $published || $published < time() - self::RESCUE_WINDOW_DAYS * DAY_IN_SECONDS ) {
			return;
		}

		self::maybe_generate( $post_id, $post );
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
	public static function should_regenerate( $post_id, $post = null ) {
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

			$options = ayudawp_aiss_get_summary_options();

			// Resolve the admin's generation mode (v2.2.0). "extractive_only"
			// behaves like a forced-extractive call; "ai_only" disables the
			// fallback; "ai_fallback" keeps the A->C cascade. A caller that
			// forces extractive (public frontend) always allows the fallback.
			$mode                       = ayudawp_aiss_get_summary_mode();
			$force_extractive_effective = $force_extractive || 'extractive_only' === $mode;
			$allow_fallback             = $force_extractive || 'ai_fallback' === $mode;

			if ( $force_extractive_effective ) {
				// Skip Level A entirely — caller wants zero-cost generation
				// (typically the public frontend endpoint, or extractive_only mode).
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
				if ( ! $force_extractive_effective ) {
					self::store_last_ai_error( $ai_error );
				}

				// Whether to run the extractive fallback is driven by the mode
				// ("ai_fallback"), or by a caller that explicitly forced extractive.
				if ( ! $force_extractive_effective && ! $allow_fallback ) {
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

			// Refresh caches now that the summary exists. Generation usually
			// runs seconds after publishing (the async event, or the scheduled
			// -post cron), by which point a page cache may already have stored
			// the post, its archive or the home without the summary.
			// clean_post_cache() invalidates the object cache and fires
			// 'clean_post_cache', which several page caches hook; the dedicated
			// action below lets any cache plugin purge the exact URLs. Both
			// no-op when nothing is caching.
			clean_post_cache( $post_id );

			/**
			 * Fires after an automatic summary is generated and stored.
			 *
			 * Lets page-cache integrations purge the affected URLs so the fresh
			 * summary shows without waiting for the cache to expire on its own.
			 *
			 * @since 2.2.2
			 * @param int    $post_id  Post the summary was generated for.
			 * @param string $provider Provider that produced it ('wp_ai_client' or 'extractive').
			 */
			do_action( 'ayudawp_aiss_summary_generated', $post_id, $provider );

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
	 * In "Automatic" mode we try an ordered chain of candidate models, cheapest
	 * tier first, degrading to the next model when one is listed but not actually
	 * usable (unavailable for the account, removed mid-flight). A manual selection
	 * is tried as-is. If the SDK trips over a corrupt object-cache model list (a
	 * core TypeError), we flush the 'wp_ai_client' cache group once and retry so it
	 * self-heals. See resolve_model_candidates() and flush_ai_model_cache().
	 *
	 * When running inside WP-Cron (the async path scheduled after save),
	 * transient provider errors are retried with a short backoff (2s, 5s)
	 * before giving up — retry count filterable via
	 * 'ayudawp_aiss_ai_retry_attempts' (default 2). Sync callers are never
	 * retried so the editor and the public endpoint keep failing fast.
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
	 * @since 2.2.1 Ordered candidate chain with degradation + model-cache self-heal.
	 * @param string $content Post content.
	 * @param string $title   Post title.
	 * @return string|WP_Error Summary text on success.
	 */
	public static function generate_with_wp_ai_client( $content, $title ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'no_wp_ai_client', __( 'WP AI Client is not available (requires WordPress 7.0+).', 'ai-share-summarize' ) );
		}

		$options     = ayudawp_aiss_get_summary_options();
		$n_sentences = isset( $options['ai_summary_sentences'] ) ? max( 1, min( 5, (int) $options['ai_summary_sentences'] ) ) : 3;
		$bullets     = ! empty( $options['ai_summary_bullets'] );

		$system  = 'You are a content summarizer. Return ONLY the summary in plain text, no markdown, no quotes, no preamble. Match the language of the input.';
		$clean   = ayudawp_aiss_clean_html_for_summary( (string) $content );
		$excerpt = function_exists( 'mb_substr' )
			? mb_substr( $clean, 0, 3000 )
			: substr( $clean, 0, 3000 );

		if ( $bullets ) {
			// Bullet-list mode (v2.4.0): one point per line; the renderer splits
			// on newlines and prints one <li> per point.
			$system .= ' Write each point on its own line, with no list markers or numbering.';
			$prompt  = sprintf(
				"Summarize the following content in exactly %d concise key points, one per line:\n\n%s\n\n%s",
				$n_sentences,
				$title,
				$excerpt
			);
		} else {
			$prompt = sprintf(
				"Summarize the following content in exactly %d sentences:\n\n%s\n\n%s",
				$n_sentences,
				$title,
				$excerpt
			);
		}

		// Resolve the ordered candidate chain of [providerId, modelId] tuples to
		// try, cheapest tier first. A manual selection yields a single candidate;
		// "Automatic" yields the full chain so we can degrade through it. Empty
		// means no provider is configured (or the model list is momentarily
		// unavailable) — we still make one provider-agnostic attempt so the AI
		// Client can apply its own default.
		$candidates = self::resolve_model_candidates( $options );
		if ( empty( $candidates ) ) {
			$candidates = array( null );
		}

		// Transient provider errors (overloaded, rate limits, a momentarily
		// malformed "Missing the 'content' key" response) resolve on a quick
		// retry. Only the async cron path retries — sync calls (editor
		// "Regenerate now", public frontend endpoint) fail fast because a user is
		// actively waiting.
		$retries       = wp_doing_cron() ? (int) apply_filters( 'ayudawp_aiss_ai_retry_attempts', 2 ) : 0;
		$max_rounds    = 1 + min( 5, max( 0, $retries ) );
		$error         = null;
		$cache_flushed = false;

		// wp_ai_client_prompt() ships with WordPress 7.0; this plugin's floor is
		// 6.1 (share buttons work there, and the AI summary degrades to the PHP
		// extractive fallback). The function_exists() guard at the top of this
		// method already gates it, so we invoke it through a variable: the static
		// "requires WP 7.0" analysis would otherwise flag a call the plugin only
		// ever reaches on 7.0+, where the function actually exists.
		$prompt_fn = 'wp_ai_client_prompt';

		for ( $round = 1; $round <= $max_rounds; $round++ ) {
			if ( $round > 1 ) {
				sleep( 2 === $round ? 2 : 5 );
			}

			foreach ( $candidates as $candidate ) {
				try {
					$builder = $prompt_fn( $prompt )->using_system_instruction( $system );
					if ( is_array( $candidate ) ) {
						// Pin the provider and pass the model as a [provider, id]
						// tuple so the builder resolves it directly, without
						// enumerating other providers' (possibly poisoned) lists.
						$builder = $builder->using_provider( $candidate[0] )
							->using_model_preference( array( $candidate[0], $candidate[1] ) );
					}
					$result = $builder->generate_text();
				} catch ( \Throwable $e ) {
					$message = $e->getMessage();
					$error   = new WP_Error( 'wp_ai_client_exception', sprintf( '%s (%s)', $message, get_class( $e ) ) );

					if ( ! $cache_flushed && self::is_model_cache_error( $message ) ) {
						// Core robustness net: a poisoned wp_ai_client object-cache
						// entry makes the SDK return a non-array model map and fatal
						// on its ": array" return type. Flush it once so the next
						// candidate/round rebuilds a clean list and self-heals
						// instead of blocking generation for the 24h cache TTL.
						self::flush_ai_model_cache();
						$cache_flushed = true;
					}
					if ( self::is_transient_error( $message ) ) {
						// Whole-API hiccup: other models fail the same way. Break to
						// the round-level backoff instead of burning the chain.
						break;
					}
					// Model-specific failure (listed but unusable for this account,
					// removed mid-flight, incompatible): degrade to the next model.
					continue;
				}

				if ( is_wp_error( $result ) ) {
					$message = $result->get_error_message();
					$error   = $result;

					if ( ! $cache_flushed && self::is_model_cache_error( $message ) ) {
						self::flush_ai_model_cache();
						$cache_flushed = true;
					}
					if ( self::is_transient_error( $message ) ) {
						break;
					}
					continue;
				}

				$text = trim( wp_strip_all_tags( (string) $result ) );

				if ( '' === $text ) {
					$error = new WP_Error( 'wp_ai_client_empty', __( 'WP AI Client returned an empty response.', 'ai-share-summarize' ) );
					continue;
				}

				return $text;
			}
		}

		// Every candidate and round failed. Surface the round count so the admin
		// can tell a persistent failure from a single transient hiccup.
		if ( $max_rounds > 1 && $error instanceof WP_Error ) {
			return new WP_Error(
				$error->get_error_code(),
				sprintf(
					/* translators: 1: error message from the AI client, 2: number of attempts. */
					__( '%1$s (failed after %2$d attempts)', 'ai-share-summarize' ),
					$error->get_error_message(),
					$max_rounds
				)
			);
		}

		return $error;
	}

	/**
	 * Resolve the ordered model candidate chain to try for a summary (v2.2.1)
	 *
	 * Honors an explicit manual selection when it still exists in the live model
	 * list (a single candidate); otherwise returns the cheapest-tier-first chain
	 * from ayudawp_aiss_resolve_default_model_preference(). The chain is capped so
	 * a run of failures cannot fan out into an unbounded number of paid calls; the
	 * extractive fallback still covers a total wipeout.
	 *
	 * @since 2.2.1
	 * @param array $options Plugin options.
	 * @return array<int,array{0:string,1:string}> Ordered [providerId, modelId] tuples. May be empty.
	 */
	private static function resolve_model_candidates( $options ) {
		$available = ayudawp_aiss_get_available_models();
		$selected  = isset( $options['ai_summary_model'] ) ? (string) $options['ai_summary_model'] : '';

		// Explicit manual choice: honor it only if it still exists in the live
		// list, as a single candidate. An explicit pick is respected, not padded
		// with fallbacks — that is what "Automatic" is for.
		if ( '' !== $selected && false !== strpos( $selected, ':' ) ) {
			list( $sel_provider, $sel_model ) = explode( ':', $selected, 2 );
			if ( isset( $available[ $sel_provider ]['models'] ) ) {
				foreach ( $available[ $sel_provider ]['models'] as $candidate ) {
					if ( $candidate['id'] === $sel_model ) {
						return array( array( $sel_provider, $sel_model ) );
					}
				}
			}
			// Stale manual selection: fall through to the Automatic chain.
		}

		$candidates = ayudawp_aiss_resolve_default_model_preference( $available );

		// Bound how many models we will actually try generating with when they
		// fail one after another, to keep the cost of a bad day in check.
		$max = (int) apply_filters( 'ayudawp_aiss_max_model_candidates', 3 );
		if ( $max > 0 && count( $candidates ) > $max ) {
			$candidates = array_slice( $candidates, 0, $max );
		}

		return $candidates;
	}

	/**
	 * Whether an error message points at the AI Client model-list cache bug
	 *
	 * The core SDK's AbstractApiBasedModelMetadataDirectory::getModelMetadataMap()
	 * is typed ": array" but returns whatever the object cache hands back; a
	 * corrupt persistent entry makes it a fatal TypeError. Detecting it lets us
	 * flush the cache group and retry instead of failing for the cache TTL.
	 *
	 * @since 2.2.1
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_model_cache_error( $message ) {
		return ( false !== strpos( $message, 'getModelMetadataMap' )
			|| false !== strpos( $message, 'ModelMetadataDirectory' ) );
	}

	/**
	 * Whether an error message looks like a transient, whole-provider hiccup
	 *
	 * Transient errors (overloaded, rate limited, timeouts, a momentarily
	 * malformed response) hit every model the same way, so we retry the same
	 * request after a backoff rather than degrading to another model.
	 *
	 * @since 2.2.1
	 * @param string $message Error message.
	 * @return bool
	 */
	private static function is_transient_error( $message ) {
		$needles = array(
			'overloaded',
			'rate limit',
			'rate_limit',
			'too many requests',
			'timed out',
			'timeout',
			'temporarily',
			'service unavailable',
			'try again',
			"missing the 'content' key",
		);
		foreach ( $needles as $needle ) {
			if ( false !== stripos( $message, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Flush the AI Client's cached provider model lists, best effort (v2.2.1)
	 *
	 * The core AI Client stores each provider's model list in the 'wp_ai_client'
	 * object-cache group. A corrupt entry there triggers a fatal TypeError deep in
	 * the SDK (see is_model_cache_error()). We clear it two ways: flush the whole
	 * group when the object cache supports it, and ask the SDK to invalidate each
	 * configured provider's cache directly as a belt-and-braces fallback. Both are
	 * wrapped so cache maintenance can never break generation.
	 *
	 * @since 2.2.1
	 * @return void
	 */
	private static function flush_ai_model_cache() {
		if ( function_exists( 'wp_cache_supports' ) && function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'wp_ai_client' );
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( ! $registry->isProviderConfigured( $provider_id ) ) {
					continue;
				}
				$class_name = $registry->getProviderClassName( $provider_id );
				if ( ! is_callable( array( $class_name, 'modelMetadataDirectory' ) ) ) {
					continue;
				}
				$directory = $class_name::modelMetadataDirectory();
				if ( is_callable( array( $directory, 'invalidateCaches' ) ) ) {
					$directory->invalidateCaches();
				}
			}
		} catch ( \Throwable $e ) {
			// Best effort — never let cache invalidation break generation.
			unset( $e );
		}
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
			. '.ayudawp-aiss-summary-content ul{margin:0;padding-left:1.2em;list-style:disc}'
			. '.ayudawp-aiss-summary-content li{line-height:1.5}'
			. '.ayudawp-aiss-summary--placeholder{padding:0;border:none;background:transparent}'
			. '.ayudawp-aiss-summary-generate-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:transparent;border:1px solid #e0e0e0;border-radius:6px;font-weight:600;font-size:.95em;color:inherit;cursor:pointer}'
			. '.ayudawp-aiss-summary-icon{display:inline-flex;align-items:center}'
			. '.ayudawp-aiss-summary-icon svg{width:16px;height:16px}'
			. '</style>';
		// phpcs:enable
	}

	/**
	 * Split a stored summary into bullet-list items (v2.4.0)
	 *
	 * Bullet-mode summaries are stored one point per line, so newlines are
	 * the primary separator (stray list markers the model may emit despite
	 * the instruction are stripped). Summaries generated in paragraph mode
	 * or by the extractive fallback have no newlines: fall back to sentence
	 * segmentation so each sentence becomes one item without regenerating.
	 *
	 * @since 2.4.0
	 * @param string $summary Stored summary text.
	 * @return array<int,string> Trimmed, non-empty list items.
	 */
	private static function split_summary_lines( $summary ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $summary );
		if ( ! is_array( $lines ) ) {
			$lines = array();
		}

		$lines = array_map(
			static function ( $line ) {
				return trim( (string) preg_replace( '/^\s*(?:[-*•–]|\d+[.)])\s+/u', '', $line ) );
			},
			$lines
		);
		$lines = array_values( array_filter( $lines, 'strlen' ) );

		if ( count( $lines ) > 1 ) {
			return $lines;
		}

		return ayudawp_aiss_split_sentences( $summary );
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
			// Self-heal (v2.2.2): if this post should have an automatic
			// summary and its generation event was lost, reschedule it now.
			self::maybe_self_heal( $post_id );

			// No stored summary — render the public "Generate" button when
			// the admin has enabled visitor-driven generation. Otherwise
			// stay silent so the post layout isn't disturbed.
			return self::get_generate_button_html( $post_id );
		}

		$provider = get_post_meta( $post_id, self::META_PROVIDER, true );
		$options  = ayudawp_aiss_get_summary_options();
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
		// summaries without regenerating them. In bullet-list mode (v2.4.0)
		// the split prefers the stored newlines, falling back to sentence
		// segmentation for summaries generated in paragraph mode.
		$bullets_mode = ! empty( $options['ai_summary_bullets'] );
		$items        = $bullets_mode
			? self::split_summary_lines( $summary )
			: ayudawp_aiss_split_sentences( $summary );
		if ( empty( $items ) ) {
			$items = array( $summary );
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
						<?php if ( $bullets_mode ) : ?>
							<ul class="ayudawp-aiss-summary-list">
								<?php foreach ( $items as $item ) : ?>
									<li><?php echo esc_html( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<?php foreach ( $items as $item ) : ?>
								<p><?php echo esc_html( $item ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>
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
		$options = ayudawp_aiss_get_summary_options();

		if ( empty( $options['ai_summary_frontend_button'] ) ) {
			return '';
		}

		if ( ayudawp_aiss_is_summary_excluded( $post_id ) ) {
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

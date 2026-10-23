<?php
/**
 * Buttons generation class for Share Buttons & AI-powered Summaries plugin
 *
 * @package AiShareSummarize
 * @since 1.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle button generation and configuration
 */
class AyudaWP_AISS_Buttons {

	/**
	 * Sequence used to build unique ids for aria-labelledby / aria-controls
	 *
	 * @since 2.5.0
	 * @var int
	 */
	private static $uid = 0;

	/**
	 * Generate complete buttons HTML
	 *
	 * @param array  $enabled_buttons List of enabled buttons.
	 * @param array  $options         Plugin options.
	 * @param string $url             Current URL.
	 * @param string $title           Current title.
	 * @param int    $post_id         Post the block belongs to (v2.6.0). Defaults
	 *                                to the global post; a theme action passes it
	 *                                because the global may be another post.
	 * @return string Complete buttons HTML.
	 */
	public static function ayudawp_generate_buttons_html( $enabled_buttons = null, $options = null, $url = null, $title = null, $post_id = null ) {
		if ( null === $options ) {
			$options = get_option( 'ayudawp_aiss_options' );
		}

		if ( null === $enabled_buttons ) {
			$enabled_buttons = isset( $options['enabled_buttons'] ) ? $options['enabled_buttons'] : array();
		}

		if ( empty( $enabled_buttons ) || ! is_array( $enabled_buttons ) ) {
			return '';
		}

		if ( null === $url ) {
			$url = get_permalink();
		}

		if ( null === $title ) {
			$title = get_the_title();
		}

		$button_order    = isset( $options['button_order'] ) ? $options['button_order'] : 'ayudawp_ai_first';
		$button_alignment = isset( $options['button_alignment'] ) ? $options['button_alignment'] : 'left';
		$title_text      = isset( $options['title_text'] ) ? $options['title_text'] : '';
		$title_style     = ayudawp_aiss_sanitize_title_style( isset( $options['title_style'] ) ? $options['title_style'] : 'span' );
		// 'minimal' is what activation writes, and what the settings screen
		// shows as selected when the key is missing; the renderer used to fall
		// back to 'brand', so those two disagreed.
		$color_style     = isset( $options['button_colors'] ) ? $options['button_colors'] : 'minimal';
		$show_icons      = isset( $options['show_icons'] ) ? $options['show_icons'] : false;
		$icon_style      = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';
		$seo_button_type = isset( $options['seo_button_type'] ) ? $options['seo_button_type'] : 'link';
		$button_size     = isset( $options['button_size'] ) ? $options['button_size'] : 'normal';

		// Separator lines (v2.4.0): both on by default, the pre-2.4.0 look.
		$separator_top    = isset( $options['separator_top'] ) ? $options['separator_top'] : true;
		$separator_bottom = isset( $options['separator_bottom'] ) ? $options['separator_bottom'] : true;

		// Mobile compact mode (v2.5.0). Off by default: the pre-2.5.0 render.
		$compact_mode    = isset( $options['mobile_compact'] ) ? $options['mobile_compact'] : 'disabled';
		$compact_visible = isset( $options['mobile_compact_visible'] ) ? (int) $options['mobile_compact_visible'] : 3;
		$compact_visible = max( 1, min( 5, $compact_visible ) );
		if ( ! in_array( $compact_mode, array( 'single', 'partial' ), true ) ) {
			$compact_mode = 'disabled';
		}

		// Section titles (v1.6.0).
		$ai_section_title     = isset( $options['ai_section_title'] ) ? $options['ai_section_title'] : '';
		$social_section_title = isset( $options['social_section_title'] ) ? $options['social_section_title'] : '';

		// Check if section titles are overridden via shortcode.
		if ( isset( $options['_sc_ai_title'] ) ) {
			$ai_section_title = $options['_sc_ai_title'];
		}
		if ( isset( $options['_sc_social_title'] ) ) {
			$social_section_title = $options['_sc_social_title'];
		}

		// Custom colors (v1.6.0).
		$custom_bg   = isset( $options['custom_color_bg'] ) ? $options['custom_color_bg'] : '';
		$custom_text = isset( $options['custom_color_text'] ) ? $options['custom_color_text'] : '';

		$enabled_buttons = ayudawp_aiss_remove_unavailable_buttons( self::ayudawp_order_buttons( $enabled_buttons, $button_order, $options ) );

		/*
		 * Where the Google preferred source button goes (v2.6.0). Outside the
		 * block it only shows when a shortcode names it in its buttons list;
		 * in a section of its own it leaves the groups and is printed last.
		 * Alone in the block there is nothing to set it apart from, so it is
		 * printed as an ordinary single button.
		 */
		$google_place   = ayudawp_aiss_get_google_preferred_placement( $options );
		$google_section = false;
		if ( in_array( 'google_preferred', $enabled_buttons, true ) ) {
			if ( 'outside' === $google_place && empty( $options['_sc_explicit_buttons'] ) ) {
				$enabled_buttons = array_values( array_diff( $enabled_buttons, array( 'google_preferred' ) ) );
			} elseif ( 'section' === $google_place && count( $enabled_buttons ) > 1 ) {
				$enabled_buttons = array_values( array_diff( $enabled_buttons, array( 'google_preferred' ) ) );
				$google_section  = true;
			}
		}

		if ( empty( $enabled_buttons ) ) {
			return '';
		}

		/*
		 * Compact mode needs something to compact (v2.6.0). With a single
		 * button, "one Share button that opens the rest" hid that button
		 * behind a toggle that opened nothing else.
		 */
		if ( count( $enabled_buttons ) + ( $google_section ? 1 : 0 ) < 2 ) {
			$compact_mode = 'disabled';
		}

		// Determine if we use section titles (override global title).
		// Mixed order always uses the general title since groups are interleaved.
		$use_section_titles = ( ! empty( $ai_section_title ) || ! empty( $social_section_title ) ) && 'ayudawp_mixed' !== $button_order;

		// Determine CSS classes.
		$container_classes = array( 'ayudawp-share-buttons' );

		if ( 'center' === $button_alignment ) {
			$container_classes[] = 'ayudawp-aiss-centered';
		}

		if ( ! $separator_top ) {
			$container_classes[] = 'no-separator-top';
		}
		if ( ! $separator_bottom ) {
			$container_classes[] = 'no-separator-bottom';
		}

		// Size class.
		if ( 'normal' !== $button_size ) {
			$container_classes[] = 'size-' . $button_size;
		}

		if ( 'icons-only' === $color_style ) {
			$container_classes[] = 'icons-only';
			$container_classes[] = $icon_style;
			$show_icons = true;
		} elseif ( 'minimal' !== $color_style ) {
			$container_classes[] = $color_style;
		}

		if ( $show_icons && 'icons-only' !== $color_style ) {
			$container_classes[] = 'with-icons';
		}

		/*
		 * Mobile compact mode (v2.5.0). Everything is decided here in PHP and
		 * applied by CSS inside the mobile breakpoint, so the desktop render is
		 * byte-for-byte what it was and the extra markup costs one button. The
		 * JS only flips `is-expanded` and the aria state.
		 */
		if ( 'disabled' !== $compact_mode ) {
			$container_classes[] = 'is-mobile-compact';
			$container_classes[] = 'compact-' . $compact_mode;
		}

		$style_class = implode( ' ', $container_classes );

		self::$uid++;
		$block_id = 'ayudawp-aiss-share-' . self::$uid;

		// Custom color inline style.
		$custom_style = '';
		if ( 'custom' === $color_style && ! empty( $custom_bg ) ) {
			$custom_style = sprintf(
				' style="--ayudawp-custom-bg:%s;--ayudawp-custom-text:%s;"',
				esc_attr( $custom_bg ),
				esc_attr( ! empty( $custom_text ) ? $custom_text : '#ffffff' )
			);
		}

		/*
		 * data-post-id (v2.5.0) so click tracking knows which post it is
		 * counting without guessing from the theme's `article id="post-N"`,
		 * which only some themes emit.
		 */
		$block_post_id = null === $post_id ? get_the_ID() : (int) $post_id;
		$post_id_attr  = $block_post_id ? ' data-post-id="' . esc_attr( (string) $block_post_id ) . '"' : '';

		$html = '<div id="' . esc_attr( $block_id ) . '" class="' . esc_attr( $style_class ) . '"' . $post_id_attr . $custom_style . '>';

		// Global title (only if no section titles). Carries an id so the
		// buttons group can point at it with aria-labelledby (v2.5.0).
		$global_title_id = '';
		if ( ! $use_section_titles && ! empty( trim( $title_text ) ) ) {
			$global_title_id = $block_id . '-title';
			$html           .= ayudawp_aiss_open_tag( $title_style, 'ayudawp-title', $global_title_id ) . esc_html( $title_text ) . ayudawp_aiss_close_tag( $title_style );
		}

		// Running index across every group, so "N visible + more" counts
		// buttons in render order and not per section.
		$rendered = 0;

		// Split buttons into groups for section titles.
		if ( $use_section_titles ) {
			$social_buttons_list = ayudawp_aiss_get_social_buttons();
			$ai_buttons_list     = ayudawp_aiss_get_ai_buttons();

			$social_group = array_values( array_intersect( $enabled_buttons, $social_buttons_list ) );
			$ai_group     = array_values( array_intersect( $enabled_buttons, $ai_buttons_list ) );

			// Determine display order (mixed never reaches here due to $use_section_titles check).
			$groups = array();
			if ( 'ayudawp_ai_first' === $button_order ) {
				if ( ! empty( $ai_group ) ) {
					$groups[] = array( 'title' => $ai_section_title, 'buttons' => $ai_group, 'kind' => 'ai' );
				}
				if ( ! empty( $social_group ) ) {
					$groups[] = array( 'title' => $social_section_title, 'buttons' => $social_group, 'kind' => 'social' );
				}
			} else {
				if ( ! empty( $social_group ) ) {
					$groups[] = array( 'title' => $social_section_title, 'buttons' => $social_group, 'kind' => 'social' );
				}
				if ( ! empty( $ai_group ) ) {
					$groups[] = array( 'title' => $ai_section_title, 'buttons' => $ai_group, 'kind' => 'ai' );
				}
			}

			foreach ( $groups as $index => $group ) {
				$group_id = $block_id . '-group-' . $index;
				$title_id = '';

				/*
				 * A section whose every button falls past the visible count is
				 * hidden along with its title, so the compact render never
				 * leaves a heading standing over nothing.
				 */
				$group_hidden = ( 'partial' === $compact_mode && $rendered >= $compact_visible );

				if ( ! empty( $group['title'] ) ) {
					$title_id      = $group_id . '-title';
					$section_class = $index > 0 ? ' ayudawp-section-title' : '';
					if ( $group_hidden ) {
						$section_class .= ' aiss-overflow';
					}
					$html .= ayudawp_aiss_open_tag( $title_style, 'ayudawp-title' . $section_class, $title_id ) . esc_html( $group['title'] ) . ayudawp_aiss_close_tag( $title_style );
				} elseif ( $index > 0 ) {
					$html .= '<div class="ayudawp-section-separator' . ( $group_hidden ? ' aiss-overflow' : '' ) . '"></div>';
				}

				$html .= '<div id="' . esc_attr( $group_id ) . '" class="ayudawp-buttons-container" role="group"'
					. self::ayudawp_group_label_attr( $title_id, $group['kind'] ) . '>';
				foreach ( $group['buttons'] as $button ) {
					$overflow = ( 'partial' === $compact_mode && $rendered >= $compact_visible );
					$html    .= self::ayudawp_get_button_html( $button, $url, $title, $options, $show_icons, $color_style, $seo_button_type, $overflow );
					$rendered++;
				}
				$html .= '</div>';
			}
		} else {
			$html .= '<div id="' . esc_attr( $block_id ) . '-group" class="ayudawp-buttons-container" role="group"'
				. self::ayudawp_group_label_attr( $global_title_id, 'mixed' ) . '>';
			foreach ( $enabled_buttons as $button ) {
				$overflow = ( 'partial' === $compact_mode && $rendered >= $compact_visible );
				$html    .= self::ayudawp_get_button_html( $button, $url, $title, $options, $show_icons, $color_style, $seo_button_type, $overflow );
				$rendered++;
			}
			$html .= '</div>';
		}

		// Google preferred source in a section of its own, last (v2.6.0).
		if ( $google_section ) {
			$group_id     = $block_id . '-google';
			$title_id     = '';
			$group_hidden = ( 'partial' === $compact_mode && $rendered >= $compact_visible );
			$google_title = isset( $options['_sc_google_title'] )
				? $options['_sc_google_title']
				: ( isset( $options['google_section_title'] ) ? $options['google_section_title'] : '' );

			if ( '' !== trim( (string) $google_title ) ) {
				$title_id = $group_id . '-title';
				$html    .= ayudawp_aiss_open_tag( $title_style, 'ayudawp-title ayudawp-section-title' . ( $group_hidden ? ' aiss-overflow' : '' ), $title_id ) . esc_html( $google_title ) . ayudawp_aiss_close_tag( $title_style );
			} else {
				// A visible line, unlike the spacer between the other groups:
				// without a title it is what sets the section apart.
				$html .= '<div class="ayudawp-section-separator ayudawp-google-separator' . ( $group_hidden ? ' aiss-overflow' : '' ) . '"></div>';
			}

			$html .= '<div id="' . esc_attr( $group_id ) . '" class="ayudawp-buttons-container ayudawp-google-section" role="group"'
				. self::ayudawp_group_label_attr( $title_id, 'google' ) . '>';
			$html .= self::ayudawp_get_button_html( 'google_preferred', $url, $title, $options, $show_icons, $color_style, $seo_button_type, $group_hidden );
			$html .= '</div>';
			$rendered++;
		}

		// The compact toggle is rendered whenever the mode is on, but CSS only
		// shows it inside the mobile breakpoint — and, in "N visible" mode,
		// only when there is something hidden behind it.
		if ( 'disabled' !== $compact_mode && ( 'single' === $compact_mode || $rendered > $compact_visible ) ) {
			$html .= self::ayudawp_compact_toggle_html( $compact_mode, $block_id );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Accessible name for a buttons group (v2.5.0)
	 *
	 * Points at the section title when there is one, so screen readers announce
	 * the group with the same words sighted visitors read. Without a title the
	 * group still needs a name, or `role="group"` announces an unnamed group.
	 *
	 * @since 2.5.0
	 * @param string $title_id Id of the title element, or '' when there is none.
	 * @param string $kind     'ai', 'social', 'mixed' or 'google' (v2.6.0).
	 * @return string Attribute string, starting with a space.
	 */
	private static function ayudawp_group_label_attr( $title_id, $kind ) {
		if ( '' !== $title_id ) {
			return ' aria-labelledby="' . esc_attr( $title_id ) . '"';
		}

		if ( 'ai' === $kind ) {
			$label = __( 'Ask an AI assistant about this content', 'ai-share-summarize' );
		} elseif ( 'social' === $kind ) {
			$label = __( 'Share this content on social networks', 'ai-share-summarize' );
		} elseif ( 'google' === $kind ) {
			$label = __( 'Google preferred source', 'ai-share-summarize' );
		} else {
			$label = __( 'Share this content', 'ai-share-summarize' );
		}

		return ' aria-label="' . esc_attr( $label ) . '"';
	}

	/**
	 * Toggle that expands the compact mobile render (v2.5.0)
	 *
	 * Server-rendered so it works with page caching and needs no JS to exist;
	 * the script only toggles `is-expanded` on the block and keeps
	 * `aria-expanded` in sync. Hidden on desktop by CSS.
	 *
	 * @since 2.5.0
	 * @param string $mode     'single' or 'partial'.
	 * @param string $block_id Id of the share block it controls.
	 * @return string Toggle HTML.
	 */
	private static function ayudawp_compact_toggle_html( $mode, $block_id ) {
		$label = 'single' === $mode
			? __( 'Share', 'ai-share-summarize' )
			: __( 'More', 'ai-share-summarize' );

		/*
		 * No icon of its own: the caret comes from CSS, the same one the summary
		 * block uses to say "there is more behind this", and it rotates when the
		 * block opens. A share glyph here was indistinguishable from the native
		 * Share button, and an ellipsis was one more symbol for a gesture the
		 * interface already had a sign for.
		 */
		return '<div class="ayudawp-aiss-compact-toggle">'
			. '<button type="button" class="ayudawp-share-btn ayudawp-aiss-more-btn" aria-expanded="false" aria-controls="' . esc_attr( $block_id ) . '">'
			. '<span class="ayudawp-button-text">' . esc_html( $label ) . '</span>'
			. '</button></div>';
	}

	/**
	 * Generate individual button HTML
	 *
	 * @param string $button          Button type.
	 * @param string $url             Current URL.
	 * @param string $title           Current title.
	 * @param array  $options         Plugin options.
	 * @param bool   $show_icons      Whether to show icons.
	 * @param string $color_style     Color style.
	 * @param string $seo_button_type Button HTML type ('link' or 'button').
	 * @param bool   $overflow        Whether this button hides behind the mobile
	 *                                compact toggle (v2.5.0).
	 * @return string Button HTML.
	 */
	public static function ayudawp_get_button_html( $button, $url, $title, $options, $show_icons = false, $color_style = 'default', $seo_button_type = 'link', $overflow = false ) {
		$twitter_handle    = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
		$custom_text       = isset( $options['custom_text'] ) ? $options['custom_text'] : '';
		$prompt            = isset( $options['default_prompt'] ) ? $options['default_prompt'] : ayudawp_aiss_get_default_prompt();
		$mastodon_instance = isset( $options['mastodon_instance'] ) ? $options['mastodon_instance'] : 'mastodon.social';

		/*
		 * UTM tagging (v2.5.0) applies to the site's own URL, the one that
		 * travels to the destination, and is built per platform because
		 * utm_source is the platform key. Off by default, in which case this
		 * returns $url untouched.
		 */
		$url = ayudawp_aiss_add_utm( $url, $button, $options );

		$full_prompt = $prompt;
		if ( ! empty( $custom_text ) ) {
			$full_prompt .= ' ' . $custom_text;
		}
		$full_prompt .= ' Source: ' . $url;

		// Ensure mastodon instance has a fallback.
		if ( empty( $mastodon_instance ) ) {
			$mastodon_instance = 'mastodon.social';
		}

		$buttons_config = array(
			// Social platforms.
			'twitter'   => array(
				'label' => 'X (Twitter)',
				'url'   => 'https://twitter.com/intent/tweet?text=' . urlencode( $title . ' ' . $twitter_handle ) . '&url=' . urlencode( $url ),
				'class' => 'twitter',
			),
			'linkedin'  => array(
				'label' => 'LinkedIn',
				'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode( $url ),
				'class' => 'linkedin',
			),
			'facebook'  => array(
				'label' => 'Facebook',
				'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $url ),
				'class' => 'facebook',
			),
			'telegram'  => array(
				'label' => 'Telegram',
				'url'   => 'https://t.me/share/url?url=' . urlencode( $url ) . '&text=' . urlencode( $title ),
				'class' => 'telegram',
			),
			'whatsapp'  => array(
				'label' => 'WhatsApp',
				'url'   => 'https://api.whatsapp.com/send?text=' . urlencode( $title . ' ' . $url ),
				'class' => 'whatsapp',
			),
			'email'     => array(
				'label' => 'Email',
				'url'   => 'mailto:?subject=' . urlencode( $title ) . '&body=' . urlencode( $url ),
				'class' => 'email',
			),
			'raindrop'  => array(
				'label' => 'Raindrop',
				'url'   => 'https://app.raindrop.io/add?link=' . urlencode( $url ) . '&title=' . urlencode( $title ),
				'class' => 'raindrop',
			),
			'reddit'    => array(
				'label' => 'Reddit',
				'url'   => 'https://www.reddit.com/submit?url=' . urlencode( $url ) . '&title=' . urlencode( $title ),
				'class' => 'reddit',
			),
			'bluesky'   => array(
				'label' => 'Bluesky',
				'url'   => 'https://bsky.app/intent/compose?text=' . urlencode( $title . ' ' . $url ),
				'class' => 'bluesky',
			),
			'line'      => array(
				'label' => 'LINE',
				'url'   => 'https://social-plugins.line.me/lineit/share?url=' . urlencode( $url ),
				'class' => 'line',
			),
			'mastodon'  => array(
				'label' => 'Mastodon',
				'url'   => 'https://' . $mastodon_instance . '/share?text=' . urlencode( $title . ' ' . $url ),
				'class' => 'mastodon',
			),
			'threads'   => array(
				'label' => 'Threads',
				'url'   => 'https://www.threads.net/intent/post?text=' . urlencode( $title . ' ' . $url ),
				'class' => 'threads',
			),
			'pinterest' => array(
				'label' => 'Pinterest',
				'url'   => 'https://pinterest.com/pin/create/button/?url=' . urlencode( $url ) . '&description=' . urlencode( $title ),
				'class' => 'pinterest',
			),

			// Browser actions (v2.5.0). Their "url" is the page itself: the
			// script copies it to the clipboard or hands it to the operating
			// system share sheet. They are always rendered as <button>.
			'copy_link' => array(
				'label' => __( 'Copy link', 'ai-share-summarize' ),
				'url'   => $url,
				'class' => 'copy-link',
			),
			'web_share' => array(
				'label' => __( 'Share', 'ai-share-summarize' ),
				'url'   => $url,
				'class' => 'web-share',
			),

			// Google preferred source (v2.6.0). Points at the site's domain,
			// not at the post, so the UTM-tagged $url plays no part in it.
			'google_preferred' => array(
				'label' => __( 'Preferred source', 'ai-share-summarize' ),
				'url'   => 'https://www.google.com/preferences/source?q=' . rawurlencode( ayudawp_aiss_get_google_preferred_domain() ),
				'class' => 'google-preferred',
			),

			// AI platforms.
			'claude'    => array(
				'label' => 'Claude',
				'url'   => 'https://claude.ai/new?q=' . urlencode( $full_prompt ),
				'class' => 'claude ai',
			),
			'chatgpt'   => array(
				'label' => 'ChatGPT',
				'url'   => 'https://chatgpt.com/?q=' . urlencode( $full_prompt ),
				'class' => 'chatgpt ai',
			),
			'google_ai' => array(
				'label' => 'Google AI',
				'url'   => 'https://www.google.com/search?udm=50&aep=11&q=' . urlencode( $full_prompt ),
				'class' => 'google-ai ai',
			),
			'gemini'    => array(
				'label' => 'Gemini',
				'url'   => 'https://gemini.google.com/app?prompt=' . urlencode( $full_prompt ),
				'class' => 'gemini ai',
			),
			'grok'      => array(
				'label' => 'Grok',
				'url'   => 'https://grok.com/?q=' . urlencode( $full_prompt ),
				'class' => 'grok ai',
			),
			'perplexity' => array(
				'label' => 'Perplexity',
				'url'   => 'https://www.perplexity.ai/?q=' . urlencode( $full_prompt ),
				'class' => 'perplexity ai',
			),
			// DeepSeek: Uses prompt parameter for copy-to-clipboard behavior.
			'deepseek'  => array(
				'label' => 'DeepSeek',
				'url'   => 'https://chat.deepseek.com/?prompt=' . urlencode( $full_prompt ),
				'class' => 'deepseek ai',
			),
			'mistral'   => array(
				'label' => 'Mistral',
				'url'   => 'https://chat.mistral.ai/chat?q=' . urlencode( $full_prompt ),
				'class' => 'mistral ai',
			),
			// Copilot: Uses prompt parameter for copy-to-clipboard behavior.
			'copilot'   => array(
				'label' => 'Copilot',
				'url'   => 'https://copilot.microsoft.com/?prompt=' . urlencode( $full_prompt ),
				'class' => 'copilot ai',
			),
			'qwen'      => array(
				'label' => 'Qwen',
				'url'   => 'https://chat.qwen.ai/?text=' . urlencode( $full_prompt ),
				'class' => 'qwen ai',
			),
			'meta_ai'   => array(
				'label' => 'Meta AI',
				'url'   => 'https://www.meta.ai/?prompt=' . urlencode( $full_prompt ),
				'class' => 'meta-ai ai',
			),
		);

		if ( ! isset( $buttons_config[ $button ] ) ) {
			return '';
		}

		// A caller that skipped ayudawp_aiss_remove_unavailable_buttons() still
		// gets no link to an empty search.
		if ( 'google_preferred' === $button && '' === ayudawp_aiss_get_google_preferred_domain() ) {
			return '';
		}

		$config = $buttons_config[ $button ];

		// Get icon if needed.
		$icon_html = '';
		if ( $show_icons ) {
			$icon_size = ( 'icons-only' === $color_style ) ? 20 : 16;
			$icon_html = AyudaWP_AISS_Icons::ayudawp_get_button_icon( $button, $icon_size );
		}

		// Build button content.
		$button_content = $icon_html;
		if ( 'icons-only' !== $color_style ) {
			if ( ! empty( $icon_html ) ) {
				$button_content .= '<span class="ayudawp-button-text">' . esc_html( $config['label'] ) . '</span>';
			} else {
				$button_content = esc_html( $config['label'] );
			}
		}

		$classes = $config['class'];
		if ( $overflow ) {
			// Marked in PHP, hidden by CSS inside the mobile breakpoint only.
			$classes .= ' aiss-overflow';
		}

		$is_action = in_array( $button, ayudawp_aiss_get_action_buttons(), true );

		if ( 'copy_link' === $button ) {
			$aria = __( 'Copy link to clipboard', 'ai-share-summarize' );
		} elseif ( 'web_share' === $button ) {
			$aria = __( 'Share using your device', 'ai-share-summarize' );
		} elseif ( 'google_preferred' === $button ) {
			// Not a share: "Share in Preferred source" would mislead.
			$aria = __( 'Add this site as a preferred source on Google', 'ai-share-summarize' );
		} else {
			/* translators: %s is the button label */
			$aria = sprintf( __( 'Share in %s', 'ai-share-summarize' ), $config['label'] );
		}

		/*
		 * Native share is hidden until the script confirms navigator.share
		 * exists, so desktop Firefox (and anything else without the API) never
		 * shows a button that would do nothing. Rendering it hidden rather than
		 * omitting it keeps the markup identical for every visitor, which is
		 * what page caches store.
		 */
		if ( 'web_share' === $button ) {
			$classes .= ' ayudawp-aiss-needs-share-api';
		}

		// Actions have no destination to follow, so they are buttons whatever
		// the global "Button HTML type" setting says: an <a href> pointing at
		// the current page would add a self-link to every post.
		if ( $is_action || 'button' === $seo_button_type ) {
			return sprintf(
				'<button type="button" class="ayudawp-share-btn %s"%s data-url="%s" data-platform="%s"%s aria-label="%s">%s</button>',
				esc_attr( $classes ),
				'web_share' === $button ? ' hidden' : '',
				esc_attr( $config['url'] ),
				esc_attr( $button ),
				'web_share' === $button ? ' data-title="' . esc_attr( $title ) . '"' : '',
				esc_attr( $aria ),
				$button_content
			);
		}

		return sprintf(
			'<a href="%s" class="ayudawp-share-btn %s" data-url="%s" data-platform="%s" target="_blank" rel="nofollow noopener" aria-label="%s">%s</a>',
			esc_url( $config['url'] ),
			esc_attr( $classes ),
			esc_attr( $config['url'] ),
			esc_attr( $button ),
			esc_attr( $aria ),
			$button_content
		);
	}

	/**
	 * Order buttons according to configuration
	 *
	 * Supports custom manual order (drag & drop) and preset orders.
	 *
	 * @param array  $buttons List of buttons.
	 * @param string $order   Order type.
	 * @param array  $options Plugin options for custom order.
	 * @return array Ordered buttons.
	 */
	public static function ayudawp_order_buttons( $buttons, $order, $options = array() ) {
		$social_buttons = ayudawp_aiss_get_social_buttons();
		$ai_buttons     = ayudawp_aiss_get_ai_buttons();

		// Get per-group custom orders (v1.6.0).
		$custom_ai     = isset( $options['button_custom_order_ai'] ) ? $options['button_custom_order_ai'] : array();
		$custom_social = isset( $options['button_custom_order_social'] ) ? $options['button_custom_order_social'] : array();

		switch ( $order ) {
			case 'ayudawp_ai_first':
				$ai     = self::ayudawp_apply_group_order( $buttons, $ai_buttons, $custom_ai );
				$social = self::ayudawp_apply_group_order( $buttons, $social_buttons, $custom_social );
				return array_merge( $ai, $social );

			case 'ayudawp_mixed':
				$social     = array_values( self::ayudawp_apply_group_order( $buttons, $social_buttons, $custom_social ) );
				$ai         = array_values( self::ayudawp_apply_group_order( $buttons, $ai_buttons, $custom_ai ) );
				$mixed      = array();
				$max_length = max( count( $social ), count( $ai ) );

				for ( $i = 0; $i < $max_length; $i++ ) {
					if ( isset( $social[ $i ] ) ) {
						$mixed[] = $social[ $i ];
					}
					if ( isset( $ai[ $i ] ) ) {
						$mixed[] = $ai[ $i ];
					}
				}
				return $mixed;

			case 'ayudawp_social_first':
			default:
				$social = self::ayudawp_apply_group_order( $buttons, $social_buttons, $custom_social );
				$ai     = self::ayudawp_apply_group_order( $buttons, $ai_buttons, $custom_ai );
				return array_merge( $social, $ai );
		}
	}

	/**
	 * Apply custom internal order within a button group
	 *
	 * @param array $enabled      All enabled buttons.
	 * @param array $group_keys   Keys belonging to this group.
	 * @param array $custom_order Saved custom order for this group.
	 * @return array Ordered buttons for this group.
	 */
	private static function ayudawp_apply_group_order( $enabled, $group_keys, $custom_order ) {
		$group = array_values( array_intersect( $enabled, $group_keys ) );

		if ( empty( $custom_order ) ) {
			return $group;
		}

		// Custom order first, then any remaining.
		$ordered = array();
		foreach ( $custom_order as $btn ) {
			if ( in_array( $btn, $group, true ) ) {
				$ordered[] = $btn;
			}
		}
		foreach ( $group as $btn ) {
			if ( ! in_array( $btn, $ordered, true ) ) {
				$ordered[] = $btn;
			}
		}
		return $ordered;
	}
}
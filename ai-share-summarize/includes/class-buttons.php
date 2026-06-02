<?php
/**
 * Buttons generation class for AI Share & Summarize plugin
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
	 * Generate complete buttons HTML
	 *
	 * @param array  $enabled_buttons List of enabled buttons.
	 * @param array  $options         Plugin options.
	 * @param string $url             Current URL.
	 * @param string $title           Current title.
	 * @return string Complete buttons HTML.
	 */
	public static function ayudawp_generate_buttons_html( $enabled_buttons = null, $options = null, $url = null, $title = null ) {
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
		$color_style     = isset( $options['button_colors'] ) ? $options['button_colors'] : 'brand';
		$show_icons      = isset( $options['show_icons'] ) ? $options['show_icons'] : false;
		$icon_style      = isset( $options['icon_style'] ) ? $options['icon_style'] : 'circular';
		$seo_button_type = isset( $options['seo_button_type'] ) ? $options['seo_button_type'] : 'link';
		$button_size     = isset( $options['button_size'] ) ? $options['button_size'] : 'normal';

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

		$enabled_buttons = self::ayudawp_order_buttons( $enabled_buttons, $button_order, $options );

		// Determine if we use section titles (override global title).
		// Mixed order always uses the general title since groups are interleaved.
		$use_section_titles = ( ! empty( $ai_section_title ) || ! empty( $social_section_title ) ) && 'ayudawp_mixed' !== $button_order;

		// Determine CSS classes.
		$container_classes = array( 'ayudawp-share-buttons' );

		if ( 'center' === $button_alignment ) {
			$container_classes[] = 'ayudawp-aiss-centered';
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

		$style_class = implode( ' ', $container_classes );

		// Custom color inline style.
		$custom_style = '';
		if ( 'custom' === $color_style && ! empty( $custom_bg ) ) {
			$custom_style = sprintf(
				' style="--ayudawp-custom-bg:%s;--ayudawp-custom-text:%s;"',
				esc_attr( $custom_bg ),
				esc_attr( ! empty( $custom_text ) ? $custom_text : '#ffffff' )
			);
		}

		$html = '<div class="' . esc_attr( $style_class ) . '"' . $custom_style . '>';

		// Global title (only if no section titles).
		if ( ! $use_section_titles && ! empty( trim( $title_text ) ) ) {
			$html .= '<' . esc_attr( $title_style ) . ' class="ayudawp-title">' . esc_html( $title_text ) . '</' . esc_attr( $title_style ) . '>';
		}

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
					$groups[] = array( 'title' => $ai_section_title, 'buttons' => $ai_group );
				}
				if ( ! empty( $social_group ) ) {
					$groups[] = array( 'title' => $social_section_title, 'buttons' => $social_group );
				}
			} else {
				if ( ! empty( $social_group ) ) {
					$groups[] = array( 'title' => $social_section_title, 'buttons' => $social_group );
				}
				if ( ! empty( $ai_group ) ) {
					$groups[] = array( 'title' => $ai_section_title, 'buttons' => $ai_group );
				}
			}

			foreach ( $groups as $index => $group ) {
				if ( ! empty( $group['title'] ) ) {
					$section_class = $index > 0 ? ' ayudawp-section-title' : '';
					$html .= '<' . esc_attr( $title_style ) . ' class="ayudawp-title' . esc_attr( $section_class ) . '">' . esc_html( $group['title'] ) . '</' . esc_attr( $title_style ) . '>';
				} elseif ( $index > 0 ) {
					$html .= '<div class="ayudawp-section-separator"></div>';
				}

				$html .= '<div class="ayudawp-buttons-container">';
				foreach ( $group['buttons'] as $button ) {
					$html .= self::ayudawp_get_button_html( $button, $url, $title, $options, $show_icons, $color_style, $seo_button_type );
				}
				$html .= '</div>';
			}
		} else {
			$html .= '<div class="ayudawp-buttons-container">';
			foreach ( $enabled_buttons as $button ) {
				$html .= self::ayudawp_get_button_html( $button, $url, $title, $options, $show_icons, $color_style, $seo_button_type );
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
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
	 * @return string Button HTML.
	 */
	public static function ayudawp_get_button_html( $button, $url, $title, $options, $show_icons = false, $color_style = 'default', $seo_button_type = 'link' ) {
		$twitter_handle    = isset( $options['twitter_handle'] ) ? $options['twitter_handle'] : '';
		$custom_text       = isset( $options['custom_text'] ) ? $options['custom_text'] : '';
		$prompt            = isset( $options['default_prompt'] ) ? $options['default_prompt'] : ayudawp_aiss_get_default_prompt();
		$mastodon_instance = isset( $options['mastodon_instance'] ) ? $options['mastodon_instance'] : 'mastodon.social';

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
				'url'   => 'https://chat.qwen.ai/?prompt=' . urlencode( $full_prompt ),
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

		// Generate HTML based on seo_button_type.
		if ( 'button' === $seo_button_type ) {
			return sprintf(
				'<button type="button" class="ayudawp-share-btn %s" data-url="%s" data-platform="%s" aria-label="%s">%s</button>',
				esc_attr( $config['class'] ),
				esc_attr( $config['url'] ),
				esc_attr( $button ),
				/* translators: %s is the button label */
				esc_attr( sprintf( __( 'Share in %s', 'ai-share-summarize' ), $config['label'] ) ),
				$button_content
			);
		} else {
			return sprintf(
				'<a href="%s" class="ayudawp-share-btn %s" data-url="%s" data-platform="%s" target="_blank" rel="nofollow noopener" aria-label="%s">%s</a>',
				esc_url( $config['url'] ),
				esc_attr( $config['class'] ),
				esc_attr( $config['url'] ),
				esc_attr( $button ),
				/* translators: %s is the button label */
				esc_attr( sprintf( __( 'Share in %s', 'ai-share-summarize' ), $config['label'] ) ),
				$button_content
			);
		}
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
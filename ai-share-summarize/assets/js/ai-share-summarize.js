/**
 * AI Share & Summarize Plugin - JavaScript
 * Advanced functionality for tooltips, hover effects, responsive behavior, click tracking
 * and the visitor-facing AI summary generation button (v2.0.0).
 * Version: 2.0.0
 */

/**
 * Optional dark-background detection.
 *
 * Active only when the admin selects the "auto" dark-mode adaptation mode.
 * Walks up from each share-buttons container looking for the first opaque
 * background, computes its WCAG relative luminance, and toggles the
 * .is-on-dark class accordingly. Runs once on DOMContentLoaded and again
 * when the OS-level prefers-color-scheme flips (themes that wire dark mode
 * to that media query change their background at that moment, so we need
 * to re-measure). No MutationObserver is installed to keep runtime cost
 * negligible.
 */
(function() {
    if ( ! window.ayudawpAissL10n || 'auto' !== window.ayudawpAissL10n.darkAdaptMode ) {
        return;
    }

    function detect() {
        var nodes = document.querySelectorAll(
            '.ayudawp-share-buttons:not(.dark):not(.custom), .ayudawp-aiss-summary'
        );
        for ( var i = 0; i < nodes.length; i++ ) {
            var node = nodes[ i ].parentElement;
            var bg   = '';
            while ( node && node !== document.documentElement ) {
                var computed = window.getComputedStyle( node ).backgroundColor;
                if ( computed && 'rgba(0, 0, 0, 0)' !== computed && 'transparent' !== computed ) {
                    bg = computed;
                    break;
                }
                node = node.parentElement;
            }
            if ( ! bg ) {
                continue;
            }
            var match = bg.match( /\d+(\.\d+)?/g );
            if ( ! match || match.length < 3 ) {
                continue;
            }
            var r = parseFloat( match[ 0 ] ) / 255;
            var g = parseFloat( match[ 1 ] ) / 255;
            var b = parseFloat( match[ 2 ] ) / 255;
            var lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
            nodes[ i ].classList.toggle( 'is-on-dark', lum < 0.5 );
        }
    }

    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', detect );
    } else {
        detect();
    }

    if ( window.matchMedia ) {
        var mq = window.matchMedia( '(prefers-color-scheme: dark)' );
        if ( mq.addEventListener ) {
            mq.addEventListener( 'change', detect );
        } else if ( mq.addListener ) {
            mq.addListener( detect );
        }
    }
})();

jQuery(document).ready(function($) {

    /**
     * Platforms that require copy-to-clipboard behavior
     * These platforms don't support direct URL parameters for prompts
     */
    var copyPromptPlatforms = ['gemini', 'deepseek', 'copilot', 'qwen', 'meta_ai'];

    /**
     * Platform base URLs for copy-to-clipboard platforms
     */
    var platformBaseUrls = {
        'gemini': 'https://gemini.google.com/app',
        'deepseek': 'https://chat.deepseek.com/',
        'copilot': 'https://copilot.microsoft.com/',
        'qwen': 'https://chat.qwen.ai/',
        'meta_ai': 'https://www.meta.ai/'
    };

    /**
     * Universal tooltip system for all buttons
     */
    function attachUniversalTooltips() {
        $('.ayudawp-tooltip').remove();

        $('.ayudawp-share-btn').each(function() {
            var $button = $(this);
            var platform = detectPlatform($button);
            var tooltipText = '';

            if (copyPromptPlatforms.indexOf(platform) !== -1) {
                var isIconsOnly = $button.closest('.ayudawp-share-buttons').hasClass('icons-only');
                var platformName = '';

                if (platform === 'gemini') {
                    platformName = 'Gemini';
                } else if (platform === 'deepseek') {
                    platformName = 'DeepSeek';
                } else if (platform === 'copilot') {
                    platformName = 'Copilot';
                } else if (platform === 'qwen') {
                    platformName = 'Qwen';
                } else if (platform === 'meta_ai' || platform === 'meta-ai') {
                    platformName = 'Meta AI';
                }

                if (isIconsOnly) {
                    tooltipText = (window.ayudawpAissL10n && window.ayudawpAissL10n[platform + 'TooltipShort']) ||
                                  (window.ayudawpAissL10n && window.ayudawpAissL10n.copyPromptShort) ||
                                  'Copy prompt & open';
                } else {
                    tooltipText = (window.ayudawpAissL10n && window.ayudawpAissL10n[platform + 'TooltipLong']) ||
                                  (window.ayudawpAissL10n && window.ayudawpAissL10n.copyPromptLong) ||
                                  ('Copy prompt and open ' + platformName);
                }
            } else if (platform && window.ayudawpAissL10n && window.ayudawpAissL10n.platformNames) {
                tooltipText = window.ayudawpAissL10n.platformNames[platform] || platform;
            }

            if (tooltipText) {
                var $tooltip = $('<div class="ayudawp-tooltip">' + tooltipText + '</div>');
                $('body').append($tooltip);

                $button.on('mouseenter', function() {
                    var offset = $button.offset();
                    var buttonWidth = $button.outerWidth();
                    var tooltipWidth = $tooltip.outerWidth();
                    var tooltipHeight = $tooltip.outerHeight();

                    $tooltip.css({
                        top: offset.top - tooltipHeight - 8,
                        left: offset.left + (buttonWidth / 2) - (tooltipWidth / 2)
                    }).addClass('active');
                });

                $button.on('mouseleave', function() {
                    $tooltip.removeClass('active');
                });

                $button.data('tooltip', $tooltip);
            }
        });
    }

    /**
     * Detect platform from button data attribute or class
     *
     * @param {jQuery} $button Button element
     * @return {string} Platform identifier
     */
    function detectPlatform($button) {
        var platform = $button.attr('data-platform');

        if (!platform) {
            var classes = $button.attr('class').split(' ');
            var platforms = ['twitter', 'linkedin', 'facebook', 'telegram', 'whatsapp', 'email', 'raindrop',
                           'reddit', 'bluesky', 'line', 'mastodon', 'threads', 'pinterest',
                           'claude', 'chatgpt', 'google-ai', 'gemini',
                           'grok', 'perplexity', 'deepseek', 'mistral', 'copilot', 'qwen', 'meta-ai'];
            for (var i = 0; i < platforms.length; i++) {
                if (classes.indexOf(platforms[i]) !== -1) {
                    platform = platforms[i];
                    break;
                }
            }
        }

        return platform || '';
    }

    /**
     * Enhanced click handling with proper platform detection and analytics tracking
     */
    function attachClickHandlers() {
        $(document).off('click.ayudawp').on('click.ayudawp', '.ayudawp-share-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var url = $button.attr('data-url') || $button.attr('href');
            var platform = detectPlatform($button);

            if (!url) {
                return false;
            }

            // Track the click (fire and forget).
            trackClick(platform, $button);

            // Special handling for platforms that require copy-to-clipboard.
            if (copyPromptPlatforms.indexOf(platform) !== -1) {
                handleCopyPromptClick(url, platform);
                return false;
            }

            // Social media popups.
            if (['twitter', 'linkedin', 'facebook', 'telegram', 'reddit', 'bluesky'].indexOf(platform) !== -1) {
                var width = 600, height = 400;
                if (platform === 'reddit') {
                    width = 900;
                    height = 600;
                }
                var left = (screen.width / 2) - (width / 2);
                var top = (screen.height / 2) - (height / 2);

                window.open(url, 'share', 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
                return false;
            }

            // Default behavior for all other buttons.
            window.open(url, '_blank', 'noopener,noreferrer');
            return false;
        });
    }

    /**
     * Track button click via REST API (fire and forget)
     * No nonce needed: public non-destructive endpoint, compatible with page caching.
     *
     * @param {string} platform Platform identifier
     * @param {jQuery} $button The clicked button element
     */
    function trackClick(platform, $button) {
        if (!window.ayudawpAissL10n || !window.ayudawpAissL10n.trackUrl) {
            return;
        }

        // Get post ID from the closest container or page body.
        var postId = $button.closest('[data-post-id]').attr('data-post-id') ||
                     $('article[id^="post-"]').first().attr('id');

        if (postId && typeof postId === 'string') {
            postId = postId.replace('post-', '');
        }

        // Normalize platform names (google-ai -> google_ai).
        var normalizedPlatform = platform ? platform.replace(/-/g, '_') : '';

        if (!normalizedPlatform) {
            return;
        }

        // Fire and forget - don't block user interaction.
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.ayudawpAissL10n.trackUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify({
                platform: normalizedPlatform,
                post_id: parseInt(postId, 10) || 0
            }));
        } catch (err) {
            // Silently fail - tracking should never break user experience.
        }
    }

    /**
     * Handle click for platforms that require copy-to-clipboard behavior
     * Works for Gemini, DeepSeek, and Copilot
     *
     * @param {string} url - The URL containing the prompt parameter
     * @param {string} platform - The platform identifier
     */
    function handleCopyPromptClick(url, platform) {
        try {
            var urlParts = url.split('?');
            var queryString = urlParts.length > 1 ? urlParts[1] : '';
            var urlParams = new URLSearchParams(queryString);
            var prompt = urlParams.get('prompt') || urlParams.get('q') || '';
            var baseUrl = platformBaseUrls[platform] || urlParts[0];

            if (prompt) {
                copyToClipboard(decodeURIComponent(prompt)).then(function() {
                    showNotification(window.ayudawpAissL10n.promptCopied || 'Prompt copied!', 'success');
                    setTimeout(function() {
                        window.open(baseUrl, '_blank', 'noopener,noreferrer');
                    }, 500);
                }).catch(function() {
                    window.open(baseUrl, '_blank', 'noopener,noreferrer');
                });
            } else {
                window.open(baseUrl, '_blank', 'noopener,noreferrer');
            }
        } catch (error) {
            var fallbackUrl = platformBaseUrls[platform] || url.split('?')[0];
            window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
        }
    }

    /**
     * Copy to clipboard function
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        } else {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            return new Promise(function(resolve, reject) {
                try {
                    document.execCommand('copy') ? resolve() : reject();
                } catch (err) {
                    reject(err);
                } finally {
                    textArea.remove();
                }
            });
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        if (document.querySelector('.ayudawp-notification')) return;

        var notification = document.createElement('div');
        notification.className = 'ayudawp-notification ayudawp-notification-' + type;
        notification.textContent = message;
        notification.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#333;color:white;padding:16px 24px;border-radius:8px;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,0.3);opacity:0;transition:all 0.4s ease;border:1px solid #555;';

        document.body.appendChild(notification);

        setTimeout(function() {
            notification.style.opacity = '1';
        }, 50);

        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transform = 'translate(-50%,-50%) scale(0.9)';
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 400);
        }, 2500);
    }

    /**
     * Improve accessibility with keyboard navigation
     */
    function enhanceKeyboardNavigation() {
        $(document).on('keydown', '.ayudawp-share-btn', function(e) {
            if (e.keyCode === 13 || e.keyCode === 32) {
                e.preventDefault();
                $(this).click();
            }
        });
    }

    /**
     * Enhanced responsive behavior for icons-only mode
     */
    function handleResponsiveIconsOnly() {
        var $iconsOnlyContainers = $('.ayudawp-share-buttons.icons-only .ayudawp-buttons-container');

        $iconsOnlyContainers.each(function() {
            var $container = $(this);
            var $buttons = $container.find('.ayudawp-share-btn');
            var containerWidth = $container.width();
            var buttonWidth = 44;
            var gap = 10;
            var buttonsPerRow = Math.floor((containerWidth + gap) / (buttonWidth + gap));

            if ($(window).width() <= 480 && buttonsPerRow < $buttons.length) {
                $container.css({
                    'display': 'grid',
                    'grid-template-columns': 'repeat(auto-fit, 44px)',
                    'gap': '8px',
                    'max-width': '300px',
                    'margin': '0 auto'
                });
            } else {
                $container.css({
                    'display': 'flex',
                    'grid-template-columns': '',
                    'max-width': '',
                    'margin': ''
                });
            }
        });
    }

    /**
     * Smooth hover animation for all buttons
     */
    function attachHoverEffects() {
        $(document).on('mouseenter', '.ayudawp-share-btn', function() {
            var $this = $(this);
            var platform = $this.attr('data-platform');
            if (copyPromptPlatforms.indexOf(platform) === -1) {
                $this.css('transform', 'translateY(-2px)');
            }
        });

        $(document).on('mouseleave', '.ayudawp-share-btn', function() {
            var $this = $(this);
            $this.css('transform', 'translateY(0)');
        });
    }

    /**
     * Initialize all features
     */
    function initializeAllFeatures() {
        attachUniversalTooltips();
        attachClickHandlers();
        enhanceKeyboardNavigation();
        handleResponsiveIconsOnly();
        attachHoverEffects();
    }

    // Initialize on document ready.
    initializeAllFeatures();

    // Handle window resize for responsive behavior.
    $(window).on('resize', function() {
        handleResponsiveIconsOnly();
    });

    // Re-initialize when new content is added dynamically.
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('ayudawp-share-buttons') || $(e.target).find('.ayudawp-share-buttons').length > 0) {
            setTimeout(function() {
                attachUniversalTooltips();
                handleResponsiveIconsOnly();
            }, 100);
        }
    });

    /**
     * AI Summary frontend generation button (v2.0.0)
     *
     * Renders the summary on demand for visitors when the post doesn't
     * have one stored. Delegated handler so it works for shortcode-rendered
     * placeholders and dynamic content (page builders, ajax loaders).
     */
    $(document).on('click', '.ayudawp-aiss-summary-generate-btn', function(e) {
        e.preventDefault();

        var $btn         = $(this);
        var $placeholder = $btn.closest('.ayudawp-aiss-summary--placeholder');
        var postId       = parseInt($placeholder.data('post-id'), 10);

        if (!postId || $btn.prop('disabled')) {
            return;
        }

        var L = window.ayudawpAissL10n || {};
        var originalLabel = $btn.find('.ayudawp-aiss-summary-label').text();

        $btn.prop('disabled', true);
        $btn.find('.ayudawp-aiss-summary-label').text(L.summaryGenerating || 'Generating…');

        var endpoint = (L.publicGenerateUrl || (window.location.origin + '/wp-json/aiss/v1/summary/generate'));

        $.ajax({
            url:      endpoint,
            method:   'POST',
            dataType: 'json',
            contentType: 'application/json',
            data:     JSON.stringify({ post_id: postId })
        }).done(function(response) {
            if (response && response.html) {
                $placeholder.replaceWith(response.html);
            } else {
                $btn.find('.ayudawp-aiss-summary-label').text(L.summaryGenerateError || 'Generation failed. Try again.');
                $btn.prop('disabled', false);
            }
        }).fail(function(xhr) {
            var msg = L.summaryGenerateError || 'Generation failed. Try again.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            $btn.find('.ayudawp-aiss-summary-label').text(msg);
            // Re-enable after a short cooldown so the user can retry.
            setTimeout(function() {
                $btn.prop('disabled', false);
                $btn.find('.ayudawp-aiss-summary-label').text(originalLabel);
            }, 3000);
        });
    });

});
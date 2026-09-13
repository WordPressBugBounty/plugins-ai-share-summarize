/**
 * Share Buttons & AI-powered Summaries Plugin - JavaScript
 * Advanced functionality for tooltips, hover effects, responsive behavior, click tracking
 * and the visitor-facing AI summary generation button (v2.0.0).
 * Version: 2.5.0
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

/**
 * Share buttons runtime (rewritten without jQuery in v2.5.0)
 *
 * Everything below used to run inside jQuery(document).ready. The plugin no
 * longer declares a jQuery dependency, so block themes that do not load it on
 * the front end stop pulling in ~85 KB to show share buttons. Three things
 * went away rather than being ported, because they were doing work the
 * stylesheet already does or the browser does better:
 *
 *   - the hover handler that wrote `transform: translateY(-2px)` inline, which
 *     the CSS :hover rule had been applying since 1.6.0;
 *   - the resize handler that rewrote the icons-only grid with inline styles,
 *     now a media query;
 *   - the DOMNodeInserted listener, a Mutation Event removed from Chrome in
 *     version 127, replaced by a MutationObserver that only watches for share
 *     blocks arriving.
 *
 * @since 2.5.0
 */
(function () {
    'use strict';

    /**
     * AI platforms with no way to pre-fill the prompt from the URL, so the
     * prompt is copied to the clipboard and the site opened empty.
     *
     * Checked again on 12 Sep 2026 against each platform, with Perplexity's
     * working ?q= as the positive control: Meta AI does pre-fill from
     * ?prompt= and left this list in 2.5.0; Gemini, DeepSeek, Copilot and
     * Qwen still do not.
     */
    var copyPromptPlatforms = ['gemini', 'deepseek', 'copilot', 'qwen'];

    /**
     * Landing pages for the copy-prompt platforms.
     */
    var platformBaseUrls = {
        'gemini':   'https://gemini.google.com/app',
        'deepseek': 'https://chat.deepseek.com/',
        'copilot':  'https://copilot.microsoft.com/',
        'qwen':     'https://chat.qwen.ai/'
    };

    /** Platforms that open in a sized popup window instead of a tab. */
    var popupPlatforms = ['twitter', 'linkedin', 'facebook', 'telegram', 'reddit', 'bluesky'];

    /** Buttons that act on the current page instead of opening a destination. */
    var actionPlatforms = ['copy_link', 'web_share'];

    var L = window.ayudawpAissL10n || {};
    var tooltipEl = null;

    function l10n(key, fallback) {
        return (L && L[key]) ? L[key] : fallback;
    }

    /**
     * Platform of a button, from its data attribute or, failing that, its class.
     *
     * @param {Element} btn Button element.
     * @return {string} Platform key, '' when unknown.
     */
    function detectPlatform(btn) {
        var platform = btn.getAttribute('data-platform');
        if (platform) {
            return platform;
        }
        var known = ['twitter', 'linkedin', 'facebook', 'telegram', 'whatsapp', 'email', 'raindrop',
            'reddit', 'bluesky', 'line', 'mastodon', 'threads', 'pinterest',
            'claude', 'chatgpt', 'google-ai', 'gemini', 'grok', 'perplexity',
            'deepseek', 'mistral', 'copilot', 'qwen', 'meta-ai', 'copy-link', 'web-share'];
        for (var i = 0; i < known.length; i++) {
            if (btn.classList.contains(known[i])) {
                return known[i].replace(/-/g, '_');
            }
        }
        return '';
    }

    /**
     * Tooltip text for a button, or '' when it needs none.
     *
     * @param {Element} btn      Button element.
     * @param {string}  platform Platform key.
     * @return {string} Tooltip text.
     */
    function tooltipFor(btn, platform) {
        if (copyPromptPlatforms.indexOf(platform) !== -1) {
            var block = btn.closest('.ayudawp-share-buttons');
            var short = block && block.classList.contains('icons-only');
            return short
                ? l10n(platform + 'TooltipShort', l10n('copyPromptShort', 'Copy prompt & open'))
                : l10n(platform + 'TooltipLong', l10n('copyPromptLong', 'Copy prompt and open'));
        }
        var names = L.platformNames || {};
        return names[platform] || '';
    }

    /**
     * Single shared tooltip element.
     *
     * One node reused by every button, instead of one node plus two listeners
     * per button as before 2.5.0 — a 24-button block was adding 24 divs to
     * <body> on every page load.
     *
     * @return {Element} The tooltip element.
     */
    function getTooltip() {
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.className = 'ayudawp-tooltip';
            tooltipEl.setAttribute('aria-hidden', 'true');
            document.body.appendChild(tooltipEl);
        }
        return tooltipEl;
    }

    function showTooltip(btn) {
        var platform = detectPlatform(btn);
        var text     = tooltipFor(btn, platform);
        if (!text) {
            return;
        }
        var tip = getTooltip();
        tip.textContent = text;
        tip.classList.add('active');

        var rect = btn.getBoundingClientRect();
        var top  = rect.top + window.pageYOffset - tip.offsetHeight - 8;
        var left = rect.left + window.pageXOffset + (rect.width / 2) - (tip.offsetWidth / 2);
        tip.style.top  = Math.max(0, top) + 'px';
        tip.style.left = Math.max(0, left) + 'px';
    }

    function hideTooltip() {
        if (tooltipEl) {
            tooltipEl.classList.remove('active');
        }
    }

    /**
     * Report a click to the analytics endpoint, fire and forget.
     *
     * sendBeacon since 2.5.0: the browser queues the request and delivers it
     * even if the page is being replaced by the destination, which is exactly
     * what happens when the click opens another site.
     *
     * @param {string}  platform Platform key.
     * @param {Element} btn      Clicked button.
     */
    function trackClick(platform, btn) {
        if (!L.trackUrl || !platform) {
            return;
        }

        var container = btn.closest('[data-post-id]');
        var postId    = container ? container.getAttribute('data-post-id') : '';

        if (!postId) {
            var article = document.querySelector('article[id^="post-"]');
            if (article) {
                postId = article.id.replace('post-', '');
            }
        }

        var payload = JSON.stringify({
            platform: platform.replace(/-/g, '_'),
            post_id: parseInt(postId, 10) || 0
        });

        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(L.trackUrl, new Blob([payload], { type: 'application/json' }));
                return;
            }
            if (window.fetch) {
                window.fetch(L.trackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () {});
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', L.trackUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(payload);
        } catch (err) {
            // Tracking must never break sharing.
        }
    }

    /**
     * Copy text to the clipboard, resolving when it is done.
     *
     * @param {string} text Text to copy.
     * @return {Promise} Resolves on success.
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', 'readonly');
            area.style.position = 'fixed';
            area.style.left = '-999999px';
            area.style.top = '-999999px';
            document.body.appendChild(area);
            area.focus();
            area.select();
            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject();
                }
            } catch (err) {
                reject(err);
            } finally {
                area.remove();
            }
        });
    }

    /**
     * Transient on-screen message.
     *
     * role="status" so screen readers announce it: before 2.5.0 the copy
     * confirmation was visual only.
     *
     * @param {string} message Text to show.
     */
    function showNotification(message) {
        var existing = document.querySelector('.ayudawp-notification');
        if (existing) {
            existing.remove();
        }

        var note = document.createElement('div');
        note.className = 'ayudawp-notification';
        note.setAttribute('role', 'status');
        note.textContent = message;
        document.body.appendChild(note);

        window.setTimeout(function () {
            note.classList.add('is-visible');
        }, 20);

        window.setTimeout(function () {
            note.classList.remove('is-visible');
            window.setTimeout(function () {
                if (note.parentNode) {
                    note.parentNode.removeChild(note);
                }
            }, 400);
        }, 2500);
    }

    /**
     * Copy the prompt and open the platform empty.
     *
     * @param {string} url      Button URL carrying the prompt.
     * @param {string} platform Platform key.
     */
    function handleCopyPromptClick(url, platform) {
        var base = platformBaseUrls[platform] || url.split('?')[0];
        var prompt = '';

        try {
            var query = url.split('?')[1] || '';
            var params = new URLSearchParams(query);
            prompt = params.get('prompt') || params.get('q') || '';
        } catch (err) {
            prompt = '';
        }

        if (!prompt) {
            window.open(base, '_blank', 'noopener,noreferrer');
            return;
        }

        copyToClipboard(decodeURIComponent(prompt)).then(function () {
            showNotification(l10n('promptCopied', 'Prompt copied!'));
            window.setTimeout(function () {
                window.open(base, '_blank', 'noopener,noreferrer');
            }, 500);
        }).catch(function () {
            window.open(base, '_blank', 'noopener,noreferrer');
        });
    }

    /**
     * Copy the post link (v2.5.0).
     *
     * @param {string} url Link to copy.
     */
    function handleCopyLink(url) {
        copyToClipboard(url).then(function () {
            showNotification(l10n('linkCopied', 'Link copied to clipboard!'));
        }).catch(function () {
            showNotification(l10n('copyFailed', 'Could not copy.'));
        });
    }

    /**
     * Hand the post to the operating system share sheet (v2.5.0).
     *
     * @param {Element} btn Button element.
     * @param {string}  url Link to share.
     */
    function handleWebShare(btn, url) {
        // No API, or the browser refuses the call (it is only available in a
        // secure context, so plain http never has it): fall back to copying
        // the link rather than doing nothing, which is what a visitor who
        // manages to click it would otherwise get.
        if (!navigator.share) {
            handleCopyLink(url);
            return;
        }

        var data = { url: url };
        var title = btn.getAttribute('data-title');
        if (title) {
            data.title = title;
        }

        try {
            var shared = navigator.share(data);
            if (shared && shared.catch) {
                shared.catch(function (err) {
                    // AbortError is the visitor closing the sheet: not a failure.
                    if (err && 'AbortError' === err.name) {
                        return;
                    }
                    handleCopyLink(url);
                });
            }
        } catch (err) {
            handleCopyLink(url);
        }
    }

    /**
     * Expand or collapse the mobile compact block (v2.5.0).
     *
     * @param {Element} toggle The toggle button.
     */
    function handleCompactToggle(toggle) {
        var block = toggle.closest('.ayudawp-share-buttons');
        if (!block) {
            return;
        }

        var expanded = block.classList.toggle('is-expanded');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        var label = toggle.querySelector('.ayudawp-button-text');
        if (!label) {
            return;
        }
        if (expanded) {
            if (!toggle.hasAttribute('data-collapsed-label')) {
                toggle.setAttribute('data-collapsed-label', label.textContent);
            }
            label.textContent = block.classList.contains('compact-single')
                ? l10n('compactClose', 'Close')
                : l10n('compactLess', 'Less');
        } else {
            label.textContent = toggle.getAttribute('data-collapsed-label') || label.textContent;
        }
    }

    /**
     * Single delegated click handler for every share button on the page.
     *
     * @param {Event} e Click event.
     */
    function onClick(e) {
        var target = e.target;
        if (!target || !target.closest) {
            return;
        }

        var toggle = target.closest('.ayudawp-aiss-more-btn');
        if (toggle) {
            e.preventDefault();
            handleCompactToggle(toggle);
            return;
        }

        var btn = target.closest('.ayudawp-share-btn');
        if (!btn) {
            return;
        }

        var url = btn.getAttribute('data-url') || btn.getAttribute('href');
        if (!url) {
            return;
        }

        var platform = detectPlatform(btn);

        e.preventDefault();
        trackClick(platform, btn);

        if (actionPlatforms.indexOf(platform) !== -1) {
            if ('copy_link' === platform) {
                handleCopyLink(url);
            } else {
                handleWebShare(btn, url);
            }
            return;
        }

        if (copyPromptPlatforms.indexOf(platform) !== -1) {
            handleCopyPromptClick(url, platform);
            return;
        }

        if (popupPlatforms.indexOf(platform) !== -1) {
            var width  = 'reddit' === platform ? 900 : 600;
            var height = 'reddit' === platform ? 600 : 400;
            var left   = (window.screen.width / 2) - (width / 2);
            var top    = (window.screen.height / 2) - (height / 2);
            window.open(url, 'share', 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes');
            return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
    }

    /**
     * Reveal the native share buttons where the browser supports the API.
     *
     * Rendered hidden by PHP so the markup is the same for every visitor (and
     * therefore cacheable), and only shown here when navigator.share exists.
     */
    function revealNativeShare() {
        if (!navigator.share) {
            return;
        }
        var buttons = document.querySelectorAll('.ayudawp-aiss-needs-share-api[hidden]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].removeAttribute('hidden');
        }
    }

    function init() {
        document.addEventListener('click', onClick, false);

        // Tooltips follow both pointer and keyboard focus.
        document.addEventListener('mouseover', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.ayudawp-share-btn') : null;
            if (btn && !btn.classList.contains('ayudawp-aiss-more-btn')) {
                showTooltip(btn);
            }
        }, false);
        document.addEventListener('mouseout', function (e) {
            if (e.target && e.target.closest && e.target.closest('.ayudawp-share-btn')) {
                hideTooltip();
            }
        }, false);
        document.addEventListener('focusin', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.ayudawp-share-btn') : null;
            if (btn && !btn.classList.contains('ayudawp-aiss-more-btn')) {
                showTooltip(btn);
            }
        }, false);
        document.addEventListener('focusout', hideTooltip, false);
        window.addEventListener('scroll', hideTooltip, { passive: true });

        revealNativeShare();

        // Share blocks injected later (AJAX pagination, page builders, infinite
        // scroll) only need the native-share buttons revealing: clicks and
        // tooltips are delegated on document and work without re-binding.
        if (window.MutationObserver && document.body) {
            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    for (var j = 0; j < mutations[i].addedNodes.length; j++) {
                        var node = mutations[i].addedNodes[j];
                        if (node.nodeType !== 1) {
                            continue;
                        }
                        if (node.classList && node.classList.contains('ayudawp-share-buttons')) {
                            revealNativeShare();
                            return;
                        }
                        if (node.querySelector && node.querySelector('.ayudawp-share-buttons')) {
                            revealNativeShare();
                            return;
                        }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/**
 * AI Summary frontend generation button (v2.0.0)
 *
 * Vanilla JS — runs outside jQuery so the handler survives aggressive
 * defer/async optimizations from caching/perf plugins (e.g. wpo-tweaks,
 * WP Rocket, LiteSpeed). Event delegation on document so it picks up
 * placeholders rendered by shortcodes, page builders or AJAX-loaded
 * content. Uses fetch() with credentials so it works for logged-in and
 * anonymous visitors alike.
 *
 * @since 2.0.0
 */
(function () {
    'use strict';

    function ayudawpAissInitSummaryButton() {
        if (document.body && document.body.__aissSummaryBtnBound) {
            return;
        }
        if (document.body) {
            document.body.__aissSummaryBtnBound = true;
        }

        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest ? e.target.closest('.ayudawp-aiss-summary-generate-btn') : null;
            if (!btn) {
                return;
            }
            e.preventDefault();

            var placeholder = btn.closest('.ayudawp-aiss-summary--placeholder');
            if (!placeholder) {
                return;
            }
            var postId = parseInt(placeholder.getAttribute('data-post-id'), 10);
            if (!postId || btn.disabled) {
                return;
            }

            var L = window.ayudawpAissL10n || {};
            var labelEl = btn.querySelector('.ayudawp-aiss-summary-label');
            var originalLabel = labelEl ? labelEl.textContent : '';

            btn.disabled = true;
            if (labelEl) {
                labelEl.textContent = L.summaryGenerating || 'Generating…';
            }

            var endpoint = L.publicGenerateUrl || (window.location.origin + '/wp-json/aiss/v1/summary/generate');

            fetch(endpoint, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:        JSON.stringify({ post_id: postId })
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (result.ok && result.data && result.data.html) {
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = result.data.html.trim();
                    // The server response includes an inline <style> for the
                    // critical CSS plus the actual <aside> summary block.
                    // Picking firstChild would replace the placeholder with
                    // the invisible <style>, making the box disappear. Pick
                    // the real summary node instead (the .ayudawp-aiss-summary
                    // that is NOT the placeholder we're replacing).
                    var node = wrapper.querySelector('.ayudawp-aiss-summary:not(.ayudawp-aiss-summary--placeholder)');
                    if (node && placeholder.parentNode) {
                        placeholder.parentNode.replaceChild(node, placeholder);
                        // A11y: open the freshly generated summary and move focus
                        // to its toggle so screen readers announce the new content
                        // that replaced the button the visitor just activated.
                        var details = node.querySelector('details.ayudawp-aiss-summary-details');
                        if (details) { details.open = true; }
                        var toggle = node.querySelector('summary.ayudawp-aiss-summary-toggle') || node.querySelector('summary');
                        if (toggle && typeof toggle.focus === 'function') {
                            try { toggle.focus(); } catch (e) {}
                        }
                    }
                    return;
                }
                var msg = (result.data && result.data.message) ? result.data.message : (L.summaryGenerateError || 'Generation failed. Try again.');
                if (labelEl) { labelEl.textContent = msg; }
                window.setTimeout(function () {
                    btn.disabled = false;
                    if (labelEl) { labelEl.textContent = originalLabel; }
                }, 3000);
            }).catch(function () {
                if (labelEl) { labelEl.textContent = L.summaryGenerateError || 'Generation failed. Try again.'; }
                window.setTimeout(function () {
                    btn.disabled = false;
                    if (labelEl) { labelEl.textContent = originalLabel; }
                }, 3000);
            });
        }, false);
    }

    // Bind whenever the DOM is ready — works whether this script runs
    // before or after DOMContentLoaded (covers defer/async loading).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ayudawpAissInitSummaryButton);
    } else {
        ayudawpAissInitSummaryButton();
    }
})();
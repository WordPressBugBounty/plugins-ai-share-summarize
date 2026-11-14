/**
 * AI Summaries & Share Buttons Plugin - JavaScript
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
    var l10n = window.ayudawpAissL10n;
    if ( ! l10n ) {
        return;
    }

    /*
     * The content (with the summary) and the floating bar each have their own
     * setting since 2.8.0; the bar follows the content's unless it has its
     * own look, so both are usually the same.
     */
    var selectors = [];
    if ( 'auto' === l10n.darkAdaptMode ) {
        selectors.push( '.ayudawp-share-buttons:not(.ayudawp-aiss-floating):not(.dark):not(.custom)', '.ayudawp-aiss-summary' );
    }
    if ( 'auto' === l10n.darkAdaptBar ) {
        selectors.push( '.ayudawp-aiss-floating:not(.dark):not(.custom)' );
    }
    if ( ! selectors.length ) {
        return;
    }

    function detect() {
        var nodes = document.querySelectorAll( selectors.join( ', ' ) );
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
     * ?prompt= and left this list in 2.5.0. On 23 Oct 2026 Qwen pre-filled
     * from ?text= (not ?prompt= or ?q=) and left it in 2.7.0. Gemini ignored
     * ?q= and ?prompt=, and DeepSeek and Copilot, tried signed in, pre-filled
     * nothing either, so the three stay here.
     */
    var copyPromptPlatforms = ['gemini', 'deepseek', 'copilot'];

    /**
     * Landing pages for the copy-prompt platforms.
     */
    var platformBaseUrls = {
        'gemini':   'https://gemini.google.com/app',
        'deepseek': 'https://chat.deepseek.com/',
        'copilot':  'https://copilot.microsoft.com/'
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
    /*
     * Without the API the native share button stays hidden, but PHP counted it
     * among the ones in sight, so "a few and More" showed one fewer (v2.8.0).
     * These two put the next one in its place, with the same rules PHP uses,
     * and only run where a hidden button was in the way.
     */
    function isShareButton(el) {
        return el.classList && el.classList.contains('ayudawp-share-btn') && !el.classList.contains('ayudawp-aiss-more-btn');
    }

    // The block on phones: walk it in order, as AyudaWP_AISS_Buttons does.
    function rebalanceBlock(block) {
        if (!block.classList.contains('compact-partial') || block.hasAttribute('data-aiss-balanced')) {
            return;
        }
        block.setAttribute('data-aiss-balanced', '1');

        var buttons = block.querySelectorAll('.ayudawp-buttons-container .ayudawp-share-btn');
        var inSight = 0;
        var hidden = 0;
        for (var i = 0; i < buttons.length; i++) {
            if (!buttons[i].classList.contains('aiss-overflow')) {
                inSight++;
            }
            if (buttons[i].hidden) {
                hidden++;
            }
        }
        if (!hidden) {
            return;
        }

        var count = 0;
        var children = block.children;
        for (var c = 0; c < children.length; c++) {
            var child = children[c];
            if (child.classList.contains('ayudawp-buttons-container')) {
                var own = child.querySelectorAll('.ayudawp-share-btn');
                for (var b = 0; b < own.length; b++) {
                    if (own[b].hidden) {
                        continue;
                    }
                    own[b].classList.toggle('aiss-overflow', count >= inSight);
                    count++;
                }
            } else if (child.classList.contains('ayudawp-section-title') || child.classList.contains('ayudawp-section-separator')) {
                // A section heading hides with its section, as in PHP.
                child.classList.toggle('aiss-overflow', count >= inSight);
            }
        }

        // Nothing left behind "More": no toggle.
        var toggle = block.querySelector('.ayudawp-aiss-compact-toggle');
        if (toggle && count <= inSight) {
            toggle.parentNode.removeChild(toggle);
        }
    }

    // The floating bar: split again, as ayudawp_aiss_split_floating_bar_buttons().
    function rebalanceBar(bar) {
        var box = bar.querySelector('.ayudawp-buttons-container');
        var more = box ? box.querySelector('.ayudawp-aiss-bar-more') : null;
        if (!more || bar.hasAttribute('data-aiss-balanced')) {
            return;
        }
        bar.setAttribute('data-aiss-balanced', '1');

        var panel = more.querySelector('.ayudawp-aiss-bar-panel');
        var single = more.classList.contains('is-single');
        var inSight = Array.prototype.filter.call(box.children, isShareButton);
        var all = inSight.concat(Array.prototype.filter.call(panel ? panel.children : [], isShareButton));
        var visible = all.filter(function (el) { return !el.hidden; });
        if (!panel || visible.length === all.length) {
            return;
        }

        var n = inSight.length;
        var shown = visible;
        var rest = [];
        if (single && visible.length > 1) {
            shown = [];
            rest = visible;
        } else if (!single && visible.length > n + 1) {
            shown = visible.slice(0, n);
            rest = visible.slice(n);
        }

        shown.forEach(function (el) { box.insertBefore(el, more); });
        rest.forEach(function (el) { panel.appendChild(el); });

        if (!rest.length) {
            box.removeChild(more);
            bar.classList.remove('has-more', 'is-single');
            return;
        }

        var summary = more.querySelector('summary');
        if (summary) {
            summary.setAttribute('aria-label', String(summary.getAttribute('aria-label') || '').replace(/\d+/, String(rest.length)));
        }
        var columns = bar.classList.contains('ayudawp-aiss-floating--bottom')
            ? Math.min(rest.length, 6)
            : (rest.length <= 4 ? 1 : (rest.length <= 10 ? 2 : 3));
        panel.style.setProperty('--aiss-cols', String(columns));
        panel.style.setProperty('--aiss-cols-phone', String(Math.min(rest.length, 5)));
    }

    function rebalanceWithoutShare() {
        var blocks = document.querySelectorAll('.ayudawp-share-buttons.compact-partial');
        for (var i = 0; i < blocks.length; i++) {
            rebalanceBlock(blocks[i]);
        }
        var bars = document.querySelectorAll('.ayudawp-aiss-floating');
        for (var j = 0; j < bars.length; j++) {
            rebalanceBar(bars[j]);
        }
    }

    function revealNativeShare() {
        if (!navigator.share) {
            rebalanceWithoutShare();
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
                // The custom content only on the post's own page (v2.7.0).
                body:        JSON.stringify({ post_id: postId, with_custom: postId === parseInt(L.singularPostId, 10) })
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

/**
 * Custom content inside the summary box (v2.7.0).
 *
 * Text, HTML or scripts (an ad, a form, a map...). It arrives inert in
 * #ayudawp-aiss-custom, as a JSON string since 2.8.1 (a <template> until
 * then), and is put in place the first time its box is open: on load when the
 * box starts open, on the first click otherwise, and also for a box that
 * arrives later through the visitor button. Nothing is requested into a
 * folded box, where an ad would have no size.
 *
 * Only one box on the page gets it (v2.8.1): the shortcode plus the automatic
 * box, or a box printed twice by a theme, used to load it twice, with two
 * spaces under the same id. The first box opened keeps it, and the others
 * lose their space and label at that moment.
 *
 * Each <script> is created anew rather than taken from the template. One
 * that came in through innerHTML (the visitor button) is marked as already
 * run, the copy keeps that mark and would never execute; one parsed with the
 * page would run on insertion. So scripts are pulled out before the rest goes
 * in, then recreated one by one, an external one without async waiting for
 * its load before the next, which keeps the order the code was written in.
 *
 * Opening the box also fires an ayudawp_aiss_custom_visible event on the slot and
 * pushes the same event to the dataLayer when there is one, so an ad server
 * or a Tag Manager tag that fills the slot itself can wait for it.
 *
 * @since 2.7.0
 */
(function () {
    'use strict';

    var EVENT = 'ayudawp_aiss_custom_visible';

    function freshScript(old) {
        var script = document.createElement('script');
        for (var i = 0; i < old.attributes.length; i++) {
            script.setAttribute(old.attributes[i].name, old.attributes[i].value);
        }
        if (script.src && !old.hasAttribute('async')) {
            script.async = false;
        }
        script.text = old.textContent;
        return script;
    }

    function runScripts(pairs, index) {
        if (index >= pairs.length) {
            return;
        }
        var stub   = pairs[index].stub;
        var script = freshScript(pairs[index].old);
        var next   = function () { runScripts(pairs, index + 1); };

        if (!stub.parentNode) {
            next();
            return;
        }
        if (script.src && !script.async) {
            script.onload  = next;
            script.onerror = next;
            stub.parentNode.replaceChild(script, stub);
            return;
        }
        stub.parentNode.replaceChild(script, stub);
        next();
    }

    function inject(template) {
        if (!template.content || !template.parentNode) {
            return;
        }
        var fragment = document.importNode(template.content, true);
        var olds     = fragment.querySelectorAll('script');
        var pairs    = [];

        for (var i = 0; i < olds.length; i++) {
            var stub = document.createComment('');
            olds[i].parentNode.replaceChild(stub, olds[i]);
            pairs.push({ old: olds[i], stub: stub });
        }

        template.parentNode.replaceChild(fragment, template);
        runScripts(pairs, 0);
    }

    /**
     * The content of a space as a <template> ready for inject() (v2.8.1).
     *
     * The JSON is parsed into a template made here, so whatever the pasted
     * markup leaves open (a comment, a script) closes at the end of its own
     * string instead of running into the rest of the page. A page cached
     * before 2.8.1 still carries the old <template>, taken as it is.
     *
     * @param {Element} slot The space.
     * @return {HTMLTemplateElement|null}
     */
    function payload(slot) {
        var json = slot.querySelector('script.ayudawp-aiss-custom-code');
        if (!json) {
            return slot.querySelector('template.ayudawp-aiss-custom-code');
        }

        var code = '';
        try {
            code = JSON.parse(json.textContent);
        } catch (e) {
            code = '';
        }

        if ('string' !== typeof code || '' === code) {
            json.parentNode.removeChild(json);
            return null;
        }

        var template = document.createElement('template');
        template.innerHTML = code;
        json.parentNode.replaceChild(template, json);
        return template;
    }

    /** The space that took the content, once a box has been opened. */
    var taken = null;

    /**
     * Remove a space and its label (v2.8.1).
     *
     * @param {Element} slot The space.
     */
    function drop(slot) {
        var box = slot.closest ? slot.closest('.ayudawp-aiss-custom') : null;
        var el  = box || slot;
        if (el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function announce(slot) {
        try {
            slot.dispatchEvent(new CustomEvent(EVENT, { bubbles: true }));
        } catch (e) {}
        if (window.dataLayer && typeof window.dataLayer.push === 'function') {
            window.dataLayer.push({ event: EVENT });
        }
    }

    function show(details) {
        if (!details.open) {
            return;
        }
        var slots = details.querySelectorAll('.ayudawp-aiss-custom-slot');
        for (var i = 0; i < slots.length; i++) {
            var slot = slots[i];
            if (slot.__aissCustomShown) {
                continue;
            }

            // A space with no content to load (left empty for an ad server
            // or Tag Manager) only announces itself. Only one that carries
            // the content can take it from the others: post content can hold
            // a look-alike space, but kses never lets it carry the content.
            if (!slot.querySelector('script.ayudawp-aiss-custom-code, template.ayudawp-aiss-custom-code')) {
                slot.__aissCustomShown = true;
                announce(slot);
                continue;
            }

            // Another box already has it: this one goes without.
            if (taken && taken !== slot && document.documentElement.contains(taken)) {
                drop(slot);
                continue;
            }

            slot.__aissCustomShown = true;
            taken = slot;

            var others = document.querySelectorAll('.ayudawp-aiss-custom-slot');
            for (var j = 0; j < others.length; j++) {
                if (others[j] !== slot) {
                    drop(others[j]);
                }
            }

            var template = payload(slot);
            if (template) {
                inject(template);
            }
            announce(slot);
        }
    }

    // toggle does not bubble: listen in the capture phase.
    document.addEventListener('toggle', function (e) {
        var target = e.target;
        if (target && target.matches && target.matches('details.ayudawp-aiss-summary-details')) {
            show(target);
        }
    }, true);

    function showOpenBoxes() {
        var open = document.querySelectorAll('details.ayudawp-aiss-summary-details[open]');
        for (var i = 0; i < open.length; i++) {
            show(open[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showOpenBoxes);
    } else {
        showOpenBoxes();
    }
})();

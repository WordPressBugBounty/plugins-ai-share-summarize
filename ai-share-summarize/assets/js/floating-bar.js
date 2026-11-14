/**
 * AI Summaries & Share Buttons — floating bar behaviour (v2.5.0)
 *
 * Only loaded on pages where the bar actually renders. Clicks, tracking and
 * tooltips are not handled here: the main script delegates those on document,
 * so this file is the optional auto-hide and, since 2.8.0, the manners of the
 * "More" panel.
 *
 * The bar is visible by default in CSS and this script adds `js-autohide`
 * before touching anything, so a site whose JS fails to load keeps a visible
 * bar instead of an invisible one. "More" is a native <details>, so it opens
 * and closes without this script too.
 *
 * A page too short to scroll past the reveal point keeps the bar in sight
 * (v2.8.1). Until then the auto-hide, on by default, waited for 200px of
 * scrolling that such a page does not have, and the bar never showed.
 */
(function () {
    'use strict';

    /**
     * Close every open "More" panel, except the one given.
     *
     * @param {Element|null} keep Panel to leave open.
     */
    function closeMore(keep) {
        var open = document.querySelectorAll('.ayudawp-aiss-bar-more[open]');
        for (var i = 0; i < open.length; i++) {
            if (open[i] !== keep) {
                open[i].removeAttribute('open');
            }
        }
    }

    /**
     * What a native <details> does not do by itself (v2.8.0): close on
     * Escape, giving the focus back to its button, and on a click outside.
     */
    function initMore() {
        if (!document.querySelector('.ayudawp-aiss-bar-more')) {
            return;
        }

        document.addEventListener('click', function (e) {
            var inside = e.target && e.target.closest ? e.target.closest('.ayudawp-aiss-bar-more') : null;
            closeMore(inside);
        }, false);

        document.addEventListener('keydown', function (e) {
            if ('Escape' !== e.key && 'Esc' !== e.key) {
                return;
            }
            var open = e.target && e.target.closest ? e.target.closest('.ayudawp-aiss-bar-more[open]') : null;
            if (!open) {
                open = document.querySelector('.ayudawp-aiss-bar-more[open]');
            }
            if (!open) {
                return;
            }
            open.removeAttribute('open');
            var summary = open.querySelector('summary');
            if (summary) {
                summary.focus();
            }
        }, false);
    }

    /** Scroll distance before the bar appears, in pixels. */
    var REVEAL_AFTER = 200;

    /** Movement below which a scroll is treated as noise. */
    var THRESHOLD = 8;

    /**
     * How far the window can scroll, in pixels (v2.8.1).
     *
     * Measured on the element that scrolls the window, not on the body: a
     * theme that scrolls inside its own container (body or a wrapper at 100%
     * height with its own overflow) never moves the window, so the scroll
     * listener below never fires. There the window has no room and the bar
     * stays in sight instead of waiting for a scroll that will not come.
     *
     * @return {number} Distance between the top and the bottom of the page.
     */
    function scrollRoom() {
        var root = document.scrollingElement || document.documentElement;

        return Math.max(0, root.scrollHeight - window.innerHeight);
    }

    /** Current scroll position, in pixels. */
    function scrollTop() {
        return window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    function init() {
        var bars = document.querySelectorAll('.ayudawp-aiss-floating[data-autohide="1"]');
        if (!bars.length) {
            return;
        }

        var lastY     = scrollTop();
        var ticking   = false;
        var measuring = false;
        // Where the bar appears on the way down, and whether the page is too
        // short to hide it at all. Worked out by measure(), not here: the
        // height changes as images and late content load.
        var revealAt  = REVEAL_AFTER;
        var short     = null;

        for (var i = 0; i < bars.length; i++) {
            bars[i].classList.add('js-autohide');
        }

        function apply(visible) {
            for (var i = 0; i < bars.length; i++) {
                bars[i].classList.toggle('is-visible', visible);
            }
            // A panel left open would come back open with the bar, over
            // whatever the visitor has scrolled to in the meantime.
            if (!visible) {
                closeMore(null);
            }
        }

        /*
         * A page that cannot scroll past the reveal point would never show the
         * bar, so there it stays in sight and scrolling does not hide it. On a
         * page with a little more room the bar appears halfway down, so it is
         * not left for the last few pixels of the page.
         */
        function measure() {
            var room = scrollRoom();
            var now  = room <= REVEAL_AFTER;

            measuring = false;
            revealAt  = now ? 0 : Math.min(REVEAL_AFTER, room / 2);

            if (now === short) {
                return;
            }

            short = now;
            lastY = scrollTop();
            apply(short || lastY > revealAt);
        }

        function scheduleMeasure() {
            if (!measuring) {
                measuring = true;
                window.requestAnimationFrame(measure);
            }
        }

        function onScroll() {
            var y     = scrollTop();
            var delta = y - lastY;

            ticking = false;

            if (short || Math.abs(delta) < THRESHOLD) {
                return;
            }

            // Scrolling down means reading on: show the bar once the visitor is
            // past the top of the page. Scrolling back up hides it again.
            apply(delta > 0 && y > revealAt);

            lastY = y;
        }

        measure();

        window.addEventListener('scroll', function () {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(onScroll);
            }
        }, { passive: true });

        // The page's height moves after this runs: images, embeds, fonts,
        // content opened by other scripts, a window made smaller.
        window.addEventListener('resize', scheduleMeasure, { passive: true });
        window.addEventListener('load', scheduleMeasure);
        if ('ResizeObserver' in window && document.body) {
            new window.ResizeObserver(scheduleMeasure).observe(document.body);
        }
    }

    function boot() {
        initMore();
        init();
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

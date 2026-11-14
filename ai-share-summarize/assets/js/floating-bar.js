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

    function init() {
        var bars = document.querySelectorAll('.ayudawp-aiss-floating[data-autohide="1"]');
        if (!bars.length) {
            return;
        }

        var lastY   = window.pageYOffset || document.documentElement.scrollTop || 0;
        var ticking = false;

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

        function onScroll() {
            var y     = window.pageYOffset || document.documentElement.scrollTop || 0;
            var delta = y - lastY;

            if (Math.abs(delta) < THRESHOLD) {
                ticking = false;
                return;
            }

            // Scrolling down means reading on: show the bar once the visitor is
            // past the top of the page. Scrolling back up hides it again.
            apply(delta > 0 && y > REVEAL_AFTER);

            lastY   = y;
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(onScroll);
            }
        }, { passive: true });
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

/**
 * Share Buttons & AI-powered Summaries — floating bar behaviour (v2.5.0)
 *
 * Only loaded on pages where the bar actually renders. Clicks, tracking and
 * tooltips are not handled here: the main script delegates those on document,
 * so this file is just the optional auto-hide.
 *
 * The bar is visible by default in CSS and this script adds `js-autohide`
 * before touching anything, so a site whose JS fails to load keeps a visible
 * bar instead of an invisible one.
 */
(function () {
    'use strict';

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

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

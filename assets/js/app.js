/* Thesis Finder — small front-end polish layer.
 * Vanilla JS, deferred. No external libraries.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    /* 1. Mark body as loaded so CSS entrance plays. */
    function markLoaded() {
        document.body.classList.add('is-loaded');
    }

    /* 2. Scroll reveals via IntersectionObserver. */
    function setupReveals() {
        if (reduceMotion || !('IntersectionObserver' in window)) {
            document.querySelectorAll('[data-reveal]').forEach(function (el) {
                el.classList.add('is-visible');
            });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
        document.querySelectorAll('[data-reveal]').forEach(function (el) { io.observe(el); });
    }

    /* 3. Sticky-header condense at scrollY > 80. */
    function setupHeaderCondense() {
        var bar = document.querySelector('.topbar');
        if (!bar) return;
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            window.requestAnimationFrame(function () {
                if (window.scrollY > 80) bar.classList.add('is-condensed');
                else bar.classList.remove('is-condensed');
                ticking = false;
            });
            ticking = true;
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* 4. Active nav link based on current pathname. */
    function setupActiveNav() {
        var here = window.location.pathname.replace(/\/+$/, '');
        document.querySelectorAll('.nav a').forEach(function (a) {
            try {
                var p = new URL(a.href, window.location.origin).pathname.replace(/\/+$/, '');
                if (p && p === here) a.classList.add('is-active');
            } catch (_) {}
        });
    }

    /* 5. Submit-button loading state. Does NOT preventDefault — let the form submit.
     *
     *    Important: disabling the button synchronously inside the submit handler
     *    excludes its name/value from the submitted form data (per WHATWG form
     *    construction algorithm). For forms where the action lives on a named
     *    button (Accept / Reject / Cancel / etc.), that wipes $_POST['action']
     *    server-side. We defer the disable to the next macrotask so the form
     *    data is collected with the button still enabled. */
    function setupSubmitSpinner() {
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var btn = e.submitter
                       || form.querySelector('button[type="submit"], button:not([type])');
                if (!btn || btn.classList.contains('is-loading')) return;
                btn.classList.add('is-loading');
                setTimeout(function () { btn.disabled = true; }, 0);
                /* Re-enable on bfcache restore so the back button doesn't leave it disabled. */
                window.addEventListener('pageshow', function once() {
                    btn.classList.remove('is-loading');
                    btn.disabled = false;
                    window.removeEventListener('pageshow', once);
                });
            });
        });
    }

    /* 6. Auto-dismiss toast flashes — element animation handles the visual,
     *    we just remove from DOM after it's done so it doesn't intercept clicks. */
    function setupToasts() {
        document.querySelectorAll('.alert-toast').forEach(function (el) {
            setTimeout(function () { el.remove(); }, 5000);
        });
    }

    /* 7. Chat composer: auto-grow textarea, Ctrl/Cmd+Enter submits. */
    function setupChatComposer() {
        var ta = document.querySelector('.chat-form textarea');
        if (!ta) return;
        var maxRows = 6;
        function resize() {
            ta.style.height = 'auto';
            var lineHeight = parseInt(getComputedStyle(ta).lineHeight, 10) || 20;
            var maxHeight = lineHeight * maxRows + 22;
            ta.style.height = Math.min(ta.scrollHeight, maxHeight) + 'px';
        }
        ta.addEventListener('input', resize);
        ta.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                var form = ta.form;
                if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
        resize();
    }

    ready(function () {
        markLoaded();
        setupReveals();
        setupHeaderCondense();
        setupActiveNav();
        setupSubmitSpinner();
        setupToasts();
        setupChatComposer();
    });
})();

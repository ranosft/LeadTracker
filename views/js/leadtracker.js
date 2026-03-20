/**
 * LeadTracker Frontend JS
 * Handles: mobile resolution, cookie management, event tracking, modal
 */
(function () {
    'use strict';

    var cfg = window.LeadTrackerConfig || {};
    var COOKIE_NAME = 'ps_guest_mobile';
    var POPUP_SKIP_COOKIE = 'lt_popup_skipped';
    var trackQueue = [];
    var debounceTimers = {};
    var resolvedMobile = null;

    /* ── Cookie helpers ─────────────────────────────────── */
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 864e5);
            expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function deleteCookie(name) {
        setCookie(name, '', -1);
    }

    /* ── Mobile normalization (JS mirror of PHP logic) ─── */
    function normalizeMobile(raw) {
        if (!raw) return null;
        var digits = String(raw).replace(/\D/g, '');
        if (digits.length === 12 && digits.substr(0, 2) === '91') {
            digits = digits.substr(2);
        }
        if (digits.length === 11 && digits[0] === '0') {
            digits = digits.substr(1);
        }
        if (digits.length === 10 && /^[6-9]/.test(digits)) {
            return digits;
        }
        return null;
    }

    /* ── Priority-based mobile resolution ───────────────── */
    function resolveMobile() {
        // 1. URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var urlMobile = urlParams.get('mobile') || urlParams.get('m');
        if (urlMobile) {
            var nm = normalizeMobile(urlMobile);
            if (nm) {
                setCookie(COOKIE_NAME, nm, cfg.cookieDays || 30);
                return { mobile: nm, source: 'url_param' };
            }
        }

        // 2. Logged-in customer
        if (cfg.customerMobile) {
            var nm2 = normalizeMobile(cfg.customerMobile);
            if (nm2) {
                setCookie(COOKIE_NAME, nm2, cfg.cookieDays || 30);
                return { mobile: nm2, source: 'customer' };
            }
        }

        // 3. Cookie
        var cookieMobile = getCookie(COOKIE_NAME);
        if (cookieMobile) {
            var nm3 = normalizeMobile(cookieMobile);
            if (nm3) return { mobile: nm3, source: 'cookie' };
        }

        // 4. Manual — handled by modal
        return null;
    }

    /* ── AJAX tracker ────────────────────────────────────── */
    function sendToServer(action, payload) {
        var data = Object.assign({ action: action }, payload);
        var body = Object.keys(data)
            .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); })
            .join('&');

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            keepalive: true,
        }).catch(function () { /* silent */ });
    }

    function captureLead(mobile, source) {
        sendToServer('capture', { mobile: mobile, source: source });
    }

    /* ── Debounced event tracker ─────────────────────────── */
    function trackEvent(eventType, extraData, debounceMs) {
        if (!resolvedMobile) return;

        debounceMs = debounceMs || 0;
        var key = eventType;

        if (debounceTimers[key]) {
            clearTimeout(debounceTimers[key]);
        }

        debounceTimers[key] = setTimeout(function () {
            var payload = Object.assign({
                mobile:     resolvedMobile,
                source:     'cookie',
                event_type: eventType,
                page_url:   window.location.pathname + window.location.search,
                controller: cfg.controller || '',
                session_id: cfg.sessionId || '',
            }, extraData || {});

            sendToServer('track', payload);
            delete debounceTimers[key];
        }, debounceMs);
    }

    /* ── Page view tracking ─────────────────────────────── */
    function trackPageView() {
        if (!cfg.trackPageview) return;
        trackEvent('pageview', {}, 1500); // 1.5s debounce
    }

    /* ── Product view tracking ──────────────────────────── */
    function trackProductView() {
        if (!cfg.trackProduct) return;
        var productData = {};

        // Prestashop product page — try to pick up product info
        var prodIdEl = document.querySelector('[data-id-product]');
        if (prodIdEl) productData.product_id = prodIdEl.getAttribute('data-id-product');

        var titleEl = document.querySelector('h1.page-title, h1.product-name, .product-name h1');
        if (titleEl) productData.product_name = titleEl.innerText.trim().substr(0, 255);

        trackEvent('product_view', productData, 2000);
    }

    /* ── Add-to-cart tracking ───────────────────────────── */
    function bindAddToCart() {
        if (!cfg.trackCart) return;

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-button-action="add-to-cart"], .add-to-cart, #add_to_cart button');
            if (!btn) return;

            var productId   = null;
            var productName = null;

            var form = btn.closest('form');
            if (form) {
                var pidInput = form.querySelector('[name="id_product"]');
                if (pidInput) productId = pidInput.value;
            }

            var nameEl = document.querySelector('.product-name, h1.page-title');
            if (nameEl) productName = nameEl.innerText.trim().substr(0, 255);

            trackEvent('add_to_cart', {
                product_id:   productId || '',
                product_name: productName || '',
            }, 500);
        });

        // PrestaShop custom JS event (PS 1.7+)
        document.addEventListener('updateCart', function (e) {
            if (e.detail && e.detail.reason && e.detail.reason.linkAction === 'add-to-cart') {
                trackEvent('add_to_cart', {
                    product_id: e.detail.reason.idProduct || '',
                }, 500);
            }
        });
    }

    /* ── Checkout tracking ───────────────────────────────── */
    function bindCheckout() {
        if (!cfg.trackCheckout) return;

        // Detect checkout page by controller
        if (cfg.controller === 'order' || window.location.pathname.indexOf('/order') !== -1) {
            trackEvent('checkout', {}, 1000);
        }
    }

    /* ── Modal ────────────────────────────────────────────── */
    function initModal() {
        if (!cfg.showPopup) return;
        if (getCookie(POPUP_SKIP_COOKIE)) return;
        if (resolvedMobile) return;

        var modal = document.getElementById('lt-mobile-modal');
        if (!modal) return;

        // Show after 3 seconds
        setTimeout(function () {
            modal.style.display = 'flex';
            document.getElementById('lt-mobile-input').focus();
        }, 3000);

        var submitBtn  = document.getElementById('lt-submit-btn');
        var skipBtn    = document.getElementById('lt-skip-btn');
        var inputEl    = document.getElementById('lt-mobile-input');
        var errorEl    = document.getElementById('lt-mobile-error');

        function closeModal() {
            modal.style.display = 'none';
        }

        function showError(msg) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
            inputEl.classList.add('lt-input-error');
        }

        function clearError() {
            errorEl.style.display = 'none';
            inputEl.classList.remove('lt-input-error');
        }

        submitBtn.addEventListener('click', function () {
            var raw = inputEl.value.trim();
            var nm  = normalizeMobile(raw);

            if (!nm) {
                showError('Please enter a valid 10-digit Indian mobile number');
                return;
            }
            clearError();

            // Show spinner
            submitBtn.querySelector('.lt-btn-text').style.display = 'none';
            submitBtn.querySelector('.lt-btn-spinner').style.display = 'inline';
            submitBtn.disabled = true;

            // Store in cookie
            setCookie(COOKIE_NAME, nm, cfg.cookieDays || 30);
            resolvedMobile = nm;

            // Capture on server
            captureLead(nm, 'manual');
            closeModal();

            // Track page view now that we have mobile
            trackPageView();
        });

        skipBtn.addEventListener('click', function () {
            setCookie(POPUP_SKIP_COOKIE, '1', 7); // skip for 7 days
            closeModal();
        });

        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') submitBtn.click();
        });

        // Only allow digits
        inputEl.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
            clearError();
        });

        // Backdrop click closes
        modal.querySelector('.lt-modal-backdrop').addEventListener('click', function () {
            setCookie(POPUP_SKIP_COOKIE, '1', 1);
            closeModal();
        });
    }

    /* ── Bootstrap ───────────────────────────────────────── */
    function init() {
        var resolved = resolveMobile();

        if (resolved) {
            resolvedMobile = resolved.mobile;
            captureLead(resolved.mobile, resolved.source);
            trackPageView();
        }

        // Controller-specific tracking
        var controller = cfg.controller || '';

        if (controller === 'product') {
            trackProductView();
        }

        bindAddToCart();
        bindCheckout();
        initModal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

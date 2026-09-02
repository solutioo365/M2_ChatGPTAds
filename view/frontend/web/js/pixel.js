define([
    'jquery'
], function ($) {
    'use strict';

    function hasMarketingConsent(category) {
        if (!window.Cookiebot || !window.Cookiebot.consent) {
            return false;
        }
        return !!window.Cookiebot.consent[category];
    }

    function measure(name, data, options) {
        if (typeof window.oaiq !== 'function' || !data) {
            return;
        }
        if (options && Object.keys(options).length) {
            window.oaiq('measure', name, data, options);
            return;
        }
        window.oaiq('measure', name, data);
    }

    function fireQueued(config) {
        var events = config.events || {};
        Object.keys(events).forEach(function (name) {
            var item = events[name];
            if (item && item.data) {
                measure(name, item.data, item.options);
            }
        });
    }

    function bindAddToCart(config) {
        if (!config.trackAddToCart) {
            return;
        }
        $(document).on('ajax:addToCart', function (event, data) {
            var form = data && data.form ? $(data.form) : $();
            var sku = (data && data.sku) || form.find('[name="sku"]').val() || '';
            var qty = parseInt(form.find('[name="qty"]').val(), 10) || 1;
            var name = form.closest('.product-item, .product-info-main').find('.product-item-name, .page-title').first().text().trim()
                || document.title;
            var price = parseFloat(
                form.closest('.product-item, .product-info-main')
                    .find('[data-price-type="finalPrice"]')
                    .first()
                    .attr('data-price-amount')
            ) || 0;
            var amount = Math.round(price * (config.currency === 'JPY' ? 1 : 100));
            if (!sku && data && data.productIds && data.productIds[0]) {
                sku = String(data.productIds[0]);
            }
            if (!sku) {
                return;
            }
            measure('items_added', {
                type: 'contents',
                amount: amount,
                currency: config.currency || 'EUR',
                contents: [{
                    id: String(sku),
                    name: name,
                    content_type: 'product',
                    quantity: qty,
                    amount: amount,
                    currency: config.currency || 'EUR'
                }]
            });
        });
    }

    return function (config) {
        var started = false;
        var category = (config && config.cookiebotCategory) || 'marketing';

        function start(attempt) {
            attempt = attempt || 0;
            if (started) {
                return;
            }
            if (typeof window.oaiq !== 'function') {
                if (attempt < 20) {
                    setTimeout(function () {
                        start(attempt + 1);
                    }, 250);
                }
                return;
            }
            if (config.respectCookiebot && !hasMarketingConsent(category)) {
                return;
            }
            started = true;
            if (config.respectCookiebot) {
                window.oaiq('consent', true);
            }
            fireQueued(config);
        }

        bindAddToCart(config);

        if (!config.respectCookiebot) {
            start();
            return;
        }

        window.addEventListener('CookiebotOnAccept', start);
        window.addEventListener('CookiebotOnLoad', start);
        if (hasMarketingConsent(category)) {
            start();
        }
    };
});

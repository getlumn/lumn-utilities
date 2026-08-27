/**
 * LUMN Tracking / SEO Tools - form submission relay consumption (Step 3).
 *
 * Depends on public/js/lumn-tracking.js (window.LumnTracking). The core
 * script already auto-consumes any relayed event once, at load, from the
 * top-level document (covers a plain form postback or a redirect to a
 * confirmation/thank-you page). This file adds the piece that core script
 * can't provide on its own: reacting *immediately*, on the parent page,
 * when a provider's own JS confirms a successful AJAX submission has
 * actually been displayed - rather than waiting for the visitor's next
 * page load.
 *
 * Gravity Forms triggers `gform_confirmation_loaded` on the parent
 * document once it has swapped a *successful* confirmation into the
 * visible page (never on a validation-error re-render). Formidable Forms
 * triggers `frmFormComplete` on the parent document the same way after a
 * successful AJAX submission. Both are provider-documented public JS
 * hooks, not DOM scraping.
 *
 * Calling LumnTracking.consumeRelay() a second time (e.g. once from the
 * core script's own auto-consume, once from a listener here) is always
 * safe - the relay cookie is cleared on first read, so any later call
 * simply finds nothing queued.
 */
(function (window) {
    'use strict';

    var LumnTracking = window.LumnTracking;
    if (!LumnTracking) {
        return;
    }

    var config = LumnTracking.getConfig();
    if (!config.enabled || !LumnTracking.isFeatureEnabled('form_tracking')) {
        return;
    }

    // Nothing to listen for if no provider is actually enabled.
    var providers = config.formProviders || {};
    var anyProviderEnabled = Object.keys(providers).some(function (key) {
        return providers[key];
    });
    if (!anyProviderEnabled) {
        return;
    }

    if (window.__lumnTrackingFormsBound) {
        return;
    }
    window.__lumnTrackingFormsBound = true;

    // Only the top-level document ever sees these provider events fire
    // meaningfully - see the matching note in lumn-tracking.js about why
    // a hidden AJAX/confirmation iframe must not act on its own copy.
    if (window.self !== window.top) {
        return;
    }

    if (!window.jQuery) {
        return;
    }

    var $ = window.jQuery;
    $(document).on('gform_confirmation_loaded', function () {
        LumnTracking.consumeRelay();
    });
    $(document).on('frmFormComplete', function () {
        LumnTracking.consumeRelay();
    });
})(window);

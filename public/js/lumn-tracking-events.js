/**
 * LUMN Tracking / SEO Tools - automatic + explicit click detection.
 *
 * Depends on public/js/lumn-tracking.js (window.LumnTracking) and is only
 * ever enqueued alongside it when tracking is enabled overall (see
 * lumn_ut_tracking_public_scripts() in register/tracking.php). This file
 * does not decide *whether* tracking is enabled - it only classifies
 * clicks and hands them to LumnTracking.pushEvent(), which enforces the
 * master switch and every feature toggle itself.
 *
 * ---------------------------------------------------------------------
 * Detection precedence (see docs/TRACKING.md "Detection precedence")
 * ---------------------------------------------------------------------
 * 1. Explicit: the clicked element (or its nearest ancestor) carries a
 *    data-lumn-event attribute. Always wins - automatic classification
 *    is never attempted for that click, so a link that is both a tel:
 *    link AND explicitly tagged only ever fires once.
 * 2. Automatic, for a plain <a href="..."> with no data-lumn-event
 *    anywhere in its ancestor chain, tried in this fixed order:
 *      a. tel:                              -> LUMN_PHONE_CLICK
 *      b. mailto:                            -> LUMN_EMAIL_CLICK
 *      c. matches a configured Appointments  -> LUMN_APPOINTMENT_CLICK
 *         link (site-wide or per-location)
 *      d. known maps/directions domain       -> LUMN_DIRECTIONS_CLICK
 *    A link that matches none of these produces no event.
 *
 * Exactly one delegated click listener is attached to `document`
 * (capture phase) the first time this script runs - never per-element -
 * so dynamically inserted links and data-lumn-event elements are covered
 * automatically with no MutationObserver and no risk of double-binding.
 */
(function (window, document) {
    'use strict';

    var LumnTracking = window.LumnTracking;
    if (!LumnTracking) {
        return; // core script failed to load/init - nothing to attach to
    }

    var config = LumnTracking.getConfig();
    if (!config.enabled) {
        return;
    }

    // If every click-related feature is off, there is nothing this
    // module could ever fire - skip attaching the listener entirely
    // rather than paying for delegation on every click for no reason.
    var CLICK_FEATURES = [
        'phone_click_tracking',
        'email_click_tracking',
        'appointment_click_tracking',
        'directions_click_tracking',
        'event_tracking'
    ];
    var anyClickFeatureEnabled = CLICK_FEATURES.some(function (feature) {
        return LumnTracking.isFeatureEnabled(feature);
    });
    if (!anyClickFeatureEnabled) {
        return;
    }

    // Guard against this script somehow running twice on one page (e.g.
    // a theme/CDN double-enqueue) - the delegated listener must only
    // ever be attached once, or every click would fire two events.
    if (window.__lumnTrackingEventsBound) {
        return;
    }
    window.__lumnTrackingEventsBound = true;

    // ---- explicit data-lumn-event resolution ---------------------------

    // Resolves a data-lumn-event value against the server-localized event
    // registry (config.events). Accepts, case-insensitively:
    //   - a registry key, e.g. "LUMN_PHONE_CLICK"
    //   - the dataLayer event name, e.g. "lumn_phone_click"
    //   - the short action form, e.g. "phone_click"
    // Returns the registry key on a match, or null - an unrecognized
    // value fails safe and never reaches dataLayer.push().
    function resolveExplicitEventKey(rawValue) {
        var needle = rawValue ? String(rawValue).trim() : '';
        if (!needle) {
            return null;
        }

        var events = config.events || {};

        if (Object.prototype.hasOwnProperty.call(events, needle.toUpperCase())) {
            return needle.toUpperCase();
        }

        var lower = needle.toLowerCase();
        for (var key in events) {
            if (!Object.prototype.hasOwnProperty.call(events, key)) {
                continue;
            }
            if (events[key].name === lower || events[key].action === lower) {
                return key;
            }
        }

        return null;
    }

    // Only these three data-lumn-* attributes are ever read. Arbitrary
    // data-* attributes are never copied into the event payload - see
    // docs/TRACKING.md "Supported data-lumn-* attributes".
    function readExplicitParams(el) {
        return {
            lumn_location: el.getAttribute('data-lumn-location') || undefined,
            lumn_component: el.getAttribute('data-lumn-component') || undefined
        };
    }

    // ---- automatic classification --------------------------------------

    // Human-readable "how was this detected" labels for the Tracking
    // Debugger's Recent Events feed (Step 4) - see docs/TRACKING.md
    // "Debugger event sources". Purely descriptive; never part of the
    // dataLayer payload.
    var AUTOMATIC_SOURCE_LABELS = {
        LUMN_PHONE_CLICK: 'Automatic phone detection',
        LUMN_EMAIL_CLICK: 'Automatic email detection',
        LUMN_APPOINTMENT_CLICK: 'Automatic appointment detection',
        LUMN_DIRECTIONS_CLICK: 'Automatic directions detection'
    };

    function classifyAnchor(anchor) {
        var href = anchor.getAttribute('href') || '';
        if (!href) {
            return null;
        }

        if (/^tel:/i.test(href)) {
            return 'LUMN_PHONE_CLICK';
        }
        if (/^mailto:/i.test(href)) {
            return 'LUMN_EMAIL_CLICK';
        }

        var absolute;
        try {
            absolute = new window.URL(href, window.location.href);
        } catch (e) {
            return null; // not a parseable absolute/relative URL (e.g. "#", "javascript:") - not our concern
        }

        if (isKnownAppointmentUrl(absolute)) {
            return 'LUMN_APPOINTMENT_CLICK';
        }
        if (isKnownDirectionsUrl(absolute)) {
            return 'LUMN_DIRECTIONS_CLICK';
        }

        return null;
    }

    function normalizeForCompare(url) {
        return (url.origin + url.pathname).replace(/\/+$/, '').toLowerCase();
    }

    // Matches against this site's actually-configured Appointments
    // link(s) - the site-wide [lumn_social_url name="appointments"] URL
    // and/or any Practice Location's per-location override (see
    // lumn_ut_tracking_known_appointment_urls() in register/tracking.php)
    // - never a guess based on link text or a hardcoded third-party
    // booking domain.
    function isKnownAppointmentUrl(url) {
        var known = config.appointmentUrls || [];
        if (!known.length) {
            return false;
        }
        var candidate = normalizeForCompare(url);
        for (var i = 0; i < known.length; i++) {
            var knownUrl;
            try {
                knownUrl = new window.URL(known[i], window.location.href);
            } catch (e) {
                continue; // skip a malformed configured URL rather than throwing
            }
            if (normalizeForCompare(knownUrl) === candidate) {
                return true;
            }
        }
        return false;
    }

    // Hosts where any path counts as a directions/maps link.
    var MAPS_HOSTS_ANY_PATH = ['maps.google.com', 'maps.apple.com', 'maps.app.goo.gl', 'waze.com', 'www.waze.com'];
    // Hosts that also serve non-map content - only the /maps path counts.
    var MAPS_HOSTS_REQUIRE_MAPS_PATH = ['google.com', 'www.google.com', 'goo.gl', 'bing.com', 'www.bing.com'];

    function isKnownDirectionsUrl(url) {
        var host = url.hostname.toLowerCase();
        if (MAPS_HOSTS_ANY_PATH.indexOf(host) !== -1) {
            return true;
        }
        if (MAPS_HOSTS_REQUIRE_MAPS_PATH.indexOf(host) !== -1) {
            return /^\/maps(\/|$)/i.test(url.pathname);
        }
        return false;
    }

    // ---- click handling --------------------------------------------------

    function handleClick(nativeEvent) {
        var target = nativeEvent.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        // Explicit always takes precedence - checked first, and if an
        // ancestor carries data-lumn-event, automatic classification is
        // never attempted for this click (see precedence note above).
        var explicitEl = target.closest('[data-lumn-event]');
        if (explicitEl) {
            var rawValue = explicitEl.getAttribute('data-lumn-event');

            if (!LumnTracking.isFeatureEnabled('event_tracking')) {
                LumnTracking.reportSuppressed(rawValue, 'Explicit Event Tracking is disabled.');
                return;
            }

            var eventKey = resolveExplicitEventKey(rawValue);
            if (!eventKey) {
                LumnTracking.reportSuppressed(rawValue, 'Unrecognized data-lumn-event value - no event was sent.');
                return;
            }

            LumnTracking.pushEvent(eventKey, readExplicitParams(explicitEl), 'Explicit data-lumn-event');
            return;
        }

        var anchor = target.closest('a[href]');
        if (!anchor) {
            return;
        }

        var autoKey = classifyAnchor(anchor);
        if (!autoKey) {
            return;
        }

        LumnTracking.pushEvent(autoKey, {}, AUTOMATIC_SOURCE_LABELS[autoKey] || 'Automatic detection');
    }

    document.addEventListener('click', handleClick, true);
})(window, document);

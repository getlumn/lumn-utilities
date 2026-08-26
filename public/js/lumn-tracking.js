/**
 * LUMN Tracking / SEO Tools - safe data-layer abstraction (front end).
 *
 * This file is only ever enqueued by register/tracking.php when the
 * master "Enable LUMN SEO & Tracking" switch is on (see
 * lumn_ut_tracking_public_scripts()) - a disabled/default install never
 * loads this script and never touches window.dataLayer. See
 * docs/TRACKING.md for the full developer guide.
 *
 * This file only provides the shared, safe primitive other modules call:
 * window.LumnTracking.pushEvent(eventKey, params). Detection of *when* to
 * call it - automatic classification of tel:/mailto:/maps/appointment
 * links, and the explicit data-lumn-event markup mechanism - lives in
 * public/js/lumn-tracking-events.js, which depends on this file.
 */
(function (window) {
    'use strict';

    var config = window.lumnTrackingConfig || {
        enabled: false,
        features: {},
        events: {},
        forbiddenParamKeys: [],
        appointmentUrls: [],
        debug: false
    };

    function isScalar(value) {
        var type = typeof value;
        return type === 'string' || type === 'number' || type === 'boolean';
    }

    // Mirrors the allowlist/denylist rules enforced server-side in
    // lumn_ut_tracking_build_payload() (register/tracking.php): only
    // params the event definition explicitly declares are considered,
    // the shared PII/PHI denylist is checked even on declared keys, and
    // only scalar values are ever forwarded.
    function buildPayload(eventDef, extraParams) {
        var payload = {
            event: eventDef.name,
            lumn_event_category: eventDef.category,
            lumn_event_action: eventDef.action
        };

        var allowedKeys = eventDef.params || [];
        var forbiddenKeys = config.forbiddenParamKeys || [];
        extraParams = extraParams || {};

        for (var key in extraParams) {
            if (!Object.prototype.hasOwnProperty.call(extraParams, key)) {
                continue;
            }
            var value = extraParams[key];
            if (value === undefined || value === null || value === '') {
                continue; // nothing supplied for this optional param
            }
            if (allowedKeys.indexOf(key) === -1) {
                continue; // not declared for this event - dropped, not passed through
            }
            if (forbiddenKeys.indexOf(key.toLowerCase()) !== -1) {
                continue; // defense-in-depth PII/PHI guard, even on declared keys
            }
            if (!isScalar(value)) {
                continue; // arrays/objects (e.g. a raw form-fields dump) are never accepted
            }
            payload[key] = value;
        }

        return payload;
    }

    // ---- cross-request event relay (Step 3) ----------------------------
    //
    // Reads/clears the short-lived, non-sensitive cookie a server-side
    // hook may have queued via lumn_ut_tracking_relay_event() (PHP, see
    // register/tracking.php) when it couldn't reach this page directly -
    // most notably a form plugin's "submission saved" hook firing on a
    // request that then redirects to a different confirmation page/URL.
    // See docs/TRACKING.md "Form tracking" for the full explanation of
    // when/why this is needed instead of a direct pushEvent() call.

    var RELAY_COOKIE_NAME = 'lumn_ut_pending_form_event';

    function readRelayCookie() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + RELAY_COOKIE_NAME + '=([^;]*)'));
        if (!match) {
            return null;
        }
        try {
            return decodeURIComponent(match[1]);
        } catch (e) {
            return null;
        }
    }

    function clearRelayCookie() {
        document.cookie = RELAY_COOKIE_NAME + '=; Max-Age=0; path=/';
    }

    // ---- debugger (Step 1 architecture, expanded in Step 2) -----------
    //
    // Every call is a no-op unless the "Debugger" feature toggle is on
    // (config.debug) - no console output and no browser events are ever
    // produced otherwise, so there is no noisy production logging when
    // Debug Mode is disabled.

    function label(key, eventDef) {
        return eventDef ? eventDef.name : key;
    }

    // status is 'fired' or 'suppressed'. detail is a plain object of
    // extra fields to log (payload params for 'fired', a human-readable
    // reason for 'suppressed').
    function debugLog(status, key, eventDef, detail) {
        if (!config.debug) {
            return;
        }

        var title = '[LUMN Tracking] ' + (status === 'fired' ? 'Event detected' : 'Event suppressed') + ': ' + label(key, eventDef);

        if (window.console) {
            var useGroup = typeof console.groupCollapsed === 'function' && typeof console.groupEnd === 'function';
            if (useGroup) {
                console.groupCollapsed(title);
            } else if (console.debug) {
                console.debug(title);
            }

            if (eventDef) {
                console.log('Category:', eventDef.category);
                console.log('Action:', eventDef.action);
            }
            if (detail) {
                for (var k in detail) {
                    if (Object.prototype.hasOwnProperty.call(detail, k)) {
                        console.log(k + ':', detail[k]);
                    }
                }
            }

            if (useGroup) {
                console.groupEnd();
            }
        }

        if (typeof window.CustomEvent === 'function') {
            var eventName = status === 'fired' ? 'lumn:tracking:event' : 'lumn:tracking:suppressed';
            window.dispatchEvent(new window.CustomEvent(eventName, {
                detail: { key: key, event: eventDef ? eventDef.name : null, detail: detail || {} }
            }));
        }
    }

    var LumnTracking = {
        // Mirrors lumn_ut_tracking_feature_enabled() server-side: true only
        // when the master switch AND the named feature toggle are both on.
        isFeatureEnabled: function (feature) {
            return !!(config.enabled && config.features && config.features[feature]);
        },

        // Read-only accessor for other LUMN scripts (e.g.
        // lumn-tracking-events.js) that need the localized config - e.g.
        // the event registry or the configured appointment URLs -
        // without reaching into window.lumnTrackingConfig directly.
        getConfig: function () {
            return config;
        },

        /**
         * Pushes one standardized LUMN event to window.dataLayer.
         *
         * eventKey must be a key from the LUMN event registry (e.g.
         * 'LUMN_PHONE_CLICK') that the server localized into
         * config.events - an unrecognized key silently no-ops. Fails
         * closed the same way on every other axis: tracking disabled
         * overall, or the specific feature that event belongs to
         * disabled, both silently no-op rather than pushing anything.
         *
         * window.dataLayer is only ever created here, lazily, the first
         * time an event actually gets pushed - never on script load.
         *
         * Returns true if an event was pushed, false if it was
         * suppressed.
         */
        pushEvent: function (eventKey, extraParams) {
            if (!config.enabled) {
                return false;
            }

            var eventDef = config.events ? config.events[eventKey] : null;
            if (!eventDef) {
                debugLog('suppressed', eventKey, null, { Reason: 'Unrecognized LUMN event key.' });
                return false;
            }

            if (!this.isFeatureEnabled(eventDef.feature)) {
                debugLog('suppressed', eventKey, eventDef, { Reason: 'Feature "' + eventDef.feature + '" is disabled.' });
                return false;
            }

            var payload = buildPayload(eventDef, extraParams);

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(payload);

            var logDetail = {};
            for (var k in payload) {
                if (Object.prototype.hasOwnProperty.call(payload, k) && k !== 'event' && k !== 'lumn_event_category' && k !== 'lumn_event_action') {
                    logDetail[k] = payload[k];
                }
            }
            debugLog('fired', eventKey, eventDef, logDetail);

            return true;
        },

        // For callers that decide NOT to call pushEvent() for a reason
        // pushEvent() itself has no way to know (e.g. a gate specific to
        // how the event was detected, rather than to the event itself) -
        // lets the debugger still show why nothing happened. No-ops
        // unless the Debugger feature is on, same as every debug path
        // above.
        reportSuppressed: function (eventKeyOrRaw, reason) {
            var eventDef = config.events ? config.events[eventKeyOrRaw] : null;
            debugLog('suppressed', eventKeyOrRaw, eventDef, { Reason: reason });
        },

        /**
         * Reads and clears the relay cookie (if any) and pushes every
         * queued event through the normal pushEvent() - so a relayed
         * event is subject to the exact same fail-closed feature check
         * and param filtering as any other event, never a bypass.
         *
         * Called automatically once, at script load, from the top-level
         * document only (see the guard below) - covers a full page
         * navigation (a plain form postback, or a redirect to a
         * confirmation/thank-you page). Also safe to call again any time
         * (e.g. public/js/lumn-tracking-forms.js calls it in response to
         * a provider's own "confirmation shown" event, for the AJAX
         * case) - a second call is a no-op once the cookie has already
         * been cleared by the first.
         *
         * Returns how many events were actually pushed.
         */
        consumeRelay: function () {
            var raw = readRelayCookie();
            if (!raw) {
                return 0;
            }

            clearRelayCookie(); // clear before processing - never re-process the same queue twice

            var queue;
            try {
                queue = JSON.parse(raw);
            } catch (e) {
                return 0;
            }
            if (!Array.isArray(queue)) {
                return 0;
            }

            var fired = 0;
            for (var i = 0; i < queue.length; i++) {
                var item = queue[i];
                if (item && typeof item.eventKey === 'string' && this.pushEvent(item.eventKey, item.params || {})) {
                    fired++;
                }
            }
            return fired;
        }
    };

    window.LumnTracking = LumnTracking;

    // Only auto-consume from the top-level document. A provider's AJAX
    // submission mechanism (e.g. Gravity Forms' hidden confirmation
    // iframe) re-renders this entire script inside its own throwaway
    // document; consuming the relay cookie there would clear it before
    // the *visible* parent page ever gets a chance to see the event - see
    // docs/TRACKING.md "Form tracking" for the full explanation. The
    // parent page still gets the event promptly via
    // public/js/lumn-tracking-forms.js's provider-specific listeners.
    if (window.self === window.top) {
        LumnTracking.consumeRelay();
    }
})(window);

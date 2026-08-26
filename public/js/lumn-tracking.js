/**
 * LUMN Tracking / SEO Tools - safe data-layer abstraction (front end).
 *
 * This file is only ever enqueued by register/tracking.php when the
 * master "Enable LUMN SEO & Tracking" switch is on (see
 * lumn_ut_tracking_public_scripts()) - a disabled/default install never
 * loads this script and never touches window.dataLayer. See
 * docs/TRACKING.md for the full developer guide.
 *
 * Nothing in this file scans the DOM or attaches any click/submit
 * listeners - that is intentionally left to future feature-specific
 * modules (phone click tracking, form tracking, etc.), each gated on its
 * own feature toggle. This file only provides the shared, safe primitive
 * those modules will call: window.LumnTracking.pushEvent(eventKey, params).
 *
 * Future modules are expected to read tracked elements via a
 * `data-lumn-event="<event_key_or_name>"` attribute (optionally paired
 * with `data-lumn-location`, `data-lumn-component`, etc.) and pass those
 * values straight through as `params` to pushEvent() - so markup-driven
 * tracking and any other future caller share this exact same validation
 * path rather than becoming a second, incompatible tracking system.
 */
(function (window) {
    'use strict';

    var config = window.lumnTrackingConfig || {
        enabled: false,
        features: {},
        events: {},
        forbiddenParamKeys: [],
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
            if (allowedKeys.indexOf(key) === -1) {
                continue; // not declared for this event - dropped, not passed through
            }
            if (forbiddenKeys.indexOf(key.toLowerCase()) !== -1) {
                continue; // defense-in-depth PII/PHI guard, even on declared keys
            }
            if (!isScalar(extraParams[key])) {
                continue; // arrays/objects (e.g. a raw form-fields dump) are never accepted
            }
            payload[key] = extraParams[key];
        }

        return payload;
    }

    function dispatchDebugEvent(eventKey, payload) {
        if (!config.debug) {
            return;
        }
        if (window.console && typeof window.console.debug === 'function') {
            window.console.debug('[LUMN Tracking]', eventKey, payload);
        }
        if (typeof window.CustomEvent === 'function') {
            window.dispatchEvent(new window.CustomEvent('lumn:tracking:event', {
                detail: { key: eventKey, payload: payload }
            }));
        }
    }

    var LumnTracking = {
        // Mirrors lumn_ut_tracking_feature_enabled() server-side: true only
        // when the master switch AND the named feature toggle are both on.
        isFeatureEnabled: function (feature) {
            return !!(config.enabled && config.features && config.features[feature]);
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
                return false;
            }

            if (!this.isFeatureEnabled(eventDef.feature)) {
                return false;
            }

            var payload = buildPayload(eventDef, extraParams);

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(payload);

            dispatchDebugEvent(eventKey, payload);

            return true;
        }
    };

    window.LumnTracking = LumnTracking;
})(window);

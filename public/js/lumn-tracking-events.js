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
 * 2. Suppressed: the clicked element (or its nearest ancestor) carries
 *    data-lumn-track="false". No automatic classification is attempted
 *    (explicit, above, still works even on a suppressed element - this
 *    only opts a link out of *automatic* classification).
 * 3. Globally excluded (Step 6): the link's own URL path starts with an
 *    admin-configured Global URL Exclusion (register/tracking-config.php)
 *    - the URL-pattern-based counterpart to data-lumn-track="false",
 *    for excluding a whole section of the site without tagging every
 *    link in it. Same rule as suppression: never blocks an explicit
 *    data-lumn-event, only automatic classification.
 * 4. Automatic, for a plain <a href="..."> with no data-lumn-event, no
 *    data-lumn-track="false", and no matching Global URL Exclusion, tried
 *    in this fixed order - the first match wins, and only one event is
 *    ever produced per click (see docs/TRACKING.md "Duplicate-event
 *    prevention (Step 5)"):
 *      a. tel:                                    -> LUMN_PHONE_CLICK
 *      b. mailto:                                  -> LUMN_EMAIL_CLICK
 *      c. sms:                                     -> LUMN_SMS_CLICK
 *      d. matches a configured Appointments link    -> LUMN_APPOINTMENT_CLICK
 *         (site-wide or per-location, exact match)
 *      e. matches a configured Appointment URL       -> LUMN_APPOINTMENT_CLICK
 *         pattern or scheduling-provider domain
 *         (only attempted when Automatic CTA
 *         Classification is on)
 *      f. known maps/directions domain               -> LUMN_DIRECTIONS_CLICK
 *      g. known download-file extension               -> LUMN_FILE_DOWNLOAD
 *      h. any other cross-origin link, unless its      -> LUMN_EXTERNAL_LINK
 *         domain is excluded
 *    A link that matches none of these produces no event - an ambiguous
 *    interaction is never guessed at.
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
        'event_tracking',
        'download_tracking',
        'external_link_tracking',
        'cta_classification'
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
        LUMN_SMS_CLICK: 'Automatic SMS detection',
        LUMN_APPOINTMENT_CLICK: 'Automatic appointment detection',
        LUMN_DIRECTIONS_CLICK: 'Automatic directions detection',
        LUMN_FILE_DOWNLOAD: 'Automatic download detection',
        LUMN_EXTERNAL_LINK: 'Automatic external-link detection'
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
        if (/^sms:/i.test(href)) {
            return 'LUMN_SMS_CLICK';
        }
        // javascript: parses "successfully" via new URL() with an empty
        // hostname, which would otherwise look like an external link
        // below - explicitly not our concern, same as a bare "#".
        if (/^javascript:/i.test(href)) {
            return null;
        }

        var absolute;
        try {
            absolute = new window.URL(href, window.location.href);
        } catch (e) {
            return null; // not a parseable absolute/relative URL - not our concern
        }

        // This site's own exact configured Appointments link(s) - always
        // checked; gated only by Appointment Click Tracking itself (via
        // pushEvent's own feature check), same as Step 2.
        if (isKnownAppointmentUrl(absolute)) {
            return 'LUMN_APPOINTMENT_CLICK';
        }

        // Broader pattern/domain-based appointment matching (Step 5) -
        // only attempted when Automatic CTA Classification is on. If it's
        // off, a link that would have matched here simply falls through
        // to directions/download/external classification instead, per
        // docs/TRACKING.md "Automatic CTA classification".
        if (LumnTracking.isFeatureEnabled('cta_classification')) {
            if (matchesAppointmentUrlPattern(absolute) || matchesAppointmentDomain(absolute)) {
                return 'LUMN_APPOINTMENT_CLICK';
            }
        }

        if (isKnownDirectionsUrl(absolute)) {
            return 'LUMN_DIRECTIONS_CLICK';
        }

        if (isDownloadUrl(absolute)) {
            return 'LUMN_FILE_DOWNLOAD';
        }

        if (isExternalUrl(absolute) && !isExcludedExternalDomain(absolute)) {
            return 'LUMN_EXTERNAL_LINK';
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

    // Path-prefix match against admin-configured "Appointment URL
    // Patterns" (register/engagement-tracking.php) - e.g. a configured
    // "/schedule/" matches "/schedule/downtown-office/" too. Path-only:
    // never matches based on link text or page content.
    function matchesAppointmentUrlPattern(url) {
        var patterns = config.appointmentUrlPatterns || [];
        if (!patterns.length) {
            return false;
        }
        var path = url.pathname.toLowerCase();
        for (var i = 0; i < patterns.length; i++) {
            var pattern = String(patterns[i] || '').toLowerCase();
            if (pattern && path.indexOf(pattern) === 0) {
                return true;
            }
        }
        return false;
    }

    // Exact-or-subdomain match against admin-configured
    // "Appointment / Scheduling Domains" - e.g. a configured
    // "scheduler.example.com" also matches "book.scheduler.example.com".
    function matchesAppointmentDomain(url) {
        return hostMatchesAny(url.hostname, config.appointmentDomains);
    }

    function isExcludedExternalDomain(url) {
        return hostMatchesAny(url.hostname, config.externalLinkExcludedDomains);
    }

    // Path-prefix match against admin-configured Global URL Exclusions
    // (register/tracking-config.php) - e.g. a configured "/staff/"
    // suppresses automatic classification for any link under
    // "/staff/...", on this site only (an absolute link to a DIFFERENT
    // site's "/staff/" path is unaffected). Only ever checked against
    // internal-looking paths; a link that fails to parse is never
    // excluded by this (classifyAnchor()'s own try/catch already
    // handles that case downstream).
    function isGloballyExcludedUrl(anchor) {
        var exclusions = config.globalUrlExclusions || [];
        if (!exclusions.length) {
            return false;
        }
        var href = anchor.getAttribute('href') || '';
        var absolute;
        try {
            absolute = new window.URL(href, window.location.href);
        } catch (e) {
            return false;
        }
        var path = absolute.pathname.toLowerCase();
        for (var i = 0; i < exclusions.length; i++) {
            var prefix = String(exclusions[i] || '').toLowerCase();
            if (prefix && path.indexOf(prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    function hostMatchesAny(hostname, domainList) {
        var domains = domainList || [];
        if (!domains.length) {
            return false;
        }
        var host = String(hostname || '').toLowerCase();
        for (var i = 0; i < domains.length; i++) {
            var domain = String(domains[i] || '').toLowerCase();
            if (domain && (host === domain || host.slice(-(domain.length + 1)) === '.' + domain)) {
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

    // Path fragments that mean "this is WordPress plumbing, not a
    // document a visitor deliberately downloaded" - see
    // docs/TRACKING.md "Download classification". Checked before the
    // extension match below so e.g. a query-exporting admin-ajax.php
    // action can never be misread as a download.
    var DOWNLOAD_EXCLUDED_PATH_FRAGMENTS = ['/wp-admin/', '/wp-json/', '/wp-cron.php', 'admin-ajax.php', '/feed/'];

    function isExcludedDownloadPath(url) {
        var path = url.pathname.toLowerCase();
        for (var i = 0; i < DOWNLOAD_EXCLUDED_PATH_FRAGMENTS.length; i++) {
            if (path.indexOf(DOWNLOAD_EXCLUDED_PATH_FRAGMENTS[i]) !== -1) {
                return true;
            }
        }
        if (url.searchParams && (url.searchParams.has('preview') || url.searchParams.has('doing_wp_cron'))) {
            return true;
        }
        return false;
    }

    // The file extension (lowercase, no dot) at the end of the URL's
    // path, e.g. "pdf" for "/forms/new-patient-forms.PDF" - or null if
    // the path doesn't end in one at all.
    function extensionOf(url) {
        var match = /\.([a-z0-9]{2,5})$/i.exec(url.pathname);
        return match ? match[1].toLowerCase() : null;
    }

    function isDownloadUrl(url) {
        if (isExcludedDownloadPath(url)) {
            return false;
        }
        var ext = extensionOf(url);
        if (!ext) {
            return false;
        }
        var extensions = config.downloadExtensions || [];
        return extensions.indexOf(ext) !== -1;
    }

    function isExternalUrl(url) {
        return url.hostname.toLowerCase() !== window.location.hostname.toLowerCase();
    }

    // Safe metadata for an automatically-detected download - file type +
    // just the final path segment as a "name", never the full URL (no
    // query string, no host, no directory path) - see docs/TRACKING.md
    // "PII / PHI restrictions".
    function downloadParamsFor(url) {
        var ext = extensionOf(url) || '';
        var segments = url.pathname.split('/');
        var last = segments.length ? segments[segments.length - 1] : '';
        var name = '';
        try {
            name = decodeURIComponent(last);
        } catch (e) {
            name = last;
        }
        return { lumn_file_type: ext, lumn_file_name: name };
    }

    // Safe metadata for an automatically-detected external link - the
    // destination hostname only, never the full URL (no path, no query
    // string, no fragment).
    function externalLinkParamsFor(url) {
        return { lumn_external_domain: url.hostname.toLowerCase() };
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

        // data-lumn-track="false" opts an element out of *automatic*
        // classification only - an explicit data-lumn-event (checked
        // above) always still fires regardless. Useful for a link that's
        // technically a phone/download/external link but shouldn't count
        // as a marketing conversion. See docs/TRACKING.md "Suppressing
        // automatic tracking".
        if (target.closest('[data-lumn-track="false"]')) {
            return;
        }

        var anchor = target.closest('a[href]');
        if (!anchor) {
            return;
        }

        // Global URL Exclusions (Step 6) - a path-prefix list that
        // suppresses ALL automatic classification for a matching link,
        // site-wide, without tagging every link individually with
        // data-lumn-track="false". Like that attribute, this never
        // blocks an explicit data-lumn-event (already handled above,
        // before this point is ever reached).
        if (isGloballyExcludedUrl(anchor)) {
            return;
        }

        var autoKey = classifyAnchor(anchor);
        if (!autoKey) {
            return;
        }

        var params = {};
        if (autoKey === 'LUMN_FILE_DOWNLOAD' || autoKey === 'LUMN_EXTERNAL_LINK') {
            var absolute;
            try {
                absolute = new window.URL(anchor.getAttribute('href') || '', window.location.href);
            } catch (e) {
                absolute = null;
            }
            if (absolute) {
                params = autoKey === 'LUMN_FILE_DOWNLOAD' ? downloadParamsFor(absolute) : externalLinkParamsFor(absolute);
            }
        }

        LumnTracking.pushEvent(autoKey, params, AUTOMATIC_SOURCE_LABELS[autoKey] || 'Automatic detection');
    }

    document.addEventListener('click', handleClick, true);
})(window, document);

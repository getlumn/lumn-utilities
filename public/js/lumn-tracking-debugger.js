/**
 * LUMN Tracking Debugger - front-end overlay panel (Step 4).
 *
 * Only ever enqueued for an authenticated, capability-checked
 * administrator who has explicitly activated it (see
 * lumn_ut_debug_overlay_should_render() in register/tracking-debugger.php)
 * - a normal site visitor never loads this file, never gets this UI, and
 * this file never sends anything anywhere except a deliberate,
 * explicitly-confirmed Test Event through the exact same
 * LumnTracking.pushEvent() path (and the exact same PII filtering) as
 * every other LUMN event. See docs/TRACKING.md "Debugger, catalog,
 * health checker, and GTM guide".
 *
 * Entirely client-side and in-memory: the Recent Events feed is a plain
 * JS array, reset on every page load - nothing is written to
 * localStorage, a cookie, or any server-side store to build it. It
 * simply listens for the lumn:tracking:event / lumn:tracking:suppressed
 * browser events the tracking core (public/js/lumn-tracking.js) already
 * dispatches whenever the Debugger *feature* toggle is on - this overlay
 * doesn't change what gets dispatched, it only visualizes it.
 */
(function (window, document) {
    'use strict';

    var config = window.lumnTrackingDebuggerConfig;
    if (!config) {
        return;
    }

    if (window.__lumnTrackingDebuggerBound) {
        return;
    }
    window.__lumnTrackingDebuggerBound = true;

    var events = [];
    var MAX_EVENTS = 50;

    // ---- shared safe-value helpers (mirrors the server-side denylist
    // idea, but this is display-only - the real enforcement already
    // happened before any of this data reached the browser) ------------

    function text(str) {
        return document.createTextNode(str === undefined || str === null ? '' : String(str));
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var key in attrs) {
                if (Object.prototype.hasOwnProperty.call(attrs, key)) {
                    if (key === 'class') {
                        node.className = attrs[key];
                    } else {
                        node.setAttribute(key, attrs[key]);
                    }
                }
            }
        }
        (children || []).forEach(function (child) {
            if (child === null || child === undefined) {
                return;
            }
            node.appendChild(typeof child === 'string' ? text(child) : child);
        });
        return node;
    }

    // ---- shadow DOM host, so the host theme's CSS can never leak in
    // (or our styles leak out) -------------------------------------------

    var host = document.createElement('div');
    host.id = 'lumn-ut-debugger-host';
    var attachTarget = document.body || document.documentElement;
    attachTarget.appendChild(host);
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

    var STYLE = '' +
        ':host, .lumn-ut-dbg { all: initial; }' +
        '.lumn-ut-dbg * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }' +
        '.lumn-ut-dbg { position: fixed; z-index: 2147483000; bottom: 16px; right: 16px; width: 360px; max-height: 70vh; ' +
        'background: #12232b; color: #e8f0f4; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,0.35); font-size: 12px; ' +
        'display: flex; flex-direction: column; overflow: hidden; border: 1px solid #045a7b; }' +
        '.lumn-ut-dbg.lumn-ut-dbg-collapsed { max-height: none; }' +
        '.lumn-ut-dbg.lumn-ut-dbg-collapsed .lumn-ut-dbg-body { display: none; }' +
        '.lumn-ut-dbg-header { background: #045a7b; padding: 8px 10px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; }' +
        '.lumn-ut-dbg-title { font-weight: 600; font-size: 12px; color: #fff; }' +
        '.lumn-ut-dbg-body { overflow-y: auto; padding: 10px; }' +
        '.lumn-ut-dbg h4 { margin: 12px 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #7fd0f5; }' +
        '.lumn-ut-dbg h4:first-child { margin-top: 0; }' +
        '.lumn-ut-dbg-row { display: flex; justify-content: space-between; padding: 2px 0; }' +
        '.lumn-ut-dbg-ok { color: #6fe3a0; } .lumn-ut-dbg-bad { color: #ff9d9d; } .lumn-ut-dbg-muted { color: #9db3bd; }' +
        '.lumn-ut-dbg-event { border: 1px solid #234; border-radius: 4px; padding: 6px 8px; margin-bottom: 6px; cursor: pointer; }' +
        '.lumn-ut-dbg-event.suppressed { opacity: 0.65; border-style: dashed; }' +
        '.lumn-ut-dbg-event-name { font-weight: 600; }' +
        '.lumn-ut-dbg-event-time { color: #9db3bd; font-size: 10px; float: right; }' +
        '.lumn-ut-dbg-event-detail { display: none; margin-top: 6px; padding-top: 6px; border-top: 1px solid #234; }' +
        '.lumn-ut-dbg-event.expanded .lumn-ut-dbg-event-detail { display: block; }' +
        '.lumn-ut-dbg-btn { background: #00a0e3; color: #fff; border: 0; border-radius: 4px; padding: 5px 10px; font-size: 11px; cursor: pointer; }' +
        '.lumn-ut-dbg-btn:hover { background: #0087c1; }' +
        '.lumn-ut-dbg-btn-danger { background: #b3423d; }' +
        '.lumn-ut-dbg select, .lumn-ut-dbg-body a { color: #e8f0f4; font-size: 11px; }' +
        '.lumn-ut-dbg select { width: 100%; margin-bottom: 6px; background: #1c2f38; border: 1px solid #345; color: #e8f0f4; padding: 4px; border-radius: 4px; }' +
        '.lumn-ut-dbg-body a { color: #7fd0f5; }' +
        '.lumn-ut-dbg-scan-item { padding: 4px 0; border-bottom: 1px solid #1c2f38; }' +
        '.lumn-ut-dbg-empty { color: #9db3bd; font-style: italic; }';

    var styleEl = document.createElement('style');
    styleEl.textContent = STYLE;
    root.appendChild(styleEl);

    var panel = el('div', { class: 'lumn-ut-dbg' }, []);
    var header = el('div', { class: 'lumn-ut-dbg-header' }, [
        el('span', { class: 'lumn-ut-dbg-title' }, ['LUMN Tracking Debugger']),
        el('span', {}, ['▾'])
    ]);
    var body = el('div', { class: 'lumn-ut-dbg-body' }, []);
    panel.appendChild(header);
    panel.appendChild(body);
    root.appendChild(panel);

    header.addEventListener('click', function () {
        panel.classList.toggle('lumn-ut-dbg-collapsed');
    });

    // ---- status section --------------------------------------------------

    function detectGTM() {
        if (window.google_tag_manager) {
            return true;
        }
        if (Array.isArray(window.dataLayer)) {
            for (var i = 0; i < window.dataLayer.length; i++) {
                var entry = window.dataLayer[i];
                if (entry && (entry.event === 'gtm.js' || entry.event === 'gtm.dom' || entry['gtm.start'] !== undefined)) {
                    return true;
                }
            }
        }
        var scripts = document.getElementsByTagName('script');
        for (var j = 0; j < scripts.length; j++) {
            if (scripts[j].src && scripts[j].src.indexOf('googletagmanager.com/gtm.js') !== -1) {
                return true;
            }
        }
        return false;
    }

    function statusRow(label, ok) {
        return el('div', { class: 'lumn-ut-dbg-row' }, [
            el('span', {}, [label]),
            el('span', { class: ok ? 'lumn-ut-dbg-ok' : 'lumn-ut-dbg-bad' }, [ok ? '✓' : '✗'])
        ]);
    }

    function renderStatus() {
        var wrap = el('div', {}, []);
        wrap.appendChild(el('h4', {}, ['Status']));
        wrap.appendChild(statusRow('Master Tracking', !!config.masterEnabled));
        wrap.appendChild(statusRow('Debugger feature', !!config.debuggerFeatureEnabled));
        wrap.appendChild(statusRow('Data Layer available', Array.isArray(window.dataLayer) || !!config.masterEnabled));
        wrap.appendChild(statusRow('GTM detected', detectGTM()));
        for (var key in config.features) {
            if (Object.prototype.hasOwnProperty.call(config.features, key) && config.features[key].enabled) {
                wrap.appendChild(statusRow(config.features[key].label, true));
            }
        }
        if (!config.masterEnabled || !config.debuggerFeatureEnabled) {
            wrap.appendChild(el('p', { class: 'lumn-ut-dbg-muted' }, ['Recent Events will stay empty until both Master Tracking and the Debugger feature toggle are on.']));
        }
        return wrap;
    }

    // ---- recent events ------------------------------------------------

    var eventsListEl = el('div', {}, []);

    function paramLabel(key) {
        return key.replace(/^lumn_/, '').replace(/_/g, ' ');
    }

    function renderEventItem(item) {
        var time = new Date(item.at).toLocaleTimeString();
        var isSuppressed = item.status === 'suppressed';
        var row = el('div', { class: 'lumn-ut-dbg-event' + (isSuppressed ? ' suppressed' : '') }, []);

        var head = el('div', {}, [
            el('span', { class: 'lumn-ut-dbg-event-time' }, [time]),
            el('span', { class: 'lumn-ut-dbg-event-name' }, [(isSuppressed ? '⊘ ' : '● ') + (item.eventName || item.key)])
        ]);
        row.appendChild(head);

        if (item.source) {
            row.appendChild(el('div', { class: 'lumn-ut-dbg-muted' }, ['Source: ' + item.source]));
        }

        var detail = el('div', { class: 'lumn-ut-dbg-event-detail' }, []);
        var eventDef = config.events ? config.events[item.key] : null;
        if (isSuppressed) {
            detail.appendChild(el('div', {}, ['Reason: ' + (item.detail && item.detail.Reason ? item.detail.Reason : 'unknown')]));
        } else if (eventDef) {
            detail.appendChild(el('div', {}, ['Category: ' + eventDef.category]));
            detail.appendChild(el('div', {}, ['Action: ' + eventDef.action]));
            // Only ever display keys the event registry actually declares
            // for this event - never arbitrary properties off the raw
            // browser event, per docs/TRACKING.md "Debugger safety".
            (eventDef.params || []).forEach(function (paramKey) {
                if (item.detail && Object.prototype.hasOwnProperty.call(item.detail, paramKey) && item.detail[paramKey] !== undefined && item.detail[paramKey] !== '') {
                    detail.appendChild(el('div', {}, [paramLabel(paramKey) + ': ' + item.detail[paramKey]]));
                }
            });
        }
        row.appendChild(detail);

        row.addEventListener('click', function () {
            row.classList.toggle('expanded');
        });

        return row;
    }

    function renderEvents() {
        eventsListEl.innerHTML = '';
        if (!events.length) {
            eventsListEl.appendChild(el('p', { class: 'lumn-ut-dbg-empty' }, ['No events detected yet.']));
            return;
        }
        // Most recent first.
        for (var i = events.length - 1; i >= 0; i--) {
            eventsListEl.appendChild(renderEventItem(events[i]));
        }
    }

    function addEvent(status, detail) {
        events.push({
            at: Date.now(),
            status: status,
            key: detail.key,
            eventName: detail.event,
            source: detail.source,
            detail: detail.detail || {}
        });
        if (events.length > MAX_EVENTS) {
            events.shift();
        }
        renderEvents();
    }

    window.addEventListener('lumn:tracking:event', function (e) {
        addEvent('fired', e.detail || {});
    });
    window.addEventListener('lumn:tracking:suppressed', function (e) {
        addEvent('suppressed', e.detail || {});
    });

    // ---- test event tool ------------------------------------------------

    function renderTestTool() {
        var wrap = el('div', {}, []);
        wrap.appendChild(el('h4', {}, ['Test Event']));

        if (!window.LumnTracking) {
            wrap.appendChild(el('p', { class: 'lumn-ut-dbg-muted' }, ['Master Tracking must be on to send a test event.']));
            return wrap;
        }

        var select = el('select', {}, []);
        for (var key in config.events) {
            if (Object.prototype.hasOwnProperty.call(config.events, key)) {
                select.appendChild(el('option', { value: key }, [config.events[key].name]));
            }
        }
        wrap.appendChild(select);

        var warning = el('p', { class: 'lumn-ut-dbg-muted' }, [
            'This sends a real event through the normal data layer, clearly flagged lumn_debug: true. If this site already has a GTM container configured, it may still see it - do not use this to test something you don\'t want an existing tag to react to.'
        ]);
        wrap.appendChild(warning);

        var button = el('button', { type: 'button', class: 'lumn-ut-dbg-btn' }, ['Send Test Event']);
        button.addEventListener('click', function () {
            var eventKey = select.value;
            var eventName = config.events[eventKey] ? config.events[eventKey].name : eventKey;
            var confirmed = window.confirm(
                'Send a real "' + eventName + '" test event? It will be marked lumn_debug: true, but an existing GTM container may still act on it.'
            );
            if (!confirmed) {
                return;
            }
            window.LumnTracking.pushEvent(eventKey, {
                lumn_debug: true,
                lumn_location: 'debugger_test',
                lumn_component: 'test_event_tool'
            }, 'Debugger Test Event');
        });
        wrap.appendChild(button);

        return wrap;
    }

    // ---- page scanner ---------------------------------------------------
    //
    // Read-only preview logic, deliberately NOT reused from
    // public/js/lumn-tracking-events.js: that script self-bails (attaches
    // no listener at all) whenever every relevant feature is off, or
    // isn't loaded at all when Master Tracking is off - but the whole
    // point of this scanner is to work even then, to help answer "why
    // isn't this tracking". Mirrors the same classification rules; keep
    // both in sync if those rules change (see docs/TRACKING.md).

    var MAPS_HOSTS_ANY_PATH = ['maps.google.com', 'maps.apple.com', 'maps.app.goo.gl', 'waze.com', 'www.waze.com'];
    var MAPS_HOSTS_REQUIRE_MAPS_PATH = ['google.com', 'www.google.com', 'goo.gl', 'bing.com', 'www.bing.com'];

    function normalizeForCompare(url) {
        return (url.origin + url.pathname).replace(/\/+$/, '').toLowerCase();
    }

    function isKnownAppointmentUrl(url) {
        var known = config.appointmentUrls || [];
        var candidate = normalizeForCompare(url);
        for (var i = 0; i < known.length; i++) {
            try {
                if (normalizeForCompare(new window.URL(known[i], window.location.href)) === candidate) {
                    return true;
                }
            } catch (e) { /* skip malformed configured URL */ }
        }
        return false;
    }

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

    function classifyAnchorForPreview(anchor) {
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
            return null;
        }
        if (isKnownAppointmentUrl(absolute)) {
            return 'LUMN_APPOINTMENT_CLICK';
        }
        if (isKnownDirectionsUrl(absolute)) {
            return 'LUMN_DIRECTIONS_CLICK';
        }
        return null;
    }

    function resolveExplicitEventKeyForPreview(rawValue) {
        var needle = rawValue ? String(rawValue).trim() : '';
        if (!needle) {
            return null;
        }
        var evs = config.events || {};
        if (Object.prototype.hasOwnProperty.call(evs, needle.toUpperCase())) {
            return needle.toUpperCase();
        }
        var lower = needle.toLowerCase();
        for (var key in evs) {
            if (Object.prototype.hasOwnProperty.call(evs, key) && (evs[key].name === lower || evs[key].action === lower)) {
                return key;
            }
        }
        return null;
    }

    function elementLabel(elm) {
        var label = elm.getAttribute('aria-label') || (elm.textContent || '').trim();
        if (label.length > 40) {
            label = label.slice(0, 40) + '…';
        }
        return label || elm.tagName.toLowerCase();
    }

    function scanPage() {
        var results = [];
        var seen = new Set ? new Set() : null;

        function markSeen(elm) {
            if (seen) {
                if (seen.has(elm)) {
                    return false;
                }
                seen.add(elm);
            }
            return true;
        }

        document.querySelectorAll('[data-lumn-event]').forEach(function (elm) {
            if (!markSeen(elm)) {
                return;
            }
            var raw = elm.getAttribute('data-lumn-event');
            var resolved = resolveExplicitEventKeyForPreview(raw);
            results.push({
                label: elementLabel(elm),
                event: resolved ? config.events[resolved].name : null,
                raw: raw,
                source: 'Explicit data-lumn-event',
                recognized: !!resolved
            });
        });

        document.querySelectorAll('a[href]').forEach(function (anchor) {
            if (!markSeen(anchor)) {
                return;
            }
            var key = classifyAnchorForPreview(anchor);
            if (!key) {
                return;
            }
            results.push({
                label: elementLabel(anchor),
                event: config.events[key] ? config.events[key].name : key,
                raw: null,
                source: 'Automatic detection',
                recognized: true
            });
        });

        return results;
    }

    function renderScanner() {
        var wrap = el('div', {}, []);
        wrap.appendChild(el('h4', {}, ['Current Page']));

        var resultsEl = el('div', {}, []);
        wrap.appendChild(resultsEl);

        var button = el('button', { type: 'button', class: 'lumn-ut-dbg-btn' }, ['Scan This Page']);
        button.addEventListener('click', function () {
            var results = scanPage();
            resultsEl.innerHTML = '';
            if (!results.length) {
                resultsEl.appendChild(el('p', { class: 'lumn-ut-dbg-empty' }, ['No LUMN-trackable elements found on this page.']));
                return;
            }
            results.forEach(function (r) {
                var line = el('div', { class: 'lumn-ut-dbg-scan-item' }, []);
                if (r.recognized) {
                    line.appendChild(el('div', {}, [r.label]));
                    line.appendChild(el('div', { class: 'lumn-ut-dbg-muted' }, ['Event: ' + r.event + ' - Source: ' + r.source]));
                } else {
                    line.appendChild(el('div', {}, [r.label]));
                    line.appendChild(el('div', { class: 'lumn-ut-dbg-bad' }, ['⚠ Unknown LUMN event: "' + r.raw + '" - not recognized by the current event registry, so it will never fire.']));
                }
                resultsEl.appendChild(line);
            });
        });
        wrap.appendChild(button);

        return wrap;
    }

    // ---- assemble -------------------------------------------------------

    body.appendChild(renderStatus());
    body.appendChild(el('h4', {}, ['Recent Events']));
    body.appendChild(eventsListEl);
    renderEvents();
    body.appendChild(renderTestTool());
    body.appendChild(renderScanner());

    var footer = el('div', {}, []);
    var offLink = el('a', { href: config.toggleOffUrl }, ['Disable debugging']);
    footer.appendChild(offLink);
    footer.appendChild(text(' · '));
    footer.appendChild(el('a', { href: config.settingsUrl }, ['SEO & Tracking settings']));
    body.appendChild(footer);
})(window, document);

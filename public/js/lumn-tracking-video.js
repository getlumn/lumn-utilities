/**
 * LUMN Tracking / SEO Tools - native HTML5 <video> engagement tracking
 * (Step 5).
 *
 * Depends on public/js/lumn-tracking.js (window.LumnTracking) and is only
 * ever enqueued alongside it when tracking is enabled overall (see
 * lumn_ut_tracking_public_scripts() in register/tracking.php). Self-bails
 * (no listener attached) when Video Tracking is off.
 *
 * ---------------------------------------------------------------------
 * Provider support
 * ---------------------------------------------------------------------
 * Only native HTML5 <video> elements are tracked in this step (the
 * WordPress core Video block, or any theme/plugin markup that renders a
 * real <video> tag - lumn_video_provider: "html5"). This plugin's own
 * dcmo-ut-su-lightbox block (a third-party "Shortcodes Ultimate" lightbox
 * wrapping a raw video file URL) was inspected before choosing this
 * implementation - see docs/TRACKING.md "Video providers" for why a
 * cross-origin iframe pointed at a raw file (its `type="iframe"` mode)
 * cannot be reliably tracked at all (no JS API access into it), and why
 * YouTube/Vimeo are deferred rather than built on brittle postMessage
 * sniffing or an unconditionally-loaded third-party API script.
 *
 * `providers` below is deliberately structured as a small registry so a
 * future provider (YouTube/Vimeo, using their own supported player APIs,
 * loaded only when that provider's markup is actually detected on the
 * page) can be added without restructuring this file.
 *
 * ---------------------------------------------------------------------
 * Duplicate-event prevention
 * ---------------------------------------------------------------------
 * Per-element session state (a WeakMap, not a DOM attribute) tracks
 * whether a video has already started/completed, and which progress
 * milestones have already fired:
 *   play (never started this session)      -> lumn_video_start
 *   play (started, not yet ended - resume)  -> nothing
 *   play (already ended - a replay)         -> lumn_video_start again (a
 *                                               fresh session - see
 *                                               docs/TRACKING.md)
 *   timeupdate crossing 25/50/75%           -> lumn_video_progress (each
 *                                               milestone at most once)
 *   ended                                    -> lumn_video_complete
 *
 * Native `play`/`pause`/`ended`/`timeupdate` events do not bubble, so
 * this uses capture-phase delegation on `document` (works for dynamically
 * inserted <video> elements automatically, with no MutationObserver and
 * no per-element listener setup).
 */
(function (window, document) {
    'use strict';

    var LumnTracking = window.LumnTracking;
    if (!LumnTracking) {
        return;
    }

    var config = LumnTracking.getConfig();
    if (!config.enabled || !LumnTracking.isFeatureEnabled('video_tracking')) {
        return;
    }

    if (window.__lumnTrackingVideoBound) {
        return;
    }
    window.__lumnTrackingVideoBound = true;

    var MILESTONES = [25, 50, 75];
    var state = typeof WeakMap === 'function' ? new WeakMap() : null;
    var fallbackState = []; // {el, data} pairs, only used if WeakMap is unavailable
    var idCounter = 0;

    function getState(el) {
        if (state) {
            var existing = state.get(el);
            if (!existing) {
                existing = { started: false, ended: false, milestones: {}, id: null };
                state.set(el, existing);
            }
            return existing;
        }
        for (var i = 0; i < fallbackState.length; i++) {
            if (fallbackState[i].el === el) {
                return fallbackState[i].data;
            }
        }
        var data = { started: false, ended: false, milestones: {}, id: null };
        fallbackState.push({ el: el, data: data });
        return data;
    }

    function sanitizeText(raw, maxLength) {
        var text = (raw || '').toString().replace(/[<>]/g, '').trim();
        return text.length > maxLength ? text.slice(0, maxLength) : text;
    }

    function videoIdFor(el, videoState) {
        if (videoState.id) {
            return videoState.id;
        }
        var explicit = el.getAttribute('data-lumn-video-id') || el.id;
        videoState.id = explicit ? sanitizeText(explicit, 80) : 'video-' + (++idCounter);
        return videoState.id;
    }

    function videoTitleFor(el) {
        var raw = el.getAttribute('data-lumn-video-title') || el.getAttribute('title') || el.getAttribute('aria-label') || '';
        return sanitizeText(raw, 120);
    }

    function baseParams(el, videoState) {
        return {
            lumn_video_provider: 'html5',
            lumn_video_id: videoIdFor(el, videoState),
            lumn_video_title: videoTitleFor(el)
        };
    }

    // ---- native HTML5 <video> provider -----------------------------------

    function handlePlay(nativeEvent) {
        var el = nativeEvent.target;
        if (!el || el.tagName !== 'VIDEO') {
            return;
        }

        // data-lumn-track="false" on the <video> itself or any ancestor
        // (e.g. a page builder's row/section wrapper around a decorative
        // background video) suppresses automatic tracking for it - same
        // attribute and same rule as automatic click classification (see
        // docs/TRACKING.md "Tracking overrides and precedence"). Checked
        // once here, on the first play: videoState.started is never set
        // true for a suppressed video, so handleTimeUpdate()/handleEnded()
        // - which both bail out unless started is true - naturally never
        // fire for it either, without needing their own separate check.
        if (el.closest && el.closest('[data-lumn-track="false"]')) {
            return;
        }

        var videoState = getState(el);

        if (videoState.started && !videoState.ended) {
            return; // a plain resume-from-pause - not a new start
        }

        // Either the first play ever, or a replay after a previous
        // completion - both count as a fresh playback session.
        videoState.started = true;
        videoState.ended = false;
        videoState.milestones = {};

        LumnTracking.pushEvent('LUMN_VIDEO_START', baseParams(el, videoState), 'Native HTML5 video');
    }

    function handleTimeUpdate(nativeEvent) {
        var el = nativeEvent.target;
        if (!el || el.tagName !== 'VIDEO') {
            return;
        }

        var videoState = getState(el);
        if (!videoState.started || videoState.ended) {
            return;
        }

        var duration = el.duration;
        if (!isFinite(duration) || duration <= 0) {
            return; // unknown/live-stream duration - milestones aren't meaningful
        }

        var percent = (el.currentTime / duration) * 100;

        for (var i = 0; i < MILESTONES.length; i++) {
            var milestone = MILESTONES[i];
            if (percent >= milestone && !videoState.milestones[milestone]) {
                videoState.milestones[milestone] = true;
                var params = baseParams(el, videoState);
                params.lumn_video_percent = milestone;
                LumnTracking.pushEvent('LUMN_VIDEO_PROGRESS', params, 'Native HTML5 video');
            }
        }
    }

    function handleEnded(nativeEvent) {
        var el = nativeEvent.target;
        if (!el || el.tagName !== 'VIDEO') {
            return;
        }

        var videoState = getState(el);
        if (!videoState.started || videoState.ended) {
            return;
        }

        videoState.ended = true;
        var params = baseParams(el, videoState);
        params.lumn_video_percent = 100;
        LumnTracking.pushEvent('LUMN_VIDEO_COMPLETE', params, 'Native HTML5 video');
    }

    var providers = {
        html5: {
            bind: function () {
                // capture: true - play/timeupdate/ended don't bubble, so
                // this is the only way delegation can observe them.
                document.addEventListener('play', handlePlay, true);
                document.addEventListener('timeupdate', handleTimeUpdate, true);
                document.addEventListener('ended', handleEnded, true);
            }
        }
        // Future providers (YouTube/Vimeo) register here, each loading
        // and binding only to their own markup/API - see file header.
    };

    providers.html5.bind();
})(window, document);

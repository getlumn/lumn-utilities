<?php
namespace Lumn\Utilities;

/**
 * LUMN Tracking Debugger, Event Catalog, Health Checker, and GTM Guide
 * (Step 4). See docs/TRACKING.md "Debugger, catalog, health checker, and
 * GTM guide" for the full developer guide.
 *
 * Four pieces, all read-only/diagnostic - none of them enable tracking,
 * inject GTM/GA4, or talk to any service outside this site:
 * - A front-end, admin-only overlay panel (public/js/lumn-tracking-debugger.js)
 *   that observes the same lumn:tracking:event / lumn:tracking:suppressed
 *   browser events the tracking core already dispatches (Steps 1-2) -
 *   entirely client-side, nothing new is stored server-side to build it.
 * - An Event Catalog admin tab, rendered directly from the Step 1 event
 *   registry.
 * - A Health Checker admin tab that inspects this plugin's own
 *   configuration (always) plus, only when an admin explicitly clicks
 *   "Run Health Check", a single local fetch of this site's own front
 *   page to look for a GTM container and any data-lumn-event attributes.
 * - A GTM Guide admin tab, generating recipes deterministically from the
 *   event registry (never manually duplicated per event).
 */

const LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG = 'lumn-ut-tracking-debugger';
const LUMN_UT_DEBUG_OVERLAY_USER_META = 'lumn_ut_debug_overlay_enabled';
const LUMN_UT_DEBUG_OVERLAY_QUERY_VAR = 'lumn_debug';
const LUMN_UT_DEBUG_OVERLAY_SCRIPT_HANDLE = 'lumn-ut-tracking-debugger';

// ---------------------------------------------------------------------
// Front-end overlay activation/authorization
// ---------------------------------------------------------------------

function lumn_ut_debug_overlay_user_enabled($user_id = null) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) {
        return false;
    }
    return (bool) get_user_meta($user_id, LUMN_UT_DEBUG_OVERLAY_USER_META, true);
}

/**
 * Whether the front-end Tracking Debugger overlay should render on this
 * request. Fails closed: requires an authenticated user with the
 * tracking capability, full stop - never activated for an anonymous or
 * unauthorized visitor, regardless of any query string. Two independent
 * ways an already-authorized admin can turn it on:
 *
 * - A persistent per-user preference (user meta), toggled via a
 *   nonce-verified admin-post action from the Debugger admin tab or the
 *   overlay's own button.
 * - A one-off `?lumn_debug=1` for the current page view only. This never
 *   persists anything and is not itself a state-changing action - it
 *   only decides whether to render extra HTML/JS for a viewer who has
 *   already passed the same capability check above, so it doesn't need a
 *   nonce (there's nothing here a forged link could make happen without
 *   that check).
 */
function lumn_ut_debug_overlay_should_render() {
    if (!is_user_logged_in() || !current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        return false;
    }

    if (lumn_ut_debug_overlay_user_enabled()) {
        return true;
    }

    return isset($_GET[LUMN_UT_DEBUG_OVERLAY_QUERY_VAR]) && $_GET[LUMN_UT_DEBUG_OVERLAY_QUERY_VAR] === '1';
}

function lumn_ut_debug_overlay_toggle_url($enable) {
    $url = add_query_arg(array(
        'action' => 'lumn_ut_toggle_debug_overlay',
        'enable' => $enable ? '1' : '0',
    ), admin_url('admin-post.php'));
    return wp_nonce_url($url, 'lumn_ut_toggle_debug_overlay');
}

add_action('admin_post_lumn_ut_toggle_debug_overlay', 'Lumn\Utilities\lumn_ut_handle_toggle_debug_overlay');
function lumn_ut_handle_toggle_debug_overlay() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_toggle_debug_overlay');

    $enable = isset($_GET['enable']) && $_GET['enable'] === '1';
    update_user_meta(get_current_user_id(), LUMN_UT_DEBUG_OVERLAY_USER_META, $enable);

    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=' . LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG));
    exit;
}

// ---------------------------------------------------------------------
// Front-end overlay script - only enqueued for an authorized, activated
// admin (see lumn_ut_debug_overlay_should_render() above). A normal
// visitor's page load never loads this script, attaches no listeners,
// and renders no UI.
// ---------------------------------------------------------------------

function lumn_ut_tracking_debugger_public_scripts() {
    if (!lumn_ut_debug_overlay_should_render()) {
        return;
    }

    $script_path = LUMN_UTILITIES_PLUGIN_PATH . 'public/js/lumn-tracking-debugger.js';
    if (!file_exists($script_path)) {
        return;
    }

    $deps = array();
    if (wp_script_is(LUMN_UT_TRACKING_SCRIPT_HANDLE, 'enqueued') || wp_script_is(LUMN_UT_TRACKING_SCRIPT_HANDLE, 'registered')) {
        $deps[] = LUMN_UT_TRACKING_SCRIPT_HANDLE; // ensures window.LumnTracking exists first, when tracking is enabled
    }

    wp_enqueue_script(
        LUMN_UT_DEBUG_OVERLAY_SCRIPT_HANDLE,
        plugins_url('public/js/lumn-tracking-debugger.js', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'),
        $deps,
        filemtime($script_path),
        true
    );

    $events_out = array();
    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        $events_out[$key] = array(
            'name' => $event['name'],
            'category' => $event['category'],
            'action' => $event['action'],
            'description' => $event['description'],
            'feature' => $event['feature'],
            'params' => array_merge(lumn_ut_tracking_base_event_params(), $event['params']),
        );
    }

    $features_out = array();
    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        $features_out[$key] = array('label' => $meta['label'], 'enabled' => lumn_ut_tracking_feature_enabled($key));
    }

    wp_localize_script(LUMN_UT_DEBUG_OVERLAY_SCRIPT_HANDLE, 'lumnTrackingDebuggerConfig', array(
        'masterEnabled' => lumn_ut_tracking_is_enabled(),
        'debuggerFeatureEnabled' => lumn_ut_tracking_feature_enabled('debugger'),
        'trackingScriptPresent' => in_array(LUMN_UT_TRACKING_SCRIPT_HANDLE, $deps, true),
        'events' => $events_out,
        'features' => $features_out,
        'appointmentUrls' => lumn_ut_tracking_known_appointment_urls(),
        'toggleOffUrl' => lumn_ut_debug_overlay_toggle_url(false),
        'settingsUrl' => admin_url('admin.php?page=' . LUMN_UT_TRACKING_PAGE_SLUG),
    ));
}
// Priority 20: after lumn_ut_tracking_public_scripts() (default priority
// 10), so wp_script_is( LUMN_UT_TRACKING_SCRIPT_HANDLE ) above correctly
// reflects whether the core tracking script was already enqueued this
// request.
add_action('wp_enqueue_scripts', 'Lumn\Utilities\lumn_ut_tracking_debugger_public_scripts', 20);

// ---------------------------------------------------------------------
// Health Checker
// ---------------------------------------------------------------------

const LUMN_UT_HEALTH_HOMEPAGE_CACHE_KEY = 'lumn_ut_health_homepage_html';
const LUMN_UT_HEALTH_HOMEPAGE_CACHE_TTL = 60; // seconds - just long enough to avoid re-fetching on every click while reviewing results

/**
 * Fetches this site's own front page HTML - never any other host - for
 * the GTM-detection and data-lumn-event-scan checks below. Cached
 * briefly so re-visiting the Health Check tab doesn't re-fetch every
 * time. Returns '' (not an error) on any failure, so callers can treat
 * "could not check" as its own state rather than crashing.
 */
function lumn_ut_health_fetch_homepage_html() {
    $cached = get_transient(LUMN_UT_HEALTH_HOMEPAGE_CACHE_KEY);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get(home_url('/'), array('timeout' => 5));
    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
        set_transient(LUMN_UT_HEALTH_HOMEPAGE_CACHE_KEY, '', LUMN_UT_HEALTH_HOMEPAGE_CACHE_TTL);
        return '';
    }

    $body = (string) wp_remote_retrieve_body($response);
    set_transient(LUMN_UT_HEALTH_HOMEPAGE_CACHE_KEY, $body, LUMN_UT_HEALTH_HOMEPAGE_CACHE_TTL);
    return $body;
}

// Returns true/false, or null if $html is empty (i.e. "could not check",
// distinct from "checked and found nothing").
function lumn_ut_health_detect_gtm($html) {
    if ($html === '') {
        return null;
    }
    return (bool) preg_match('/googletagmanager\.com\/(?:gtm|ns)\.(?:js|html)|\bGTM-[A-Z0-9]{4,}\b/i', $html);
}

// Every data-lumn-event="..." value found in $html, each flagged
// recognized/unrecognized against the current event registry.
function lumn_ut_health_scan_explicit_events($html) {
    $found = array();
    if ($html === '' || !preg_match_all('/data-lumn-event=["\']([^"\']+)["\']/i', $html, $matches)) {
        return $found;
    }
    foreach (array_unique($matches[1]) as $raw) {
        $found[] = array('raw' => $raw, 'recognized' => lumn_ut_tracking_resolve_event_key($raw) !== null);
    }
    return $found;
}

// Every registered event's structural validity - this only ever fails
// if a future edit to the registry itself introduces a mistake (a
// missing feature reference, a malformed name), not anything a site
// admin's settings could cause.
function lumn_ut_health_check_event_registry() {
    $errors = array();
    $features = lumn_ut_tracking_feature_registry();

    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        if (empty($event['feature']) || !isset($features[$event['feature']])) {
            $errors[] = sprintf(
                /* translators: 1: event registry key, 2: feature key */
                __('%1$s references an unrecognized feature "%2$s".', 'lumn-utilities'),
                $key,
                isset($event['feature']) ? $event['feature'] : '(missing)'
            );
        }
        if (empty($event['name']) || !preg_match('/^lumn_[a-z0-9_]+$/', $event['name'])) {
            $errors[] = sprintf(__('%s has a missing or invalid event name.', 'lumn-utilities'), $key);
        }
        if (empty($event['category']) || empty($event['action'])) {
            $errors[] = sprintf(__('%s is missing a category or action.', 'lumn-utilities'), $key);
        }
    }

    return $errors;
}

// Live forms (from each detected provider's own API) that have no saved
// LUMN config, or are configured but not enabled.
function lumn_ut_health_check_form_configuration() {
    $issues = array();
    $provider_lists = array(
        'gravity_forms' => lumn_ut_form_tracking_get_gravity_forms_list(),
        'formidable_forms' => lumn_ut_form_tracking_get_formidable_forms_list(),
    );
    $all_config = lumn_ut_form_tracking_get_all_config();
    $providers = lumn_ut_tracking_form_provider_registry();

    foreach ($provider_lists as $provider => $forms) {
        if (!lumn_ut_form_tracking_provider_detected($provider)) {
            continue;
        }
        foreach ($forms as $form) {
            $key = lumn_ut_form_tracking_config_key($provider, $form['id']);
            $configured_and_enabled = isset($all_config[$key]) && !empty($all_config[$key]['enabled']);
            if (!$configured_and_enabled) {
                $issues[] = sprintf(
                    /* translators: 1: form name, 2: form id, 3: provider label */
                    __('"%1$s" (ID %2$s, %3$s) is detected but not configured for LUMN tracking.', 'lumn-utilities'),
                    $form['name'],
                    $form['id'],
                    isset($providers[$provider]['label']) ? $providers[$provider]['label'] : $provider
                );
            }
        }
    }

    return $issues;
}

/**
 * Runs every health check and returns a flat list of
 * array('label', 'status' => good|warning|error|info, 'message', 'can_verify').
 * $run_remote_checks gates the two checks (GTM detection, explicit-event
 * page scan) that fetch this site's own front page - false by default so
 * nothing remote-ish ever happens just from loading the admin tab; only
 * the "Run Health Check" button (admin/tracking-debugger-page.php) passes
 * true.
 */
function lumn_ut_health_run_checks($run_remote_checks = false) {
    $checks = array();
    $settings = lumn_ut_get_tracking_settings();

    $checks[] = array(
        'label' => __('Master Tracking', 'lumn-utilities'),
        'status' => !empty($settings['master']) ? 'good' : 'info',
        'message' => !empty($settings['master'])
            ? __('Master Tracking is enabled.', 'lumn-utilities')
            : __('Master Tracking is off - no LUMN events are generated on this site right now.', 'lumn-utilities'),
        'can_verify' => true,
    );

    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        if (empty($meta['implemented'])) {
            continue; // nothing to check for a feature with no code behind it yet
        }
        $enabled = lumn_ut_tracking_feature_enabled($key);
        $checks[] = array(
            'label' => $meta['label'],
            'status' => $enabled ? 'good' : 'info',
            'message' => $enabled
                ? sprintf(__('%s is enabled.', 'lumn-utilities'), $meta['label'])
                : sprintf(__('%s is off.', 'lumn-utilities'), $meta['label']),
            'can_verify' => true,
        );
    }

    $registry_errors = lumn_ut_health_check_event_registry();
    if (empty($registry_errors)) {
        $checks[] = array('label' => __('Event Configuration', 'lumn-utilities'), 'status' => 'good', 'message' => __('Every registered LUMN event has valid, internally consistent configuration.', 'lumn-utilities'), 'can_verify' => true);
    } else {
        foreach ($registry_errors as $error) {
            $checks[] = array('label' => __('Event Configuration', 'lumn-utilities'), 'status' => 'error', 'message' => $error, 'can_verify' => true);
        }
    }

    $checks[] = array(
        'label' => __('Data Layer', 'lumn-utilities'),
        'status' => !empty($settings['master']) ? 'good' : 'info',
        'message' => !empty($settings['master'])
            ? __('LUMN creates window.dataLayer automatically the first time a tracked event actually fires (if it does not already exist).', 'lumn-utilities')
            : __('Master Tracking is off, so LUMN will not initialize window.dataLayer.', 'lumn-utilities'),
        'can_verify' => true,
    );

    foreach (lumn_ut_tracking_form_provider_registry() as $provider => $meta) {
        $detected = lumn_ut_form_tracking_provider_detected($provider);
        $enabled = lumn_ut_tracking_form_provider_enabled($provider);
        if (!$detected) {
            $status = 'info';
            $message = sprintf(__('%s was not detected on this site.', 'lumn-utilities'), $meta['label']);
        } elseif (!$enabled) {
            $status = 'warning';
            $message = sprintf(__('%s is installed, but its LUMN tracking toggle is off.', 'lumn-utilities'), $meta['label']);
        } else {
            $status = 'good';
            $message = sprintf(__('%s is detected and its LUMN tracking toggle is on.', 'lumn-utilities'), $meta['label']);
        }
        $checks[] = array('label' => $meta['label'], 'status' => $status, 'message' => $message, 'can_verify' => true);
    }

    $form_issues = lumn_ut_health_check_form_configuration();
    if (empty($form_issues)) {
        $checks[] = array('label' => __('Form Configuration', 'lumn-utilities'), 'status' => 'good', 'message' => __('No detected forms are missing LUMN configuration.', 'lumn-utilities'), 'can_verify' => true);
    } else {
        foreach ($form_issues as $issue) {
            $checks[] = array('label' => __('Form Configuration', 'lumn-utilities'), 'status' => 'warning', 'message' => $issue, 'can_verify' => true);
        }
    }

    if ($run_remote_checks) {
        $html = lumn_ut_health_fetch_homepage_html();

        if ($html === '') {
            $checks[] = array(
                'label' => __('Google Tag Manager', 'lumn-utilities'),
                'status' => 'warning',
                'message' => __('Could not fetch this site\'s own front page to check for GTM - this may be a temporary issue, not necessarily a real problem.', 'lumn-utilities'),
                'can_verify' => false,
            );
        } else {
            $gtm_detected = lumn_ut_health_detect_gtm($html);
            $checks[] = array(
                'label' => __('Google Tag Manager', 'lumn-utilities'),
                // Finding it is a solid "yes"; not finding it is a weaker
                // signal (GTM could load conditionally, or only on other
                // pages), so it's a warning, not an error either way.
                'status' => $gtm_detected ? 'good' : 'warning',
                'message' => $gtm_detected
                    ? __('A GTM container appears to be installed on the front page.', 'lumn-utilities')
                    : __('No GTM container was detected on the front page. If this site loads GTM conditionally, or only on other pages, this may be a false negative.', 'lumn-utilities'),
                'can_verify' => $gtm_detected,
            );

            $explicit_events = lumn_ut_health_scan_explicit_events($html);
            $unknown = array_values(array_filter($explicit_events, function ($e) {
                return !$e['recognized'];
            }));

            if (empty($explicit_events)) {
                $checks[] = array('label' => __('Explicitly Tracked Elements', 'lumn-utilities'), 'status' => 'info', 'message' => __('No data-lumn-event attributes were found on the front page.', 'lumn-utilities'), 'can_verify' => true);
            } elseif (empty($unknown)) {
                $checks[] = array('label' => __('Explicitly Tracked Elements', 'lumn-utilities'), 'status' => 'good', 'message' => sprintf(
                    /* translators: %d: number of data-lumn-event attributes found */
                    _n('Found %d data-lumn-event attribute on the front page, and it is recognized.', 'Found %d data-lumn-event attributes on the front page, all recognized.', count($explicit_events), 'lumn-utilities'),
                    count($explicit_events)
                ), 'can_verify' => true);
            } else {
                foreach ($unknown as $bad) {
                    $checks[] = array('label' => __('Explicitly Tracked Elements', 'lumn-utilities'), 'status' => 'warning', 'message' => sprintf(
                        /* translators: %s: the unrecognized data-lumn-event value found on the page */
                        __('Unknown LUMN event "%s" found on the front page - not recognized by the current event registry, so it will never fire.', 'lumn-utilities'),
                        $bad['raw']
                    ), 'can_verify' => true);
                }
            }
        }
    }

    return $checks;
}

function lumn_ut_health_overall_status($checks) {
    $overall = 'good';
    foreach ($checks as $check) {
        if ($check['status'] === 'error') {
            return 'error';
        }
        if ($check['status'] === 'warning') {
            $overall = 'warning';
        }
    }
    return $overall;
}

// ---------------------------------------------------------------------
// GTM recipes - the single source of truth for GTM setup guidance,
// reused by both the Event Catalog tab and the GTM Guide tab so nothing
// is duplicated between them.
// ---------------------------------------------------------------------

function lumn_ut_tracking_gtm_recipe($event_key) {
    $registry = lumn_ut_tracking_event_registry();
    if (!isset($registry[$event_key])) {
        return null;
    }
    $event = $registry[$event_key];

    $recipe = array(
        'event_key' => $event_key,
        'event_name' => $event['name'],
        'trigger_type' => __('Custom Event', 'lumn-utilities'),
        'custom_event_name' => $event['name'],
        'condition' => null,
        'ga4_note' => null,
    );

    if ($event_key === 'LUMN_FORM_SUBMIT') {
        $types = lumn_ut_tracking_form_type_registry();
        unset($types['other']);
        $recipe['condition'] = array(
            'variable' => 'lumn_form_type',
            'operator' => __('equals', 'lumn-utilities'),
            'example_values' => array_keys($types),
        );
        $recipe['ga4_note'] = __('Consider mapping to a GA4 generate_lead event for lead-generating form types (e.g. appointment, contact, consultation) - but probably not employment or newsletter forms. This decision belongs in GTM, made by whoever owns this site\'s analytics; LUMN Utilities never sends this to GA4 directly.', 'lumn-utilities');
    } elseif (in_array($event_key, array('LUMN_PHONE_CLICK', 'LUMN_APPOINTMENT_CLICK', 'LUMN_DIRECTIONS_CLICK', 'LUMN_EMAIL_CLICK'), true)) {
        $recipe['ga4_note'] = __('Consider mapping to a GA4 generate_lead event, since this represents a lead-generating interaction. Configured entirely in GTM - LUMN Utilities never sends this to GA4 directly.', 'lumn-utilities');
    }

    return $recipe;
}

// ---------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------

add_action('admin_menu', function () {
    add_submenu_page(
        'lumn-ut-shortcode-settings',
        __('Tracking Debugger', 'lumn-utilities'),
        __('Tracking Debugger', 'lumn-utilities'),
        LUMN_UT_TRACKING_CAPABILITY,
        LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG,
        'Lumn\Utilities\lumn_ut_tracking_debugger_page_callback'
    );
});

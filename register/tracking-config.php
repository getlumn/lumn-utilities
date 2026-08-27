<?php
namespace Lumn\Utilities;

/**
 * LUMN Tracking - central configuration model, dashboard support, and
 * administration/management tooling (Step 6). See docs/TRACKING.md
 * "Central configuration model" for the full developer guide.
 *
 * This file does NOT introduce a new storage format for settings already
 * established in Steps 1-5 (lumn_ut_tracking_settings,
 * lumn_ut_form_tracking_config, lumn_ut_tracking_classification_config) -
 * per the Step 6 spec's own instruction not to blindly restructure working
 * code, those options keep their existing shapes so upgrading sites are
 * never at risk of a migration bug. Instead, this file adds:
 *
 * - lumn_ut_tracking_get_full_config() - a single, authoritative, computed
 *   READ view that assembles every one of those options (plus the two new
 *   ones below) into one nested structure, so nothing that only needs to
 *   *read* the current configuration (the dashboard, the summary, export,
 *   the debugger, the health checker) has to know which of several options
 *   a given setting actually lives in.
 * - Two new, genuinely new pieces of configuration: per-event overrides
 *   and global URL exclusions (see below).
 * - Reset / export / import / presets / last-modified tracking - all
 *   built on top of the existing sanitize callbacks, never bypassing them.
 */

// ---------------------------------------------------------------------
// Schema version - describes the SHAPE of the aggregated/exported
// configuration this file produces, independent of the plugin's own
// version number (index.php). Bump this only when a future change to
// lumn_ut_tracking_get_full_config()'s shape or the export format would
// require lumn_ut_tracking_validate_import() below to translate an
// older export before sanitizing it - see docs/TRACKING.md
// "Configuration schema version and migrations" for the exact pattern
// to follow when that's actually needed. Do not confuse this with LUMN
// Utilities' plugin version - the plugin can ship many releases without
// this ever moving.
// ---------------------------------------------------------------------

const LUMN_UT_TRACKING_SCHEMA_VERSION = 1;

// ---------------------------------------------------------------------
// Per-event overrides - lets an administrator turn off one specific
// event (e.g. lumn_video_progress) without touching its feature's other
// events (lumn_video_start/lumn_video_complete would keep working). A
// feature with only one event (Phone, Email, Directions, Download,
// External Link) gets no meaningful use out of this - the feature
// toggle IS the per-event control for those - so the settings UI only
// ever surfaces this for features with more than one event.
//
// Storage: a flat map of event registry key => false. Absence of a key
// means "not overridden" (inherits the feature toggle) - there is no
// explicit "true" state, since that's just the default already. This
// keeps the option tiny and keeps a future new event automatically
// un-overridden (never silently disabled by an older saved shape).
// ---------------------------------------------------------------------

const LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION = 'lumn_ut_tracking_event_overrides';

/**
 * Which events ever get their own per-event checkbox in the admin UI -
 * deliberately a short, fixed list, not "every registered event": a
 * feature with only one event (Phone, Email, SMS, Appointment,
 * Directions, Download, External Link) gets no useful granularity out
 * of a second, redundant checkbox next to its feature toggle - see
 * docs/TRACKING.md "Per-event controls". Video Tracking's three events
 * and Form Tracking's one event are the only features where this
 * currently adds anything.
 *
 * This list matters beyond just rendering: lumn_ut_tracking_sanitize_event_overrides()
 * below only ever infers "unchecked" (i.e. overridden off) from an
 * event's ABSENCE in the submitted form for keys in this list - an event
 * that never got a checkbox in the first place must never be silently
 * toggled off just because it wasn't present in $_POST.
 */
function lumn_ut_tracking_overridable_events() {
    return array('LUMN_VIDEO_START', 'LUMN_VIDEO_PROGRESS', 'LUMN_VIDEO_COMPLETE', 'LUMN_FORM_SUBMIT');
}

function lumn_ut_tracking_get_event_overrides() {
    $stored = get_option(LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION, array());
    return is_array($stored) ? $stored : array();
}

// Normalizes an already-in-storage-shape overrides map (event_key =>
// false) - keeps only entries for a currently-overridable event key.
// Used directly by import (where the incoming JSON already carries this
// shape, from a previous export) - see lumn_ut_tracking_validate_import().
function lumn_ut_tracking_sanitize_event_overrides_stored($input) {
    $clean = array();
    if (is_array($input)) {
        $overridable = lumn_ut_tracking_overridable_events();
        foreach ($input as $key => $value) {
            if (is_string($key) && in_array($key, $overridable, true) && empty($value)) {
                $clean[$key] = false;
            }
        }
    }
    return $clean;
}

// register_setting() sanitize_callback for the admin form. $input is
// the submitted CHECKBOX array (only checked boxes are present, per
// normal HTML form behavior) - the opposite shape from storage: for
// each overridable event, a checkbox that IS present means "on" (no
// override needed, simply omitted from the stored option), and one
// that's ABSENT means the admin unchecked it, stored as an explicit
// `false`. Converts to the storage shape and delegates to
// lumn_ut_tracking_sanitize_event_overrides_stored() above, so both
// entry points (a form save, and an import) end up validated the same
// way.
function lumn_ut_tracking_sanitize_event_overrides($input) {
    $input = is_array($input) ? $input : array();
    $storage_shape = array();
    foreach (lumn_ut_tracking_overridable_events() as $event_key) {
        if (empty($input[$event_key])) {
            $storage_shape[$event_key] = false;
        }
    }
    lumn_ut_tracking_touch_last_modified();
    return lumn_ut_tracking_sanitize_event_overrides_stored($storage_shape);
}

/**
 * Whether one event may fire right now - the feature-level check
 * (lumn_ut_tracking_feature_enabled(), which itself checks the master
 * switch) PLUS this event's own override, if one is stored. This is
 * what lumn_ut_tracking_push_event()/relay_event() (PHP) and
 * LumnTracking.pushEvent() (JS, via the localized eventOverrides map)
 * actually gate on - never lumn_ut_tracking_feature_enabled() alone,
 * so a per-event override can never be bypassed by code that forgot
 * about it.
 */
function lumn_ut_tracking_event_enabled($event_key) {
    $registry = lumn_ut_tracking_event_registry();
    if (!isset($registry[$event_key])) {
        return false;
    }
    if (!lumn_ut_tracking_feature_enabled($registry[$event_key]['feature'])) {
        return false;
    }
    $overrides = lumn_ut_tracking_get_event_overrides();
    return !isset($overrides[$event_key]) || $overrides[$event_key] !== false;
}

// ---------------------------------------------------------------------
// Global URL exclusions - a path-prefix list that suppresses EVERY
// automatic classification (download, external link, pattern/domain-
// based appointment matching) for a matching link, site-wide. This is
// the URL-pattern-based counterpart to the element-based
// data-lumn-track="false" attribute - useful when an entire section of
// the site (e.g. a staff-only area) should never generate automatic
// LUMN events, without having to tag every link inside it individually.
// It does NOT affect an explicit data-lumn-event, for the same reason
// data-lumn-track="false" doesn't - see docs/TRACKING.md "Tracking
// overrides and precedence".
// ---------------------------------------------------------------------

const LUMN_UT_TRACKING_URL_EXCLUSIONS_OPTION = 'lumn_ut_tracking_url_exclusions';

function lumn_ut_tracking_get_url_exclusions() {
    $stored = get_option(LUMN_UT_TRACKING_URL_EXCLUSIONS_OPTION, array());
    return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : array();
}

function lumn_ut_tracking_sanitize_url_exclusions($input) {
    lumn_ut_tracking_touch_last_modified();
    return lumn_ut_tracking_sanitize_line_list($input, 'path');
}

// ---------------------------------------------------------------------
// Anchor/fragment exclusions - a list of "#..." fragment names that
// suppress automatic classification for a matching same-page anchor
// link, site-wide (regardless of which page it appears on). See
// lumn_ut_anchor_exclusions_field_callback() in
// register/engagement-tracking.php for why this exists: a "jump to
// section" link like href="#contact-form" resolves, once the hash is
// stripped for comparison, to the exact same origin+pathname as the page
// it's on - so on a page that IS this site's configured Appointments (or
// Google Maps) link, every same-page anchor jump false-matches that
// event. Like Global URL Exclusions and data-lumn-track="false", this
// never affects an explicit data-lumn-event.
// ---------------------------------------------------------------------

const LUMN_UT_TRACKING_ANCHOR_EXCLUSIONS_OPTION = 'lumn_ut_tracking_anchor_exclusions';

function lumn_ut_tracking_get_anchor_exclusions() {
    $stored = get_option(LUMN_UT_TRACKING_ANCHOR_EXCLUSIONS_OPTION, array());
    return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : array();
}

function lumn_ut_tracking_sanitize_anchor_exclusions($input) {
    lumn_ut_tracking_touch_last_modified();
    return lumn_ut_tracking_sanitize_line_list($input, 'fragment');
}

// ---------------------------------------------------------------------
// Last-modified tracking (Step 6, section 20) - deliberately just a
// timestamp, not a change log, per the spec's own explicit permission
// to skip a full audit-log system. Called from every sanitize_callback
// registered on the tracking settings group, so it reflects a save from
// ANY of the tracking screens, not just one.
// ---------------------------------------------------------------------

const LUMN_UT_TRACKING_LAST_MODIFIED_OPTION = 'lumn_ut_tracking_last_modified';

function lumn_ut_tracking_touch_last_modified() {
    update_option(LUMN_UT_TRACKING_LAST_MODIFIED_OPTION, time());
}

function lumn_ut_tracking_get_last_modified() {
    $value = get_option(LUMN_UT_TRACKING_LAST_MODIFIED_OPTION, 0);
    return is_numeric($value) && $value > 0 ? (int) $value : null;
}

// ---------------------------------------------------------------------
// The central configuration model - one authoritative, computed view of
// every tracking-related option, memoized for the current request (see
// docs/TRACKING.md "Performance"). Never a new storage location - purely
// an assembled read view over the options Steps 1-5 already established
// plus the two new ones above.
// ---------------------------------------------------------------------

function lumn_ut_tracking_get_full_config() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $settings = lumn_ut_get_tracking_settings();
    $classification = function_exists('Lumn\Utilities\lumn_ut_tracking_get_classification_config')
        ? lumn_ut_tracking_get_classification_config()
        : lumn_ut_tracking_classification_defaults();

    $provider_registry = lumn_ut_tracking_form_provider_registry();
    $providers_out = array();
    foreach ($provider_registry as $key => $meta) {
        $providers_out[$key] = array(
            'label' => $meta['label'],
            'detected' => lumn_ut_form_tracking_provider_detected($key),
            'enabled' => lumn_ut_tracking_form_provider_enabled($key),
        );
    }

    $mappings_out = array();
    foreach (lumn_ut_form_tracking_get_all_config() as $key => $entry) {
        list($provider, $form_id) = array_pad(explode(':', $key, 2), 2, '');
        $mappings_out[$key] = array(
            'provider' => $provider,
            'form_id' => $form_id,
            'enabled' => !empty($entry['enabled']),
            'form_type' => isset($entry['form_type']) ? $entry['form_type'] : 'other',
            'location_id' => isset($entry['location_id']) ? $entry['location_id'] : '',
        );
    }

    $config = array(
        'schema_version' => LUMN_UT_TRACKING_SCHEMA_VERSION,
        'enabled' => !empty($settings['master']),
        'phone' => array('enabled' => !empty($settings['phone_click_tracking'])),
        'email' => array('enabled' => !empty($settings['email_click_tracking'])),
        'appointment' => array('enabled' => !empty($settings['appointment_click_tracking'])),
        'directions' => array('enabled' => !empty($settings['directions_click_tracking'])),
        'explicit_events' => array('enabled' => !empty($settings['event_tracking'])),
        'forms' => array(
            'enabled' => !empty($settings['form_tracking']),
            'providers' => $providers_out,
            'mappings' => $mappings_out,
        ),
        'downloads' => array(
            'enabled' => !empty($settings['download_tracking']),
            'extensions' => lumn_ut_tracking_download_extensions(),
        ),
        'external_links' => array(
            'enabled' => !empty($settings['external_link_tracking']),
            'exclusions' => $classification['external_link_excluded_domains'],
        ),
        'videos' => array('enabled' => !empty($settings['video_tracking'])),
        'automatic_cta' => array(
            'enabled' => !empty($settings['cta_classification']),
            'appointment_patterns' => $classification['appointment_url_patterns'],
            'appointment_domains' => $classification['appointment_domains'],
        ),
        'exclusions' => array(
            'external_link_domains' => $classification['external_link_excluded_domains'],
            'global_url_paths' => lumn_ut_tracking_get_url_exclusions(),
            'anchor_fragments' => lumn_ut_tracking_get_anchor_exclusions(),
        ),
        'debugger' => array('enabled' => !empty($settings['debugger'])),
        'event_overrides' => lumn_ut_tracking_get_event_overrides(),
        'last_modified' => lumn_ut_tracking_get_last_modified(),
    );

    $cached = $config;
    return $config;
}

/**
 * What this site is CURRENTLY configured to actually do, for the
 * human-readable Configuration Summary (Step 6, section 3) - one row per
 * implemented feature, `on` true only when master + that feature's own
 * toggle are both on (mirrors lumn_ut_tracking_feature_enabled()
 * exactly, never a separate judgment call). Also includes one row per
 * currently-enabled, currently-tracked form (provider + form name),
 * since "Form Tracking is on" alone doesn't tell an administrator
 * whether any actual form will send an event.
 */
function lumn_ut_tracking_configuration_summary() {
    $config = lumn_ut_tracking_get_full_config();
    $rows = array();

    if (!$config['enabled']) {
        return array('enabled' => false, 'rows' => $rows);
    }

    $simple = array(
        'phone_click_tracking' => __('Phone clicks', 'lumn-utilities'),
        'email_click_tracking' => __('Email clicks', 'lumn-utilities'),
        'appointment_click_tracking' => __('Appointment clicks', 'lumn-utilities'),
        'directions_click_tracking' => __('Directions clicks', 'lumn-utilities'),
        'download_tracking' => __('Downloads', 'lumn-utilities'),
        'external_link_tracking' => __('External links', 'lumn-utilities'),
        'video_tracking' => __('Videos', 'lumn-utilities'),
        'cta_classification' => __('Automatic CTA classification', 'lumn-utilities'),
    );
    foreach ($simple as $feature_key => $label) {
        $rows[] = array('label' => $label, 'on' => lumn_ut_tracking_feature_enabled($feature_key));
    }

    // Form Tracking is reported per actually-enabled form, not as one
    // generic "Form Tracking" row - "on" with zero forms configured
    // would be misleading (nothing would actually fire).
    if (lumn_ut_tracking_feature_enabled('form_tracking')) {
        $providers = lumn_ut_tracking_form_provider_registry();
        foreach ($config['forms']['mappings'] as $mapping) {
            if (!$mapping['enabled'] || !lumn_ut_tracking_form_provider_enabled($mapping['provider'])) {
                continue;
            }
            $provider_label = isset($providers[$mapping['provider']]['label']) ? $providers[$mapping['provider']]['label'] : $mapping['provider'];
            $rows[] = array(
                'label' => sprintf(
                    /* translators: %s: form provider label, e.g. "Gravity Forms" */
                    __('%s submissions', 'lumn-utilities'),
                    $provider_label
                ),
                'on' => true,
            );
        }
    } else {
        $rows[] = array('label' => __('Form submissions', 'lumn-utilities'), 'on' => false);
    }

    return array('enabled' => true, 'rows' => $rows);
}

// ---------------------------------------------------------------------
// Recommended vs enabled (Step 6, section 22) - a fixed, hardcoded
// annotation, never auto-applied and never confused with the actual
// saved state. Purely advisory text in the UI.
// ---------------------------------------------------------------------

function lumn_ut_tracking_recommended_features() {
    return array('phone_click_tracking', 'email_click_tracking', 'appointment_click_tracking');
}

// ---------------------------------------------------------------------
// Presets (Step 6, section 21) - named bundles of feature toggles. Never
// applied automatically; lumn_ut_handle_apply_preset() below requires an
// explicit admin-post submission with a valid nonce, and the UI always
// shows a before/after preview before that submission is even possible.
// ---------------------------------------------------------------------

function lumn_ut_tracking_presets() {
    return array(
        'basic' => array(
            'label' => __('Basic', 'lumn-utilities'),
            'description' => __('The essentials for a lead-generation site: phone, email, and appointment clicks.', 'lumn-utilities'),
            'features' => array('phone_click_tracking', 'email_click_tracking', 'appointment_click_tracking'),
        ),
        'standard' => array(
            'label' => __('Standard', 'lumn-utilities'),
            'description' => __('Basic, plus directions clicks and form submissions.', 'lumn-utilities'),
            'features' => array('phone_click_tracking', 'email_click_tracking', 'appointment_click_tracking', 'directions_click_tracking', 'form_tracking'),
        ),
        'advanced' => array(
            'label' => __('Advanced', 'lumn-utilities'),
            'description' => __('Standard, plus downloads, external links, and video engagement.', 'lumn-utilities'),
            'features' => array('phone_click_tracking', 'email_click_tracking', 'appointment_click_tracking', 'directions_click_tracking', 'form_tracking', 'download_tracking', 'external_link_tracking', 'video_tracking'),
        ),
    );
}

/**
 * What applying $preset_key would change, as a list of
 * array('feature' => key, 'label' => ..., 'from' => bool, 'to' => bool) -
 * only entries that would actually change are included, so a preset that
 * matches the current configuration exactly previews as "no changes".
 * Never mutates anything itself - see lumn_ut_handle_apply_preset() for
 * the only code path that actually saves a preset, which always requires
 * this preview to have been shown first (same page render) and an
 * explicit follow-up submission.
 */
function lumn_ut_tracking_preset_diff($preset_key) {
    $presets = lumn_ut_tracking_presets();
    if (!isset($presets[$preset_key])) {
        return array();
    }

    $feature_registry = lumn_ut_tracking_feature_registry();
    $target_features = $presets[$preset_key]['features'];
    $diff = array();

    // Compare against the SAVED toggle, not the effective (master-gated)
    // state - applying a preset while master is off should still
    // faithfully preview which toggles would flip.
    $saved_settings = lumn_ut_get_tracking_settings();
    foreach ($feature_registry as $key => $meta) {
        $current = !empty($saved_settings[$key]);
        $target = in_array($key, $target_features, true);
        if ($current !== $target) {
            $diff[] = array('feature' => $key, 'label' => $meta['label'], 'from' => $current, 'to' => $target);
        }
    }

    return $diff;
}

add_action('admin_post_lumn_ut_apply_preset', 'Lumn\Utilities\lumn_ut_handle_apply_preset');
function lumn_ut_handle_apply_preset() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_apply_preset');

    $preset_key = isset($_POST['preset']) ? sanitize_key(wp_unslash($_POST['preset'])) : '';
    $presets = lumn_ut_tracking_presets();

    if (isset($presets[$preset_key])) {
        $settings = lumn_ut_get_tracking_settings();
        foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
            $settings[$key] = in_array($key, $presets[$preset_key]['features'], true);
        }
        // Presets only ever set feature toggles - never the master
        // switch itself, so applying one can never be the thing that
        // turns tracking on site-wide for the first time; an admin must
        // still separately enable Master Tracking, same as any other
        // feature toggle change.
        update_option(LUMN_UT_TRACKING_OPTION, lumn_ut_tracking_sanitize_settings($settings));
    }

    wp_safe_redirect(add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'lumn_ut_notice' => 'preset_applied'), admin_url('admin.php')));
    exit;
}

// ---------------------------------------------------------------------
// Safe reset (Step 6, section 14) - returns every tracking-specific
// option to its safe (all-off/empty) defaults. Never touches anything
// outside the tracking system itself (Practice Locations, shortcode
// settings, etc.) and never touches GTM/GA4, which this plugin has no
// access to in the first place.
// ---------------------------------------------------------------------

function lumn_ut_tracking_reset_options() {
    return array(
        LUMN_UT_TRACKING_OPTION,
        LUMN_UT_FORM_TRACKING_CONFIG_OPTION,
        LUMN_UT_CLASSIFICATION_CONFIG_OPTION,
        LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION,
        LUMN_UT_TRACKING_URL_EXCLUSIONS_OPTION,
        LUMN_UT_TRACKING_ANCHOR_EXCLUSIONS_OPTION,
    );
}

add_action('admin_post_lumn_ut_reset_tracking', 'Lumn\Utilities\lumn_ut_handle_reset_tracking');
function lumn_ut_handle_reset_tracking() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_reset_tracking');

    foreach (lumn_ut_tracking_reset_options() as $option) {
        delete_option($option);
    }
    lumn_ut_tracking_touch_last_modified();

    wp_safe_redirect(add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'lumn_ut_notice' => 'reset'), admin_url('admin.php')));
    exit;
}

// ---------------------------------------------------------------------
// Export (Step 6, section 15) - a downloadable lumn-tracking-config.json
// containing only tracking configuration, never anything from outside
// the tracking system, and never anything PII/PHI-bearing (there is
// none in these options in the first place - see docs/TRACKING.md "PII
// / PHI restrictions" - so this list is exactly the tracking option set,
// not a filtered subset of a larger one).
// ---------------------------------------------------------------------

function lumn_ut_tracking_build_export() {
    $config = lumn_ut_tracking_get_full_config();

    // Mappings are exported with the location's NAME (not just its ID),
    // since an ID is meaningless on a different site - import matches by
    // name against the destination site's own locations (see
    // lumn_ut_tracking_validate_import() below), skipping the
    // association entirely if nothing matches rather than pointing at
    // the wrong location.
    $mappings = array();
    foreach ($config['forms']['mappings'] as $key => $mapping) {
        $location_name = '';
        if ($mapping['location_id'] !== '' && function_exists('Lumn\Utilities\lumn_ut_get_location')) {
            $location = lumn_ut_get_location($mapping['location_id']);
            if ($location) {
                $location_name = $location['name'] !== '' ? $location['name'] : $location['practice_name'];
            }
        }
        $mappings[$key] = array(
            'provider' => $mapping['provider'],
            'form_id' => $mapping['form_id'],
            'enabled' => $mapping['enabled'],
            'form_type' => $mapping['form_type'],
            'location_name' => $location_name,
        );
    }

    return array(
        'schema_version' => LUMN_UT_TRACKING_SCHEMA_VERSION,
        'exported_at' => gmdate('c'),
        'settings' => lumn_ut_get_tracking_settings(),
        'form_mappings' => $mappings,
        'classification' => function_exists('Lumn\Utilities\lumn_ut_tracking_get_classification_config')
            ? lumn_ut_tracking_get_classification_config()
            : lumn_ut_tracking_classification_defaults(),
        'event_overrides' => lumn_ut_tracking_get_event_overrides(),
        'url_exclusions' => lumn_ut_tracking_get_url_exclusions(),
        'anchor_exclusions' => lumn_ut_tracking_get_anchor_exclusions(),
    );
}

add_action('admin_post_lumn_ut_export_tracking', 'Lumn\Utilities\lumn_ut_handle_export_tracking');
function lumn_ut_handle_export_tracking() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_export_tracking');

    $export = lumn_ut_tracking_build_export();

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="lumn-tracking-config.json"');
    echo wp_json_encode($export, JSON_PRETTY_PRINT);
    exit;
}

// ---------------------------------------------------------------------
// Import (Step 6, section 16) - validate, then preview, then a separate
// explicit "Apply" step. The uploaded file is only ever read and
// validated on the "Validate" submission; the resulting clean/normalized
// configuration is held in a short-lived transient keyed to the admin
// user (never trusted back from a hidden form field, which an
// intermediary could tamper with) until that same admin explicitly
// confirms Apply. Nothing here ever executes the imported data as code -
// every value goes through the exact same sanitize_callback used for a
// normal settings save, never a raw update_option() of untrusted input.
// ---------------------------------------------------------------------

function lumn_ut_tracking_import_transient_key() {
    return 'lumn_ut_tracking_import_' . get_current_user_id();
}

/**
 * Validates + normalizes a decoded import payload. Returns
 * array('errors' => [...], 'clean' => [...] | null) - $clean is null
 * only when $errors is non-empty. Every value that reaches $clean has
 * already been through the SAME sanitize callback a direct settings save
 * would use, so an imported value can never be less trusted than a
 * hand-typed one.
 */
function lumn_ut_tracking_validate_import($decoded) {
    $errors = array();

    if (!is_array($decoded)) {
        return array('errors' => array(__('This does not look like a LUMN tracking configuration file (not a JSON object).', 'lumn-utilities')), 'clean' => null);
    }
    if (!isset($decoded['schema_version']) || !is_numeric($decoded['schema_version'])) {
        $errors[] = __('Missing or invalid schema_version - this file may not be a LUMN tracking export.', 'lumn-utilities');
    } elseif ((int) $decoded['schema_version'] > LUMN_UT_TRACKING_SCHEMA_VERSION) {
        // Forward-compatibility guard: an export from a NEWER plugin
        // version than this site is running could carry a shape this
        // code doesn't understand yet. See docs/TRACKING.md "Schema
        // version and migrations" for how a future version bump here
        // should add a migration step instead of just this comment.
        $errors[] = sprintf(
            /* translators: 1: schema version found in the file, 2: schema version this site supports */
            __('This file uses configuration schema version %1$d, newer than what this site supports (%2$d). Update LUMN Utilities on this site before importing it.', 'lumn-utilities'),
            (int) $decoded['schema_version'],
            LUMN_UT_TRACKING_SCHEMA_VERSION
        );
    }

    if (!empty($errors)) {
        return array('errors' => $errors, 'clean' => null);
    }

    $clean = array(
        'settings' => lumn_ut_tracking_sanitize_settings(isset($decoded['settings']) ? $decoded['settings'] : array()),
        'classification' => function_exists('Lumn\Utilities\lumn_ut_tracking_sanitize_classification_config')
            ? lumn_ut_tracking_sanitize_classification_config(isset($decoded['classification']) ? $decoded['classification'] : array())
            : lumn_ut_tracking_classification_defaults(),
        'event_overrides' => lumn_ut_tracking_sanitize_event_overrides_stored(isset($decoded['event_overrides']) ? $decoded['event_overrides'] : array()),
        'url_exclusions' => lumn_ut_tracking_sanitize_url_exclusions(isset($decoded['url_exclusions']) ? $decoded['url_exclusions'] : array()),
        'anchor_exclusions' => lumn_ut_tracking_sanitize_anchor_exclusions(isset($decoded['anchor_exclusions']) ? $decoded['anchor_exclusions'] : array()),
        'form_mappings' => array(),
    );

    // Form mappings: re-key by matching provider+form_id against forms
    // that actually exist on THIS site right now, and resolve
    // location_name against THIS site's own locations - an imported
    // form_id or location that doesn't exist here is simply skipped
    // (never invented), consistent with how a stale location_id already
    // behaves elsewhere in this plugin (see lumn_ut_form_tracking_submit()).
    $providers = lumn_ut_tracking_form_provider_registry();
    $types = lumn_ut_tracking_form_type_registry();
    $raw_mappings = isset($decoded['form_mappings']) && is_array($decoded['form_mappings']) ? $decoded['form_mappings'] : array();

    foreach ($raw_mappings as $entry) {
        if (!is_array($entry) || empty($entry['provider']) || !isset($providers[$entry['provider']]) || !isset($entry['form_id']) || $entry['form_id'] === '') {
            continue;
        }
        $form_id = sanitize_text_field((string) $entry['form_id']);
        $form_type = isset($entry['form_type']) && isset($types[$entry['form_type']]) ? $entry['form_type'] : 'other';

        $location_id = '';
        if (!empty($entry['location_name']) && function_exists('Lumn\Utilities\lumn_ut_get_locations')) {
            foreach (lumn_ut_get_locations() as $location) {
                $name = $location['name'] !== '' ? $location['name'] : $location['practice_name'];
                if ($name !== '' && strcasecmp($name, (string) $entry['location_name']) === 0) {
                    $location_id = (string) $location['id'];
                    break;
                }
            }
        }

        $key = lumn_ut_form_tracking_config_key($entry['provider'], $form_id);
        $clean['form_mappings'][$key] = array(
            'enabled' => !empty($entry['enabled']),
            'form_type' => $form_type,
            'location_id' => $location_id,
        );
    }

    return array('errors' => array(), 'clean' => $clean);
}

/**
 * Diffs a validated/clean import payload against this site's CURRENT
 * configuration - one row per setting that would actually change, in
 * the same shape lumn_ut_tracking_preset_diff() uses, so the same
 * rendering code can show both. Never includes a row for a value that's
 * already identical, so a re-import of the same file previews as "no
 * changes."
 */
function lumn_ut_tracking_import_diff($clean) {
    $diff = array();

    $current_settings = lumn_ut_get_tracking_settings();
    foreach (lumn_ut_tracking_default_settings() as $key => $default) {
        $from = !empty($current_settings[$key]);
        $to = !empty($clean['settings'][$key]);
        if ($from !== $to) {
            $diff[] = array('label' => $key, 'from' => $from, 'to' => $to);
        }
    }

    $current_all_forms = lumn_ut_form_tracking_get_all_config();
    foreach ($clean['form_mappings'] as $key => $entry) {
        $current = isset($current_all_forms[$key]) ? $current_all_forms[$key] : array('enabled' => false, 'form_type' => 'other', 'location_id' => '');
        if (!empty($current['enabled']) !== !empty($entry['enabled'])) {
            $diff[] = array('label' => sprintf(__('Form tracking: %s', 'lumn-utilities'), $key), 'from' => !empty($current['enabled']), 'to' => !empty($entry['enabled']));
        }
    }

    return $diff;
}

add_action('admin_post_lumn_ut_validate_import', 'Lumn\Utilities\lumn_ut_handle_validate_import');
function lumn_ut_handle_validate_import() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_validate_import');

    $redirect_base = array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'tab' => 'importexport');

    if (empty($_FILES['lumn_ut_import_file']['tmp_name']) || !is_uploaded_file($_FILES['lumn_ut_import_file']['tmp_name'])) {
        wp_safe_redirect(add_query_arg(array_merge($redirect_base, array('lumn_ut_import_error' => rawurlencode(__('No file was uploaded.', 'lumn-utilities')))), admin_url('admin.php')));
        exit;
    }

    // 1 MB is generous for this JSON shape (settings + a modest number
    // of form mappings) and rules out anything that isn't actually a
    // small config export.
    if ((int) $_FILES['lumn_ut_import_file']['size'] > 1048576) {
        wp_safe_redirect(add_query_arg(array_merge($redirect_base, array('lumn_ut_import_error' => rawurlencode(__('That file is too large to be a LUMN tracking configuration export.', 'lumn-utilities')))), admin_url('admin.php')));
        exit;
    }

    $raw = file_get_contents($_FILES['lumn_ut_import_file']['tmp_name']);
    $decoded = json_decode((string) $raw, true);

    $result = lumn_ut_tracking_validate_import($decoded);

    if (!empty($result['errors'])) {
        delete_transient(lumn_ut_tracking_import_transient_key());
        wp_safe_redirect(add_query_arg(array_merge($redirect_base, array('lumn_ut_import_error' => rawurlencode(implode(' ', $result['errors'])))), admin_url('admin.php')));
        exit;
    }

    // 10 minutes is long enough to review the preview and click Apply,
    // short enough that a validated-but-never-applied import doesn't
    // linger indefinitely.
    set_transient(lumn_ut_tracking_import_transient_key(), $result['clean'], 10 * MINUTE_IN_SECONDS);

    wp_safe_redirect(add_query_arg(array_merge($redirect_base, array('lumn_ut_import_step' => 'preview')), admin_url('admin.php')));
    exit;
}

add_action('admin_post_lumn_ut_apply_import', 'Lumn\Utilities\lumn_ut_handle_apply_import');
function lumn_ut_handle_apply_import() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_apply_import');

    $clean = get_transient(lumn_ut_tracking_import_transient_key());
    delete_transient(lumn_ut_tracking_import_transient_key());

    if (is_array($clean)) {
        update_option(LUMN_UT_TRACKING_OPTION, $clean['settings']);
        if (function_exists('Lumn\Utilities\lumn_ut_tracking_sanitize_classification_config')) {
            update_option(LUMN_UT_CLASSIFICATION_CONFIG_OPTION, $clean['classification']);
        }
        update_option(LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION, $clean['event_overrides']);
        update_option(LUMN_UT_TRACKING_URL_EXCLUSIONS_OPTION, $clean['url_exclusions']);
        update_option(LUMN_UT_TRACKING_ANCHOR_EXCLUSIONS_OPTION, $clean['anchor_exclusions']);

        if (!empty($clean['form_mappings'])) {
            $all_forms = lumn_ut_form_tracking_get_all_config();
            foreach ($clean['form_mappings'] as $key => $entry) {
                $all_forms[$key] = $entry;
            }
            update_option(LUMN_UT_FORM_TRACKING_CONFIG_OPTION, lumn_ut_form_tracking_sanitize_config($all_forms));
        }

        lumn_ut_tracking_touch_last_modified();
    }

    wp_safe_redirect(add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'lumn_ut_notice' => 'imported'), admin_url('admin.php')));
    exit;
}

// ---------------------------------------------------------------------
// Settings registration for the event-overrides option and its own
// "Per-Event Controls" section, on the same Settings API group/page as
// everything else (see register/tracking.php) - rendered by the normal
// do_settings_sections() call in admin/tracking-page.php's Configure
// Tracking tab, and saved by the same options.php form/Save Changes
// button as every other tracking setting, not a separate submission.
//
// The Global URL Exclusions and Anchor/Fragment Exclusions options are
// registered in register/engagement-tracking.php instead, alongside the
// other classification-settings fields they're grouped with in the UI.
// ---------------------------------------------------------------------

add_action('admin_init', function () {
    register_setting(LUMN_UT_TRACKING_SETTINGS_GROUP, LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'Lumn\Utilities\lumn_ut_tracking_sanitize_event_overrides',
        'default' => array(),
    ));

    add_settings_section(
        'lumn_ut_event_overrides_section',
        __('Per-Event Controls', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_event_overrides_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    add_settings_field(
        'lumn_ut_event_overrides_field',
        __('Individually Disable Events', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_event_overrides_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_event_overrides_section'
    );
});

function lumn_ut_event_overrides_section_callback() {
    echo '<p>' . esc_html__('Only shown for a feature with more than one event - turning one of these off does not affect the others under the same feature toggle. Most events don\'t need this: their feature toggle above already is the per-event control.', 'lumn-utilities') . '</p>';
}

function lumn_ut_event_overrides_field_callback() {
    $overrides = lumn_ut_tracking_get_event_overrides();
    $registry = lumn_ut_tracking_event_registry();

    $groups = array(
        'video_tracking' => array(),
        'form_tracking' => array(),
    );
    foreach (lumn_ut_tracking_overridable_events() as $event_key) {
        if (!isset($registry[$event_key])) {
            continue;
        }
        $feature = $registry[$event_key]['feature'];
        if (!isset($groups[$feature])) {
            $groups[$feature] = array();
        }
        $groups[$feature][] = $event_key;
    }

    $feature_registry = lumn_ut_tracking_feature_registry();

    foreach ($groups as $feature_key => $event_keys) {
        if (empty($event_keys)) {
            continue;
        }
        $feature_label = isset($feature_registry[$feature_key]['label']) ? $feature_registry[$feature_key]['label'] : $feature_key;
        echo '<p><strong>' . esc_html($feature_label) . '</strong> (' . ($feature_key === 'video_tracking' ? esc_html__('feature toggle above must also be on for any of these to fire', 'lumn-utilities') : esc_html__('feature toggle above must also be on', 'lumn-utilities')) . ')</p>';
        foreach ($event_keys as $event_key) {
            $event = $registry[$event_key];
            $checked = !isset($overrides[$event_key]) || $overrides[$event_key] !== false;
            echo '<p><label><input type="checkbox" name="' . esc_attr(LUMN_UT_TRACKING_EVENT_OVERRIDES_OPTION) . '[' . esc_attr($event_key) . ']" value="1"' . checked($checked, true, false) . ' /> <code>' . esc_html($event['name']) . '</code> - ' . esc_html__('Tracking:', 'lumn-utilities') . ' ' . ($checked ? esc_html__('ON', 'lumn-utilities') : esc_html__('OFF', 'lumn-utilities')) . '</label></p>';
        }
    }
}

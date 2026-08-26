<?php
namespace Lumn\Utilities;

/**
 * LUMN Tracking / SEO Tools - feature-flag API, settings registration, and
 * the safe data-layer abstraction. See docs/TRACKING.md for the full
 * developer guide and register/tracking-registry.php for the feature and
 * event registries this file enforces.
 *
 * HARD RULE (see docs/TRACKING.md for the full product requirement): every
 * function in this file fails closed. Nothing here may enqueue a script,
 * touch window.dataLayer, or push an event unless an administrator has
 * explicitly turned on the master switch - and, per event, the specific
 * feature toggle that event belongs to - from
 * LUMN Utilities -> SEO & Tracking. A missing option, an unrecognized
 * feature name, or an unrecognized event name always means disabled.
 * Installing or updating this plugin must never change what a site already
 * sends to GTM/GA4/dataLayer.
 */

// ---------------------------------------------------------------------
// Settings storage
// ---------------------------------------------------------------------

// Reads the stored tracking settings, merged onto the all-false defaults
// so any option that has never been saved - or any key a future version
// of this plugin no longer recognizes - resolves to disabled rather than
// being silently dropped or (worse) treated as enabled.
function lumn_ut_get_tracking_settings() {
    $stored = get_option(LUMN_UT_TRACKING_OPTION, array());
    if (!is_array($stored)) {
        $stored = array();
    }
    $defaults = lumn_ut_tracking_default_settings();
    return array_merge($defaults, array_intersect_key($stored, $defaults));
}

// register_setting() sanitize_callback. Only ever writes back keys this
// version of the plugin recognizes, coerced to real booleans - an admin
// (or a stray REST/import tool) can never smuggle an unrecognized or
// truthy-but-not-boolean value into the option.
function lumn_ut_tracking_sanitize_settings($input) {
    $clean = lumn_ut_tracking_default_settings();
    if (!is_array($input)) {
        return $clean;
    }
    foreach ($clean as $key => $default) {
        $clean[$key] = !empty($input[$key]);
    }
    return $clean;
}

// ---------------------------------------------------------------------
// Central feature-flag API - the one authoritative place the rest of the
// plugin (and future tracking features) must go through to decide whether
// tracking may run. Do not read the lumn_ut_tracking_settings option
// directly from anywhere else.
// ---------------------------------------------------------------------

// The master switch. Every other tracking capability - including
// individual feature toggles and the debugger - is meaningless while this
// is off, because lumn_ut_tracking_feature_enabled() below short-circuits
// to false whenever this is false.
function lumn_ut_tracking_is_enabled() {
    $settings = lumn_ut_get_tracking_settings();
    return !empty($settings['master']);
}

/**
 * Whether one named feature may run right now. Fails closed on every axis:
 * - master switch off -> false
 * - $feature not present in lumn_ut_tracking_feature_registry() -> false
 *   (an unrecognized/misspelled feature name is never treated as enabled)
 * - that feature's own toggle off -> false
 *
 * Future tracking code (form handlers, click listeners, the debugger,
 * etc.) must gate itself through this function - or, client-side, through
 * the equivalent LumnTracking.isFeatureEnabled() - rather than reading
 * options directly.
 */
function lumn_ut_tracking_feature_enabled($feature) {
    if (!lumn_ut_tracking_is_enabled()) {
        return false;
    }

    $registry = lumn_ut_tracking_feature_registry();
    if (!isset($registry[$feature])) {
        return false;
    }

    $settings = lumn_ut_get_tracking_settings();
    return !empty($settings[$feature]);
}

// ---------------------------------------------------------------------
// Safe data-layer abstraction (PHP side)
// ---------------------------------------------------------------------

/**
 * Pushes one standardized LUMN event to the site's existing
 * window.dataLayer, for code that renders server-side (e.g. a shortcode
 * or template that wants to fire an event as part of its own output).
 * Purely client-side interactions (a click on a tel:/mailto: link, a form
 * submit handled by JS) should use the LumnTracking.pushEvent() JS
 * counterpart instead - see public/js/lumn-tracking.js.
 *
 * $event_key is a key from lumn_ut_tracking_event_registry(), e.g.
 * 'LUMN_PHONE_CLICK'. $params may only contain keys that event's registry
 * entry declares in its 'params' list (plus the base params every event
 * accepts - see lumn_ut_tracking_base_event_params()); anything else is
 * silently dropped rather than passed through, and every value is run
 * through sanitize_text_field() and must be scalar - this function will
 * never forward an array/object (e.g. a raw $_POST of form fields) into
 * the data layer. See docs/TRACKING.md "PII / PHI restrictions".
 *
 * Returns true if an event was queued, false if it was suppressed for any
 * reason (tracking disabled, feature disabled, unrecognized event key,
 * or the tracking script isn't enqueued on this request).
 */
function lumn_ut_tracking_push_event($event_key, $params = array()) {
    if (!lumn_ut_tracking_is_enabled()) {
        return false;
    }

    $registry = lumn_ut_tracking_event_registry();
    if (!isset($registry[$event_key])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[LUMN Tracking] lumn_ut_tracking_push_event() called with unrecognized event key "%s" - ignored.', $event_key));
        }
        return false;
    }

    $event = $registry[$event_key];

    if (!lumn_ut_tracking_feature_enabled($event['feature'])) {
        return false;
    }

    if (!wp_script_is(LUMN_UT_TRACKING_SCRIPT_HANDLE, 'enqueued') && !wp_script_is(LUMN_UT_TRACKING_SCRIPT_HANDLE, 'done')) {
        // The tracking script is only enqueued when tracking is enabled
        // (see lumn_ut_tracking_public_scripts() below), so this should
        // already be true whenever we reach this point on a normal
        // front-end request. This is a safety net for anything called
        // outside a normal page render (e.g. admin-post.php), where
        // there is no front-end script queue to attach an inline script
        // to.
        return false;
    }

    $payload = lumn_ut_tracking_build_payload($event, $params);

    $inline = 'window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode($payload) . ');';
    if (lumn_ut_tracking_feature_enabled('debugger')) {
        $inline .= ' if (window.dispatchEvent && window.CustomEvent) { window.dispatchEvent(new CustomEvent("lumn:tracking:event", {detail: ' . wp_json_encode(array('key' => $event_key, 'payload' => $payload)) . '})); }';
    }

    wp_add_inline_script(LUMN_UT_TRACKING_SCRIPT_HANDLE, $inline);

    return true;
}

// Shared by lumn_ut_tracking_push_event() and lumn_ut_tracking_public_scripts()
// (the latter exposes the same allowlist/denylist rules to JS rather than
// duplicating them) to build the base event/category/action + filtered,
// sanitized params payload for one event.
function lumn_ut_tracking_build_payload($event, $params) {
    $payload = array(
        'event' => $event['name'],
        'lumn_event_category' => $event['category'],
        'lumn_event_action' => $event['action'],
    );

    $allowed_keys = array_merge(lumn_ut_tracking_base_event_params(), isset($event['params']) ? $event['params'] : array());
    $forbidden_keys = lumn_ut_tracking_forbidden_param_keys();

    foreach ((array) $params as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowed_keys, true)) {
            continue; // not declared for this event - dropped, not passed through
        }
        if (in_array(strtolower($key), $forbidden_keys, true)) {
            continue; // defense-in-depth PII/PHI guard, even on declared keys
        }
        if (!is_scalar($value)) {
            continue; // arrays/objects (e.g. a raw form-fields dump) are never accepted
        }
        $payload[$key] = sanitize_text_field((string) $value);
    }

    return $payload;
}

// ---------------------------------------------------------------------
// Front-end script - only enqueued when tracking is enabled. When
// disabled (the default on every existing install), this hook does
// nothing at all: no script tag, no localized config, no dataLayer
// initialization.
// ---------------------------------------------------------------------

function lumn_ut_tracking_public_scripts() {
    if (!lumn_ut_tracking_is_enabled()) {
        return;
    }

    $script_path = LUMN_UTILITIES_PLUGIN_PATH . 'public/js/lumn-tracking.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        LUMN_UT_TRACKING_SCRIPT_HANDLE,
        plugins_url('public/js/lumn-tracking.js', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'),
        array(),
        filemtime($script_path),
        true
    );

    $features = array();
    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        $features[$key] = lumn_ut_tracking_feature_enabled($key);
    }

    $events = array();
    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        $events[$key] = array(
            'name' => $event['name'],
            'feature' => $event['feature'],
            'category' => $event['category'],
            'action' => $event['action'],
            'params' => array_merge(lumn_ut_tracking_base_event_params(), $event['params']),
        );
    }

    wp_localize_script(LUMN_UT_TRACKING_SCRIPT_HANDLE, 'lumnTrackingConfig', array(
        'enabled' => true,
        'features' => $features,
        'events' => $events,
        'forbiddenParamKeys' => lumn_ut_tracking_forbidden_param_keys(),
        'debug' => lumn_ut_tracking_feature_enabled('debugger'),
    ));
}
add_action('wp_enqueue_scripts', 'Lumn\Utilities\lumn_ut_tracking_public_scripts');

// ---------------------------------------------------------------------
// Settings registration (WordPress Settings API - same pattern used by
// the shortcode settings page in register/fields.php)
// ---------------------------------------------------------------------

add_action('admin_init', function () {
    register_setting(LUMN_UT_TRACKING_SETTINGS_GROUP, LUMN_UT_TRACKING_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'Lumn\Utilities\lumn_ut_tracking_sanitize_settings',
        'default' => lumn_ut_tracking_default_settings(),
    ));

    add_settings_section(
        'lumn_ut_tracking_master_section',
        __('Master Switch', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_tracking_master_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    add_settings_field(
        'lumn_ut_tracking_master_field',
        __('Enable LUMN SEO & Tracking', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_tracking_master_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_tracking_master_section'
    );

    add_settings_section(
        'lumn_ut_tracking_features_section',
        __('Feature Toggles', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_tracking_features_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        add_settings_field(
            'lumn_ut_tracking_feature_' . $key,
            $meta['label'],
            'Lumn\Utilities\lumn_ut_tracking_feature_field_callback',
            LUMN_UT_TRACKING_SETTINGS_GROUP,
            'lumn_ut_tracking_features_section',
            array('key' => $key, 'meta' => $meta)
        );
    }
});

function lumn_ut_tracking_master_section_callback() {
    echo '<p>' . esc_html__('Off by default on every install, including existing sites this plugin is updated on. Nothing below can run - no event is ever pushed to the data layer, and no LUMN tracking script is even loaded on the front end - until this is turned on.', 'lumn-utilities') . '</p>';
}

function lumn_ut_tracking_master_field_callback() {
    $settings = lumn_ut_get_tracking_settings();
    echo '<label><input type="checkbox" name="' . esc_attr(LUMN_UT_TRACKING_OPTION) . '[master]" value="1"' . checked(!empty($settings['master']), true, false) . ' /> ' . esc_html__('Enabled', 'lumn-utilities') . '</label>';
    echo '<p class="description">' . esc_html__('LUMN Utilities never creates a GTM container/tag, a GA4 property, or its own analytics cookies. It only ever pushes standardized events into the data layer your site already has - GTM (or whatever is already listening on window.dataLayer) decides what happens with them.', 'lumn-utilities') . '</p>';
}

function lumn_ut_tracking_features_section_callback() {
    echo '<p>' . esc_html__('Each feature below also requires the master switch above. A feature marked "Coming soon" has no tracking code wired up to it yet in this version of the plugin - turning it on now is safe (it still does nothing) and means it will start working automatically, with no further setup, once that feature ships.', 'lumn-utilities') . '</p>';
}

function lumn_ut_tracking_feature_field_callback($args) {
    $key = $args['key'];
    $meta = $args['meta'];
    $settings = lumn_ut_get_tracking_settings();

    echo '<label><input type="checkbox" name="' . esc_attr(LUMN_UT_TRACKING_OPTION) . '[' . esc_attr($key) . ']" value="1"' . checked(!empty($settings[$key]), true, false) . ' /> ' . esc_html($meta['label']) . '</label>';
    if (empty($meta['implemented'])) {
        echo ' <span class="lumn-ut-tracking-badge">' . esc_html__('Coming soon', 'lumn-utilities') . '</span>';
    }
    if (!empty($meta['description'])) {
        echo '<p class="description">' . esc_html($meta['description']) . '</p>';
    }
}

// ---------------------------------------------------------------------
// Admin menu - "SEO & Tracking" submenu page, same pattern as the
// Practice Locations page in register/locations.php.
// ---------------------------------------------------------------------

add_action('admin_menu', function () {
    add_submenu_page(
        'lumn-ut-shortcode-settings',
        __('SEO & Tracking', 'lumn-utilities'),
        __('SEO & Tracking', 'lumn-utilities'),
        LUMN_UT_TRACKING_CAPABILITY,
        LUMN_UT_TRACKING_PAGE_SLUG,
        'Lumn\Utilities\lumn_ut_tracking_page_callback'
    );
});

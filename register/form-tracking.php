<?php
namespace Lumn\Utilities;

/**
 * LUMN Form Tracking - provider-agnostic API + Gravity Forms / Formidable
 * Forms adapters. See docs/TRACKING.md "Form tracking" for the full
 * developer guide and "Adding a new form provider" for how to add
 * another one.
 *
 * Architecture:
 *
 *   Gravity Forms submission  -\
 *                                > lumn_ut_form_tracking_submit()  ->  lumn_ut_tracking_relay_event('LUMN_FORM_SUBMIT', ...)
 *   Formidable submission     -/
 *
 * Each provider adapter below only ever needs to know how to detect a
 * successful submission and read its own form's id/name - everything
 * else (whether tracking is enabled, per-form opt-in, PII filtering, how
 * the event actually reaches window.dataLayer) is handled once, generically,
 * by lumn_ut_form_tracking_submit() and the Step 1/2 tracking core it
 * calls into. No adapter talks to lumn_ut_tracking_relay_event() or the
 * settings option directly.
 */

const LUMN_UT_FORM_TRACKING_CONFIG_OPTION = 'lumn_ut_form_tracking_config';

// ---------------------------------------------------------------------
// Per-form configuration (which forms are tracked, as what type, and
// optionally associated with which Practice Location). Stored as a
// single array option keyed by "{provider}:{form_id}", the same
// "one option holding an open-ended associative array" pattern used by
// lumn_ut_locations - the number of forms across providers is open-ended
// and per-form config may grow more fields later without a migration.
// ---------------------------------------------------------------------

function lumn_ut_form_tracking_get_all_config() {
    $stored = get_option(LUMN_UT_FORM_TRACKING_CONFIG_OPTION, array());
    return is_array($stored) ? $stored : array();
}

function lumn_ut_form_tracking_config_key($provider, $form_id) {
    return $provider . ':' . $form_id;
}

/**
 * A single form's tracking config, merged onto safe defaults - a form
 * with no saved config is 'enabled' => false (fail closed: an
 * administrator must explicitly opt in each individual form, even when
 * its provider and Form Tracking are both already on) and
 * 'form_type' => 'other'.
 */
function lumn_ut_form_tracking_get_form_config($provider, $form_id) {
    $all = lumn_ut_form_tracking_get_all_config();
    $key = lumn_ut_form_tracking_config_key($provider, $form_id);
    $defaults = array('enabled' => false, 'form_type' => 'other', 'location_id' => '');
    return isset($all[$key]) && is_array($all[$key]) ? array_merge($defaults, $all[$key]) : $defaults;
}

// register_setting() sanitize_callback for LUMN_UT_FORM_TRACKING_CONFIG_OPTION.
// Drops any key that isn't a recognized "{provider}:{form_id}" pair (a
// provider not in lumn_ut_tracking_form_provider_registry() is dropped,
// not stored) and coerces every entry's shape - unrecognized form_type
// values fall back to 'other' rather than being stored verbatim. Does
// NOT require the form_id to currently exist in that provider's live
// form list, so a temporarily-deactivated provider plugin never silently
// loses its saved configuration.
function lumn_ut_form_tracking_sanitize_config($input) {
    $clean = array();
    if (!is_array($input)) {
        return $clean;
    }

    $providers = lumn_ut_tracking_form_provider_registry();
    $types = lumn_ut_tracking_form_type_registry();

    foreach ($input as $key => $entry) {
        if (!is_string($key) || strpos($key, ':') === false || !is_array($entry)) {
            continue;
        }

        list($provider, $form_id) = explode(':', $key, 2);
        if (!isset($providers[$provider])) {
            continue;
        }

        $form_id = sanitize_text_field((string) $form_id);
        if ($form_id === '') {
            continue;
        }

        $form_type = isset($entry['form_type']) && isset($types[$entry['form_type']]) ? $entry['form_type'] : 'other';
        $location_id = isset($entry['location_id']) ? sanitize_text_field((string) $entry['location_id']) : '';

        $clean[lumn_ut_form_tracking_config_key($provider, $form_id)] = array(
            'enabled' => !empty($entry['enabled']),
            'form_type' => $form_type,
            'location_id' => $location_id,
        );
    }

    return $clean;
}

// Light heuristic used ONLY to pre-select a sensible default in the
// "Type" dropdown for a form that has no saved config yet - never used
// to silently classify a form on its own. See docs/TRACKING.md "Form
// type classification" for why this is deliberately just a suggestion,
// not the source of truth.
function lumn_ut_form_tracking_suggest_type($form_name) {
    $name = strtolower((string) $form_name);
    $map = array(
        'appointment' => 'appointment',
        'book' => 'appointment',
        'schedule' => 'appointment',
        'consult' => 'consultation',
        'newsletter' => 'newsletter',
        'subscribe' => 'newsletter',
        'insurance' => 'insurance',
        'career' => 'employment',
        'employ' => 'employment',
        'application' => 'employment',
        'contact' => 'contact',
    );
    foreach ($map as $needle => $type) {
        if (strpos($name, $needle) !== false) {
            return $type;
        }
    }
    return 'other';
}

// ---------------------------------------------------------------------
// The provider-agnostic entry point every adapter calls after it has
// confirmed a successful submission. Never called speculatively - only
// once an adapter's own successful-submission hook has actually fired.
// ---------------------------------------------------------------------

/**
 * Normalizes one successful submission into a LUMN_FORM_SUBMIT event and
 * relays it to the front end, if - and only if - every level of opt-in is
 * satisfied: master tracking, Form Tracking, this specific provider, and
 * this specific form (in that order - see
 * lumn_ut_tracking_form_provider_enabled() in register/tracking.php).
 * Returns false (does nothing) if any level is off, exactly like every
 * other gate in this plugin.
 */
function lumn_ut_form_tracking_submit($provider, $form_id, $form_name) {
    if (!lumn_ut_tracking_form_provider_enabled($provider)) {
        return false;
    }

    $form_id = (string) $form_id;
    if ($form_id === '') {
        return false;
    }

    $config = lumn_ut_form_tracking_get_form_config($provider, $form_id);
    if (empty($config['enabled'])) {
        return false;
    }

    $params = array(
        'lumn_form_id' => $form_id,
        'lumn_form_name' => sanitize_text_field((string) $form_name),
        'lumn_form_type' => $config['form_type'],
        'lumn_form_provider' => $provider,
    );

    if (!empty($config['location_id']) && function_exists('Lumn\Utilities\lumn_ut_get_location')) {
        $location = lumn_ut_get_location($config['location_id']);
        if ($location) {
            $params['lumn_location_id'] = (string) $location['id'];
            $params['lumn_location_name'] = $location['practice_name'] !== '' ? $location['practice_name'] : $location['name'];
        }
        // A stale/deleted location reference simply omits the location
        // params, per docs/TRACKING.md - never invents one.
    }

    $providers = lumn_ut_tracking_form_provider_registry();
    $source = isset($providers[$provider]['label']) ? $providers[$provider]['label'] : $provider;

    return lumn_ut_tracking_relay_event('LUMN_FORM_SUBMIT', $params, $source);
}

// ---------------------------------------------------------------------
// Provider detection - safe to call regardless of whether either plugin
// is installed. Never assumes a class/function exists without checking.
// ---------------------------------------------------------------------

function lumn_ut_form_tracking_gravity_forms_detected() {
    return class_exists('GFForms') || class_exists('GFAPI');
}

function lumn_ut_form_tracking_formidable_forms_detected() {
    return class_exists('FrmForm') || class_exists('FrmAppHelper');
}

function lumn_ut_form_tracking_provider_detected($provider) {
    switch ($provider) {
        case 'gravity_forms':
            return lumn_ut_form_tracking_gravity_forms_detected();
        case 'formidable_forms':
            return lumn_ut_form_tracking_formidable_forms_detected();
        default:
            return false;
    }
}

// ---------------------------------------------------------------------
// Gravity Forms adapter
// ---------------------------------------------------------------------
//
// gform_after_submission is Gravity Forms' own "an entry was just
// successfully saved" action - it fires once per successful submission
// (never on validation failure, never merely because the form was
// displayed or a field changed) and fires identically whether the form
// used a normal postback or GF's AJAX (iframe) submission, since both
// go through the same server-side entry-saving code path. This is the
// most reliable integration point GF exposes; nothing here scrapes
// rendered HTML.

add_action('plugins_loaded', function () {
    if (lumn_ut_form_tracking_gravity_forms_detected()) {
        add_action('gform_after_submission', 'Lumn\Utilities\lumn_ut_form_tracking_handle_gravity_forms_submission', 10, 2);
    }
});

function lumn_ut_form_tracking_handle_gravity_forms_submission($entry, $form) {
    if (!is_array($form) || !isset($form['id'])) {
        return;
    }
    $form_name = isset($form['title']) ? $form['title'] : '';
    lumn_ut_form_tracking_submit('gravity_forms', $form['id'], $form_name);
}

// Best-effort form listing for the "Tracked Forms" admin UI. Never
// fatal-errors if the API this relies on is missing/changed - falls back
// to an empty list, which the UI renders as "no forms found" rather than
// breaking the settings page.
function lumn_ut_form_tracking_get_gravity_forms_list() {
    if (!lumn_ut_form_tracking_gravity_forms_detected() || !class_exists('GFAPI') || !method_exists('GFAPI', 'get_forms')) {
        return array();
    }

    try {
        $forms = \GFAPI::get_forms();
    } catch (\Throwable $e) {
        return array();
    }

    if (!is_array($forms)) {
        return array();
    }

    $list = array();
    foreach ($forms as $form) {
        if (is_array($form) && isset($form['id'])) {
            $title = isset($form['title']) && $form['title'] !== '' ? $form['title'] : ('Form ' . $form['id']);
            $list[] = array('id' => (string) $form['id'], 'name' => $title);
        }
    }
    return $list;
}

// ---------------------------------------------------------------------
// Formidable Forms adapter
// ---------------------------------------------------------------------
//
// frm_after_create_entry is Formidable's own "a new entry was just
// created" action, fired only once an entry has actually been saved
// (validation failures never create an entry, so this hook is naturally
// success-only). Priority 30 matches Formidable's own documented
// recommendation, so this runs after Formidable's core entry-creation
// processing has fully finished. Fires the same way for a normal
// postback or Formidable's AJAX submission.

add_action('plugins_loaded', function () {
    if (lumn_ut_form_tracking_formidable_forms_detected()) {
        add_action('frm_after_create_entry', 'Lumn\Utilities\lumn_ut_form_tracking_handle_formidable_submission', 30, 2);
    }
});

function lumn_ut_form_tracking_handle_formidable_submission($entry_id, $form_id) {
    $form_name = lumn_ut_form_tracking_get_formidable_form_name($form_id);
    lumn_ut_form_tracking_submit('formidable_forms', $form_id, $form_name);
}

function lumn_ut_form_tracking_get_formidable_form_name($form_id) {
    if (!class_exists('FrmForm') || !method_exists('FrmForm', 'getOne')) {
        return '';
    }
    try {
        $form = \FrmForm::getOne($form_id);
    } catch (\Throwable $e) {
        return '';
    }
    if (is_object($form) && isset($form->name)) {
        return $form->name;
    }
    if (is_array($form) && isset($form['name'])) {
        return $form['name'];
    }
    return '';
}

// Best-effort form listing, same defensive shape as the Gravity Forms
// version above - falls back to an empty list rather than risking a
// fatal error against an API surface this can't be tested against here.
function lumn_ut_form_tracking_get_formidable_forms_list() {
    if (!lumn_ut_form_tracking_formidable_forms_detected() || !class_exists('FrmForm')) {
        return array();
    }

    $forms = array();
    try {
        if (method_exists('FrmForm', 'get_published_forms')) {
            $forms = \FrmForm::get_published_forms();
        } elseif (method_exists('FrmForm', 'getAll')) {
            $forms = \FrmForm::getAll();
        }
    } catch (\Throwable $e) {
        return array();
    }

    if (!is_array($forms) && !($forms instanceof \Traversable)) {
        return array();
    }

    $list = array();
    foreach ($forms as $form) {
        $id = is_object($form) ? ($form->id ?? null) : (is_array($form) ? ($form['id'] ?? null) : null);
        $name = is_object($form) ? ($form->name ?? '') : (is_array($form) ? ($form['name'] ?? '') : '');
        if ($id !== null && $id !== '') {
            $list[] = array('id' => (string) $id, 'name' => $name !== '' ? $name : ('Form ' . $id));
        }
    }
    return $list;
}

// ---------------------------------------------------------------------
// Settings UI - "Form Tracking Providers" and "Tracked Forms" sections,
// added to the existing SEO & Tracking settings screen/group (same
// settings_fields()/do_settings_sections() call in
// admin/tracking-page.php - saves together with everything else, and
// inherits that page's capability check and the Settings API's own
// nonce).
// ---------------------------------------------------------------------

add_action('admin_init', function () {
    register_setting(LUMN_UT_TRACKING_SETTINGS_GROUP, LUMN_UT_FORM_TRACKING_CONFIG_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'Lumn\Utilities\lumn_ut_form_tracking_sanitize_config',
        'default' => array(),
    ));

    add_settings_section(
        'lumn_ut_form_tracking_providers_section',
        __('Form Tracking Providers', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_form_tracking_providers_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    foreach (lumn_ut_tracking_form_provider_registry() as $provider => $meta) {
        add_settings_field(
            'lumn_ut_form_tracking_provider_' . $provider,
            $meta['label'],
            'Lumn\Utilities\lumn_ut_form_tracking_provider_field_callback',
            LUMN_UT_TRACKING_SETTINGS_GROUP,
            'lumn_ut_form_tracking_providers_section',
            array('provider' => $provider)
        );
    }

    add_settings_section(
        'lumn_ut_form_tracking_forms_section',
        __('Tracked Forms', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_form_tracking_forms_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    add_settings_field(
        'lumn_ut_form_tracking_forms_table',
        __('Per-Form Tracking', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_form_tracking_forms_table_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_form_tracking_forms_section'
    );
});

function lumn_ut_form_tracking_providers_section_callback() {
    echo '<p>' . esc_html__('Also requires Form Tracking (in Feature Toggles, above) to be on. Detection only affects what this page shows you - a detected provider is never enabled automatically.', 'lumn-utilities') . '</p>';
}

function lumn_ut_form_tracking_provider_field_callback($args) {
    $provider = $args['provider'];
    $providers = lumn_ut_tracking_form_provider_registry();
    $label = isset($providers[$provider]['label']) ? $providers[$provider]['label'] : $provider;
    $detected = lumn_ut_form_tracking_provider_detected($provider);
    $settings = lumn_ut_get_tracking_settings();
    $key = 'form_tracking_' . $provider;

    if ($detected) {
        echo '<p><span class="lumn-ut-tracking-badge lumn-ut-tracking-badge-ok">' . esc_html__('Detected', 'lumn-utilities') . '</span></p>';
        echo '<label><input type="checkbox" name="' . esc_attr(LUMN_UT_TRACKING_OPTION) . '[' . esc_attr($key) . ']" value="1"' . checked(!empty($settings[$key]), true, false) . ' /> ' . esc_html__('Enable tracking', 'lumn-utilities') . '</label>';
        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %s: form provider name, e.g. "Gravity Forms" */
            __('Once enabled, use the Tracked Forms table below to turn tracking on for individual %s forms - none are tracked automatically just because this is checked.', 'lumn-utilities'),
            $label
        )) . '</p>';
    } else {
        echo '<p><span class="lumn-ut-tracking-badge">' . esc_html__('Not detected', 'lumn-utilities') . '</span></p>';
        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %s: form provider name, e.g. "Gravity Forms" */
            __('%s does not appear to be installed/active on this site.', 'lumn-utilities'),
            $label
        )) . '</p>';
    }
}

function lumn_ut_form_tracking_forms_section_callback() {
    echo '<p>' . esc_html__('Every form is off by default, even when its provider and Form Tracking above are both on - pick exactly which forms should send lumn_form_submit, and optionally the type of form and which Practice Location it belongs to.', 'lumn-utilities') . '</p>';
}

function lumn_ut_form_tracking_forms_table_callback() {
    $provider_forms = array(
        'gravity_forms' => lumn_ut_form_tracking_get_gravity_forms_list(),
        'formidable_forms' => lumn_ut_form_tracking_get_formidable_forms_list(),
    );
    $registry = lumn_ut_tracking_form_provider_registry();
    $locations = function_exists('Lumn\Utilities\lumn_ut_get_locations') ? lumn_ut_get_locations() : array();
    $all_config = lumn_ut_form_tracking_get_all_config();

    $any_detected = false;
    foreach ($provider_forms as $provider => $forms) {
        if (!lumn_ut_form_tracking_provider_detected($provider)) {
            continue;
        }
        $any_detected = true;
        $label = isset($registry[$provider]['label']) ? $registry[$provider]['label'] : $provider;
        lumn_ut_form_tracking_render_provider_table($provider, $label, $forms, $locations, $all_config);
    }

    if (!$any_detected) {
        echo '<p>' . esc_html__('No supported form plugin was detected on this site.', 'lumn-utilities') . '</p>';
    }
}

function lumn_ut_form_tracking_render_provider_table($provider, $label, $forms, $locations, $all_config) {
    echo '<h4>' . esc_html($label) . '</h4>';

    if (empty($forms)) {
        echo '<p class="description">' . esc_html__('No forms were found for this provider.', 'lumn-utilities') . '</p>';
        return;
    }

    $types = lumn_ut_tracking_form_type_registry();

    echo '<table class="widefat striped lumn-ut-form-tracking-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('ID', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Name', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Type', 'lumn-utilities') . '</th>';
    if (!empty($locations)) {
        echo '<th>' . esc_html__('Location', 'lumn-utilities') . '</th>';
    }
    echo '<th>' . esc_html__('Tracking', 'lumn-utilities') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($forms as $form) {
        $form_id = $form['id'];
        $raw_key = lumn_ut_form_tracking_config_key($provider, $form_id);
        $has_stored_config = isset($all_config[$raw_key]);
        $config = lumn_ut_form_tracking_get_form_config($provider, $form_id);
        $selected_type = $has_stored_config ? $config['form_type'] : lumn_ut_form_tracking_suggest_type($form['name']);
        $field_name_base = LUMN_UT_FORM_TRACKING_CONFIG_OPTION . '[' . esc_attr($raw_key) . ']';

        echo '<tr>';
        echo '<td><code>' . esc_html($form_id) . '</code></td>';
        echo '<td>' . esc_html($form['name']) . '</td>';

        echo '<td><select name="' . $field_name_base . '[form_type]">';
        foreach ($types as $type_key => $type_label) {
            echo '<option value="' . esc_attr($type_key) . '"' . selected($selected_type, $type_key, false) . '>' . esc_html($type_label) . '</option>';
        }
        echo '</select></td>';

        if (!empty($locations)) {
            echo '<td><select name="' . $field_name_base . '[location_id]">';
            echo '<option value="">' . esc_html__('— None —', 'lumn-utilities') . '</option>';
            foreach ($locations as $location) {
                $loc_label = $location['name'] !== '' ? $location['name'] : $location['practice_name'];
                echo '<option value="' . esc_attr($location['id']) . '"' . selected((string) $config['location_id'], (string) $location['id'], false) . '>' . esc_html($loc_label) . '</option>';
            }
            echo '</select></td>';
        }

        echo '<td><label><input type="checkbox" name="' . $field_name_base . '[enabled]" value="1"' . checked(!empty($config['enabled']), true, false) . ' /> ' . esc_html__('Enabled', 'lumn-utilities') . '</label></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

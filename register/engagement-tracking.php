<?php
namespace Lumn\Utilities;

/**
 * LUMN Engagement Tracking (Step 5) - configuration for automatic
 * download/external-link/CTA classification. Feature toggles themselves
 * (download_tracking, external_link_tracking, video_tracking,
 * cta_classification) are already handled generically by the existing
 * Feature Toggles section in register/tracking.php - this file only adds
 * the *configuration* those features need beyond a simple on/off:
 * appointment URL patterns/domains, and excluded domains for generic
 * external-link tracking. See docs/TRACKING.md "Engagement tracking".
 *
 * The actual click-classification logic lives client-side in
 * public/js/lumn-tracking-events.js (that's where a click is observed);
 * this file is the config those classifiers read, localized via
 * register/tracking.php's lumn_ut_tracking_public_scripts().
 */

const LUMN_UT_CLASSIFICATION_CONFIG_OPTION = 'lumn_ut_tracking_classification_config';

function lumn_ut_tracking_classification_defaults() {
    return array(
        'appointment_url_patterns' => array(),
        'appointment_domains' => array(),
        'external_link_excluded_domains' => array(),
    );
}

function lumn_ut_tracking_get_classification_config() {
    $stored = get_option(LUMN_UT_CLASSIFICATION_CONFIG_OPTION, array());
    $defaults = lumn_ut_tracking_classification_defaults();
    if (!is_array($stored)) {
        return $defaults;
    }
    return array_merge($defaults, array_intersect_key($stored, $defaults));
}

// Splits a newline/comma-separated textarea value into a clean array of
// trimmed, deduplicated, non-empty strings. For 'domain' mode, a pasted
// full URL is reduced to just its host; for 'path' mode, a pasted full
// URL is reduced to just its path. Accepts either a raw string (the
// normal textarea POST shape) or an array (already-split values), so
// this is also safe to reuse against already-sanitized/programmatic
// input.
function lumn_ut_tracking_sanitize_line_list($raw, $mode = 'text') {
    if (is_array($raw)) {
        $raw = implode("\n", $raw);
    }
    $raw = is_string($raw) ? $raw : '';

    $lines = preg_split('/[\r\n,]+/', $raw);
    $clean = array();

    foreach ($lines as $line) {
        $line = trim(sanitize_text_field($line));
        if ($line === '') {
            continue;
        }

        if ($mode === 'domain') {
            $line = strtolower($line);
            $parsed = wp_parse_url(strpos($line, '://') !== false ? $line : 'https://' . $line);
            $line = !empty($parsed['host']) ? $parsed['host'] : $line;
        } elseif ($mode === 'path') {
            if (strpos($line, '://') !== false) {
                $parsed = wp_parse_url($line);
                $line = !empty($parsed['path']) ? $parsed['path'] : $line;
            }
        }

        $clean[] = $line;
    }

    return array_values(array_unique($clean));
}

function lumn_ut_tracking_sanitize_classification_config($input) {
    $clean = lumn_ut_tracking_classification_defaults();
    if (!is_array($input)) {
        return $clean;
    }

    $clean['appointment_url_patterns'] = lumn_ut_tracking_sanitize_line_list(
        isset($input['appointment_url_patterns']) ? $input['appointment_url_patterns'] : '',
        'path'
    );
    $clean['appointment_domains'] = lumn_ut_tracking_sanitize_line_list(
        isset($input['appointment_domains']) ? $input['appointment_domains'] : '',
        'domain'
    );
    $clean['external_link_excluded_domains'] = lumn_ut_tracking_sanitize_line_list(
        isset($input['external_link_excluded_domains']) ? $input['external_link_excluded_domains'] : '',
        'domain'
    );

    if (function_exists('Lumn\Utilities\lumn_ut_tracking_touch_last_modified')) {
        lumn_ut_tracking_touch_last_modified();
    }

    return $clean;
}

// ---------------------------------------------------------------------
// Settings UI - a new section on the existing SEO & Tracking screen/group
// (register/tracking.php's Feature Toggles section already renders
// simple on/off checkboxes for download_tracking, external_link_tracking,
// video_tracking, and cta_classification generically - this section only
// adds the extra configuration those features can optionally use).
// ---------------------------------------------------------------------

add_action('admin_init', function () {
    register_setting(LUMN_UT_TRACKING_SETTINGS_GROUP, LUMN_UT_CLASSIFICATION_CONFIG_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'Lumn\Utilities\lumn_ut_tracking_sanitize_classification_config',
        'default' => lumn_ut_tracking_classification_defaults(),
    ));

    add_settings_section(
        'lumn_ut_classification_config_section',
        __('Automatic Classification Settings', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_classification_config_section_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP
    );

    add_settings_field(
        'lumn_ut_appointment_url_patterns_field',
        __('Appointment URL Patterns', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_appointment_url_patterns_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_classification_config_section'
    );

    add_settings_field(
        'lumn_ut_appointment_domains_field',
        __('Appointment / Scheduling Domains', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_appointment_domains_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_classification_config_section'
    );

    add_settings_field(
        'lumn_ut_external_link_excluded_domains_field',
        __('External Link Tracking: Excluded Domains', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_external_link_excluded_domains_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_classification_config_section'
    );

    // Registered here (not register/tracking-config.php) because it's a
    // field on this same "Automatic Classification Settings" section,
    // even though the option and its sanitize callback live in
    // tracking-config.php (Step 6) alongside the other new
    // configuration-management pieces - see docs/TRACKING.md "Central
    // configuration model".
    register_setting(LUMN_UT_TRACKING_SETTINGS_GROUP, LUMN_UT_TRACKING_URL_EXCLUSIONS_OPTION, array(
        'type' => 'array',
        'sanitize_callback' => 'Lumn\Utilities\lumn_ut_tracking_sanitize_url_exclusions',
        'default' => array(),
    ));

    add_settings_field(
        'lumn_ut_global_url_exclusions_field',
        __('Global URL Exclusions', 'lumn-utilities'),
        'Lumn\Utilities\lumn_ut_global_url_exclusions_field_callback',
        LUMN_UT_TRACKING_SETTINGS_GROUP,
        'lumn_ut_classification_config_section'
    );
});

function lumn_ut_classification_config_section_callback() {
    echo '<p>' . esc_html__('Optional. One value per line. These only take effect when Automatic CTA Classification and/or External Link Tracking (in Feature Toggles, above) are on - and, like everything else here, an explicit data-lumn-event on a link always wins over any of this.', 'lumn-utilities') . '</p>';
}

function lumn_ut_appointment_url_patterns_field_callback() {
    $config = lumn_ut_tracking_get_classification_config();
    echo '<textarea name="' . esc_attr(LUMN_UT_CLASSIFICATION_CONFIG_OPTION) . '[appointment_url_patterns]" rows="4" placeholder="/request-appointment/&#10;/schedule/&#10;/book-online/">' . esc_textarea(implode("\n", $config['appointment_url_patterns'])) . '</textarea>';
    echo '<p class="description">' . esc_html__('Path prefixes on THIS site. A link whose path starts with one of these is treated as an appointment click - e.g. /schedule/ also matches /schedule/downtown-office/. Requires Automatic CTA Classification.', 'lumn-utilities') . '</p>';
}

function lumn_ut_appointment_domains_field_callback() {
    $config = lumn_ut_tracking_get_classification_config();
    echo '<textarea name="' . esc_attr(LUMN_UT_CLASSIFICATION_CONFIG_OPTION) . '[appointment_domains]" rows="4" placeholder="scheduler.example.com">' . esc_textarea(implode("\n", $config['appointment_domains'])) . '</textarea>';
    echo '<p class="description">' . esc_html__('Third-party scheduling provider domains. A link to one of these (e.g. an online booking widget hosted elsewhere) is treated as an appointment click instead of a generic external link. Requires Automatic CTA Classification.', 'lumn-utilities') . '</p>';
}

function lumn_ut_external_link_excluded_domains_field_callback() {
    $config = lumn_ut_tracking_get_classification_config();
    echo '<textarea name="' . esc_attr(LUMN_UT_CLASSIFICATION_CONFIG_OPTION) . '[external_link_excluded_domains]" rows="4" placeholder="patientportal.example.com">' . esc_textarea(implode("\n", $config['external_link_excluded_domains'])) . '</textarea>';
    echo '<p class="description">' . esc_html__('Domains that should never generate a generic lumn_external_link event - e.g. a patient portal or sister site you consider part of this site\'s own flow. Excluding a domain here does not remove it from other tracking (an appointment/directions match still fires normally) - it only suppresses the generic external-link event for it.', 'lumn-utilities') . '</p>';
}

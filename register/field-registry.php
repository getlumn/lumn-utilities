<?php
namespace Lumn\Utilities;

/**
 * Canonical list of the practice-data options this plugin registers under
 * 'lumn_ut_shortcode_settings', keyed by option name. This is the single
 * source of truth that both register/fields.php (the admin settings form,
 * via lumn_ut_registered_setting_args()) and register/rest.php (the
 * lumn/v1 REST allowlist + registry endpoint) build from, so the set of
 * writable keys and how each is sanitized can't silently drift between
 * the two.
 *
 * Deliberately excludes 'lumn_other_shortcodes_field' - a Settings API
 * placeholder with no real data, not a practice-data field.
 */
/**
 * Canonical name => description map for the social/link shortcode types
 * (lumn_social_url_{name}). Single source of truth shared by the settings
 * registry below, the per-location override schema in register/locations.php,
 * the [lumn_social_url] shortcode, and the redirect handler - adding a new
 * social type only requires editing this list once.
 */
function lumn_ut_social_url_descriptions() {
    return array(
        'appointments' => 'Appointments link.',
        'payments' => 'Payments link.',
        'facebook' => 'Facebook URL.',
        'google' => 'Google Business Profile URL.',
        'googlemaps' => 'Google Maps / directions link. Automatically fires a lumn_directions_click event when clicked (see Directions Click Tracking).',
        'googlereviews' => 'Google Reviews link (view existing reviews).',
        'googlewritereview' => 'Write a Google Review link (direct link to leave a new review).',
        'instagram' => 'Instagram URL.',
        'linkedin' => 'LinkedIn URL.',
        'pinterest' => 'Pinterest URL.',
        'threads' => 'Threads URL.',
        'tiktok' => 'TikTok URL.',
        'x' => 'X (Twitter) URL.',
        'yelp' => 'Yelp URL.',
        'youtube' => 'YouTube URL.',
    );
}

// Just the names, e.g. for building '{name}_url' location override keys.
function lumn_ut_social_url_names() {
    return array_keys(lumn_ut_social_url_descriptions());
}

function lumn_ut_get_settings_registry() {
    $registry = array(
        'lumn_site_name' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_site_name', 'description' => 'Practice / site name.'),
        'lumn_call' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_call', 'description' => 'Primary phone number.'),
        'lumn_txt' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_txt', 'description' => 'Text-message number.'),
        'lumn_fax' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_fax', 'description' => 'Fax number.'),
        'lumn_email' => array('sanitize_callback' => 'sanitize_email', 'type' => 'string', 'shortcode' => 'lumn_email', 'description' => 'Contact email address.'),
        'lumn_address_street' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_address_street', 'description' => 'Street address.'),
        'lumn_address_street2' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_address_street2', 'description' => 'Street address line 2.'),
        'lumn_address_city' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_address_city', 'description' => 'City.'),
        'lumn_address_state' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_address_state', 'description' => 'State.'),
        'lumn_address_zip' => array('sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'shortcode' => 'lumn_address_zip', 'description' => 'ZIP code.'),
        'lumn_map' => array('sanitize_callback' => 'Lumn\Utilities\lumn_ut_sanitize_google_maps_embed', 'type' => 'string', 'shortcode' => 'lumn_map', 'description' => 'Google Maps embed iframe. Only a validated Google Maps embed URL survives sanitization; anything else is stored as empty.'),
    );

    foreach (lumn_ut_get_days_of_week() as $day) {
        $registry['lumn_hours_' . $day] = array(
            'sanitize_callback' => 'sanitize_text_field',
            'type' => 'string',
            'shortcode' => 'lumn_hours_' . $day,
            'description' => ucfirst($day) . ' hours (free-text display string, e.g. "8:00 AM - 5:00 PM").',
        );
    }

    foreach (lumn_ut_social_url_descriptions() as $name => $description) {
        $registry['lumn_social_url_' . $name] = array(
            'sanitize_callback' => 'esc_url_raw',
            'type' => 'string',
            'shortcode' => 'lumn_social_url',
            'shortcode_arg' => array('name' => $name),
            'description' => $description,
        );
    }

    return $registry;
}

// The register_setting() args array for one option, pulled from the
// registry above so the admin form's sanitize_callback can never drift
// from what the REST allowlist believes is registered.
function lumn_ut_registered_setting_args($option_name) {
    $registry = lumn_ut_get_settings_registry();
    return isset($registry[$option_name]['sanitize_callback'])
        ? array('sanitize_callback' => $registry[$option_name]['sanitize_callback'])
        : array();
}

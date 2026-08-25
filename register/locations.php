<?php
namespace Lumn\Utilities;

/**
 * Practice Locations data layer.
 *
 * Locations are stored as a single array option ('lumn_ut_locations') rather than
 * one wp_options row per field, since the number of locations and their fields is
 * open-ended. Each location is an associative array so new keys can be added later
 * without a schema migration.
 *
 * When no locations have been created, everything continues to resolve to the
 * legacy global options (lumn_site_name, lumn_call, lumn_address_street, etc.) that
 * the rest of the plugin already reads directly - existing sites are not required
 * to create a location to keep working.
 */

const LUMN_UT_LOCATIONS_OPTION = 'lumn_ut_locations';
const LUMN_UT_LOCATIONS_CAPABILITY = 'edit_pages';

function lumn_ut_default_location() {
    $hours = array();
    $structured_hours = array();
    foreach (lumn_ut_get_days_of_week() as $day) {
        $hours[$day] = '';
        $structured_hours[$day] = array(
            'open' => '',
            'close' => '',
            'closed' => false,
        );
    }

    $social_url_overrides = array();
    foreach (lumn_ut_social_url_names() as $name) {
        $social_url_overrides[$name . '_url'] = '';
    }

    return array_merge(array(
        'id' => 0,
        'slug' => '',
        'name' => '',
        'practice_name' => '',
        'address_street' => '',
        'address_street2' => '',
        'address_city' => '',
        'address_state' => '',
        'address_zip' => '',
        'phone' => '',
        'text_phone' => '',
        'fax' => '',
        'email' => '',
        'hours' => $hours,
        'map' => '',
        'google_place_id' => '',
        'latitude' => '',
        'longitude' => '',
        'timezone' => '',
        'structured_hours' => $structured_hours,
        'page_id' => null,
        'is_primary' => false,
        'menu_order' => 0,
        'meta' => array(),
    ), $social_url_overrides);
}

function lumn_ut_get_locations() {
    $locations = get_option(LUMN_UT_LOCATIONS_OPTION, array());

    if (!is_array($locations)) {
        return array();
    }

    $locations = array_map(function ($location) {
        return wp_parse_args(is_array($location) ? $location : array(), lumn_ut_default_location());
    }, $locations);

    usort($locations, function ($a, $b) {
        return $a['menu_order'] <=> $b['menu_order'];
    });

    return $locations;
}

function lumn_ut_save_locations($locations) {
    update_option(LUMN_UT_LOCATIONS_OPTION, array_values($locations));
}

function lumn_ut_get_location($location_id) {
    foreach (lumn_ut_get_locations() as $location) {
        if ((string) $location['id'] === (string) $location_id) {
            return $location;
        }
    }
    return null;
}

function lumn_ut_get_location_by_slug($slug) {
    foreach (lumn_ut_get_locations() as $location) {
        if ($location['slug'] === $slug) {
            return $location;
        }
    }
    return null;
}

function lumn_ut_get_primary_location() {
    $locations = lumn_ut_get_locations();

    foreach ($locations as $location) {
        if (!empty($location['is_primary'])) {
            return $location;
        }
    }

    return !empty($locations) ? $locations[0] : null;
}

/**
 * Resolves a "location" shortcode attribute (blank, "primary", a slug, or a numeric ID).
 * Returns:
 * - a location array, when $location_ref resolves to one
 * - null, when NO locations have been created yet - callers fall back to
 *   the legacy single-practice options in this case
 * - false, when locations exist but $location_ref (a slug/ID that isn't
 *   blank or 'primary') didn't match any of them - deliberately distinct
 *   from null, so an unknown reference resolves to nothing rather than
 *   silently falling back to the legacy options or the primary location.
 */
function lumn_ut_resolve_location($location_ref = '') {
    $locations = lumn_ut_get_locations();

    if (empty($locations)) {
        return null;
    }

    if (empty($location_ref) || $location_ref === 'primary') {
        return lumn_ut_get_primary_location();
    }

    $by_id = lumn_ut_get_location($location_ref);
    if ($by_id) {
        return $by_id;
    }

    $by_slug = lumn_ut_get_location_by_slug(sanitize_title($location_ref));
    if ($by_slug) {
        return $by_slug;
    }

    return false;
}

/**
 * Maps a location field key to the legacy single-practice option name it replaces.
 */
function lumn_ut_legacy_location_option_map() {
    return array(
        'practice_name' => 'lumn_site_name',
        'phone' => 'lumn_call',
        'text_phone' => 'lumn_txt',
        'fax' => 'lumn_fax',
        'email' => 'lumn_email',
        'address_street' => 'lumn_address_street',
        'address_street2' => 'lumn_address_street2',
        'address_city' => 'lumn_address_city',
        'address_state' => 'lumn_address_state',
        'address_zip' => 'lumn_address_zip',
        'map' => 'lumn_map',
    );
}

/**
 * Central lookup for future location-aware shortcodes, e.g. [lumn_call location="primary"].
 * Falls back to the existing legacy options when no locations exist yet, so current
 * installs keep behaving exactly as they do today.
 */
function lumn_ut_get_location_field($field_key, $location_ref = '') {
    $location = lumn_ut_resolve_location($location_ref);

    if ($location === null) {
        $legacy_map = lumn_ut_legacy_location_option_map();
        if (isset($legacy_map[$field_key])) {
            return get_option($legacy_map[$field_key]);
        }
        return '';
    }

    return isset($location[$field_key]) ? $location[$field_key] : '';
}

/**
 * Same fallback pattern as lumn_ut_get_location_field(), for a single day's hours.
 */
function lumn_ut_get_location_hours($day, $location_ref = '') {
    $location = lumn_ut_resolve_location($location_ref);

    if ($location === null) {
        return get_option('lumn_hours_' . $day);
    }

    return isset($location['hours'][$day]) ? $location['hours'][$day] : '';
}

/**
 * Structured (open/close/closed) hours for a single day. No legacy fallback -
 * pre-locations sites never had structured hours, so a no-locations site
 * simply reports every day closed rather than guessing from the display string.
 */
function lumn_ut_get_location_structured_hours($day, $location_ref = '') {
    $location = lumn_ut_resolve_location($location_ref);
    $default = array('open' => '', 'close' => '', 'closed' => false);

    if ($location === null) {
        return $default;
    }

    return isset($location['structured_hours'][$day]) ? $location['structured_hours'][$day] : $default;
}

function lumn_ut_generate_location_id($locations) {
    $max_id = 0;
    foreach ($locations as $location) {
        $max_id = max($max_id, (int) $location['id']);
    }
    return $max_id + 1;
}

function lumn_ut_generate_unique_location_slug($name, $locations, $ignore_id = null) {
    $base_slug = sanitize_title($name);
    if ($base_slug === '') {
        $base_slug = 'location';
    }

    $slug = $base_slug;
    $suffix = 2;

    while (true) {
        $collision = false;
        foreach ($locations as $location) {
            if ($location['slug'] === $slug && (string) $location['id'] !== (string) $ignore_id) {
                $collision = true;
                break;
            }
        }
        if (!$collision) {
            return $slug;
        }
        $slug = $base_slug . '-' . $suffix;
        $suffix++;
    }
}

// Sanitizes a 24-hour "HH:MM" time string. Returns '' for anything else,
// including empty input - callers treat '' as "not set", not midnight.
function lumn_ut_sanitize_time_hhmm($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
}

// Sanitizes a latitude/longitude float. Returns '' (not 0) for invalid
// input, since 0,0 is a real coordinate and shouldn't be indistinguishable
// from "not set".
function lumn_ut_sanitize_coordinate($value, $max_abs) {
    if ($value === '' || $value === null) {
        return '';
    }
    if (!is_numeric($value)) {
        return '';
    }
    $float = (float) $value;
    if ($float < -$max_abs || $float > $max_abs) {
        return '';
    }
    return $float;
}

function lumn_ut_sanitize_location_input($input) {
    $hours = array();
    $structured_hours = array();
    foreach (lumn_ut_get_days_of_week() as $day) {
        $hours[$day] = isset($input['hours'][$day]) ? sanitize_text_field(wp_unslash($input['hours'][$day])) : '';

        $day_input = isset($input['structured_hours'][$day]) && is_array($input['structured_hours'][$day])
            ? $input['structured_hours'][$day]
            : array();
        $closed = !empty($day_input['closed']);
        $structured_hours[$day] = array(
            'open' => $closed ? '' : lumn_ut_sanitize_time_hhmm(isset($day_input['open']) ? wp_unslash($day_input['open']) : ''),
            'close' => $closed ? '' : lumn_ut_sanitize_time_hhmm(isset($day_input['close']) ? wp_unslash($day_input['close']) : ''),
            'closed' => $closed,
        );
    }

    $timezone = isset($input['timezone']) ? sanitize_text_field(wp_unslash($input['timezone'])) : '';
    if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
        $timezone = '';
    }

    $page_id = isset($input['page_id']) ? absint($input['page_id']) : 0;
    if ($page_id > 0 && get_post_type($page_id) !== 'page') {
        $page_id = 0;
    }

    $social_url_overrides = array();
    foreach (lumn_ut_social_url_names() as $name) {
        $key = $name . '_url';
        $social_url_overrides[$key] = isset($input[$key]) ? esc_url_raw(wp_unslash($input[$key])) : '';
    }

    return array_merge(array(
        'name' => isset($input['name']) ? sanitize_text_field(wp_unslash($input['name'])) : '',
        'practice_name' => isset($input['practice_name']) ? sanitize_text_field(wp_unslash($input['practice_name'])) : '',
        'address_street' => isset($input['address_street']) ? sanitize_text_field(wp_unslash($input['address_street'])) : '',
        'address_street2' => isset($input['address_street2']) ? sanitize_text_field(wp_unslash($input['address_street2'])) : '',
        'address_city' => isset($input['address_city']) ? sanitize_text_field(wp_unslash($input['address_city'])) : '',
        'address_state' => isset($input['address_state']) ? sanitize_text_field(wp_unslash($input['address_state'])) : '',
        'address_zip' => isset($input['address_zip']) ? sanitize_text_field(wp_unslash($input['address_zip'])) : '',
        'phone' => isset($input['phone']) ? sanitize_text_field(wp_unslash($input['phone'])) : '',
        'text_phone' => isset($input['text_phone']) ? sanitize_text_field(wp_unslash($input['text_phone'])) : '',
        'fax' => isset($input['fax']) ? sanitize_text_field(wp_unslash($input['fax'])) : '',
        'email' => isset($input['email']) ? sanitize_email(wp_unslash($input['email'])) : '',
        'hours' => $hours,
        'map' => isset($input['map']) ? lumn_ut_sanitize_google_maps_embed(wp_unslash($input['map'])) : '',
        'google_place_id' => isset($input['google_place_id']) ? sanitize_text_field(wp_unslash($input['google_place_id'])) : '',
        'latitude' => isset($input['latitude']) ? lumn_ut_sanitize_coordinate(wp_unslash($input['latitude']), 90) : '',
        'longitude' => isset($input['longitude']) ? lumn_ut_sanitize_coordinate(wp_unslash($input['longitude']), 180) : '',
        'timezone' => $timezone,
        'structured_hours' => $structured_hours,
        'page_id' => $page_id > 0 ? $page_id : null,
    ), $social_url_overrides);
}

function lumn_ut_locations_redirect($notice) {
    wp_safe_redirect(add_query_arg(
        array('page' => 'lumn-ut-locations', 'lumn_ut_notice' => $notice),
        admin_url('admin.php')
    ));
    exit;
}

add_action('admin_post_lumn_ut_save_location', 'Lumn\Utilities\lumn_ut_handle_save_location');
function lumn_ut_handle_save_location() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_save_location');

    $locations = lumn_ut_get_locations();
    $location_id = isset($_POST['location_id']) ? sanitize_text_field(wp_unslash($_POST['location_id'])) : '';
    $fields = lumn_ut_sanitize_location_input($_POST);

    $existing_index = null;
    foreach ($locations as $index => $location) {
        if ($location_id !== '' && (string) $location['id'] === (string) $location_id) {
            $existing_index = $index;
            break;
        }
    }

    if ($existing_index !== null) {
        $fields['id'] = $locations[$existing_index]['id'];
        $fields['is_primary'] = $locations[$existing_index]['is_primary'];
        $fields['menu_order'] = $locations[$existing_index]['menu_order'];
        $fields['slug'] = $locations[$existing_index]['slug'];
        $fields['meta'] = $locations[$existing_index]['meta'];
        if ($fields['slug'] === '') {
            $fields['slug'] = lumn_ut_generate_unique_location_slug($fields['name'] ?: $fields['practice_name'], $locations, $fields['id']);
        }
        $locations[$existing_index] = $fields;
    } else {
        $fields['id'] = lumn_ut_generate_location_id($locations);
        $fields['slug'] = lumn_ut_generate_unique_location_slug($fields['name'] ?: $fields['practice_name'], $locations);
        $fields['is_primary'] = empty($locations);
        $fields['menu_order'] = count($locations);
        $fields['meta'] = array();
        $locations[] = $fields;
    }

    lumn_ut_save_locations($locations);
    lumn_ut_locations_redirect('saved');
}

add_action('admin_post_lumn_ut_delete_location', 'Lumn\Utilities\lumn_ut_handle_delete_location');
function lumn_ut_handle_delete_location() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }

    $location_id = isset($_GET['location_id']) ? sanitize_text_field(wp_unslash($_GET['location_id'])) : '';
    check_admin_referer('lumn_ut_delete_location_' . $location_id);

    $locations = lumn_ut_get_locations();
    $deleted_was_primary = false;
    $remaining = array();

    foreach ($locations as $location) {
        if ((string) $location['id'] === (string) $location_id) {
            $deleted_was_primary = !empty($location['is_primary']);
            continue;
        }
        $remaining[] = $location;
    }

    if ($deleted_was_primary && !empty($remaining)) {
        $remaining[0]['is_primary'] = true;
    }

    foreach ($remaining as $index => &$location) {
        $location['menu_order'] = $index;
    }
    unset($location);

    lumn_ut_save_locations($remaining);
    lumn_ut_locations_redirect('deleted');
}

add_action('admin_post_lumn_ut_set_primary_location', 'Lumn\Utilities\lumn_ut_handle_set_primary_location');
function lumn_ut_handle_set_primary_location() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }

    $location_id = isset($_GET['location_id']) ? sanitize_text_field(wp_unslash($_GET['location_id'])) : '';
    check_admin_referer('lumn_ut_set_primary_location_' . $location_id);

    $locations = lumn_ut_get_locations();
    foreach ($locations as &$location) {
        $location['is_primary'] = ((string) $location['id'] === (string) $location_id);
    }
    unset($location);

    lumn_ut_save_locations($locations);
    lumn_ut_locations_redirect('primary_updated');
}

add_action('admin_post_lumn_ut_move_location', 'Lumn\Utilities\lumn_ut_handle_move_location');
function lumn_ut_handle_move_location() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }

    $location_id = isset($_GET['location_id']) ? sanitize_text_field(wp_unslash($_GET['location_id'])) : '';
    $direction = isset($_GET['direction']) && $_GET['direction'] === 'up' ? 'up' : 'down';
    check_admin_referer('lumn_ut_move_location_' . $location_id . '_' . $direction);

    $locations = lumn_ut_get_locations();
    $index = null;
    foreach ($locations as $i => $location) {
        if ((string) $location['id'] === (string) $location_id) {
            $index = $i;
            break;
        }
    }

    if ($index !== null) {
        $swap_index = $direction === 'up' ? $index - 1 : $index + 1;
        if (isset($locations[$swap_index])) {
            $tmp_order = $locations[$index]['menu_order'];
            $locations[$index]['menu_order'] = $locations[$swap_index]['menu_order'];
            $locations[$swap_index]['menu_order'] = $tmp_order;
        }
    }

    lumn_ut_save_locations($locations);
    lumn_ut_locations_redirect('reordered');
}

/**
 * Explicitly user-triggered only - never runs automatically on upgrade or
 * activation. Snapshots the existing single-practice options into a new
 * primary location; existing locations (if any) are kept and demoted from
 * primary rather than replaced, so running this more than once is harmless.
 */
add_action('admin_post_lumn_ut_backfill_location', 'Lumn\Utilities\lumn_ut_handle_backfill_location');
function lumn_ut_handle_backfill_location() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_backfill_location');

    $hours = array();
    foreach (lumn_ut_get_days_of_week() as $day) {
        $hours[$day] = sanitize_text_field(get_option('lumn_hours_' . $day));
    }

    $site_name = sanitize_text_field(get_option('lumn_site_name'));
    $locations = lumn_ut_get_locations();

    $new_location = wp_parse_args(array(
        'name' => $site_name !== '' ? $site_name : __('Main Location', 'lumn-utilities'),
        'practice_name' => $site_name,
        'address_street' => sanitize_text_field(get_option('lumn_address_street')),
        'address_street2' => sanitize_text_field(get_option('lumn_address_street2')),
        'address_city' => sanitize_text_field(get_option('lumn_address_city')),
        'address_state' => sanitize_text_field(get_option('lumn_address_state')),
        'address_zip' => sanitize_text_field(get_option('lumn_address_zip')),
        'phone' => sanitize_text_field(get_option('lumn_call')),
        'text_phone' => sanitize_text_field(get_option('lumn_txt')),
        'fax' => sanitize_text_field(get_option('lumn_fax')),
        'email' => sanitize_email(get_option('lumn_email')),
        'hours' => $hours,
        'map' => lumn_ut_sanitize_google_maps_embed(get_option('lumn_map')),
    ), lumn_ut_default_location());

    $new_location['id'] = lumn_ut_generate_location_id($locations);
    $new_location['slug'] = lumn_ut_generate_unique_location_slug($new_location['name'], $locations);
    $new_location['is_primary'] = true;
    $new_location['menu_order'] = count($locations);

    foreach ($locations as &$existing_location) {
        $existing_location['is_primary'] = false;
    }
    unset($existing_location);

    $locations[] = $new_location;

    lumn_ut_save_locations($locations);
    lumn_ut_locations_redirect('backfilled');
}

add_action('admin_menu', function () {
    add_submenu_page(
        'lumn-ut-shortcode-settings',
        'Practice Locations',
        'Practice Locations',
        LUMN_UT_LOCATIONS_CAPABILITY,
        'lumn-ut-locations',
        'Lumn\Utilities\lumn_ut_locations_page_callback'
    );
});

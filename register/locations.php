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
    foreach (lumn_ut_get_days_of_week() as $day) {
        $hours[$day] = '';
    }

    return array(
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
        'is_primary' => false,
        'menu_order' => 0,
        'meta' => array(),
    );
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
 * Resolves a "location" shortcode attribute (blank, "primary", a slug, or a numeric ID)
 * to a location array. Returns null when no locations have been created, so callers can
 * fall back to the legacy global options.
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

    return lumn_ut_get_location_by_slug(sanitize_title($location_ref));
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

function lumn_ut_sanitize_location_input($input) {
    $hours = array();
    foreach (lumn_ut_get_days_of_week() as $day) {
        $hours[$day] = isset($input['hours'][$day]) ? sanitize_text_field(wp_unslash($input['hours'][$day])) : '';
    }

    return array(
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
    );
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

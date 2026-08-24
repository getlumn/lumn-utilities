<?php
namespace Lumn\Utilities;

/**
 * The lumn/v1 REST namespace - the only programmatic door into this plugin's
 * data. Data plane only (read/write practice data and locations); the
 * blueprint executor, page creation, and pattern rendering are a later,
 * separate piece of work and are not part of this namespace.
 *
 * Every route requires the lumn_manage_site_data capability. That
 * capability is deliberately not the same as LUMN_UT_LOCATIONS_CAPABILITY
 * (edit_pages, used by the admin screen) - REST access is a narrower,
 * separate grant so it can later be handed to a dedicated lumn_builder
 * role without also widening admin-screen access.
 */

const LUMN_UT_REST_CAPABILITY = 'lumn_manage_site_data';
const LUMN_UT_REST_NAMESPACE = 'lumn/v1';

/**
 * Grants the REST capability to Administrators. Registered both on plugin
 * activation (the normal WordPress convention) and on admin_init: this
 * plugin is typically deployed by overwriting files via git rather than
 * through a deactivate/reactivate cycle (see the Kinsta test pipeline),
 * so relying on the activation hook alone would leave already-active
 * installs without the capability after an update. Both call sites are
 * idempotent.
 */
function lumn_ut_rest_ensure_capability() {
    $role = get_role('administrator');
    if ($role && !$role->has_cap(LUMN_UT_REST_CAPABILITY)) {
        $role->add_cap(LUMN_UT_REST_CAPABILITY);
    }
}
add_action('admin_init', 'Lumn\Utilities\lumn_ut_rest_ensure_capability');

// permission_callback for every route below. 401 when there is no
// authenticated user at all, 403 when there is one but it lacks the
// capability - never __return_true.
function lumn_ut_rest_permission_check($request) {
    if (!is_user_logged_in()) {
        return new \WP_Error(
            'lumn_rest_unauthorized',
            __('Authentication is required to access this resource.', 'lumn-utilities'),
            array('status' => 401)
        );
    }

    if (!current_user_can(LUMN_UT_REST_CAPABILITY)) {
        return new \WP_Error(
            'lumn_rest_forbidden',
            __('You do not have permission to access this resource.', 'lumn-utilities'),
            array('status' => 403)
        );
    }

    return true;
}

function lumn_ut_rest_id_args() {
    return array(
        'id' => array(
            'required' => true,
            'validate_callback' => function ($param) {
                return is_numeric($param) && (int) $param > 0;
            },
            'sanitize_callback' => 'absint',
        ),
    );
}

add_action('rest_api_init', function () {
    register_rest_route(LUMN_UT_REST_NAMESPACE, '/registry', array(
        'methods' => 'GET',
        'callback' => 'Lumn\Utilities\lumn_ut_rest_get_registry',
        'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
    ));

    register_rest_route(LUMN_UT_REST_NAMESPACE, '/settings', array(
        array(
            'methods' => 'GET',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_get_settings',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
        ),
        array(
            'methods' => 'POST',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_update_settings',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
        ),
    ));

    register_rest_route(LUMN_UT_REST_NAMESPACE, '/locations', array(
        array(
            'methods' => 'GET',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_get_locations',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
        ),
        array(
            'methods' => 'POST',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_create_location',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
        ),
    ));

    register_rest_route(LUMN_UT_REST_NAMESPACE, '/locations/(?P<id>\d+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_get_location',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
            'args' => lumn_ut_rest_id_args(),
        ),
        array(
            'methods' => array('PUT', 'PATCH'),
            'callback' => 'Lumn\Utilities\lumn_ut_rest_update_location',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
            'args' => lumn_ut_rest_id_args(),
        ),
        array(
            'methods' => 'DELETE',
            'callback' => 'Lumn\Utilities\lumn_ut_rest_delete_location',
            'permission_callback' => 'Lumn\Utilities\lumn_ut_rest_permission_check',
            'args' => lumn_ut_rest_id_args(),
        ),
    ));
});

// ---------------------------------------------------------------------
// GET /registry
// ---------------------------------------------------------------------

function lumn_ut_rest_get_registry($request) {
    $settings_out = array();
    foreach (lumn_ut_get_settings_registry() as $key => $meta) {
        $settings_out[$key] = array(
            'type' => $meta['type'],
            'description' => $meta['description'],
        );
    }

    return rest_ensure_response(array(
        'plugin_version' => lumn_ut_get_plugin_version(),
        'registry_version' => 1,
        'settings' => $settings_out,
        'shortcodes' => lumn_ut_get_shortcode_registry(),
        'icons' => lumn_ut_get_available_svg_icons(),
        'location_fields' => array_keys(lumn_ut_default_location()),
    ));
}

function lumn_ut_get_plugin_version() {
    $data = get_file_data(LUMN_UTILITIES_PLUGIN_PATH . 'index.php', array('Version' => 'Version'));
    return isset($data['Version']) ? $data['Version'] : '';
}

function lumn_ut_get_available_svg_icons() {
    $icons = array();
    foreach (glob(LUMN_UTILITIES_PLUGIN_PATH . 'svgs/*.svg') ?: array() as $file) {
        $icons[] = basename($file, '.svg');
    }
    sort($icons);
    return $icons;
}

// Hand-maintained shortcode/attribute descriptions for the builder service.
// Kept here rather than derived by reflection, since shortcode_atts() args
// are local to each callback and not introspectable; this stays a small,
// flat data structure in keeping with the rest of the plugin.
function lumn_ut_get_shortcode_registry() {
    $html_tag_attr = array('name' => 'html_tag', 'type' => 'string', 'description' => 'Optional wrapping tag: p, h1-h6, span, div, strong, em, i, or b.');
    $location_attr = array('name' => 'location', 'type' => 'string', 'description' => "Optional. '', 'primary', a location slug, or a numeric location ID.");

    $location_aware = array(
        'lumn_call', 'lumn_txt', 'lumn_fax', 'lumn_email',
        'lumn_address_street', 'lumn_address_street2', 'lumn_address_city', 'lumn_address_state', 'lumn_address_zip',
        'lumn_map',
    );
    $site_wide_only = array('lumn_site_name', 'lumn_copyright', 'lumn_year');

    $registry = array();

    foreach ($location_aware as $tag) {
        $registry[$tag] = array('attributes' => array($html_tag_attr, $location_attr));
    }
    foreach ($site_wide_only as $tag) {
        $registry[$tag] = array('attributes' => array($html_tag_attr));
    }

    $registry['lumn_address'] = array('attributes' => array(
        array('name' => 'singleline', 'type' => 'boolean', 'description' => 'Render on a single line instead of with <br> separators.'),
        $html_tag_attr,
        $location_attr,
    ));

    $registry['lumn_hours'] = array('attributes' => array(
        array('name' => 'format', 'type' => 'string', 'description' => "'list', 'table', or plain text (default)."),
        $html_tag_attr,
        array('name' => 'abbreviate', 'type' => 'boolean', 'description' => 'Abbreviate day names.'),
        array('name' => 'grouped', 'type' => 'boolean', 'description' => 'Group consecutive days with identical hours.'),
        array('name' => 'hide_closed', 'type' => 'boolean', 'description' => "Hide days set to 'Closed'."),
        $location_attr,
    ));

    foreach (lumn_ut_get_days_of_week() as $day) {
        $registry['lumn_hours_' . $day] = array('attributes' => array($html_tag_attr, $location_attr));
    }

    $registry['lumn_social_url'] = array('attributes' => array(
        array('name' => 'name', 'type' => 'string', 'description' => 'appointments, payments, facebook, google, instagram, linkedin, pinterest, threads, tiktok, x, yelp, or youtube.'),
        array('name' => 'location', 'type' => 'string', 'description' => "Per-location override - appointments/payments only, ignored for every other name. '', 'primary', a slug, or a numeric location ID."),
    ));

    $registry['lumn_svg'] = array('attributes' => array(
        array('name' => 'name', 'type' => 'string', 'description' => 'A bundled icon name - see the icons list in this response.'),
        array('name' => 'src', 'type' => 'string', 'description' => 'Path to an .svg file inside the uploads directory.'),
    ));

    $registry['lumn_locations'] = array('attributes' => array(
        array('name' => 'format', 'type' => 'string', 'description' => "'list' (default) or 'table'."),
        $html_tag_attr,
    ));

    return $registry;
}

// ---------------------------------------------------------------------
// GET/POST /settings
// ---------------------------------------------------------------------

function lumn_ut_rest_get_settings($request) {
    $values = array();
    foreach (lumn_ut_get_settings_registry() as $key => $meta) {
        $values[$key] = get_option($key);
    }
    return rest_ensure_response($values);
}

function lumn_ut_rest_update_settings($request) {
    $registry = lumn_ut_get_settings_registry();
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = array();
    }

    $unknown_keys = array_diff(array_keys($params), array_keys($registry));
    if (!empty($unknown_keys)) {
        return new \WP_Error(
            'lumn_rest_unknown_setting',
            sprintf(
                /* translators: %s: comma-separated list of unrecognized setting keys */
                __('Unknown setting key(s): %s', 'lumn-utilities'),
                implode(', ', $unknown_keys)
            ),
            array('status' => 400)
        );
    }

    $result = array();
    $changed = array();

    foreach ($params as $key => $value) {
        $previous = get_option($key);

        $sanitize_callback = $registry[$key]['sanitize_callback'];
        $sanitized = is_callable($sanitize_callback) ? call_user_func($sanitize_callback, $value) : sanitize_text_field($value);

        update_option($key, $sanitized);

        $current = get_option($key);
        $result[$key] = $current;
        $changed[$key] = ((string) $previous !== (string) $current);
    }

    return rest_ensure_response(array(
        'settings' => $result,
        'changed' => $changed,
    ));
}

// ---------------------------------------------------------------------
// /locations
// ---------------------------------------------------------------------

function lumn_ut_rest_get_locations($request) {
    return rest_ensure_response(lumn_ut_get_locations());
}

function lumn_ut_rest_get_location($request) {
    $location = lumn_ut_get_location($request['id']);
    if ($location === null) {
        return new \WP_Error('lumn_rest_location_not_found', __('Location not found.', 'lumn-utilities'), array('status' => 404));
    }
    return rest_ensure_response($location);
}

function lumn_ut_rest_create_location($request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = array();
    }

    $locations = lumn_ut_get_locations();
    $fields = lumn_ut_sanitize_location_input($params);

    $fields['id'] = lumn_ut_generate_location_id($locations);
    $fields['slug'] = lumn_ut_generate_unique_location_slug($fields['name'] !== '' ? $fields['name'] : $fields['practice_name'], $locations);
    $fields['is_primary'] = empty($locations);
    $fields['menu_order'] = count($locations);
    $fields['meta'] = array();

    $locations[] = $fields;
    lumn_ut_save_locations($locations);

    $response = rest_ensure_response($fields);
    $response->set_status(201);
    return $response;
}

function lumn_ut_rest_update_location($request) {
    $locations = lumn_ut_get_locations();
    $location_id = $request['id'];

    $existing_index = null;
    foreach ($locations as $index => $location) {
        if ((string) $location['id'] === (string) $location_id) {
            $existing_index = $index;
            break;
        }
    }

    if ($existing_index === null) {
        return new \WP_Error('lumn_rest_location_not_found', __('Location not found.', 'lumn-utilities'), array('status' => 404));
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = array();
    }

    // PATCH/PUT both merge onto the existing record here, so a partial body
    // (e.g. just { "phone": "..." }) updates only what it names rather than
    // blanking every field lumn_ut_sanitize_location_input() doesn't see.
    $merged_input = array_replace_recursive($locations[$existing_index], $params);
    $fields = lumn_ut_sanitize_location_input($merged_input);

    $fields['id'] = $locations[$existing_index]['id'];
    $fields['slug'] = $locations[$existing_index]['slug'];
    $fields['is_primary'] = $locations[$existing_index]['is_primary'];
    $fields['menu_order'] = $locations[$existing_index]['menu_order'];
    $fields['meta'] = $locations[$existing_index]['meta'];

    $locations[$existing_index] = $fields;
    lumn_ut_save_locations($locations);

    return rest_ensure_response($fields);
}

function lumn_ut_rest_delete_location($request) {
    $locations = lumn_ut_get_locations();
    $location_id = $request['id'];

    $found = false;
    $deleted_was_primary = false;
    $remaining = array();

    foreach ($locations as $location) {
        if ((string) $location['id'] === (string) $location_id) {
            $found = true;
            $deleted_was_primary = !empty($location['is_primary']);
            continue;
        }
        $remaining[] = $location;
    }

    if (!$found) {
        return new \WP_Error('lumn_rest_location_not_found', __('Location not found.', 'lumn-utilities'), array('status' => 404));
    }

    if ($deleted_was_primary && !empty($remaining)) {
        $remaining[0]['is_primary'] = true;
    }

    foreach ($remaining as $index => &$location) {
        $location['menu_order'] = $index;
    }
    unset($location);

    lumn_ut_save_locations($remaining);

    return rest_ensure_response(array('deleted' => true, 'id' => (int) $location_id));
}

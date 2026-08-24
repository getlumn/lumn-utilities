<?php
namespace Lumn\Utilities;

/**
 * Rendering for the "Practice Locations" admin page. Kept separate from
 * register/locations.php (data + hooks) so each file stays focused.
 */

function lumn_ut_locations_page_callback() {
    if (!current_user_can(LUMN_UT_LOCATIONS_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    $editing_id = isset($_GET['location_id']) ? sanitize_text_field(wp_unslash($_GET['location_id'])) : '';

    echo '<div class="lumn-ut-admin-settings-wrap wrap lumn-ut-locations-page">';
    lumn_ut_render_admin_header(__('Manage one or more physical practice locations.', 'lumn-utilities'));

    if (isset($_GET['lumn_ut_notice'])) {
        lumn_ut_render_location_notice(sanitize_text_field(wp_unslash($_GET['lumn_ut_notice'])));
    }

    if ($editing_id !== '') {
        $location = $editing_id === 'new' ? null : lumn_ut_get_location($editing_id);
        lumn_ut_render_location_form($location);
    } else {
        lumn_ut_render_locations_list();
    }

    echo '</div>';
}

function lumn_ut_render_location_notice($type) {
    $messages = array(
        'saved' => __('Location saved.', 'lumn-utilities'),
        'deleted' => __('Location deleted.', 'lumn-utilities'),
        'primary_updated' => __('Primary location updated.', 'lumn-utilities'),
        'reordered' => __('Location order updated.', 'lumn-utilities'),
        'backfilled' => __('Created a location from the existing practice information.', 'lumn-utilities'),
    );

    if (isset($messages[$type])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$type]) . '</p></div>';
    }
}

function lumn_ut_render_locations_list() {
    $locations = lumn_ut_get_locations();

    echo '<p>' . esc_html__('Manage one or more practice locations. Each location can store its own name, address, contact info, and hours.', 'lumn-utilities') . '</p>';

    echo '<div class="lumn-utilites-admin-accordion">';
    echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">' . esc_html__('How to Use', 'lumn-utilities') . '</span><span class="plus">+</span><span class="minus">-</span></div>';
    echo '<div class="lumn-utilites-admin-accordion-content">';
    echo '<p>' . esc_html__('A location holds its own name, address, contact info, hours, and map - separate from the General Business Information settings above.', 'lumn-utilities') . '</p>';
    echo '<table class="lumn-utilites-table">';
    echo '<tr><th>' . esc_html__('If you...', 'lumn-utilities') . '</th><th>' . esc_html__('Then...', 'lumn-utilities') . '</th></tr>';
    echo '<tr><td>' . esc_html__('Have no locations yet', 'lumn-utilities') . '</td><td>' . esc_html__('Every shortcode keeps using the General Business Information, Address, and Hours settings, exactly as before. Nothing changes until you add a location.', 'lumn-utilities') . '</td></tr>';
    echo '<tr><td>' . esc_html__('Only have one office', 'lumn-utilities') . '</td><td>' . esc_html__('Click "Create a location from the existing practice information" to copy your current settings into a location - or just leave things as they are.', 'lumn-utilities') . '</td></tr>';
    echo '<tr><td>' . esc_html__('Have more than one office', 'lumn-utilities') . '</td><td>' . esc_html__('Click "Add New Location" for each one. Mark the main office "Primary" - that\'s what shortcodes use by default.', 'lumn-utilities') . '</td></tr>';
    echo '</table>';
    echo '<p><strong>' . esc_html__('Pointing a shortcode at a specific location:', 'lumn-utilities') . '</strong></p>';
    echo '<p>' . esc_html__('Add a location attribute to any practice-data shortcode - the slug shown in the table below, "primary", or the numeric ID:', 'lumn-utilities') . '</p>';
    echo '<p>[lumn_call location="north-office"] &nbsp; [lumn_address location="primary"] &nbsp; [lumn_hours location="2"]</p>';
    echo '<p>' . esc_html__('Leaving the attribute off (or blank) always resolves to the Primary location. Use [lumn_locations] to list every location - useful for a "Find a Location" page.', 'lumn-utilities') . '</p>';
    echo '<p><strong>' . esc_html__('Display hours vs. structured hours:', 'lumn-utilities') . '</strong> ' . esc_html__('the free-text "Hours" fields are what [lumn_hours] displays on the page; the "Structured Hours" fields (exact open/close times) are for map and search-engine data and don\'t need to match word-for-word.', 'lumn-utilities') . '</p>';
    echo '</div>';
    echo '</div>';

    $add_url = add_query_arg(array('page' => 'lumn-ut-locations', 'location_id' => 'new'), admin_url('admin.php'));
    $backfill_url = wp_nonce_url(
        add_query_arg(array('action' => 'lumn_ut_backfill_location'), admin_url('admin-post.php')),
        'lumn_ut_backfill_location'
    );

    echo '<p>';
    echo '<a href="' . esc_url($add_url) . '" class="button button-primary">' . esc_html__('Add New Location', 'lumn-utilities') . '</a> ';
    echo '<a href="' . esc_url($backfill_url) . '" class="button">' . esc_html__('Create a location from the existing practice information', 'lumn-utilities') . '</a>';
    echo '</p>';

    if (empty($locations)) {
        echo '<p>' . esc_html__('No practice locations have been created yet. The plugin will keep using the General Business Information, Business Address, and Business Hours settings above until at least one location is added here.', 'lumn-utilities') . '</p>';
        return;
    }

    $count = count($locations);

    echo '<table class="widefat striped lumn-ut-locations-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Name', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Practice Name', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Slug', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Primary', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Order', 'lumn-utilities') . '</th>';
    echo '<th>' . esc_html__('Actions', 'lumn-utilities') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($locations as $index => $location) {
        $edit_url = add_query_arg(array('page' => 'lumn-ut-locations', 'location_id' => $location['id']), admin_url('admin.php'));
        $delete_url = wp_nonce_url(
            add_query_arg(array('action' => 'lumn_ut_delete_location', 'location_id' => $location['id']), admin_url('admin-post.php')),
            'lumn_ut_delete_location_' . $location['id']
        );
        $primary_url = wp_nonce_url(
            add_query_arg(array('action' => 'lumn_ut_set_primary_location', 'location_id' => $location['id']), admin_url('admin-post.php')),
            'lumn_ut_set_primary_location_' . $location['id']
        );
        $up_url = wp_nonce_url(
            add_query_arg(array('action' => 'lumn_ut_move_location', 'location_id' => $location['id'], 'direction' => 'up'), admin_url('admin-post.php')),
            'lumn_ut_move_location_' . $location['id'] . '_up'
        );
        $down_url = wp_nonce_url(
            add_query_arg(array('action' => 'lumn_ut_move_location', 'location_id' => $location['id'], 'direction' => 'down'), admin_url('admin-post.php')),
            'lumn_ut_move_location_' . $location['id'] . '_down'
        );

        echo '<tr>';
        echo '<td><strong>' . esc_html($location['name']) . '</strong></td>';
        echo '<td>' . esc_html($location['practice_name']) . '</td>';
        echo '<td><code>' . esc_html($location['slug']) . '</code></td>';

        echo '<td>';
        if (!empty($location['is_primary'])) {
            echo '<span class="lumn-ut-primary-badge">' . esc_html__('Primary', 'lumn-utilities') . '</span>';
        } else {
            echo '<a href="' . esc_url($primary_url) . '">' . esc_html__('Make Primary', 'lumn-utilities') . '</a>';
        }
        echo '</td>';

        echo '<td>';
        if ($index > 0) {
            echo '<a href="' . esc_url($up_url) . '" aria-label="' . esc_attr__('Move up', 'lumn-utilities') . '">&uarr;</a> ';
        }
        if ($index < $count - 1) {
            echo '<a href="' . esc_url($down_url) . '" aria-label="' . esc_attr__('Move down', 'lumn-utilities') . '">&darr;</a>';
        }
        echo '</td>';

        echo '<td>';
        echo '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'lumn-utilities') . '</a> | ';
        echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this location? This cannot be undone.', 'lumn-utilities')) . '\');">' . esc_html__('Delete', 'lumn-utilities') . '</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function lumn_ut_render_location_form($location) {
    $is_edit = $location !== null;
    $location = wp_parse_args($location ?: array(), lumn_ut_default_location());
    $cancel_url = add_query_arg(array('page' => 'lumn-ut-locations'), admin_url('admin.php'));

    echo '<h3>' . ($is_edit ? esc_html__('Edit Location', 'lumn-utilities') : esc_html__('Add New Location', 'lumn-utilities')) . '</h3>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="lumn-ut-location-form">';
    wp_nonce_field('lumn_ut_save_location');
    echo '<input type="hidden" name="action" value="lumn_ut_save_location" />';
    echo '<input type="hidden" name="location_id" value="' . esc_attr($is_edit ? $location['id'] : '') . '" />';

    echo '<table class="form-table">';
    lumn_ut_location_field_row('name', __('Location Name', 'lumn-utilities'), $location['name'], 'text', __('e.g. Downtown Office (internal label)', 'lumn-utilities'));
    lumn_ut_location_field_row('practice_name', __('Practice / Display Name', 'lumn-utilities'), $location['practice_name'], 'text', __('Name shown to patients', 'lumn-utilities'));
    lumn_ut_location_field_row('address_street', __('Street Address', 'lumn-utilities'), $location['address_street'], 'text', '123 Elm St.');
    lumn_ut_location_field_row('address_street2', __('Street Address Line 2', 'lumn-utilities'), $location['address_street2'], 'text', 'Apt 4B');
    lumn_ut_location_field_row('address_city', __('City', 'lumn-utilities'), $location['address_city'], 'text', __('Example City', 'lumn-utilities'));
    lumn_ut_location_field_row('address_state', __('State', 'lumn-utilities'), $location['address_state'], 'text', 'UT');
    lumn_ut_location_field_row('address_zip', __('ZIP Code', 'lumn-utilities'), $location['address_zip'], 'text', '84123');
    lumn_ut_location_field_row('phone', __('Phone Number', 'lumn-utilities'), $location['phone'], 'tel', '555-555-5555');
    lumn_ut_location_field_row('text_phone', __('Text Number', 'lumn-utilities'), $location['text_phone'], 'tel', '555-555-5555');
    lumn_ut_location_field_row('fax', __('Fax Number', 'lumn-utilities'), $location['fax'], 'tel', '555-555-5555');
    lumn_ut_location_field_row('email', __('Email', 'lumn-utilities'), $location['email'], 'email', 'mail@example.com');
    echo '</table>';

    echo '<h4>' . esc_html__('Location Details', 'lumn-utilities') . '</h4>';
    echo '<table class="form-table">';

    echo '<tr><th><label for="location_map">' . esc_html__('Google Maps Iframe', 'lumn-utilities') . '</label></th><td>';
    echo '<textarea id="location_map" name="map" placeholder="&lt;iframe src=&hellip;">' . esc_textarea($location['map']) . '</textarea>';
    echo '</td></tr>';

    lumn_ut_location_field_row('google_place_id', __('Google Place ID', 'lumn-utilities'), $location['google_place_id'], 'text', __('Used for reviews widgets and Business Profile lookups', 'lumn-utilities'));
    lumn_ut_location_field_row('latitude', __('Latitude', 'lumn-utilities'), $location['latitude'], 'text', '40.7608');
    lumn_ut_location_field_row('longitude', __('Longitude', 'lumn-utilities'), $location['longitude'], 'text', '-111.8910');

    echo '<tr><th><label for="location_timezone">' . esc_html__('Timezone', 'lumn-utilities') . '</label></th><td>';
    echo '<select id="location_timezone" name="timezone"><option value="">' . esc_html__('— Not set —', 'lumn-utilities') . '</option>';
    foreach (timezone_identifiers_list() as $tz) {
        echo '<option value="' . esc_attr($tz) . '"' . selected($location['timezone'], $tz, false) . '>' . esc_html($tz) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th><label for="location_page_id">' . esc_html__('Location Page', 'lumn-utilities') . '</label></th><td>';
    wp_dropdown_pages(array(
        'name' => 'page_id',
        'id' => 'location_page_id',
        'selected' => (int) $location['page_id'],
        'show_option_none' => __('— None —', 'lumn-utilities'),
        'option_none_value' => '0',
    ));
    echo '</td></tr>';

    lumn_ut_location_field_row('appointments_url', __('Appointments Link (override)', 'lumn-utilities'), $location['appointments_url'], 'text', __('Leave blank to use the site-wide Appointments link', 'lumn-utilities'));
    lumn_ut_location_field_row('payments_url', __('Payments Link (override)', 'lumn-utilities'), $location['payments_url'], 'text', __('Leave blank to use the site-wide Payments link', 'lumn-utilities'));

    echo '</table>';

    echo '<h4>' . esc_html__('Hours', 'lumn-utilities') . '</h4>';
    echo '<p class="description">' . esc_html__('Display text shown by [lumn_hours] and related shortcodes.', 'lumn-utilities') . '</p>';
    echo '<table class="form-table">';
    foreach (lumn_ut_get_days_of_week() as $day) {
        $value = isset($location['hours'][$day]) ? $location['hours'][$day] : '';
        echo '<tr><th><label for="location_hours_' . esc_attr($day) . '">' . esc_html(ucfirst($day)) . '</label></th><td>';
        echo '<input type="text" id="location_hours_' . esc_attr($day) . '" name="hours[' . esc_attr($day) . ']" value="' . esc_attr($value) . '" placeholder="e.g., 8:00 AM - 5:00 PM" />';
        echo '</td></tr>';
    }
    echo '</table>';

    echo '<h4>' . esc_html__('Structured Hours', 'lumn-utilities') . '</h4>';
    echo '<p class="description">' . esc_html__('Machine-readable open/close times, used for map and schema markup rather than display.', 'lumn-utilities') . '</p>';
    echo '<table class="form-table">';
    foreach (lumn_ut_get_days_of_week() as $day) {
        $day_hours = isset($location['structured_hours'][$day]) ? $location['structured_hours'][$day] : array('open' => '', 'close' => '', 'closed' => false);
        echo '<tr><th>' . esc_html(ucfirst($day)) . '</th><td>';
        echo '<label>' . esc_html__('Open', 'lumn-utilities') . ' <input type="time" name="structured_hours[' . esc_attr($day) . '][open]" value="' . esc_attr($day_hours['open']) . '" /></label> ';
        echo '<label>' . esc_html__('Close', 'lumn-utilities') . ' <input type="time" name="structured_hours[' . esc_attr($day) . '][close]" value="' . esc_attr($day_hours['close']) . '" /></label> ';
        echo '<label><input type="checkbox" name="structured_hours[' . esc_attr($day) . '][closed]" value="1"' . checked($day_hours['closed'], true, false) . ' /> ' . esc_html__('Closed', 'lumn-utilities') . '</label>';
        echo '</td></tr>';
    }
    echo '</table>';

    submit_button($is_edit ? __('Update Location', 'lumn-utilities') : __('Add Location', 'lumn-utilities'));
    echo ' <a href="' . esc_url($cancel_url) . '" class="button">' . esc_html__('Cancel', 'lumn-utilities') . '</a>';
    echo '</form>';
}

function lumn_ut_location_field_row($key, $label, $value, $type = 'text', $placeholder = '') {
    echo '<tr>';
    echo '<th><label for="location_' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
    echo '<td><input type="' . esc_attr($type) . '" id="location_' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" /></td>';
    echo '</tr>';
}

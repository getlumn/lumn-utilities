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
    echo '<h2>' . esc_html__('Practice Locations', 'lumn-utilities') . '</h2>';

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
    );

    if (isset($messages[$type])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$type]) . '</p></div>';
    }
}

function lumn_ut_render_locations_list() {
    $locations = lumn_ut_get_locations();

    echo '<p>' . esc_html__('Manage one or more practice locations. Each location can store its own name, address, contact info, and hours.', 'lumn-utilities') . '</p>';

    $add_url = add_query_arg(array('page' => 'lumn-ut-locations', 'location_id' => 'new'), admin_url('admin.php'));
    echo '<p><a href="' . esc_url($add_url) . '" class="button button-primary">' . esc_html__('Add New Location', 'lumn-utilities') . '</a></p>';

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

    echo '<h4>' . esc_html__('Hours', 'lumn-utilities') . '</h4>';
    echo '<table class="form-table">';
    foreach (lumn_ut_get_days_of_week() as $day) {
        $value = isset($location['hours'][$day]) ? $location['hours'][$day] : '';
        echo '<tr><th><label for="location_hours_' . esc_attr($day) . '">' . esc_html(ucfirst($day)) . '</label></th><td>';
        echo '<input type="text" id="location_hours_' . esc_attr($day) . '" name="hours[' . esc_attr($day) . ']" value="' . esc_attr($value) . '" placeholder="e.g., 8:00 AM - 5:00 PM" />';
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

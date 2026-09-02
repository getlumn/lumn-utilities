<?php
namespace Lumn\Utilities;

/**
 * Rendering for the "Developers" admin page. Kept separate from
 * register/dev-notes.php (data + hooks) so each file stays focused - the
 * same split used for Practice Locations and Tracking.
 *
 * First capability check lives in register/dev-notes.php's
 * add_submenu_page() call. This is the second of the three required
 * checks (the page callback's first line); admin-post handlers and the
 * REST route in register/dev-notes.php are the third.
 */

function lumn_ut_dev_notes_page_callback() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    wp_enqueue_script('lumn-ut-admin-scripts');
    wp_localize_script('lumn-ut-admin-scripts', 'lumnUtDevNotes', array(
        'restUrl' => esc_url_raw(rest_url(LUMN_UT_REST_NAMESPACE . '/dev-notes/refresh-profile')),
        'nonce' => wp_create_nonce('wp_rest'),
        'refreshingText' => __('Refreshing…', 'lumn-utilities'),
        'refreshText' => __('Refresh now', 'lumn-utilities'),
        'refreshErrorText' => __('The refresh could not be started. Try again in a moment.', 'lumn-utilities'),
    ));

    echo '<div class="lumn-ut-admin-settings-wrap wrap lumn-ut-dev-notes-page">';
    lumn_ut_render_admin_header(__('Per-site technical context for developers: profile, change rules, dependencies, known issues, and the activity log. Visible only to super admins.', 'lumn-utilities'));

    lumn_ut_dev_notes_render_notices();

    echo '<div class="lumn-ut-dn-top-row">';
    echo '<div class="lumn-ut-dn-top-row-col">';
    lumn_ut_dev_notes_render_profile_card();
    echo '</div>';
    echo '<div class="lumn-ut-dn-top-row-col">';
    lumn_ut_dev_notes_render_known_issues();
    lumn_ut_dev_notes_render_rules_panel();
    lumn_ut_dev_notes_render_activity_log();
    echo '</div>';
    echo '</div>';

    lumn_ut_dev_notes_render_dependencies_table();

    echo '</div>';
}

function lumn_ut_dev_notes_render_notices() {
    if (isset($_GET['lumn_ut_dn_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(rawurldecode(wp_unslash($_GET['lumn_ut_dn_error']))) . '</p></div>';
        return;
    }

    if (!isset($_GET['lumn_ut_dn_notice'])) {
        return;
    }

    $notice = sanitize_key(wp_unslash($_GET['lumn_ut_dn_notice']));
    $messages = array(
        'profile_saved' => __('Profile saved.', 'lumn-utilities'),
        'profile_imported' => __('Profile imported.', 'lumn-utilities'),
        'rules_saved' => __('Rules for making changes saved.', 'lumn-utilities'),
        'dependency_saved' => __('Dependency saved.', 'lumn-utilities'),
        'dependency_deleted' => __('Dependency removed.', 'lumn-utilities'),
        'issue_saved' => __('Issue saved.', 'lumn-utilities'),
        'issue_deleted' => __('Issue deleted.', 'lumn-utilities'),
        'log_added' => __('Activity log entry added.', 'lumn-utilities'),
    );

    if (isset($messages[$notice])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }
}

// ---------------------------------------------------------------------
// Profile card
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_profile_field_labels() {
    return array(
        'client_name' => __('Client Name', 'lumn-utilities'),
        'client_tier' => __('Client Tier', 'lumn-utilities'),
        'marketer_partner' => __('Marketer Partner', 'lumn-utilities'),
        'registrar_account_owner' => __('Registrar Account Owner', 'lumn-utilities'),
        'expected_registrar' => __('Expected Registrar', 'lumn-utilities'),
        'expected_dns_provider' => __('Expected DNS Provider', 'lumn-utilities'),
        'primary_contact' => __('Primary Contact', 'lumn-utilities'),
        'primary_contact_email' => __('Primary Contact Email', 'lumn-utilities'),
        'launch_date' => __('Launch Date', 'lumn-utilities'),
        'hubspot_record_id' => __('HubSpot Record ID', 'lumn-utilities'),
        'contract_notes' => __('Contract Notes', 'lumn-utilities'),
    );
}

function lumn_ut_dev_notes_render_profile_card() {
    $profile = lumn_ut_dev_notes_get_profile();
    $detected = lumn_ut_dev_notes_get_detected();
    $mismatches = lumn_ut_dev_notes_get_profile_mismatches($profile, $detected);
    $labels = lumn_ut_dev_notes_profile_field_labels();
    $fields = lumn_ut_dev_notes_profile_fields();

    echo '<div class="lumn-ut-dn-card lumn-ut-dn-profile-card">';
    echo '<div class="lumn-ut-dn-card-header"><h2>' . esc_html__('Site Profile', 'lumn-utilities') . '</h2>';
    echo '<button type="button" class="button lumn-ut-dn-edit-toggle">' . esc_html__('Edit', 'lumn-utilities') . '</button>';
    echo '</div>';

    lumn_ut_dev_notes_render_detected_fields($detected, $mismatches);

    // Read view
    echo '<div class="lumn-ut-dn-view">';
    $has_any = false;
    foreach ($fields as $key => $type) {
        if (trim((string) $profile[$key]) === '') {
            continue;
        }
        $has_any = true;
        echo '<div class="lumn-ut-dn-field-row"><span class="lumn-ut-dn-field-label">' . esc_html($labels[$key]) . '</span> ';
        if ($type === 'hubspot_id') {
            $hubspot_url = lumn_ut_dev_notes_hubspot_record_url($profile[$key]);
            if ($hubspot_url !== '') {
                echo '<a href="' . esc_url($hubspot_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($hubspot_url) . '</a>';
            } else {
                echo '<span class="lumn-ut-dn-field-value">' . esc_html($profile[$key]) . '</span>';
            }
        } elseif ($type === 'email') {
            echo '<a href="' . esc_url('mailto:' . $profile[$key]) . '">' . esc_html($profile[$key]) . '</a>';
        } elseif ($type === 'textarea') {
            echo '<span class="lumn-ut-dn-field-value lumn-ut-dn-field-value-multiline">' . nl2br(esc_html($profile[$key])) . '</span>';
        } else {
            echo '<span class="lumn-ut-dn-field-value">' . esc_html($profile[$key]) . '</span>';
        }
        echo '</div>';
    }
    if (!$has_any) {
        echo '<p class="lumn-ut-dn-empty-state">' . esc_html__('No client/profile details on file yet. Click Edit to add them.', 'lumn-utilities') . '</p>';
    }
    echo '</div>';

    // Edit view
    echo '<form class="lumn-ut-dn-edit" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('lumn_ut_dn_save_profile');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_save_profile" />';
    echo '<table class="form-table"><tbody>';
    foreach ($fields as $key => $type) {
        echo '<tr><th scope="row"><label for="lumn-ut-dn-' . esc_attr($key) . '">' . esc_html($labels[$key]) . '</label></th><td>';
        $value = $profile[$key];
        switch ($type) {
            case 'textarea':
                echo '<textarea id="lumn-ut-dn-' . esc_attr($key) . '" name="' . esc_attr($key) . '" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
            case 'date':
                echo '<input type="date" id="lumn-ut-dn-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                break;
            case 'email':
                echo '<input type="email" id="lumn-ut-dn-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
            case 'hubspot_id':
                echo '<input type="text" inputmode="numeric" pattern="[0-9]*" id="lumn-ut-dn-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('e.g. 13645415015', 'lumn-utilities') . '" />';
                echo '<p class="description">' . esc_html__('Just the numeric record ID from the HubSpot record URL - the link is generated from it.', 'lumn-utilities') . '</p>';
                break;
            default:
                echo '<input type="text" id="lumn-ut-dn-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    submit_button(__('Save Profile', 'lumn-utilities'), 'primary', 'submit', false);
    echo ' <button type="button" class="button lumn-ut-dn-edit-cancel">' . esc_html__('Cancel', 'lumn-utilities') . '</button>';
    echo '</form>';

    lumn_ut_dev_notes_render_profile_export_import();

    echo '</div>'; // .lumn-ut-dn-card
}

function lumn_ut_dev_notes_render_detected_fields($detected, $mismatches) {
    echo '<div class="lumn-ut-dn-detected">';
    echo '<div class="lumn-ut-dn-detected-header">';
    echo '<h3>' . esc_html__('Auto-Detected', 'lumn-utilities') . '</h3>';
    echo '<button type="button" class="button button-small lumn-ut-dn-refresh-btn">' . esc_html__('Refresh now', 'lumn-utilities') . '</button>';
    echo '</div>';

    $groups = array(
        'core' => __('WordPress / PHP / Theme', 'lumn-utilities'),
        'dns' => __('Nameservers', 'lumn-utilities'),
        'ssl' => __('SSL Certificate', 'lumn-utilities'),
        'registrar' => __('Registrar / Domain Expiry', 'lumn-utilities'),
    );

    echo '<table class="widefat striped lumn-ut-dn-detected-table"><tbody>';

    foreach ($groups as $group_key => $group_label) {
        $group = isset($detected[$group_key]) ? $detected[$group_key] : null;

        echo '<tr><td class="lumn-ut-dn-detected-label">' . esc_html($group_label) . '</td><td>';

        if (!$group) {
            echo '<span class="lumn-ut-dn-empty-state">' . esc_html__('Not checked yet - runs automatically once a day, or use Refresh now.', 'lumn-utilities') . '</span>';
            echo '</td></tr>';
            continue;
        }

        if (empty($group['success'])) {
            echo '<span class="lumn-ut-dn-detect-failed">' . esc_html__('Last check failed:', 'lumn-utilities') . ' ' . esc_html(isset($group['error']) ? $group['error'] : '') . '</span>';
        } else {
            echo lumn_ut_dev_notes_render_detected_group_value($group_key, $group['data']);
            if (isset($mismatches[$group_key])) {
                echo ' <span class="lumn-ut-dn-mismatch" title="' . esc_attr(
                    sprintf(
                        /* translators: %s: the manually entered expected value */
                        __('Expected: %s', 'lumn-utilities'),
                        $mismatches[$group_key]['manual']
                    )
                ) . '">&#9888; ' . esc_html__('differs from profile', 'lumn-utilities') . '</span>';
            }
        }

        if (!empty($group['checked_at'])) {
            echo ' <span class="lumn-ut-dn-checked-at">(' . esc_html(
                sprintf(
                    /* translators: %s: human-readable time, e.g. "3 hours" */
                    __('checked %s ago', 'lumn-utilities'),
                    human_time_diff((int) $group['checked_at'], current_time('timestamp'))
                )
            ) . ')</span>';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function lumn_ut_dev_notes_render_detected_group_value($group_key, $data) {
    if ($group_key === 'core') {
        // isset()-guarded rather than trusting every key is present:
        // $data is whatever a past detection run stored, which can be
        // older than the code reading it (e.g. right after a plugin
        // update, before the next daily cron run or manual Refresh) -
        // see lumn_ut_dev_notes_migrate_to_v3() for the one-time cleanup
        // of the specific old shape this replaced.
        $theme_name = isset($data['theme_name']) ? $data['theme_name'] : __('unknown theme', 'lumn-utilities');
        $theme_version = isset($data['theme_version']) ? $data['theme_version'] : '';

        if (!empty($data['is_child_theme'])) {
            $theme_text = sprintf(
                /* translators: 1: child theme name, 2: child theme version, 3: parent theme name, 4: parent theme version */
                __('%1$s %2$s (child of %3$s %4$s)', 'lumn-utilities'),
                $theme_name,
                $theme_version,
                isset($data['parent_theme_name']) ? $data['parent_theme_name'] : __('unknown', 'lumn-utilities'),
                isset($data['parent_theme_version']) ? $data['parent_theme_version'] : ''
            );
        } else {
            $theme_text = trim(sprintf(
                /* translators: 1: theme name, 2: theme version */
                __('%1$s %2$s', 'lumn-utilities'),
                $theme_name,
                $theme_version
            ));
        }

        // The middle dot is a literal Unicode character, not an HTML
        // entity - esc_html() below would double-encode "&middot;" into
        // visible literal text instead of rendering a dot.
        return esc_html(
            sprintf(
                /* translators: 1: WP version, 2: PHP version, 3: theme name/version (and parent theme, if any) */
                __('WP %1$s · PHP %2$s · %3$s', 'lumn-utilities'),
                isset($data['wp_version']) ? $data['wp_version'] : __('unknown', 'lumn-utilities'),
                isset($data['php_version']) ? $data['php_version'] : __('unknown', 'lumn-utilities'),
                $theme_text
            )
        );
    }

    if ($group_key === 'dns') {
        $nameservers = isset($data['nameservers']) ? (array) $data['nameservers'] : array();
        return $nameservers ? esc_html(implode(', ', $nameservers)) : esc_html__('No nameservers returned.', 'lumn-utilities');
    }

    if ($group_key === 'ssl') {
        $issuer = isset($data['issuer']) ? $data['issuer'] : '';
        $expires = isset($data['expires_at']) && $data['expires_at'] ? date_i18n(get_option('date_format'), (int) $data['expires_at']) : __('unknown', 'lumn-utilities');
        return esc_html(
            sprintf(
                /* translators: 1: certificate issuer, 2: expiry date */
                __('Issued by %1$s, expires %2$s', 'lumn-utilities'),
                $issuer !== '' ? $issuer : __('unknown', 'lumn-utilities'),
                $expires
            )
        );
    }

    if ($group_key === 'registrar') {
        $registrar = isset($data['registrar']) ? $data['registrar'] : '';
        $expiry = isset($data['domain_expiry']) && $data['domain_expiry'] ? date_i18n(get_option('date_format'), (int) $data['domain_expiry']) : __('unknown', 'lumn-utilities');
        return esc_html(
            sprintf(
                /* translators: 1: registrar name, 2: domain expiry date */
                __('%1$s, expires %2$s', 'lumn-utilities'),
                $registrar !== '' ? $registrar : __('unknown', 'lumn-utilities'),
                $expiry
            )
        );
    }

    return '';
}

function lumn_ut_dev_notes_render_profile_export_import() {
    echo '<div class="lumn-ut-dn-import-export">';

    $export_url = wp_nonce_url(add_query_arg(array('action' => 'lumn_ut_dn_export_profile'), admin_url('admin-post.php')), 'lumn_ut_dn_export_profile');
    echo '<a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Export Profile (JSON)', 'lumn-utilities') . '</a> ';

    echo '<form class="lumn-ut-dn-import-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
    wp_nonce_field('lumn_ut_dn_import_profile');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_import_profile" />';
    echo '<label class="screen-reader-text" for="lumn-ut-dn-import-file">' . esc_html__('Import profile JSON', 'lumn-utilities') . '</label>';
    echo '<input type="file" id="lumn-ut-dn-import-file" name="lumn_ut_dn_import_file" accept="application/json" />';
    submit_button(__('Import Profile', 'lumn-utilities'), 'secondary', 'submit', false);
    echo '</form>';

    echo '</div>';
}

// ---------------------------------------------------------------------
// Rules for making changes
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_render_rules_panel() {
    $rules = lumn_ut_dev_notes_get_rules();

    echo '<div class="lumn-ut-dn-card lumn-ut-dn-rules-panel">';
    echo '<div class="lumn-ut-dn-card-header"><h2>' . esc_html__('Rules for Making Changes', 'lumn-utilities') . '</h2>';
    echo '<button type="button" class="button lumn-ut-dn-edit-toggle">' . esc_html__('Edit', 'lumn-utilities') . '</button>';
    echo '</div>';

    if ($rules['edited_by'] !== '') {
        echo '<p class="lumn-ut-dn-meta">' . esc_html(
            sprintf(
                /* translators: 1: user display name, 2: date */
                __('Last edited by %1$s on %2$s', 'lumn-utilities'),
                $rules['edited_by'],
                mysql2date(get_option('date_format'), $rules['edited_at'])
            )
        ) . '</p>';
    }

    echo '<div class="lumn-ut-dn-view">';
    if (trim(wp_strip_all_tags($rules['content'])) === '') {
        echo '<p class="lumn-ut-dn-empty-state">' . esc_html__('No standing rules recorded yet. Click Edit to write down anything a developer must not touch, and why.', 'lumn-utilities') . '</p>';
    } else {
        echo '<div class="lumn-ut-dn-richtext">' . wp_kses_post(wpautop($rules['content'])) . '</div>';
    }
    echo '</div>';

    echo '<form class="lumn-ut-dn-edit" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('lumn_ut_dn_save_rules');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_save_rules" />';
    echo '<textarea name="content" rows="8" class="large-text">' . esc_textarea($rules['content']) . '</textarea>';
    submit_button(__('Save Rules', 'lumn-utilities'), 'primary', 'submit', false);
    echo ' <button type="button" class="button lumn-ut-dn-edit-cancel">' . esc_html__('Cancel', 'lumn-utilities') . '</button>';
    echo '</form>';

    echo '</div>';
}

// ---------------------------------------------------------------------
// Dependencies
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_render_dependencies_table() {
    $plugin_rows = lumn_ut_dev_notes_get_plugin_dependencies();
    $manual_rows = lumn_ut_dev_notes_get_manual_dependencies();

    echo '<div class="lumn-ut-dn-card lumn-ut-dn-dependencies">';
    echo '<h2>' . esc_html__('Dependencies', 'lumn-utilities') . '</h2>';
    echo '<p class="description">' . esc_html__('Current state, not a log - plugin name and version are always pulled live; licence ownership and notes are what you add here.', 'lumn-utilities') . '</p>';

    if (empty($plugin_rows) && empty($manual_rows)) {
        echo '<p class="lumn-ut-dn-empty-state">' . esc_html__('No plugins detected and no manual dependencies added yet.', 'lumn-utilities') . '</p>';
    } else {
        echo '<table class="widefat striped lumn-ut-dn-dependencies-table"><thead><tr>';
        echo '<th>' . esc_html__('Name', 'lumn-utilities') . '</th>';
        echo '<th>' . esc_html__('Version', 'lumn-utilities') . '</th>';
        echo '<th>' . esc_html__('Licence', 'lumn-utilities') . '</th>';
        echo '<th>' . esc_html__('Notes', 'lumn-utilities') . '</th>';
        echo '<th>' . esc_html__('Actions', 'lumn-utilities') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($plugin_rows as $row) {
            lumn_ut_dev_notes_render_dependency_row($row, false);
        }
        foreach ($manual_rows as $row) {
            lumn_ut_dev_notes_render_dependency_row($row, true);
        }

        echo '</tbody></table>';
    }

    echo '<button type="button" class="button lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="lumn-ut-dn-add-dependency">' . esc_html__('Add Manual Dependency', 'lumn-utilities') . '</button>';

    echo '<form id="lumn-ut-dn-add-dependency" class="lumn-ut-dn-hidden-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('lumn_ut_dn_save_manual_dependency');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_save_manual_dependency" />';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label>' . esc_html__('Name', 'lumn-utilities') . '</label></th><td><input type="text" name="name" class="regular-text" required /></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Version', 'lumn-utilities') . '</label></th><td><input type="text" name="version" class="regular-text" /></td></tr>';
    lumn_ut_dev_notes_render_dependency_licence_fields('', '', '', false);
    echo '</tbody></table>';
    submit_button(__('Add Dependency', 'lumn-utilities'), 'primary', 'submit', false);
    echo '</form>';

    echo '</div>';
}

/**
 * $ownership/$expiry/$notes are the RAW per-site override values, not the
 * resolved ones - blank means "not overridden" (a plugin row keeps
 * tracking register/dependency-defaults.php; a manual row has nothing to
 * inherit, so blank ownership just means 'none'). $is_plugin_row adds the
 * "Inherit default" ownership option and, when a central default exists
 * for that field, shows it as a hint so it's clear what leaving the field
 * blank actually resolves to.
 */
function lumn_ut_dev_notes_render_dependency_licence_fields($ownership, $expiry, $notes, $is_plugin_row, $default_expiry = '', $default_notes = '') {
    $ownership_for_select = ($ownership === '' && !$is_plugin_row) ? 'none' : $ownership;

    echo '<tr><th scope="row"><label>' . esc_html__('Licence Ownership', 'lumn-utilities') . '</label></th><td>';
    echo '<select name="licence_ownership">';
    if ($is_plugin_row) {
        echo '<option value=""' . selected($ownership_for_select, '', false) . '>' . esc_html__('Inherit default', 'lumn-utilities') . '</option>';
    }
    echo '<option value="none"' . selected($ownership_for_select, 'none', false) . '>' . esc_html__('None', 'lumn-utilities') . '</option>';
    echo '<option value="ours"' . selected($ownership_for_select, 'ours', false) . '>' . esc_html__('Ours', 'lumn-utilities') . '</option>';
    echo '<option value="client"' . selected($ownership_for_select, 'client', false) . '>' . esc_html__("Client's", 'lumn-utilities') . '</option>';
    echo '</select></td></tr>';

    echo '<tr><th scope="row"><label>' . esc_html__('Licence Expiry', 'lumn-utilities') . '</label></th><td><input type="date" name="licence_expiry" value="' . esc_attr($expiry) . '" />';
    if ($is_plugin_row && $expiry === '' && $default_expiry !== '') {
        echo ' <span class="lumn-ut-dn-meta">' . esc_html(sprintf(
            /* translators: %s: the default licence expiry date, if left blank */
            __('blank inherits the default: %s', 'lumn-utilities'),
            mysql2date(get_option('date_format'), $default_expiry . ' 00:00:00')
        )) . '</span>';
    }
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>' . esc_html__('Notes', 'lumn-utilities') . '</label></th><td><textarea name="notes" rows="2" class="large-text"' .
        ($is_plugin_row && $default_notes !== '' ? ' placeholder="' . esc_attr(sprintf(/* translators: %s: the default notes text, if left blank */ __('Default: %s', 'lumn-utilities'), $default_notes)) . '"' : '') .
        '>' . esc_textarea($notes) . '</textarea></td></tr>';
}

function lumn_ut_dev_notes_dependency_ownership_label($ownership) {
    if ($ownership === 'client') {
        return __("Client's", 'lumn-utilities');
    }
    if ($ownership === 'ours') {
        return __('Ours', 'lumn-utilities');
    }
    return __('None', 'lumn-utilities');
}

function lumn_ut_dev_notes_render_dependency_row($row, $is_manual) {
    $row_key = $is_manual ? $row['id'] : $row['slug'];
    $form_id = 'lumn-ut-dn-dep-edit-' . sanitize_html_class($row_key);
    $default_badge = ' <span class="lumn-ut-dn-meta">(' . esc_html__('default', 'lumn-utilities') . ')</span>';

    echo '<tr>';
    echo '<td>' . esc_html($row['name']) . ($is_manual ? '' : ' ' . ($row['active'] ? '<span class="lumn-ut-dn-badge lumn-ut-dn-badge-active">' . esc_html__('active', 'lumn-utilities') . '</span>' : '<span class="lumn-ut-dn-badge">' . esc_html__('inactive', 'lumn-utilities') . '</span>'));
    if (!$is_manual) {
        // Prints the exact key register/dependency-defaults.php expects,
        // with a Copy button - lumn_ut_shortcode_hint() in
        // register/functions.php, the same "copyable reference string"
        // treatment already used for shortcodes/redirect URLs elsewhere.
        lumn_ut_shortcode_hint($row['slug']);
    }
    echo '</td>';
    echo '<td>' . esc_html($row['version']) . '</td>';
    echo '<td>' . esc_html(lumn_ut_dev_notes_dependency_ownership_label($row['licence_ownership']));
    if (!$is_manual && $row['licence_ownership_is_default']) {
        echo $default_badge;
    }
    if (!empty($row['licence_expiry'])) {
        echo '<br /><span class="lumn-ut-dn-meta">' . esc_html(sprintf(
            /* translators: %s: licence expiry date */
            __('expires %s', 'lumn-utilities'),
            mysql2date(get_option('date_format'), $row['licence_expiry'] . ' 00:00:00')
        )) . '</span>';
        if (!$is_manual && $row['licence_expiry_is_default']) {
            echo $default_badge;
        }
    }
    echo '</td>';
    echo '<td>' . nl2br(esc_html($row['notes']));
    if (!$is_manual && $row['notes_is_default']) {
        echo $default_badge;
    }
    echo '</td>';
    echo '<td>';
    echo '<button type="button" class="button button-small lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="' . esc_attr($form_id) . '">' . esc_html__('Edit', 'lumn-utilities') . '</button>';
    if ($is_manual) {
        $delete_url = wp_nonce_url(
            add_query_arg(array('action' => 'lumn_ut_dn_delete_manual_dependency', 'row_id' => $row['id']), admin_url('admin-post.php')),
            'lumn_ut_dn_delete_manual_dependency_' . $row['id']
        );
        echo ' <a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Remove this dependency?', 'lumn-utilities')) . '\');">' . esc_html__('Delete', 'lumn-utilities') . '</a>';
    }
    echo '</td>';
    echo '</tr>';

    echo '<tr id="' . esc_attr($form_id) . '" class="lumn-ut-dn-hidden-form"><td colspan="5">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    if ($is_manual) {
        wp_nonce_field('lumn_ut_dn_save_manual_dependency');
        echo '<input type="hidden" name="action" value="lumn_ut_dn_save_manual_dependency" />';
        echo '<input type="hidden" name="row_id" value="' . esc_attr($row['id']) . '" />';
        echo '<p><label>' . esc_html__('Name', 'lumn-utilities') . ' <input type="text" name="name" value="' . esc_attr($row['name']) . '" required /></label> ';
        echo '<label>' . esc_html__('Version', 'lumn-utilities') . ' <input type="text" name="version" value="' . esc_attr($row['version']) . '" /></label></p>';
        echo '<table class="form-table"><tbody>';
        lumn_ut_dev_notes_render_dependency_licence_fields($row['licence_ownership'], $row['licence_expiry'], $row['notes'], false);
        echo '</tbody></table>';
    } else {
        wp_nonce_field('lumn_ut_dn_save_dependency_overlay');
        echo '<input type="hidden" name="action" value="lumn_ut_dn_save_dependency_overlay" />';
        echo '<input type="hidden" name="slug" value="' . esc_attr($row['slug']) . '" />';
        echo '<p class="description">' . esc_html__('Leave a field on "Inherit default" or blank to keep tracking the central default below - only fill it in if this site needs to be different.', 'lumn-utilities') . '</p>';
        echo '<table class="form-table"><tbody>';
        lumn_ut_dev_notes_render_dependency_licence_fields($row['licence_ownership_override'], $row['licence_expiry_override'], $row['notes_override'], true, $row['default_expiry'], $row['default_notes']);
        echo '</tbody></table>';
    }
    submit_button(__('Save', 'lumn-utilities'), 'primary', 'submit', false);
    echo ' <button type="button" class="button lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="' . esc_attr($form_id) . '">' . esc_html__('Cancel', 'lumn-utilities') . '</button>';
    echo '</form>';
    echo '</td></tr>';
}

// ---------------------------------------------------------------------
// Known issues
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_render_known_issues() {
    $all_issues = lumn_ut_dev_notes_get_issues();
    $open_issues = array_values(array_filter($all_issues, function ($issue) {
        return $issue['status'] !== 'resolved';
    }));
    $resolved_count = count($all_issues) - count($open_issues);

    $show_all = isset($_GET['lumn_ut_dn_issues']) && sanitize_key(wp_unslash($_GET['lumn_ut_dn_issues'])) === 'all';
    $issues_to_show = $show_all ? $all_issues : $open_issues;

    echo '<div class="lumn-ut-dn-card lumn-ut-dn-issues">';
    echo '<h2>' . esc_html__('Known Issues', 'lumn-utilities') . '</h2>';

    $toggle_url = add_query_arg(array('page' => LUMN_UT_DEV_NOTES_PAGE_SLUG, 'lumn_ut_dn_issues' => $show_all ? 'open' : 'all'), admin_url('admin.php'));
    echo '<p class="lumn-ut-dn-meta">' . esc_html(
        sprintf(
            /* translators: %d: number of resolved issues */
            _n('%d resolved issue.', '%d resolved issues.', $resolved_count, 'lumn-utilities'),
            $resolved_count
        )
    ) . ' <a href="' . esc_url($toggle_url) . '">' . ($show_all ? esc_html__('Show open only', 'lumn-utilities') : esc_html__('Show all', 'lumn-utilities')) . '</a></p>';

    if (empty($issues_to_show)) {
        echo '<p class="lumn-ut-dn-empty-state">' . esc_html(
            $show_all ? __('No issues recorded yet.', 'lumn-utilities') : __('No open issues. Nice.', 'lumn-utilities')
        ) . '</p>';
    } else {
        foreach ($issues_to_show as $issue) {
            lumn_ut_dev_notes_render_issue($issue);
        }
    }

    echo '<button type="button" class="button lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="lumn-ut-dn-add-issue">' . esc_html__('Add New Issue', 'lumn-utilities') . '</button>';
    lumn_ut_dev_notes_render_issue_form(null, 'lumn-ut-dn-add-issue');

    echo '</div>';
}

function lumn_ut_dev_notes_render_issue($issue) {
    $form_id = 'lumn-ut-dn-issue-edit-' . (int) $issue['id'];

    echo '<div class="lumn-ut-dn-issue lumn-ut-dn-severity-' . esc_attr($issue['severity']) . '">';
    echo '<div class="lumn-ut-dn-issue-header">';
    echo '<span class="lumn-ut-dn-badge lumn-ut-dn-badge-' . esc_attr($issue['severity']) . '">' . esc_html(ucfirst($issue['severity'])) . '</span> ';
    echo '<span class="lumn-ut-dn-badge lumn-ut-dn-status-' . esc_attr($issue['status']) . '">' . esc_html(ucfirst($issue['status'])) . '</span> ';
    echo '<strong>' . esc_html($issue['title']) . '</strong> ';
    echo '<span class="lumn-ut-dn-meta">' . esc_html(
        sprintf(
            /* translators: %s: date the issue was opened */
            __('opened %s', 'lumn-utilities'),
            mysql2date(get_option('date_format'), $issue['opened_date'] . ' 00:00:00')
        )
    ) . '</span>';
    echo '</div>';

    if ($issue['body'] !== '') {
        echo '<div class="lumn-ut-dn-richtext">' . wp_kses_post(wpautop($issue['body'])) . '</div>';
    }
    if ($issue['status'] !== 'resolved' && trim($issue['mitigation_note']) !== '') {
        echo '<p class="lumn-ut-dn-mitigation"><strong>' . esc_html__('Why this is acceptable:', 'lumn-utilities') . '</strong> ' . esc_html($issue['mitigation_note']) . '</p>';
    }

    echo '<p>';
    echo '<button type="button" class="button button-small lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="' . esc_attr($form_id) . '">' . esc_html__('Edit', 'lumn-utilities') . '</button> ';
    $delete_url = wp_nonce_url(
        add_query_arg(array('action' => 'lumn_ut_dn_delete_issue', 'issue_id' => $issue['id']), admin_url('admin-post.php')),
        'lumn_ut_dn_delete_issue_' . $issue['id']
    );
    echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Delete this issue?', 'lumn-utilities')) . '\');">' . esc_html__('Delete', 'lumn-utilities') . '</a>';
    echo '</p>';

    lumn_ut_dev_notes_render_issue_form($issue, $form_id);

    echo '</div>';
}

function lumn_ut_dev_notes_render_issue_form($issue, $form_id) {
    $title = $issue ? $issue['title'] : '';
    $body = $issue ? $issue['body'] : '';
    $severity = $issue ? $issue['severity'] : 'low';
    $status = $issue ? $issue['status'] : 'open';
    $opened_date = $issue ? $issue['opened_date'] : current_time('Y-m-d');
    $mitigation_note = $issue ? $issue['mitigation_note'] : '';

    echo '<form id="' . esc_attr($form_id) . '" class="lumn-ut-dn-hidden-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('lumn_ut_dn_save_issue');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_save_issue" />';
    if ($issue) {
        echo '<input type="hidden" name="issue_id" value="' . esc_attr($issue['id']) . '" />';
    }
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label>' . esc_html__('Title', 'lumn-utilities') . '</label></th><td><input type="text" name="title" value="' . esc_attr($title) . '" class="regular-text" required /></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Severity', 'lumn-utilities') . '</label></th><td><select name="severity">';
    foreach (lumn_ut_dev_notes_issue_severities() as $level) {
        echo '<option value="' . esc_attr($level) . '"' . selected($severity, $level, false) . '>' . esc_html(ucfirst($level)) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Status', 'lumn-utilities') . '</label></th><td><select name="status">';
    foreach (lumn_ut_dev_notes_issue_statuses() as $status_option) {
        echo '<option value="' . esc_attr($status_option) . '"' . selected($status, $status_option, false) . '>' . esc_html(ucfirst($status_option)) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Opened', 'lumn-utilities') . '</label></th><td><input type="date" name="opened_date" value="' . esc_attr($opened_date) . '" /></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Details', 'lumn-utilities') . '</label></th><td><textarea name="body" rows="4" class="large-text">' . esc_textarea($body) . '</textarea></td></tr>';
    echo '<tr><th scope="row"><label>' . esc_html__('Mitigation Note', 'lumn-utilities') . '</label></th><td><textarea name="mitigation_note" rows="2" class="large-text" placeholder="' . esc_attr__('Why is it acceptable to leave this open?', 'lumn-utilities') . '">' . esc_textarea($mitigation_note) . '</textarea></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Save Issue', 'lumn-utilities'), 'primary', 'submit', false);
    echo ' <button type="button" class="button lumn-ut-dn-toggle-target" data-lumn-ut-dn-target="' . esc_attr($form_id) . '">' . esc_html__('Cancel', 'lumn-utilities') . '</button>';
    echo '</form>';
}

// ---------------------------------------------------------------------
// Activity log
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_render_activity_log() {
    $paged = isset($_GET['lumn_ut_dn_log_page']) ? max(1, absint($_GET['lumn_ut_dn_log_page'])) : 1;
    $query = lumn_ut_dev_notes_get_log_query($paged);

    echo '<div class="lumn-ut-dn-card lumn-ut-dn-log">';
    echo '<h2>' . esc_html__('Activity Log', 'lumn-utilities') . '</h2>';
    echo '<p class="description">' . esc_html__('Deployments, rollbacks, client decisions and the reasoning behind them, one-off interventions - anything that does not belong above. Append-only.', 'lumn-utilities') . '</p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('lumn_ut_dn_add_log_entry');
    echo '<input type="hidden" name="action" value="lumn_ut_dn_add_log_entry" />';
    echo '<p><input type="text" name="title" class="regular-text" placeholder="' . esc_attr__('Short label (optional)', 'lumn-utilities') . '" /></p>';
    echo '<p><textarea name="body" rows="3" class="large-text" placeholder="' . esc_attr__('What happened, and why?', 'lumn-utilities') . '" required></textarea></p>';
    submit_button(__('Add Entry', 'lumn-utilities'), 'primary', 'submit', false);
    echo '</form>';

    if (!$query->have_posts()) {
        echo '<p class="lumn-ut-dn-empty-state">' . esc_html__('No activity recorded yet.', 'lumn-utilities') . '</p>';
        echo '</div>';
        return;
    }

    echo '<ul class="lumn-ut-dn-log-list">';
    foreach ($query->posts as $entry) {
        echo '<li>';
        echo '<span class="lumn-ut-dn-meta">' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->post_date)) . ' &mdash; ' . esc_html(get_the_author_meta('display_name', $entry->post_author)) . '</span>';
        if ($entry->post_title !== '') {
            echo '<br /><strong>' . esc_html($entry->post_title) . '</strong>';
        }
        echo '<div class="lumn-ut-dn-richtext">' . wp_kses_post(wpautop($entry->post_content)) . '</div>';
        echo '</li>';
    }
    echo '</ul>';

    if ($query->max_num_pages > 1) {
        echo '<p class="lumn-ut-dn-log-pagination">';
        if ($paged > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(array('page' => LUMN_UT_DEV_NOTES_PAGE_SLUG, 'lumn_ut_dn_log_page' => $paged - 1), admin_url('admin.php'))) . '">' . esc_html__('&laquo; Newer', 'lumn-utilities') . '</a> ';
        }
        if ($paged < $query->max_num_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg(array('page' => LUMN_UT_DEV_NOTES_PAGE_SLUG, 'lumn_ut_dn_log_page' => $paged + 1), admin_url('admin.php'))) . '">' . esc_html__('Older &raquo;', 'lumn-utilities') . '</a>';
        }
        echo '</p>';
    }

    wp_reset_postdata();
    echo '</div>';
}

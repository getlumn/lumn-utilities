<?php
namespace Lumn\Utilities;

/**
 * Grant the custom 'lumn_manage_maintenance' capability to users with the
 * 'company_super_admin' role. This avoids hard-coding a WordPress role
 * into add_menu_page() and keeps capability checks clean.
 */
add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) {
    if (in_array('lumn_manage_maintenance', $caps, true) &&
        in_array('company_super_admin', (array) $user->roles, true)
    ) {
        $allcaps['lumn_manage_maintenance'] = true;
    }
    return $allcaps;
}, 10, 4);

/**
 * Register the Maintenance top-level admin menu item.
 */
function lumn_maintenance_register_menu() {
    add_menu_page(
        'LUMN Maintenance',
        'LUMN Maintenance',
        'lumn_manage_maintenance',
        'lumn-maintenance',
        'Lumn\Utilities\lumn_maintenance_page_callback',
        'dashicons-yes-alt',
        62
    );
}
add_action('admin_menu', 'Lumn\Utilities\lumn_maintenance_register_menu');

/**
 * Enqueue maintenance page scripts and localize AJAX data.
 * Only loads on the Maintenance admin page.
 */
function lumn_maintenance_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_lumn-maintenance') {
        return;
    }
    wp_enqueue_script(
        'lumn-maintenance-scripts',
        plugins_url('admin/maintenance-scripts.js', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'),
        array('jquery'),
        null,
        true
    );
    wp_localize_script('lumn-maintenance-scripts', 'lumnMaintenance', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('lumn_maintenance_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'Lumn\Utilities\lumn_maintenance_enqueue_scripts');

/**
 * Returns the array of default task definitions.
 * Each task has: id, label, interval (seconds; 0 = one-time).
 */
function lumn_maintenance_default_tasks() {
    $monthly = 30 * DAY_IN_SECONDS;
    $yearly  = 365 * DAY_IN_SECONDS;

    return array(
        array('id' => 'update_plugins',        'label' => 'Update plugins',                                   'interval' => $monthly),
        array('id' => 'remove_unused_plugins', 'label' => 'Remove unused plugins',                            'interval' => $monthly),
        array('id' => 'verify_recaptcha',      'label' => 'Verify reCAPTCHA is active',                       'interval' => $monthly),
        array('id' => 'verify_form_emails',    'label' => 'Verify forms have valid email addresses',          'interval' => $monthly),
        array('id' => 'verify_form_replyto',   'label' => 'Verify forms have reply-to configured',            'interval' => $monthly),
        array('id' => 'verify_spam_filters',   'label' => 'Verify Formidable spam filters',                   'interval' => $monthly),
        array('id' => 'verify_lumn_security',  'label' => 'Verify LUMN Security Plugin installed & updated',  'interval' => $monthly),
        array('id' => 'run_wordfence_scan',    'label' => 'Run WordFence scan',                               'interval' => $monthly),
        array('id' => 'update_admin_email',    'label' => 'Update admin email to dev@getlumn.com',            'interval' => 0),
        array('id' => 'update_copyright',      'label' => 'Update copyright',                                 'interval' => $yearly),
        array('id' => 'check_console_errors',  'label' => 'Check console log for errors',                     'interval' => $monthly),
    );
}

/**
 * Load tasks from wp_options, merging with defaults to ensure all tasks exist.
 * Applies reset logic: if a task's interval has elapsed since last_checked,
 * it is marked incomplete. One-time tasks (interval = 0) never reset.
 * Persists the updated task list back to options.
 *
 * @return array
 */
function lumn_maintenance_get_tasks() {
    $defaults   = lumn_maintenance_default_tasks();
    $stored     = get_option('lumn_maintenance_tasks', array());

    $stored_map = array();
    foreach ($stored as $task) {
        if (!empty($task['id'])) {
            $stored_map[$task['id']] = $task;
        }
    }

    $tasks = array();
    foreach ($defaults as $default) {
        if (isset($stored_map[$default['id']])) {
            $task = array_merge($default, $stored_map[$default['id']]);
        } else {
            $task = array_merge($default, array(
                'last_checked' => 0,
                'completed'    => false,
            ));
        }

        if ($task['interval'] > 0 &&
            !empty($task['last_checked']) &&
            $task['last_checked'] > 0 &&
            time() > ($task['last_checked'] + $task['interval'])
        ) {
            $task['completed'] = false;
        }

        $tasks[] = $task;
    }

    update_option('lumn_maintenance_tasks', $tasks);

    return $tasks;
}

/**
 * Calculate the display status for a single task.
 *
 * @param  array  $task
 * @return string 'complete' | 'overdue' | 'due_soon' | 'pending'
 */
function lumn_maintenance_get_task_status($task) {
    if (!empty($task['completed'])) {
        return 'complete';
    }

    if ($task['interval'] === 0) {
        return 'pending';
    }

    $now          = time();
    $last_checked = !empty($task['last_checked']) ? (int) $task['last_checked'] : 0;

    if ($last_checked === 0) {
        return 'overdue';
    }

    $due_at = $last_checked + $task['interval'];

    if ($now >= $due_at) {
        return 'overdue';
    }

    if (($due_at - $now) < (7 * DAY_IN_SECONDS)) {
        return 'due_soon';
    }

    return 'pending';
}

/**
 * Render the Maintenance admin page.
 */
function lumn_maintenance_page_callback() {
    if (!current_user_can('lumn_manage_maintenance')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    if (isset($_POST['lumn_maintenance_settings_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lumn_maintenance_settings_nonce'])), 'lumn_maintenance_settings')
    ) {
        $webhook_url = isset($_POST['lumn_maintenance_webhook_url'])
            ? esc_url_raw(wp_unslash($_POST['lumn_maintenance_webhook_url']))
            : '';
        $api_key = isset($_POST['lumn_maintenance_api_key'])
            ? sanitize_text_field(wp_unslash($_POST['lumn_maintenance_api_key']))
            : '';

        update_option('lumn_maintenance_webhook_url', $webhook_url);
        update_option('lumn_maintenance_api_key', $api_key);

        echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>';
    }

    $tasks = lumn_maintenance_get_tasks();

    $webhook_url = esc_attr(get_option('lumn_maintenance_webhook_url') ?: LUMN_MAINTENANCE_WEBHOOK_URL);
    $api_key     = esc_attr(get_option('lumn_maintenance_api_key')     ?: LUMN_MAINTENANCE_API_KEY);

    $status_bg = array(
        'complete' => '#d4edda',
        'overdue'  => '#f8d7da',
        'due_soon' => '#fff3cd',
        'pending'  => '#ffffff',
    );
    $status_labels = array(
        'complete' => 'Complete',
        'overdue'  => 'Overdue',
        'due_soon' => 'Due Soon',
        'pending'  => 'Pending',
    );

    ?>
    <div class="wrap lumn-maintenance-wrap">
        <h1>Maintenance Checklist</h1>
        <p>Track recurring site maintenance tasks. Only visible to <code>company_super_admin</code> users.</p>

        <table class="wp-list-table widefat fixed" style="margin-top:20px; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="width:50px; padding:10px 8px;">Done</th>
                    <th style="padding:10px 8px;">Task</th>
                    <th style="width:110px; padding:10px 8px;">Status</th>
                    <th style="width:130px; padding:10px 8px;">Last Checked</th>
                    <th style="width:130px; padding:10px 8px;">Next Due</th>
                </tr>
            </thead>
            <tbody id="lumn-maintenance-tasks">
                <?php foreach ($tasks as $task) :
                    $status    = lumn_maintenance_get_task_status($task);
                    $bg        = isset($status_bg[$status])     ? $status_bg[$status]     : '#ffffff';
                    $label     = isset($status_labels[$status]) ? $status_labels[$status] : 'Pending';

                    $last_ts = !empty($task['last_checked']) ? (int) $task['last_checked'] : 0;

                    $last_checked_str = $last_ts > 0
                        ? esc_html(date_i18n('M j, Y', $last_ts))
                        : '—';

                    if ($task['interval'] === 0) {
                        $next_due_str = 'One-time';
                    } elseif ($last_ts > 0) {
                        $next_due_str = esc_html(date_i18n('M j, Y', $last_ts + $task['interval']));
                    } else {
                        $next_due_str = 'ASAP';
                    }
                ?>
                <tr style="background-color: <?php echo esc_attr($bg); ?>; border-bottom: 1px solid #e0e0e0;"
                    data-task-id="<?php echo esc_attr($task['id']); ?>">
                    <td style="padding:10px 8px; text-align:center;">
                        <input type="checkbox"
                               class="lumn-maintenance-checkbox"
                               data-task-id="<?php echo esc_attr($task['id']); ?>"
                               <?php checked(!empty($task['completed'])); ?> />
                    </td>
                    <td style="padding:10px 8px;"><?php echo esc_html($task['label']); ?></td>
                    <td class="lumn-maintenance-status" style="padding:10px 8px;"><?php echo esc_html($label); ?></td>
                    <td class="lumn-maintenance-last-checked" style="padding:10px 8px;"><?php echo $last_checked_str; ?></td>
                    <td class="lumn-maintenance-next-due" style="padding:10px 8px;"><?php echo $next_due_str; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:12px; font-size:13px; color:#555;">
            <span style="display:inline-block;width:14px;height:14px;background:#d4edda;border:1px solid #aaa;vertical-align:middle;margin-right:4px;"></span>Complete &nbsp;&nbsp;
            <span style="display:inline-block;width:14px;height:14px;background:#f8d7da;border:1px solid #aaa;vertical-align:middle;margin-right:4px;"></span>Overdue &nbsp;&nbsp;
            <span style="display:inline-block;width:14px;height:14px;background:#fff3cd;border:1px solid #aaa;vertical-align:middle;margin-right:4px;"></span>Due Soon &nbsp;&nbsp;
            <span style="display:inline-block;width:14px;height:14px;background:#ffffff;border:1px solid #aaa;vertical-align:middle;margin-right:4px;"></span>Pending
        </p>

        <hr style="margin: 40px 0;" />

        <h2>Google Sheets Integration Settings</h2>
        <p>Default values are pre-configured in the plugin and work automatically on every install. You can enter site-specific overrides below — leave blank to use the built-in defaults.</p>

        <form method="post" action="">
            <?php wp_nonce_field('lumn_maintenance_settings', 'lumn_maintenance_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="lumn_maintenance_webhook_url">Google Script Webhook URL</label>
                    </th>
                    <td>
                        <input type="url"
                               id="lumn_maintenance_webhook_url"
                               name="lumn_maintenance_webhook_url"
                               value="<?php echo $webhook_url; ?>"
                               class="regular-text"
                               placeholder="https://script.google.com/macros/s/..." />
                        <p class="description">The deployed Google Apps Script web app URL.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lumn_maintenance_api_key">API Key</label>
                    </th>
                    <td>
                        <input type="text"
                               id="lumn_maintenance_api_key"
                               name="lumn_maintenance_api_key"
                               value="<?php echo $api_key; ?>"
                               class="regular-text"
                               placeholder="your-shared-secret-key" />
                        <p class="description">Shared secret used to authenticate requests. Must match the key set in your Apps Script.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

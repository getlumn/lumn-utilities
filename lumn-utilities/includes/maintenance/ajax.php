<?php
namespace Lumn\Utilities;

/**
 * AJAX handler for saving a single maintenance task's completion state.
 *
 * Expected POST params:
 *   nonce     - wp nonce (lumn_maintenance_nonce)
 *   task_id   - string slug of the task
 *   completed - '1' for checked, '0' for unchecked
 */
function lumn_maintenance_save_task_ajax() {
    check_ajax_referer('lumn_maintenance_nonce', 'nonce');

    if (!current_user_can('lumn_manage_maintenance')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }

    $task_id   = isset($_POST['task_id'])   ? sanitize_text_field(wp_unslash($_POST['task_id']))   : '';
    $completed = isset($_POST['completed']) ? (wp_unslash($_POST['completed']) === '1')            : false;

    if (empty($task_id)) {
        wp_send_json_error(array('message' => 'Invalid task ID.'));
    }

    $tasks = get_option('lumn_maintenance_tasks', array());

    $found = false;
    foreach ($tasks as &$task) {
        if ($task['id'] === $task_id) {
            $task['completed'] = $completed;
            if ($completed) {
                $task['last_checked'] = time();
            }
            $found = true;
            break;
        }
    }
    unset($task);

    if (!$found) {
        wp_send_json_error(array('message' => 'Task not found.'));
    }

    update_option('lumn_maintenance_tasks', $tasks);

    lumn_send_maintenance_to_sheet();

    $response_data = array('message' => 'Task updated.');
    if ($completed) {
        $response_data['last_checked'] = date_i18n('M j, Y', time());
    }

    wp_send_json_success($response_data);
}
add_action('wp_ajax_lumn_save_maintenance_task', 'Lumn\Utilities\lumn_maintenance_save_task_ajax');

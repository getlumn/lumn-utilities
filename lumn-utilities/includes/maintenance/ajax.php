<?php
namespace Lumn\Utilities;

/**
 * AJAX handler for saving a single maintenance task's completion state.
 *
 * Expected POST params:
 *   nonce     - wp nonce (lumn_maintenance_nonce)
 *   task_id   - string slug of the task
 *   completed - '1' for checked, '0' for unchecked
 *
 * Returns JSON with computed status, label, bg_color, last_checked, and next_due
 * so the client row update is driven entirely by server truth, not guesses.
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

    $found        = false;
    $updated_task = null;

    foreach ($tasks as &$task) {
        if ($task['id'] === $task_id) {
            $task['completed'] = $completed;
            if ($completed) {
                $task['last_checked'] = time();
            }
            $updated_task = $task;
            $found        = true;
            break;
        }
    }
    unset($task);

    if (!$found) {
        wp_send_json_error(array('message' => 'Task not found.'));
    }

    update_option('lumn_maintenance_tasks', $tasks);

    lumn_send_maintenance_to_sheet();

    $status = lumn_maintenance_get_task_status($updated_task);

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

    $last_ts          = !empty($updated_task['last_checked']) ? (int) $updated_task['last_checked'] : 0;
    $last_checked_str = $last_ts > 0 ? date_i18n('M j, Y', $last_ts) : '—';

    if ($updated_task['interval'] === 0) {
        $next_due_str = 'One-time';
    } elseif ($last_ts > 0) {
        $next_due_str = date_i18n('M j, Y', $last_ts + $updated_task['interval']);
    } else {
        $next_due_str = 'ASAP';
    }

    wp_send_json_success(array(
        'message'      => 'Task updated.',
        'status'       => $status,
        'status_label' => $status_labels[$status] ?? 'Pending',
        'bg_color'     => $status_bg[$status]     ?? '#ffffff',
        'last_checked' => $last_checked_str,
        'next_due'     => $next_due_str,
    ));
}
add_action('wp_ajax_lumn_save_maintenance_task', 'Lumn\Utilities\lumn_maintenance_save_task_ajax');

<?php
namespace Lumn\Utilities;

/**
 * Send maintenance summary data to the configured Google Sheets webhook.
 *
 * Sends a POST request containing:
 *   - site_url
 *   - total_tasks
 *   - incomplete_tasks
 *   - oldest_unchecked_task_timestamp
 *   - last_checked_timestamp
 *   - api_key
 *
 * Does nothing if the webhook URL is not configured.
 */
function lumn_send_maintenance_to_sheet() {
    $webhook_url = get_option('lumn_maintenance_webhook_url', '');
    $api_key     = get_option('lumn_maintenance_api_key', '');

    if (empty($webhook_url)) {
        return;
    }

    $tasks = get_option('lumn_maintenance_tasks', array());

    $total_tasks      = count($tasks);
    $incomplete_tasks = 0;
    $oldest_unchecked = 0;

    foreach ($tasks as $task) {
        if (empty($task['completed'])) {
            $incomplete_tasks++;
            if (!empty($task['last_checked']) && $task['last_checked'] > 0) {
                if ($oldest_unchecked === 0 || $task['last_checked'] < $oldest_unchecked) {
                    $oldest_unchecked = $task['last_checked'];
                }
            }
        }
    }

    $payload = array(
        'site_url'                        => get_site_url(),
        'total_tasks'                     => $total_tasks,
        'incomplete_tasks'                => $incomplete_tasks,
        'oldest_unchecked_task_timestamp' => $oldest_unchecked,
        'last_checked_timestamp'          => time(),
        'api_key'                         => $api_key,
    );

    wp_remote_post($webhook_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode($payload),
        'timeout' => 15,
    ));
}

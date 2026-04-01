<?php
namespace Lumn\Utilities;

/**
 * Ensure the daily sync cron event is scheduled.
 * Uses init to safely schedule once if not already registered.
 */
add_action('init', function () {
    if (!wp_next_scheduled('lumn_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'lumn_daily_sync');
    }
});

/**
 * Hook the cron event to the Google Sheets sync function.
 */
add_action('lumn_daily_sync', 'Lumn\Utilities\lumn_send_maintenance_to_sheet');

<?php
namespace Lumn\Utilities;

/**
 * Maintenance signals for the fleet reporter: how many updates this site
 * received recently, and how many form entries it collected.
 *
 * Both feed the monthly site report email, through the lumn-fleet pipeline
 * and HubSpot company properties. Nothing here sends anything - the fleet
 * reporter reads these when it builds its payload, and it ships disabled
 * (see docs/FLEET-REPORTER.md).
 *
 * TRI-STATE, LIKE EVERY OTHER MEASURED FIELD IN THE PAYLOAD. A count of
 * zero means "we looked and there were none"; null means "we cannot say".
 * The pipeline hides a metric it cannot vouch for rather than showing a
 * client a confident zero, so the difference has to survive the trip.
 *
 * NO PATIENT DATA. Form entries are COUNTED, never read: the queries below
 * select COUNT(*) and touch no entry content, metadata or field values.
 * Dental practice forms can carry health information, and LUMN has no
 * business agreement that would allow it to leave the site. Keep it that
 * way - see the compliance guardrails in the client email project scope.
 */

// The rolling window both counts cover. Matches the monthly report.
const LUMN_UT_MAINT_WINDOW_DAYS = 30;

// The update log. An option rather than a table: it holds at most a few
// hundred small rows, and an option needs no schema or migration.
const LUMN_UT_MAINT_UPDATES_OPTION = 'lumn_ut_maintenance_updates';

// When this site started recording updates. A count covering 30 days is
// only true once the log is at least 30 days old; before that it is a
// count of "updates since we started looking", which reads as a monthly
// figure and is not one.
const LUMN_UT_MAINT_SINCE_OPTION = 'lumn_ut_maintenance_updates_since';

// How long entries are kept, and a hard cap. Ninety days covers any window
// the report could reasonably want; the cap bounds an option that a busy
// bulk-update run could otherwise grow without limit.
const LUMN_UT_MAINT_RETENTION_DAYS = 90;
const LUMN_UT_MAINT_MAX_ENTRIES = 500;

// Contact Form 7 keeps no submissions of its own - it emails them and
// forgets - so this plugin keeps a tally instead: one number per UTC day,
// nothing else. Same "since" rule as the update log: the 30-day count is
// null until the tally has existed for the whole window.
const LUMN_UT_MAINT_CF7_OPTION = 'lumn_ut_maintenance_cf7_daily';
const LUMN_UT_MAINT_CF7_SINCE_OPTION = 'lumn_ut_maintenance_cf7_since';

// ---------------------------------------------------------------------
// Update log
// ---------------------------------------------------------------------

/**
 * Record each core, plugin and theme update as it completes.
 *
 * upgrader_process_complete fires for updates made in wp-admin, by
 * WordPress's own background auto-updates, and through WP-CLI - which is
 * how hosts and maintenance tools apply them - so one hook covers every
 * path we use. Translation updates are skipped: they are housekeeping,
 * and counting them would inflate "updates applied" with entries nobody
 * would call maintenance.
 *
 * Runs regardless of whether the reporter is switched on. The log has to
 * exist for 30 days before its count means anything, so starting it only
 * when reporting is enabled would leave every newly onboarded site a month
 * behind.
 *
 * @param object $upgrader   The upgrader instance (unused).
 * @param array  $hook_extra type / action / plugins|plugin|themes|theme.
 */
function lumn_ut_maint_record_update($upgrader, $hook_extra) {
    if (!is_array($hook_extra) || ($hook_extra['action'] ?? '') !== 'update') {
        return;
    }

    $type = $hook_extra['type'] ?? '';
    $slugs = array();

    if ($type === 'plugin') {
        // Bulk updates pass `plugins`, single updates pass `plugin`.
        $files = isset($hook_extra['plugins']) ? (array) $hook_extra['plugins'] : array($hook_extra['plugin'] ?? '');
        foreach ($files as $file) {
            if (is_string($file) && $file !== '') {
                $slugs[] = dirname($file) !== '.' ? dirname($file) : basename($file, '.php');
            }
        }
    } elseif ($type === 'theme') {
        $themes = isset($hook_extra['themes']) ? (array) $hook_extra['themes'] : array($hook_extra['theme'] ?? '');
        foreach ($themes as $theme) {
            if (is_string($theme) && $theme !== '') {
                $slugs[] = $theme;
            }
        }
    } elseif ($type === 'core') {
        $slugs[] = 'wordpress';
    } else {
        return;
    }

    if (empty($slugs)) {
        return;
    }

    lumn_ut_maint_mark_since();

    $log = lumn_ut_maint_get_updates();
    $now = time();
    foreach ($slugs as $slug) {
        $log[] = array('at' => $now, 'type' => $type, 'slug' => sanitize_key($slug));
    }

    update_option(LUMN_UT_MAINT_UPDATES_OPTION, lumn_ut_maint_prune($log, $now), false);
}
add_action('upgrader_process_complete', 'Lumn\Utilities\lumn_ut_maint_record_update', 10, 2);

/**
 * Start the clock, once. Never moves backwards or forwards after that.
 */
function lumn_ut_maint_mark_since() {
    if (!get_option(LUMN_UT_MAINT_SINCE_OPTION)) {
        update_option(LUMN_UT_MAINT_SINCE_OPTION, time(), false);
    }
}

function lumn_ut_maint_get_updates() {
    $log = get_option(LUMN_UT_MAINT_UPDATES_OPTION, array());
    return is_array($log) ? array_values(array_filter($log, 'is_array')) : array();
}

/**
 * Drop entries past retention, then keep the newest up to the cap.
 */
function lumn_ut_maint_prune($log, $now) {
    $cutoff = $now - LUMN_UT_MAINT_RETENTION_DAYS * DAY_IN_SECONDS;
    $log = array_values(array_filter($log, function ($entry) use ($cutoff) {
        return isset($entry['at']) && (int) $entry['at'] >= $cutoff;
    }));
    if (count($log) > LUMN_UT_MAINT_MAX_ENTRIES) {
        $log = array_slice($log, -LUMN_UT_MAINT_MAX_ENTRIES);
    }
    return $log;
}

/**
 * Updates applied in the window, by type, or nulls while the log is too
 * young to cover it.
 *
 * Also starts the clock if nothing has yet: the reporter calls this daily,
 * so a site that never updates anything still begins its 30-day count on
 * the first send after this version lands.
 *
 * @return array{updates_applied_30d:?int,updates_by_type_30d:?array,updates_log_since:string}
 */
function lumn_ut_maint_update_counts() {
    lumn_ut_maint_mark_since();

    $since = (int) get_option(LUMN_UT_MAINT_SINCE_OPTION);
    $now = time();
    $window_start = $now - LUMN_UT_MAINT_WINDOW_DAYS * DAY_IN_SECONDS;

    $out = array(
        'updates_applied_30d' => null,
        'updates_by_type_30d' => null,
        'updates_log_since' => $since ? gmdate('Y-m-d', $since) : '',
    );

    // Too young to cover the window: say so with null, not a partial count.
    if (!$since || $since > $window_start) {
        return $out;
    }

    $by_type = array('core' => 0, 'plugin' => 0, 'theme' => 0);
    foreach (lumn_ut_maint_get_updates() as $entry) {
        if ((int) ($entry['at'] ?? 0) < $window_start) {
            continue;
        }
        $type = $entry['type'] ?? '';
        if (isset($by_type[$type])) {
            $by_type[$type]++;
        }
    }

    $out['updates_applied_30d'] = array_sum($by_type);
    $out['updates_by_type_30d'] = $by_type;
    return $out;
}

// ---------------------------------------------------------------------
// Contact Form 7 tally - a number per day, nothing else
// ---------------------------------------------------------------------

function lumn_ut_maint_cf7_active() {
    return defined('WPCF7_VERSION') || class_exists('WPCF7');
}

/**
 * Count one successful Contact Form 7 submission.
 *
 * wpcf7_mail_sent fires only after CF7 has sent the message: spam,
 * validation failures and failed sends are not counted. The contact form
 * and the posted data are never read - the argument is accepted only
 * because the hook passes it. Patient data cannot leave through this.
 *
 * @param object $contact_form The WPCF7_ContactForm (unused, never read).
 */
function lumn_ut_maint_record_cf7_submission($contact_form = null) {
    lumn_ut_maint_mark_cf7_since();
    $counts = lumn_ut_maint_get_cf7_counts();
    $day = gmdate('Y-m-d');
    $counts[$day] = (int) ($counts[$day] ?? 0) + 1;

    $cutoff = gmdate('Y-m-d', time() - LUMN_UT_MAINT_RETENTION_DAYS * DAY_IN_SECONDS);
    foreach (array_keys($counts) as $key) {
        if ($key < $cutoff) {
            unset($counts[$key]);
        }
    }
    update_option(LUMN_UT_MAINT_CF7_OPTION, $counts, false);
}
add_action('wpcf7_mail_sent', 'Lumn\Utilities\lumn_ut_maint_record_cf7_submission', 10, 1);

function lumn_ut_maint_mark_cf7_since() {
    if (!get_option(LUMN_UT_MAINT_CF7_SINCE_OPTION)) {
        update_option(LUMN_UT_MAINT_CF7_SINCE_OPTION, time(), false);
    }
}

function lumn_ut_maint_get_cf7_counts() {
    $counts = get_option(LUMN_UT_MAINT_CF7_OPTION, array());
    return is_array($counts) ? $counts : array();
}

/**
 * CF7 submissions in the window, or null while the tally is younger than
 * the window. The clock starts at the first submission or the first
 * report, whichever comes first - never earlier than this code could
 * have been counting.
 */
function lumn_ut_maint_cf7_count() {
    lumn_ut_maint_mark_cf7_since();
    $since = (int) get_option(LUMN_UT_MAINT_CF7_SINCE_OPTION);
    $window_start = time() - LUMN_UT_MAINT_WINDOW_DAYS * DAY_IN_SECONDS;
    if (!$since || $since > $window_start) {
        return null;
    }
    $first_day = gmdate('Y-m-d', $window_start);
    $total = 0;
    foreach (lumn_ut_maint_get_cf7_counts() as $day => $count) {
        if ($day >= $first_day) {
            $total += (int) $count;
        }
    }
    return $total;
}

// ---------------------------------------------------------------------
// Form entry counts - COUNTS ONLY
// ---------------------------------------------------------------------

/**
 * Form entries received in the window, across the form plugins we use.
 *
 * Formidable Forms, Gravity Forms and Ninja Forms are counted straight from
 * where they store entries, with COUNT(*); Contact Form 7, which stores
 * nothing, from this plugin's own daily tally. No entry content is read,
 * so no patient data can leave the site through this. Spam, trash and
 * drafts are excluded.
 *
 * Null when neither plugin is active - a site with no form plugin has not
 * "received zero entries", it has nothing to count - and null if a query
 * fails, for the same reason a failed homepage fetch is not "no schema".
 *
 * Cached for a day like the reporter's other signals.
 *
 * @return array{form_entries_30d:?int,form_sources:array}
 */
function lumn_ut_maint_form_entry_counts() {
    $cached = get_transient('lumn_ut_maint_form_entries');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $window_start = gmdate('Y-m-d H:i:s', time() - LUMN_UT_MAINT_WINDOW_DAYS * DAY_IN_SECONDS);

    $total = 0;
    $sources = array();
    $failed = false;

    // Formidable: frm_items.created_at is stored in UTC.
    if (class_exists('FrmAppHelper') || class_exists('FrmEntry')) {
        $table = $wpdb->prefix . 'frm_items';
        if (lumn_ut_maint_table_exists($table)) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND is_draft = 0",
                $window_start
            ));
            if ($count === null) {
                $failed = true;
            } else {
                $total += (int) $count;
                $sources[] = 'formidable';
            }
        }
    }

    // Gravity Forms 2.3+: gf_entry.date_created is stored in UTC.
    if (class_exists('GFForms') || class_exists('GFAPI')) {
        $table = $wpdb->prefix . 'gf_entry';
        if (lumn_ut_maint_table_exists($table)) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE date_created >= %s AND status = 'active'",
                $window_start
            ));
            if ($count === null) {
                $failed = true;
            } else {
                $total += (int) $count;
                $sources[] = 'gravityforms';
            }
        }
    }

    // Ninja Forms: each submission is an `nf_sub` post. Trashed ones leave
    // the 'publish' status, so they drop out of the count.
    if (class_exists('Ninja_Forms') || function_exists('Ninja_Forms')) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'nf_sub' AND post_status = 'publish' AND post_date_gmt >= %s",
            $window_start
        ));
        if ($count === null) {
            $failed = true;
        } else {
            $total += (int) $count;
            $sources[] = 'ninjaforms';
        }
    }

    // Contact Form 7: this plugin's own tally (CF7 stores nothing). While
    // the tally is younger than the window the count is unknown, and the
    // total with it - a partial month would read as a whole one.
    if (lumn_ut_maint_cf7_active()) {
        $count = lumn_ut_maint_cf7_count();
        if ($count === null) {
            $failed = true;
        } else {
            $total += $count;
        }
        $sources[] = 'contactform7';
    }

    $result = array(
        // A partial count across several plugins would under-report and
        // look complete, so one failure (or one young CF7 tally) nulls the
        // total.
        'form_entries_30d' => (!$failed && !empty($sources)) ? $total : null,
        'form_sources' => $sources,
    );

    // A failure is retried sooner than a success, as with the homepage fetch.
    set_transient('lumn_ut_maint_form_entries', $result, $failed ? 6 * HOUR_IN_SECONDS : DAY_IN_SECONDS);
    return $result;
}

function lumn_ut_maint_table_exists($table) {
    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    return $found === $table;
}

/**
 * The `maintenance` block of the fleet reporter's payload.
 */
function lumn_ut_maint_signals() {
    return array_merge(
        array('window_days' => LUMN_UT_MAINT_WINDOW_DAYS),
        lumn_ut_maint_update_counts(),
        lumn_ut_maint_form_entry_counts()
    );
}

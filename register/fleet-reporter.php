<?php
namespace Lumn\Utilities;

/**
 * Fleet reporter: builds a snapshot of this site and posts it, signed, to
 * a central collector.
 *
 * THE DATA FLOW IS ONE-DIRECTIONAL. This file only ever sends. It adds no
 * REST route, no admin-ajax action for anonymous callers, no rewrite rule
 * and no query var - nothing about it is reachable from outside the site.
 * The collector's response body is read by nothing: a send either
 * happened or it didn't, and that is the whole of what we record.
 *
 * Nothing the collector could say can change this site's behaviour, so a
 * compromised or spoofed collector gains no foothold here. The plugin
 * behaves identically whether the collector is up, down, or absent.
 *
 * SHIPS DISABLED. Every setting comes from wp-config.php constants and
 * there is no admin toggle, so a fresh install - or a new version landing
 * somewhere unexpected - sends nothing at all until somebody edits
 * wp-config.php on that specific site. See docs/FLEET-REPORTER.md.
 */

// ---------------------------------------------------------------------
// Configuration - wp-config.php constants only, never the database.
//
// The signing key in particular must not live in wp-options: a database
// dump is a routine thing to hand around (migrations, backups, support
// tickets) and a key in one is a key that has leaked. Keeping it in
// wp-config.php also means a restored database cannot silently
// re-enable reporting on a site that was switched off.
// ---------------------------------------------------------------------

const LUMN_UT_FLEET_CRON_HOOK = 'lumn_ut_fleet_report_cron';
const LUMN_UT_FLEET_AS_GROUP = 'lumn-fleet';
const LUMN_UT_FLEET_LOG_OPTION = 'lumn_ut_fleet_reporter_log';
const LUMN_UT_FLEET_LOG_LIMIT = 10;

// Signature scheme version, sent as part of the signed material so the
// receiver can change algorithms later without ambiguity.
const LUMN_UT_FLEET_SIG_VERSION = 'v1';

// A send older than this is stale. The receiver rejects outside its own
// window; this constant only documents the intent on the sending side.
const LUMN_UT_FLEET_TIMESTAMP_WINDOW = 300;

function lumn_ut_fleet_is_enabled() {
    return defined('LUMN_FLEET_REPORTER_ENABLED') && LUMN_FLEET_REPORTER_ENABLED === true;
}

function lumn_ut_fleet_collector_url() {
    return defined('LUMN_FLEET_REPORTER_URL') ? (string) LUMN_FLEET_REPORTER_URL : '';
}

function lumn_ut_fleet_signing_key() {
    return defined('LUMN_FLEET_REPORTER_KEY') ? (string) LUMN_FLEET_REPORTER_KEY : '';
}

// Falls back to the site's host so a missing constant still produces a
// stable, recognisable id rather than an empty one.
function lumn_ut_fleet_site_id() {
    if (defined('LUMN_FLEET_SITE_ID') && LUMN_FLEET_SITE_ID !== '') {
        return (string) LUMN_FLEET_SITE_ID;
    }
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    return is_string($host) ? $host : '';
}

/**
 * Everything that must be true before a single byte leaves the site.
 *
 * Returns '' when ready, or a human-readable reason why not. Deliberately
 * not a bare boolean: the admin card shows this string, and "not
 * configured" and "no signing key" need different fixes.
 */
function lumn_ut_fleet_readiness_problem() {
    if (!lumn_ut_fleet_is_enabled()) {
        return __('Disabled. Set LUMN_FLEET_REPORTER_ENABLED to true in wp-config.php to switch it on.', 'lumn-utilities');
    }
    if (lumn_ut_fleet_collector_url() === '') {
        return __('No collector URL. Set LUMN_FLEET_REPORTER_URL in wp-config.php.', 'lumn-utilities');
    }
    if (!preg_match('#^https://#i', lumn_ut_fleet_collector_url())) {
        // Refused rather than warned about: the payload carries owner
        // names, email addresses and the tech's own notes.
        return __('The collector URL must be https. Refusing to send.', 'lumn-utilities');
    }
    if (lumn_ut_fleet_signing_key() === '') {
        return __('No signing key. Set LUMN_FLEET_REPORTER_KEY in wp-config.php.', 'lumn-utilities');
    }
    if (lumn_ut_fleet_site_id() === '') {
        return __('No site id could be determined.', 'lumn-utilities');
    }
    return '';
}

function lumn_ut_fleet_is_ready() {
    return lumn_ut_fleet_readiness_problem() === '';
}

// ---------------------------------------------------------------------
// wp-config.php helper
//
// Turning the reporter on means getting three constants right and copying
// a 64-character key into two places that must match exactly. The
// Developers page renders the block below so that is a paste rather than
// a transcription.
// ---------------------------------------------------------------------

/**
 * The LUMN collector, used to prefill the suggestion block.
 *
 * Not a secret and not a security boundary: the endpoint is write-only,
 * accepts nothing without a valid per-site HMAC, and returns no data to
 * anyone. Prefilling it just removes a step that is otherwise typed by
 * hand once per site, with a 405 as the reward for getting it wrong.
 *
 * The reporter still reads the real value from wp-config.php - this only
 * seeds what the Developers page offers to paste. Filterable for anyone
 * pointing a site at a different collector.
 */
function lumn_ut_fleet_default_collector_url() {
    return apply_filters('lumn_ut_fleet_default_collector_url', 'https://fleet.getlumn.com/');
}

// Placeholder used when the environment cannot be determined. Shaped
// like a real id so it still passes the receiver's pattern, but obviously
// unfinished so it cannot be pasted without noticing.
const LUMN_UT_FLEET_ENV_PLACEHOLDER = 'REPLACE-ME';

/**
 * Short environment tokens, keyed by WordPress environment type.
 *
 * Filterable so the vocabulary can change without a plugin release -
 * lumn_ut_fleet_site_id_conforms() reads the same list, so adding a token
 * here also stops it being flagged as unrecognised.
 */
function lumn_ut_fleet_environment_tokens() {
    return apply_filters('lumn_ut_fleet_environment_tokens', array(
        'production' => 'prod',
        'staging' => 'stg',
        'development' => 'dev',
        'local' => 'local',
    ));
}

/**
 * The environment half of a site id, or '' if it is not knowable.
 *
 * ONLY trusts an explicitly configured WP_ENVIRONMENT_TYPE. This looks
 * like excessive caution and is not: wp_get_environment_type() returns
 * 'production' when nothing is set, so trusting it would hand every
 * unconfigured staging site the SAME id as its production counterpart.
 * Two sites reporting under one id interleave rows in the collector, both
 * authenticate against whichever key is registered, and "latest snapshot
 * for this site" silently alternates between two different sites. There
 * is no error anywhere in that chain - which is exactly why the guard has
 * to be here, at the only point that knows it is guessing.
 */
function lumn_ut_fleet_environment_token() {
    $explicit = defined('WP_ENVIRONMENT_TYPE') || getenv('WP_ENVIRONMENT_TYPE') !== false;
    if (!$explicit || !function_exists('wp_get_environment_type')) {
        return '';
    }

    $tokens = lumn_ut_fleet_environment_tokens();
    $type = wp_get_environment_type();

    return isset($tokens[$type]) ? $tokens[$type] : '';
}

/**
 * The site-name half, from the host.
 *
 * "staging-lumntest.kinsta.cloud" and "www.lumntest.com" should both give
 * "lumntest": the environment is carried by the suffix, so repeating it
 * in the name would read as lumntest-stg-stg.
 */
function lumn_ut_fleet_site_name_from_host() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : '';

    $host = preg_replace('/^www\./', '', $host);
    // Host-based environment prefixes, as used by Kinsta and others.
    $host = preg_replace('/^(staging|stg|dev|test)-/', '', $host);

    // The first label only - "lumntest.com" and "lumntest.kinsta.cloud"
    // are the same site.
    $name = explode('.', $host)[0];

    $name = preg_replace('/[^A-Za-z0-9-]/', '-', $name);
    $name = trim($name, '-');

    return $name !== '' ? $name : 'lumn-site';
}

/**
 * A suggested site id following the fleet convention,
 * <site-name>-<environment>, and conforming to the pattern the receiver
 * enforces: ^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$
 *
 * Built to that pattern rather than hoping a hostname happens to match,
 * so a suggestion can never be rejected on arrival.
 */
function lumn_ut_fleet_suggested_site_id() {
    $token = lumn_ut_fleet_environment_token();
    if ($token === '') {
        $token = LUMN_UT_FLEET_ENV_PLACEHOLDER;
    }

    $name = lumn_ut_fleet_site_name_from_host();

    // Trim the NAME, never the token: a truncated environment suffix
    // would be a plausible-looking wrong answer, where a truncated name
    // is merely an ugly right one.
    $budget = 64 - strlen($token) - 1;
    if (strlen($name) > $budget) {
        $name = rtrim(substr($name, 0, $budget), '-');
    }

    return $name . '-' . $token;
}

/**
 * Whether a site id ends in a recognised environment token.
 *
 * Used only to raise a note on the Developers page. Deliberately not part
 * of the readiness gate: a naming opinion must not be able to take a site
 * out of the fleet.
 */
function lumn_ut_fleet_site_id_conforms($site_id) {
    $site_id = (string) $site_id;
    foreach (lumn_ut_fleet_environment_tokens() as $token) {
        $suffix = '-' . $token;
        if (strlen($site_id) > strlen($suffix)
            && substr($site_id, -strlen($suffix)) === $suffix) {
            return true;
        }
    }
    return false;
}

/**
 * The wp-config.php block for whatever is still missing on this site.
 *
 * Only ever emits `define()` lines for constants that are NOT DEFINED AT
 * ALL, so the block is always safe to paste: redefining a constant would
 * raise a PHP warning on every request. A constant that is defined but
 * wrong is reported by lumn_ut_fleet_wp_config_warnings() instead, since
 * that needs an edit rather than an addition.
 *
 * The key is generated fresh on every call and STORED NOWHERE - not an
 * option, not a transient. It only becomes real once it is pasted into
 * wp-config.php and registered against this site id in the receiver, so
 * an unused one is inert. Reloading the page simply produces another.
 *
 * wp_generate_password(..., false) is alphanumeric: a key containing a
 * quote would break the PHP string literal it gets pasted into.
 *
 * Returns '' when nothing is missing.
 */
function lumn_ut_fleet_wp_config_snippet() {
    $lines = array();

    if (!defined('LUMN_FLEET_REPORTER_ENABLED')) {
        $lines[] = "define( 'LUMN_FLEET_REPORTER_ENABLED', true );";
    }

    if (!defined('LUMN_FLEET_REPORTER_URL')) {
        $lines[] = "// The collector's ROOT path - any path segment returns 405.";
        $lines[] = "define( 'LUMN_FLEET_REPORTER_URL',     '" . lumn_ut_fleet_default_collector_url() . "' );";
    }

    if (!defined('LUMN_FLEET_REPORTER_KEY')) {
        $lines[] = "define( 'LUMN_FLEET_REPORTER_KEY',     '" . wp_generate_password(64, false) . "' );";
    }

    if (!defined('LUMN_FLEET_SITE_ID')) {
        $lines[] = "define( 'LUMN_FLEET_SITE_ID',          '" . lumn_ut_fleet_suggested_site_id() . "' );";
    }

    return implode("\n", $lines);
}

/**
 * Constants that are already defined but will not work as set.
 *
 * These cannot be fixed by pasting: the existing line has to change, and
 * adding a second define() for the same constant would warn on every
 * request. Kept separate from the snippet for exactly that reason.
 *
 * The collector-path check is a warning rather than a readiness failure.
 * The receiver serves POST / only, so a path 405s - but refusing to send
 * over it would be the reporter overruling a deployment it cannot see,
 * and the log records the 405 plainly enough. Say it loudly, send anyway.
 */
function lumn_ut_fleet_wp_config_warnings() {
    $warnings = array();

    if (defined('LUMN_FLEET_REPORTER_ENABLED') && LUMN_FLEET_REPORTER_ENABLED !== true) {
        $warnings[] = __('LUMN_FLEET_REPORTER_ENABLED is defined but is not boolean true - a quoted \'true\' or a 1 does not count. Edit the existing line.', 'lumn-utilities');
    }

    $url = lumn_ut_fleet_collector_url();
    if ($url !== '') {
        if (!preg_match('#^https://#i', $url)) {
            $warnings[] = __('LUMN_FLEET_REPORTER_URL is not https. Nothing will be sent.', 'lumn-utilities');
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '' && $path !== '/') {
            $warnings[] = __('LUMN_FLEET_REPORTER_URL has a path. The receiver serves its root only, so this will come back 405 - drop everything after the host.', 'lumn-utilities');
        }
    }

    // A note, never a readiness failure. A site id that does not follow
    // the convention still works perfectly well; what it risks is a
    // collision with another environment of the same site, which is worth
    // catching here rather than discovering as interleaved rows weeks
    // later.
    if (defined('LUMN_FLEET_SITE_ID') && !lumn_ut_fleet_site_id_conforms(LUMN_FLEET_SITE_ID)) {
        $warnings[] = sprintf(
            /* translators: %s: the list of recognised environment tokens, e.g. "prod, stg, dev, local". */
            __('LUMN_FLEET_SITE_ID does not end in a recognised environment (%s). Site ids are <site-name>-<environment>, and each environment needs its own - two sites sharing an id would have their snapshots interleaved with no error anywhere.', 'lumn-utilities'),
            implode(', ', lumn_ut_fleet_environment_tokens())
        );
    }

    return $warnings;
}

// ---------------------------------------------------------------------
// Payload
// ---------------------------------------------------------------------

/**
 * The full plugin inventory, active and inactive alike.
 *
 * Inactive plugins are included on purpose: an abandoned page builder
 * sitting deactivated still says something about how the site was built,
 * and the pipeline would rather filter than guess.
 */
function lumn_ut_fleet_collect_plugins() {
    if (!function_exists('get_plugins')) {
        // Not loaded in cron or on the front end, only in wp-admin.
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $active = (array) get_option('active_plugins', array());
    $out = array();

    foreach (get_plugins() as $file => $data) {
        $out[] = array(
            'slug' => dirname($file) !== '.' ? dirname($file) : basename($file, '.php'),
            'file' => $file,
            'name' => isset($data['Name']) ? $data['Name'] : '',
            'version' => isset($data['Version']) ? $data['Version'] : '',
            'active' => in_array($file, $active, true),
        );
    }

    return $out;
}

function lumn_ut_fleet_collect_themes() {
    $active = wp_get_theme();
    $parent = $active->parent();

    return array(
        'active' => array(
            'slug' => $active->get_stylesheet(),
            'name' => $active->get('Name'),
            'version' => $active->get('Version'),
        ),
        'parent' => $parent ? array(
            'slug' => $parent->get_stylesheet(),
            'name' => $parent->get('Name'),
            'version' => $parent->get('Version'),
        ) : null,
        'is_child_theme' => (bool) $parent,
    );
}

/**
 * Kinsta's own identity for this install, read off the filesystem.
 *
 * Kinsta hosts every environment at /www/{site_name}_{environment_id}/public/.
 * Verified on both a live site and a staging one:
 *
 *   /www/coulonwatts_417/public/   (live)
 *   /www/lumntestq_144/public/     (staging)
 *
 * This matters because LUMN_FLEET_SITE_ID is typed by hand and drifts. On
 * the very first site configured for the fleet the constant said
 * "lumntest-stg" while Kinsta called the site "lumntestq" - so any rule
 * that assumes the two follow a shared convention is already wrong. A
 * value the site reads from its own path cannot drift.
 *
 * The site name joins to Kinsta's API `name`. The trailing number is a
 * per-environment id, which is what separates a site's live install from
 * its staging one; it is NOT the API's UUID and must not be passed off as
 * one.
 *
 * This is a CONVENTION, not a documented interface. Kinsta could change
 * it, and a non-Kinsta host never matched it in the first place. Both
 * cases return empty strings, and the pipeline treats empty as "no
 * answer" rather than guessing - the domain match stays the fallback.
 */
function lumn_ut_fleet_kinsta_identity() {
    $empty = array('site_name' => '', 'environment_id' => '');

    if (!defined('ABSPATH')) {
        return $empty;
    }

    // [^/]+ is greedy inside the one path segment, so a site name that
    // itself contains an underscore keeps it: my_practice_417 reads as
    // ("my_practice", "417"), not ("my", "practice_417").
    if (!preg_match('#/www/([^/]+)_(\d+)/#', ABSPATH, $matches)) {
        return $empty;
    }

    return array(
        'site_name' => $matches[1],
        'environment_id' => $matches[2],
    );
}

/**
 * Earliest media library upload, as the build-date fallback.
 *
 * Cached for a day. It is a sorted-index query rather than a scan, but it
 * is also an answer that changes approximately never, and the reporter
 * has no business being the slowest thing in a cron run.
 */
function lumn_ut_fleet_earliest_upload_date() {
    $cached = get_transient('lumn_ut_fleet_earliest_upload');
    if ($cached !== false) {
        return $cached === 'none' ? '' : $cached;
    }

    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'ASC',
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => false,
    ));

    $date = '';
    if (!empty($attachments)) {
        $post = get_post($attachments[0]);
        if ($post && !empty($post->post_date_gmt) && $post->post_date_gmt !== '0000-00-00 00:00:00') {
            $date = gmdate('Y-m-d', strtotime($post->post_date_gmt));
        }
    }

    set_transient('lumn_ut_fleet_earliest_upload', $date === '' ? 'none' : $date, DAY_IN_SECONDS);
    return $date;
}

/**
 * The snapshot this site reports about itself.
 *
 * Shape matches SiteSnapshot in the lumn-fleet pipeline. Read-only: this
 * builds a payload and touches nothing.
 */
function lumn_ut_fleet_build_payload() {
    $profile = lumn_ut_dev_notes_get_profile();
    $tech = lumn_ut_tech_input_get();

    return array(
        'schema' => 1,
        'site_id' => lumn_ut_fleet_site_id(),
        'site_url' => home_url(),
        'captured_at' => gmdate('c'),
        'include_in_fleet' => ($profile['include_in_fleet'] === '1'),
        'hubspot_record_id' => $profile['hubspot_record_id'],
        'kinsta' => lumn_ut_fleet_kinsta_identity(),
        'reporter' => array(
            'plugin_version' => lumn_ut_fleet_plugin_version(),
            'signature_version' => LUMN_UT_FLEET_SIG_VERSION,
        ),
        'wordpress' => array(
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'themes' => lumn_ut_fleet_collect_themes(),
            'plugins' => lumn_ut_fleet_collect_plugins(),
            'earliest_upload_date' => lumn_ut_fleet_earliest_upload_date(),
        ),
        'profile' => $profile,
        'tech' => $tech,
    );
}

function lumn_ut_fleet_plugin_version() {
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data(LUMN_UTILITIES_PLUGIN_PATH . 'index.php', false, false);
    return isset($data['Version']) ? $data['Version'] : '';
}

// ---------------------------------------------------------------------
// Signing
//
// Signed material is "{version}.{timestamp}.{site_id}.{body}", not the
// body alone. Binding the timestamp and site id into the signature is
// what makes the receiver's replay window and per-site key lookup
// meaningful - otherwise either header could be swapped freely while the
// signature still verified.
// ---------------------------------------------------------------------

function lumn_ut_fleet_signing_base($timestamp, $site_id, $body) {
    return LUMN_UT_FLEET_SIG_VERSION . '.' . $timestamp . '.' . $site_id . '.' . $body;
}

function lumn_ut_fleet_sign($timestamp, $site_id, $body, $key) {
    return hash_hmac('sha256', lumn_ut_fleet_signing_base($timestamp, $site_id, $body), $key);
}

/**
 * Verify a signature. Not used by this plugin - there is no inbound path
 * here - but it is the exact counterpart of the signing above, and the
 * receiver in S3 must implement this and nothing else.
 *
 * hash_equals() rather than ===: signature comparison is the textbook
 * timing-attack surface.
 */
function lumn_ut_fleet_verify($timestamp, $site_id, $body, $key, $signature) {
    return hash_equals(lumn_ut_fleet_sign($timestamp, $site_id, $body, $key), (string) $signature);
}

// ---------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------

/**
 * Build, sign and post the snapshot. Never throws, never warns, never
 * affects the request it runs in.
 *
 * Blocking with a short timeout rather than fire-and-forget: a
 * non-blocking request cannot report a transport failure, and "failures
 * log locally" is a requirement. The RESPONSE BODY is still ignored
 * entirely - only the status code is recorded, and only so the admin card
 * can say whether the last send worked.
 *
 * @param string $trigger 'cron' or 'manual', for the log.
 * @return array{sent:bool,message:string}
 */
function lumn_ut_fleet_send($trigger = 'cron') {
    $problem = lumn_ut_fleet_readiness_problem();
    if ($problem !== '') {
        return array('sent' => false, 'message' => $problem);
    }

    try {
        $payload = lumn_ut_fleet_build_payload();
        $body = wp_json_encode($payload);

        if ($body === false) {
            return lumn_ut_fleet_record_result($trigger, false, __('The snapshot could not be encoded as JSON.', 'lumn-utilities'));
        }

        $timestamp = (string) time();
        $site_id = lumn_ut_fleet_site_id();
        $signature = lumn_ut_fleet_sign($timestamp, $site_id, $body, lumn_ut_fleet_signing_key());

        $response = wp_remote_post(lumn_ut_fleet_collector_url(), array(
            'timeout' => 10,
            'blocking' => true,
            'redirection' => 0,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Lumn-Site' => $site_id,
                'X-Lumn-Timestamp' => $timestamp,
                'X-Lumn-Signature' => $signature,
                'X-Lumn-Signature-Version' => LUMN_UT_FLEET_SIG_VERSION,
            ),
            'body' => $body,
            'user-agent' => 'LUMN-Utilities-Reporter/' . lumn_ut_fleet_plugin_version(),
        ));

        if (is_wp_error($response)) {
            // The WP_Error message can name the host but never the payload.
            return lumn_ut_fleet_record_result($trigger, false, $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        // Anything 2xx is a success. The receiver returns 202 with an
        // empty body; we do not look at the body either way.
        $ok = $code >= 200 && $code < 300;

        return lumn_ut_fleet_record_result(
            $trigger,
            $ok,
            /* translators: %d: HTTP status code. */
            sprintf(__('Collector responded %d.', 'lumn-utilities'), $code)
        );
    } catch (\Throwable $e) {
        // A reporter must never be able to take a site down. Anything
        // unexpected becomes a logged failure and nothing more.
        return lumn_ut_fleet_record_result($trigger, false, __('Unexpected error while sending.', 'lumn-utilities'));
    }
}

/**
 * Record the outcome of a send.
 *
 * PII IS IN SCOPE: the payload carries owner names, email addresses and
 * free-text notes. Nothing here records any of it - only a timestamp, a
 * trigger, a boolean, and a status/error string that the caller has
 * already kept free of payload content.
 */
function lumn_ut_fleet_record_result($trigger, $ok, $message) {
    $log = get_option(LUMN_UT_FLEET_LOG_OPTION, array());
    if (!is_array($log)) {
        $log = array();
    }

    array_unshift($log, array(
        'at' => time(),
        'trigger' => $trigger === 'manual' ? 'manual' : 'cron',
        'ok' => (bool) $ok,
        'message' => (string) $message,
    ));

    update_option(LUMN_UT_FLEET_LOG_OPTION, array_slice($log, 0, LUMN_UT_FLEET_LOG_LIMIT), false);

    return array('sent' => (bool) $ok, 'message' => (string) $message);
}

function lumn_ut_fleet_get_log() {
    $log = get_option(LUMN_UT_FLEET_LOG_OPTION, array());
    return is_array($log) ? $log : array();
}

// ---------------------------------------------------------------------
// Scheduling - Action Scheduler when the site has it (it ships with
// WooCommerce and several others and survives a slow cron far better),
// otherwise WP-Cron.
// ---------------------------------------------------------------------

function lumn_ut_fleet_has_action_scheduler() {
    return function_exists('as_schedule_recurring_action')
        && function_exists('as_next_scheduled_action')
        && function_exists('as_unschedule_all_actions');
}

function lumn_ut_fleet_schedule() {
    // Disabled sites hold no schedule at all, so switching the reporter
    // off in wp-config.php genuinely stops it rather than leaving a live
    // cron event that no-ops on every run.
    if (!lumn_ut_fleet_is_ready()) {
        lumn_ut_fleet_unschedule();
        return;
    }

    if (lumn_ut_fleet_has_action_scheduler()) {
        if (wp_next_scheduled(LUMN_UT_FLEET_CRON_HOOK)) {
            wp_clear_scheduled_hook(LUMN_UT_FLEET_CRON_HOOK);
        }
        if (!as_next_scheduled_action(LUMN_UT_FLEET_CRON_HOOK, array(), LUMN_UT_FLEET_AS_GROUP)) {
            // Offset the first run so a fleet-wide deploy doesn't have
            // every site posting in the same second.
            as_schedule_recurring_action(
                time() + wp_rand(0, HOUR_IN_SECONDS),
                DAY_IN_SECONDS,
                LUMN_UT_FLEET_CRON_HOOK,
                array(),
                LUMN_UT_FLEET_AS_GROUP
            );
        }
        return;
    }

    if (!wp_next_scheduled(LUMN_UT_FLEET_CRON_HOOK)) {
        wp_schedule_event(time() + wp_rand(0, HOUR_IN_SECONDS), 'daily', LUMN_UT_FLEET_CRON_HOOK);
    }
}

function lumn_ut_fleet_unschedule() {
    if (wp_next_scheduled(LUMN_UT_FLEET_CRON_HOOK)) {
        wp_clear_scheduled_hook(LUMN_UT_FLEET_CRON_HOOK);
    }
    // Checked before clearing rather than clearing unconditionally: this
    // runs on every admin_init of a disabled site, and
    // as_unschedule_all_actions() is a database write either way.
    if (lumn_ut_fleet_has_action_scheduler() && as_next_scheduled_action(LUMN_UT_FLEET_CRON_HOOK, array(), LUMN_UT_FLEET_AS_GROUP)) {
        as_unschedule_all_actions(LUMN_UT_FLEET_CRON_HOOK, array(), LUMN_UT_FLEET_AS_GROUP);
    }
}

// admin_init only. The scheduling check reads constants and one cron
// option - cheap - but there is no reason to run it on front-end traffic,
// and a reporter has no business touching a visitor's request path.
add_action('admin_init', 'Lumn\Utilities\lumn_ut_fleet_schedule');
add_action(LUMN_UT_FLEET_CRON_HOOK, 'Lumn\Utilities\lumn_ut_fleet_run_scheduled');

function lumn_ut_fleet_run_scheduled() {
    lumn_ut_fleet_send('cron');
}

function lumn_ut_fleet_deactivate() {
    lumn_ut_fleet_unschedule();
}

// ---------------------------------------------------------------------
// Manual "send now" - admin-post, so it is a capability-checked,
// nonce-checked, logged-in-only form submission. Deliberately NOT a REST
// route: this file adds no inbound surface.
// ---------------------------------------------------------------------

add_action('admin_post_lumn_ut_fleet_send_now', 'Lumn\Utilities\lumn_ut_fleet_handle_send_now');
function lumn_ut_fleet_handle_send_now() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_fleet_send_now');

    $problem = lumn_ut_fleet_readiness_problem();
    if ($problem !== '') {
        lumn_ut_dev_notes_redirect('', $problem);
    }

    $result = lumn_ut_fleet_send('manual');

    if ($result['sent']) {
        lumn_ut_dev_notes_redirect('fleet_sent');
    }

    lumn_ut_dev_notes_redirect('', $result['message']);
}

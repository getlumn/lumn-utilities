<?php
namespace Lumn\Utilities;

/**
 * "Developers" tab data layer: capability grant, the lumn_ut_dev_note CPT,
 * the site profile (manual + auto-detected), change rules, dependencies,
 * known issues, and the activity log. Rendering lives in
 * admin/dev-notes-page.php, the same split used for Practice Locations
 * (register/locations.php + admin/locations-page.php) and Tracking
 * (register/tracking*.php + admin/tracking-page.php).
 *
 * Everything here is per-site data in this site's own database. There is
 * no central server and no cross-site communication.
 */

// ---------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------

// Deliberately NOT lumn_ut_-prefixed, matching LUMN_UT_REST_CAPABILITY
// ('lumn_manage_site_data') in register/rest.php - capability names in
// this plugin are their own short namespace, separate from the lumn_ut_
// prefix used for options/hooks/functions.
const LUMN_UT_DEV_NOTES_CAPABILITY = 'lumn_manage_dev_notes';

// The role this capability is granted to. Not every site has this role
// (it's created by a separate system, sometimes after this plugin is
// already active) - see lumn_ut_dev_notes_ensure_capability() below.
const LUMN_UT_DEV_NOTES_ROLE = 'company_super_admin';

// Bump this to re-run the capability grant against every site (e.g. if a
// second role should also get it later). Never decrement.
const LUMN_UT_DEV_NOTES_CAPS_VERSION = 1;

const LUMN_UT_DEV_NOTES_CPT = 'lumn_ut_dev_note';
const LUMN_UT_DEV_NOTES_PAGE_SLUG = 'lumn-ut-dev-notes';

const LUMN_UT_DEV_NOTES_PROFILE_OPTION = 'lumn_ut_site_profile';
const LUMN_UT_DEV_NOTES_DETECTED_OPTION = 'lumn_ut_site_profile_detected';
const LUMN_UT_DEV_NOTES_DB_VERSION_OPTION = 'lumn_ut_db_version';

// Schema version for the data this file owns (profile shape, dev-note post
// meta shape). Bump alongside a migration in lumn_ut_dev_notes_run_migrations().
const LUMN_UT_DEV_NOTES_DB_VERSION = 3;

const LUMN_UT_DEV_NOTES_CRON_HOOK = 'lumn_ut_dev_notes_detect_cron';

// Hides the profile Export/Import buttons on the Developers page without
// removing the feature - the admin-post handlers (still capability/nonce
// gated either way) stay in place so this is a one-line flip back to true
// later, not a rebuild.
const LUMN_UT_DEV_NOTES_SHOW_PROFILE_IMPORT_EXPORT = false;

// LUMN's HubSpot portal (account) ID - the numeric segment right after
// "/contacts/" in any record URL for this account, e.g.
// https://app.hubspot.com/contacts/23704634/record/0-2/{record id}. One
// shared LUMN HubSpot account, so this is a constant rather than a
// per-site profile field.
const LUMN_UT_DEV_NOTES_HUBSPOT_PORTAL_ID = '23704634';

// HubSpot's own object-type ID for Contacts - the profile card always
// links to a contact record.
const LUMN_UT_DEV_NOTES_HUBSPOT_CONTACT_OBJECT_TYPE = '0-2';

// ---------------------------------------------------------------------
// Capability grant - idempotent, re-checked on every admin_init rather
// than relying on activation alone, since company_super_admin can be
// created by another plugin/system after this one is already active (in
// which case an activation-only grant would silently never happen and
// the tab would just never appear, with no error). Mirrors the pattern
// already used for LUMN_UT_REST_CAPABILITY in register/rest.php.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_ensure_capability() {
    $stored_version = (int) get_option('lumn_ut_caps_version', 0);
    if ($stored_version >= LUMN_UT_DEV_NOTES_CAPS_VERSION) {
        return;
    }

    $role = get_role(LUMN_UT_DEV_NOTES_ROLE);
    if (!$role) {
        // Role doesn't exist yet on this site - leave the stored version
        // unbumped so this retries on the next admin_init.
        return;
    }

    if (!$role->has_cap(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        $role->add_cap(LUMN_UT_DEV_NOTES_CAPABILITY);
    }

    update_option('lumn_ut_caps_version', LUMN_UT_DEV_NOTES_CAPS_VERSION);
}
add_action('admin_init', 'Lumn\Utilities\lumn_ut_dev_notes_ensure_capability');

// ---------------------------------------------------------------------
// Migrations - set up now even though there's nothing to migrate yet.
// Keyed so a site several versions behind can walk through each step.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_run_migrations() {
    $current = (int) get_option(LUMN_UT_DEV_NOTES_DB_VERSION_OPTION, 0);
    if ($current >= LUMN_UT_DEV_NOTES_DB_VERSION) {
        return;
    }

    // Nothing to migrate for version 1 - it just establishes the baseline
    // so future migrations have somewhere to start counting from.

    if ($current < 2) {
        lumn_ut_dev_notes_migrate_to_v2();
    }

    if ($current < 3) {
        lumn_ut_dev_notes_migrate_to_v3();
    }

    update_option(LUMN_UT_DEV_NOTES_DB_VERSION_OPTION, LUMN_UT_DEV_NOTES_DB_VERSION);
}
add_action('plugins_loaded', 'Lumn\Utilities\lumn_ut_dev_notes_run_migrations');

/**
 * v2 profile shape: drops 'billing_contact' (replaced by
 * 'primary_contact_email', a different kind of field with nothing
 * sensible to carry over) and replaces the stored 'hubspot_url' with
 * 'hubspot_record_id' - extracted from the trailing numeric segment of
 * whatever URL was already saved, since every real HubSpot record URL
 * ends in the record ID.
 */
function lumn_ut_dev_notes_migrate_to_v2() {
    $profile = get_option(LUMN_UT_DEV_NOTES_PROFILE_OPTION, array());
    if (!is_array($profile) || empty($profile)) {
        return;
    }

    $changed = false;

    if (array_key_exists('billing_contact', $profile)) {
        unset($profile['billing_contact']);
        $changed = true;
    }

    if (array_key_exists('hubspot_url', $profile)) {
        if (empty($profile['hubspot_record_id']) && preg_match('/(\d+)\/?$/', (string) $profile['hubspot_url'], $matches)) {
            $profile['hubspot_record_id'] = $matches[1];
        }
        unset($profile['hubspot_url']);
        $changed = true;
    }

    if ($changed) {
        update_option(LUMN_UT_DEV_NOTES_PROFILE_OPTION, $profile, false);
    }
}

/**
 * v3 detected-data shape: the 'core' auto-detected group gained
 * theme_name/theme_version/is_child_theme/parent_theme_* in place of the
 * old single 'active_theme' string. A site whose cron/refresh last ran
 * before this shipped still has the old shape sitting in
 * lumn_ut_site_profile_detected - normalize it now rather than showing
 * "undefined array key" warnings on the Developers page until the next
 * detection run (daily cron, or an explicit Refresh) happens to overwrite
 * it. lumn_ut_dev_notes_render_detected_group_value() also guards every
 * key with isset()/empty() as a second line of defense, in case this
 * option ever ends up in some other unexpected shape.
 */
function lumn_ut_dev_notes_migrate_to_v3() {
    $detected = get_option(LUMN_UT_DEV_NOTES_DETECTED_OPTION, array());
    if (!is_array($detected) || empty($detected['core']['data']) || !is_array($detected['core']['data'])) {
        return;
    }

    $data = $detected['core']['data'];
    if (array_key_exists('theme_name', $data) || !array_key_exists('active_theme', $data)) {
        return;
    }

    $detected['core']['data'] = array(
        'wp_version' => isset($data['wp_version']) ? $data['wp_version'] : '',
        'php_version' => isset($data['php_version']) ? $data['php_version'] : '',
        'theme_name' => $data['active_theme'],
        'theme_version' => '',
        'is_child_theme' => false,
        'parent_theme_name' => '',
        'parent_theme_version' => '',
    );

    update_option(LUMN_UT_DEV_NOTES_DETECTED_OPTION, $detected, false);
}

// ---------------------------------------------------------------------
// Activation / deactivation (registered from index.php, same pattern as
// lumn_ut_rest_ensure_capability())
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_activate() {
    lumn_ut_dev_notes_ensure_capability();
    lumn_ut_dev_notes_schedule_cron();
}

function lumn_ut_dev_notes_deactivate() {
    wp_clear_scheduled_hook(LUMN_UT_DEV_NOTES_CRON_HOOK);
}

// ---------------------------------------------------------------------
// CPT - rules/issue/log entries are all this one post type, distinguished
// by the 'lumn_ut_note_type' meta key. public/show_ui are false: nothing
// here is ever meant to show up in wp-admin's normal Posts screens, REST,
// search, or the front end - every read/write goes through the functions
// in this file, each gated on LUMN_UT_DEV_NOTES_CAPABILITY.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_register_cpt() {
    register_post_type(LUMN_UT_DEV_NOTES_CPT, array(
        'label' => __('Dev Notes', 'lumn-utilities'),
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'supports' => array('title', 'editor', 'author', 'revisions'),
        'capability_type' => array('lumn_dev_note', 'lumn_dev_notes'),
        'map_meta_cap' => true,
    ));
}
add_action('init', 'Lumn\Utilities\lumn_ut_dev_notes_register_cpt');

// Rules and Dependencies are each a single standing record rather than a
// feed - this finds (or, if asked, creates) that one post for a given type.
function lumn_ut_dev_notes_get_singleton_post($note_type) {
    $posts = get_posts(array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'meta_key' => 'lumn_ut_note_type',
        'meta_value' => $note_type,
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
    ));
    return !empty($posts) ? $posts[0] : null;
}

function lumn_ut_dev_notes_get_or_create_singleton_post($note_type, $default_title) {
    $post = lumn_ut_dev_notes_get_singleton_post($note_type);
    if ($post) {
        return (int) $post->ID;
    }

    $id = wp_insert_post(array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'post_title' => $default_title,
        'post_content' => '',
    ), true);

    if (is_wp_error($id)) {
        return 0;
    }

    update_post_meta($id, 'lumn_ut_note_type', $note_type);
    return (int) $id;
}

// ---------------------------------------------------------------------
// Site profile - manually entered fields
// ---------------------------------------------------------------------

// key => field type, used by both the sanitizer below and the form in
// admin/dev-notes-page.php. expected_registrar/expected_dns_provider
// aren't in the original field list from the spec, but are needed to make
// the "mismatch display" requirement mean anything: without something to
// compare the detected registrar/nameservers against, there is nothing to
// flag as mismatched. Added here as manual fields for exactly that purpose.
function lumn_ut_dev_notes_profile_fields() {
    return array(
        'client_name' => 'text',
        'client_tier' => 'text',
        'marketer_partner' => 'text',
        'registrar_account_owner' => 'text',
        'expected_registrar' => 'text',
        'expected_dns_provider' => 'text',
        'primary_contact' => 'text',
        'primary_contact_email' => 'email',
        'launch_date' => 'date',
        'hubspot_record_id' => 'hubspot_id',
        'contract_notes' => 'textarea',
    );
}

function lumn_ut_dev_notes_get_profile() {
    $stored = get_option(LUMN_UT_DEV_NOTES_PROFILE_OPTION, array());
    $defaults = array_fill_keys(array_keys(lumn_ut_dev_notes_profile_fields()), '');
    return wp_parse_args(is_array($stored) ? $stored : array(), $defaults);
}

function lumn_ut_dev_notes_sanitize_profile_input($input) {
    $out = array();
    foreach (lumn_ut_dev_notes_profile_fields() as $key => $type) {
        $raw = isset($input[$key]) ? $input[$key] : '';
        if (!is_string($raw)) {
            $raw = '';
        }

        switch ($type) {
            case 'email':
                $out[$key] = sanitize_email($raw);
                break;
            case 'textarea':
                $out[$key] = sanitize_textarea_field($raw);
                break;
            case 'date':
                $out[$key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
                break;
            case 'hubspot_id':
                // Just the numeric record ID - lumn_ut_dev_notes_hubspot_record_url()
                // builds the actual link from it. Strips anything a user
                // pastes the full URL into this field instead of just the ID.
                $out[$key] = preg_replace('/\D+/', '', $raw);
                break;
            default:
                $out[$key] = sanitize_text_field($raw);
                break;
        }
    }
    return $out;
}

function lumn_ut_dev_notes_save_profile($sanitized) {
    update_option(LUMN_UT_DEV_NOTES_PROFILE_OPTION, $sanitized, false);
}

// Builds a HubSpot contact record URL from just the record ID - see the
// LUMN_UT_DEV_NOTES_HUBSPOT_* constants above for the fixed portal/object
// type this account always uses. Returns '' for a blank/non-numeric ID.
function lumn_ut_dev_notes_hubspot_record_url($record_id) {
    $record_id = preg_replace('/\D+/', '', (string) $record_id);
    if ($record_id === '') {
        return '';
    }
    return sprintf(
        'https://app.hubspot.com/contacts/%s/record/%s/%s',
        LUMN_UT_DEV_NOTES_HUBSPOT_PORTAL_ID,
        LUMN_UT_DEV_NOTES_HUBSPOT_CONTACT_OBJECT_TYPE,
        $record_id
    );
}

add_action('admin_post_lumn_ut_dn_save_profile', 'Lumn\Utilities\lumn_ut_dev_notes_handle_save_profile');
function lumn_ut_dev_notes_handle_save_profile() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_save_profile');

    $sanitized = lumn_ut_dev_notes_sanitize_profile_input(wp_unslash($_POST));
    lumn_ut_dev_notes_save_profile($sanitized);

    lumn_ut_dev_notes_redirect('profile_saved');
}

// ---------------------------------------------------------------------
// Site profile - export / import
// ---------------------------------------------------------------------

add_action('admin_post_lumn_ut_dn_export_profile', 'Lumn\Utilities\lumn_ut_dev_notes_handle_export_profile');
function lumn_ut_dev_notes_handle_export_profile() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_export_profile');

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=lumn-dev-notes-profile.json');
    echo wp_json_encode(lumn_ut_dev_notes_get_profile(), JSON_PRETTY_PRINT);
    exit;
}

add_action('admin_post_lumn_ut_dn_import_profile', 'Lumn\Utilities\lumn_ut_dev_notes_handle_import_profile');
function lumn_ut_dev_notes_handle_import_profile() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_import_profile');

    if (empty($_FILES['lumn_ut_dn_import_file']['tmp_name']) || !is_uploaded_file($_FILES['lumn_ut_dn_import_file']['tmp_name'])) {
        lumn_ut_dev_notes_redirect('', __('No file was uploaded.', 'lumn-utilities'));
    }

    // A profile export is a handful of short strings - anything claiming
    // to be one but this large is not.
    if ((int) $_FILES['lumn_ut_dn_import_file']['size'] > 65536) {
        lumn_ut_dev_notes_redirect('', __('That file is too large to be a LUMN Dev Notes profile export.', 'lumn-utilities'));
    }

    $raw = file_get_contents($_FILES['lumn_ut_dn_import_file']['tmp_name']);
    $decoded = json_decode((string) $raw, true);

    if (!is_array($decoded)) {
        lumn_ut_dev_notes_redirect('', __('That file is not valid JSON.', 'lumn-utilities'));
    }

    // Whitelist: only known profile keys survive: unknown keys are
    // silently dropped rather than rejecting the whole import over them.
    $whitelist = array_keys(lumn_ut_dev_notes_profile_fields());
    $incoming = array_intersect_key($decoded, array_flip($whitelist));

    $merged = array_merge(lumn_ut_dev_notes_get_profile(), $incoming);
    lumn_ut_dev_notes_save_profile(lumn_ut_dev_notes_sanitize_profile_input($merged));

    lumn_ut_dev_notes_redirect('profile_imported');
}

// ---------------------------------------------------------------------
// Site profile - auto-detected fields
//
// Never run on page load - every one of these lookups can hang (a slow
// resolver, an SSL handshake against a domain mid-migration, RDAP being
// briefly down). All four run together in a single daily cron event, and
// each is wrapped so one failing doesn't take the others down with it.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_schedule_cron() {
    if (!wp_next_scheduled(LUMN_UT_DEV_NOTES_CRON_HOOK)) {
        wp_schedule_event(time(), 'daily', LUMN_UT_DEV_NOTES_CRON_HOOK);
    }
}
// Both hooks call the same idempotent (wp_next_scheduled()-guarded)
// function - 'wp' covers front-end traffic, 'admin_init' covers a site
// that's never visited on the front end, matching the belt-and-suspenders
// approach already used for the capability grant above.
add_action('wp', 'Lumn\Utilities\lumn_ut_dev_notes_schedule_cron');
add_action('admin_init', 'Lumn\Utilities\lumn_ut_dev_notes_schedule_cron');
add_action(LUMN_UT_DEV_NOTES_CRON_HOOK, 'Lumn\Utilities\lumn_ut_dev_notes_run_detection');

function lumn_ut_dev_notes_get_detected() {
    $stored = get_option(LUMN_UT_DEV_NOTES_DETECTED_OPTION, array());
    return is_array($stored) ? $stored : array();
}

function lumn_ut_dev_notes_get_site_domain() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    return is_string($host) ? $host : '';
}

/**
 * Runs every detection lookup and stores the results. Safe to call from
 * cron or from an authenticated, explicitly user-triggered refresh (the
 * REST route below) - never from a page-load path.
 */
function lumn_ut_dev_notes_run_detection() {
    $detected = lumn_ut_dev_notes_get_detected();
    $now = time();
    $domain = lumn_ut_dev_notes_get_site_domain();

    $detected['domain'] = $domain;

    // wp_get_theme() with no args returns the ACTIVE theme - the child
    // theme itself, if one is active - and ->parent() returns the parent
    // WP_Theme (or false if the active theme isn't a child theme).
    $active_theme = wp_get_theme();
    $parent_theme = $active_theme->parent();

    $detected['core'] = array(
        'success' => true,
        'checked_at' => $now,
        'data' => array(
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme_name' => $active_theme->get('Name'),
            'theme_version' => $active_theme->get('Version'),
            'is_child_theme' => (bool) $parent_theme,
            'parent_theme_name' => $parent_theme ? $parent_theme->get('Name') : '',
            'parent_theme_version' => $parent_theme ? $parent_theme->get('Version') : '',
        ),
    );

    if ($domain === '') {
        $detected['dns'] = array('success' => false, 'checked_at' => $now, 'error' => __('No domain to look up.', 'lumn-utilities'));
        $detected['ssl'] = array('success' => false, 'checked_at' => $now, 'error' => __('No domain to look up.', 'lumn-utilities'));
        $detected['registrar'] = array('success' => false, 'checked_at' => $now, 'error' => __('No domain to look up.', 'lumn-utilities'));
        update_option(LUMN_UT_DEV_NOTES_DETECTED_OPTION, $detected, false);
        return $detected;
    }

    try {
        $records = @dns_get_record($domain, DNS_NS);
        if ($records === false) {
            throw new \Exception(__('The nameserver lookup failed.', 'lumn-utilities'));
        }
        $detected['dns'] = array(
            'success' => true,
            'checked_at' => $now,
            'data' => array('nameservers' => wp_list_pluck($records, 'target')),
        );
    } catch (\Throwable $e) {
        $detected['dns'] = array('success' => false, 'checked_at' => $now, 'error' => $e->getMessage());
    }

    try {
        $detected['ssl'] = array(
            'success' => true,
            'checked_at' => $now,
            'data' => lumn_ut_dev_notes_detect_ssl($domain),
        );
    } catch (\Throwable $e) {
        $detected['ssl'] = array('success' => false, 'checked_at' => $now, 'error' => $e->getMessage());
    }

    try {
        $detected['registrar'] = array(
            'success' => true,
            'checked_at' => $now,
            'data' => lumn_ut_dev_notes_detect_registrar($domain),
        );
    } catch (\Throwable $e) {
        $detected['registrar'] = array('success' => false, 'checked_at' => $now, 'error' => $e->getMessage());
    }

    update_option(LUMN_UT_DEV_NOTES_DETECTED_OPTION, $detected, false);
    return $detected;
}

// Reads the peer certificate via a raw TLS handshake (a stream context,
// not a full HTTP request) - a 5 second connect timeout so a stalled
// handshake against a domain mid-migration can't hang the caller.
function lumn_ut_dev_notes_detect_ssl($domain) {
    $context = stream_context_create(array(
        'ssl' => array(
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    ));

    $client = @stream_socket_client(
        'ssl://' . $domain . ':443',
        $errno,
        $errstr,
        5,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$client) {
        throw new \Exception($errstr !== '' ? $errstr : __('Unable to open a TLS connection.', 'lumn-utilities'));
    }

    $params = stream_context_get_params($client);
    fclose($client);

    if (empty($params['options']['ssl']['peer_certificate'])) {
        throw new \Exception(__('No certificate was returned.', 'lumn-utilities'));
    }

    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    if (!$cert) {
        throw new \Exception(__('The certificate could not be parsed.', 'lumn-utilities'));
    }

    $issuer = '';
    if (!empty($cert['issuer']['O'])) {
        $issuer = $cert['issuer']['O'];
    } elseif (!empty($cert['issuer']['CN'])) {
        $issuer = $cert['issuer']['CN'];
    }

    return array(
        'issuer' => $issuer,
        'expires_at' => isset($cert['validTo_time_t']) ? (int) $cert['validTo_time_t'] : null,
    );
}

// RDAP - https://rdap.org/domain/{domain}, JSON, no auth/API key. A 5s
// timeout via wp_remote_get()'s own args, per the spec.
function lumn_ut_dev_notes_detect_registrar($domain) {
    $response = wp_remote_get('https://rdap.org/domain/' . rawurlencode($domain), array('timeout' => 5));

    if (is_wp_error($response)) {
        throw new \Exception($response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ((int) $code !== 200) {
        throw new \Exception(
            sprintf(
                /* translators: %d: HTTP status code */
                __('RDAP responded with HTTP %d.', 'lumn-utilities'),
                (int) $code
            )
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) {
        throw new \Exception(__('RDAP returned an unexpected response.', 'lumn-utilities'));
    }

    $registrar = '';
    if (!empty($body['entities']) && is_array($body['entities'])) {
        foreach ($body['entities'] as $entity) {
            if (empty($entity['roles']) || !in_array('registrar', (array) $entity['roles'], true)) {
                continue;
            }
            if (!empty($entity['vcardArray'][1]) && is_array($entity['vcardArray'][1])) {
                foreach ($entity['vcardArray'][1] as $vcard_row) {
                    if (isset($vcard_row[0], $vcard_row[3]) && $vcard_row[0] === 'fn') {
                        $registrar = (string) $vcard_row[3];
                    }
                }
            }
            break;
        }
    }

    $domain_expiry = null;
    if (!empty($body['events']) && is_array($body['events'])) {
        foreach ($body['events'] as $event) {
            if (!empty($event['eventAction']) && $event['eventAction'] === 'expiration' && !empty($event['eventDate'])) {
                $timestamp = strtotime($event['eventDate']);
                $domain_expiry = $timestamp ?: null;
            }
        }
    }

    return array(
        'registrar' => $registrar,
        'domain_expiry' => $domain_expiry,
    );
}

// Manual-vs-detected mismatch pairs for the profile card. Only compares
// fields where a mismatch is actually meaningful to flag - see the
// comment on lumn_ut_dev_notes_profile_fields() for why
// expected_registrar/expected_dns_provider exist.
function lumn_ut_dev_notes_get_profile_mismatches($profile, $detected) {
    $mismatches = array();

    $expected_registrar = trim($profile['expected_registrar']);
    if ($expected_registrar !== '' && !empty($detected['registrar']['success'])) {
        $actual_registrar = isset($detected['registrar']['data']['registrar']) ? $detected['registrar']['data']['registrar'] : '';
        if ($actual_registrar !== '' && stripos($actual_registrar, $expected_registrar) === false && stripos($expected_registrar, $actual_registrar) === false) {
            $mismatches['registrar'] = array(
                'label' => __('Registrar', 'lumn-utilities'),
                'manual' => $expected_registrar,
                'detected' => $actual_registrar,
            );
        }
    }

    $expected_dns = trim($profile['expected_dns_provider']);
    if ($expected_dns !== '' && !empty($detected['dns']['success'])) {
        $nameservers = isset($detected['dns']['data']['nameservers']) ? (array) $detected['dns']['data']['nameservers'] : array();
        $found = false;
        foreach ($nameservers as $ns) {
            if (stripos($ns, $expected_dns) !== false) {
                $found = true;
                break;
            }
        }
        if (!$found && !empty($nameservers)) {
            $mismatches['dns'] = array(
                'label' => __('DNS / nameservers', 'lumn-utilities'),
                'manual' => $expected_dns,
                'detected' => implode(', ', $nameservers),
            );
        }
    }

    return $mismatches;
}

// ---------------------------------------------------------------------
// REST - manual "refresh" button. The page itself never triggers
// detection; this is the only other entry point besides the daily cron
// event, and both call the exact same routine.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_rest_permission_check($request) {
    if (!is_user_logged_in()) {
        return new \WP_Error(
            'lumn_dn_rest_unauthorized',
            __('Authentication is required to access this resource.', 'lumn-utilities'),
            array('status' => 401)
        );
    }

    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        return new \WP_Error(
            'lumn_dn_rest_forbidden',
            __('You do not have permission to access this resource.', 'lumn-utilities'),
            array('status' => 403)
        );
    }

    return true;
}

add_action('rest_api_init', function () {
    register_rest_route(LUMN_UT_REST_NAMESPACE, '/dev-notes/refresh-profile', array(
        'methods' => 'POST',
        'callback' => 'Lumn\Utilities\lumn_ut_dev_notes_rest_refresh_profile',
        'permission_callback' => 'Lumn\Utilities\lumn_ut_dev_notes_rest_permission_check',
    ));
});

function lumn_ut_dev_notes_rest_refresh_profile($request) {
    return rest_ensure_response(lumn_ut_dev_notes_run_detection());
}

// ---------------------------------------------------------------------
// Rules for making changes - single standing record.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_get_rules() {
    $post = lumn_ut_dev_notes_get_singleton_post('rules');
    if (!$post) {
        return array('content' => '', 'edited_by' => '', 'edited_at' => '');
    }

    $editor_id = (int) get_post_meta($post->ID, 'lumn_ut_last_edited_by', true);

    return array(
        'content' => $post->post_content,
        'edited_by' => $editor_id ? get_the_author_meta('display_name', $editor_id) : '',
        'edited_at' => $post->post_modified,
    );
}

add_action('admin_post_lumn_ut_dn_save_rules', 'Lumn\Utilities\lumn_ut_dev_notes_handle_save_rules');
function lumn_ut_dev_notes_handle_save_rules() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_save_rules');

    $post_id = lumn_ut_dev_notes_get_or_create_singleton_post('rules', __('Rules for Making Changes', 'lumn-utilities'));
    if (!$post_id) {
        lumn_ut_dev_notes_redirect('', __('The rules panel could not be saved.', 'lumn-utilities'));
    }

    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';

    wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $content,
    ));
    update_post_meta($post_id, 'lumn_ut_last_edited_by', get_current_user_id());

    lumn_ut_dev_notes_redirect('rules_saved');
}

// ---------------------------------------------------------------------
// Dependencies - current state, edited in place. Plugin rows come from
// get_plugins() (so the version column stays accurate on its own); a
// meta overlay on the dependencies singleton post layers licence/notes on
// top, keyed by plugin slug. Manual rows (non-plugin dependencies) are a
// second meta array on the same post.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_get_dependency_overlay() {
    $post = lumn_ut_dev_notes_get_singleton_post('dependencies');
    if (!$post) {
        return array();
    }
    $overlay = get_post_meta($post->ID, 'lumn_ut_dep_overlay', true);
    return is_array($overlay) ? $overlay : array();
}

function lumn_ut_dev_notes_get_manual_dependencies() {
    $post = lumn_ut_dev_notes_get_singleton_post('dependencies');
    if (!$post) {
        return array();
    }
    $rows = get_post_meta($post->ID, 'lumn_ut_dep_manual', true);
    return is_array($rows) ? $rows : array();
}

/**
 * Plugin dependency rows: get_plugins() (name/version, always live) merged
 * with the centralized defaults in register/dependency-defaults.php,
 * merged again with this site's own per-plugin overrides
 * (lumn_ut_dev_notes_get_dependency_overlay()) - in that order, so a site
 * override always wins, a central default is used next, and 'none'/blank
 * is the last resort. Each field also gets an "is this the central
 * default, or has this site overridden it" flag for the page to display,
 * and the *_override fields carry the raw (possibly blank/inherited)
 * per-site value the edit form should show - never the resolved one, or
 * saving the form with that field untouched would freeze it forever
 * instead of continuing to track the central default.
 */
function lumn_ut_dev_notes_get_plugin_dependencies() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $overlay = lumn_ut_dev_notes_get_dependency_overlay();
    $defaults = lumn_ut_dev_notes_dependency_defaults();
    $rows = array();

    foreach (get_plugins() as $slug => $data) {
        $site_override = isset($overlay[$slug]) && is_array($overlay[$slug]) ? $overlay[$slug] : array();
        $central_default = isset($defaults[$slug]) && is_array($defaults[$slug]) ? $defaults[$slug] : array();

        $ownership_override = isset($site_override['licence_ownership']) ? $site_override['licence_ownership'] : '';
        $expiry_override = isset($site_override['licence_expiry']) ? $site_override['licence_expiry'] : '';
        $notes_override = isset($site_override['notes']) ? $site_override['notes'] : '';

        $ownership_default = isset($central_default['licence_ownership']) ? $central_default['licence_ownership'] : 'none';
        $expiry_default = isset($central_default['licence_expiry']) ? $central_default['licence_expiry'] : '';
        $notes_default = isset($central_default['notes']) ? $central_default['notes'] : '';

        $rows[] = array(
            'slug' => $slug,
            'name' => $data['Name'],
            'version' => $data['Version'],
            'active' => is_plugin_active($slug),
            'licence_ownership' => $ownership_override !== '' ? $ownership_override : $ownership_default,
            'licence_expiry' => $expiry_override !== '' ? $expiry_override : $expiry_default,
            'notes' => $notes_override !== '' ? $notes_override : $notes_default,
            'licence_ownership_override' => $ownership_override,
            'licence_expiry_override' => $expiry_override,
            'notes_override' => $notes_override,
            'licence_ownership_is_default' => $ownership_override === '',
            'licence_expiry_is_default' => $expiry_override === '' && $expiry_default !== '',
            'notes_is_default' => $notes_override === '' && $notes_default !== '',
            'default_expiry' => $expiry_default,
            'default_notes' => $notes_default,
        );
    }

    return $rows;
}

/**
 * Shared sanitizer for both the per-plugin overlay form and the manual
 * dependency form. $blank_ownership controls what an unrecognized/blank
 * "licence_ownership" submission resolves to: '' for the plugin overlay
 * (blank means "inherit the central default"), 'none' for manual rows
 * (which have no central default to inherit from, and 'none' is the
 * overall default per the Dependencies table).
 */
function lumn_ut_dev_notes_sanitize_dependency_overlay_input($input, $blank_ownership = 'none') {
    $ownership_raw = isset($input['licence_ownership']) ? $input['licence_ownership'] : '';
    if (in_array($ownership_raw, array('ours', 'client', 'none'), true)) {
        $ownership = $ownership_raw;
    } else {
        $ownership = $blank_ownership;
    }

    return array(
        'licence_ownership' => $ownership,
        'licence_expiry' => isset($input['licence_expiry']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['licence_expiry']) ? $input['licence_expiry'] : '',
        'notes' => isset($input['notes']) ? sanitize_textarea_field($input['notes']) : '',
    );
}

add_action('admin_post_lumn_ut_dn_save_dependency_overlay', 'Lumn\Utilities\lumn_ut_dev_notes_handle_save_dependency_overlay');
function lumn_ut_dev_notes_handle_save_dependency_overlay() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_save_dependency_overlay');

    $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
    if ($slug === '') {
        lumn_ut_dev_notes_redirect('', __('Missing plugin reference.', 'lumn-utilities'));
    }

    $post_id = lumn_ut_dev_notes_get_or_create_singleton_post('dependencies', __('Dependencies', 'lumn-utilities'));
    $overlay = lumn_ut_dev_notes_get_dependency_overlay();
    // '' (not 'none') so a blank/"Inherit default" submission keeps this
    // site tracking register/dependency-defaults.php instead of freezing
    // it to 'none'.
    $overlay[$slug] = lumn_ut_dev_notes_sanitize_dependency_overlay_input(wp_unslash($_POST), '');
    update_post_meta($post_id, 'lumn_ut_dep_overlay', $overlay);

    lumn_ut_dev_notes_redirect('dependency_saved');
}

add_action('admin_post_lumn_ut_dn_save_manual_dependency', 'Lumn\Utilities\lumn_ut_dev_notes_handle_save_manual_dependency');
function lumn_ut_dev_notes_handle_save_manual_dependency() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_save_manual_dependency');

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    if ($name === '') {
        lumn_ut_dev_notes_redirect('', __('A name is required.', 'lumn-utilities'));
    }

    $post_id = lumn_ut_dev_notes_get_or_create_singleton_post('dependencies', __('Dependencies', 'lumn-utilities'));
    $rows = lumn_ut_dev_notes_get_manual_dependencies();

    $row_id = isset($_POST['row_id']) ? sanitize_key(wp_unslash($_POST['row_id'])) : '';
    $overlay = lumn_ut_dev_notes_sanitize_dependency_overlay_input(wp_unslash($_POST));
    $row = array_merge($overlay, array(
        'id' => $row_id !== '' ? $row_id : wp_generate_uuid4(),
        'name' => $name,
        'version' => isset($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '',
    ));

    $found = false;
    foreach ($rows as $index => $existing) {
        if ($existing['id'] === $row['id']) {
            $rows[$index] = $row;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $rows[] = $row;
    }

    update_post_meta($post_id, 'lumn_ut_dep_manual', $rows);

    lumn_ut_dev_notes_redirect('dependency_saved');
}

add_action('admin_post_lumn_ut_dn_delete_manual_dependency', 'Lumn\Utilities\lumn_ut_dev_notes_handle_delete_manual_dependency');
function lumn_ut_dev_notes_handle_delete_manual_dependency() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }

    $row_id = isset($_GET['row_id']) ? sanitize_key(wp_unslash($_GET['row_id'])) : '';
    check_admin_referer('lumn_ut_dn_delete_manual_dependency_' . $row_id);

    $post = lumn_ut_dev_notes_get_singleton_post('dependencies');
    if ($post) {
        $rows = lumn_ut_dev_notes_get_manual_dependencies();
        $rows = array_values(array_filter($rows, function ($row) use ($row_id) {
            return $row['id'] !== $row_id;
        }));
        update_post_meta($post->ID, 'lumn_ut_dep_manual', $rows);
    }

    lumn_ut_dev_notes_redirect('dependency_deleted');
}

// ---------------------------------------------------------------------
// Known issues - one lumn_ut_dev_note post per issue, tracked with a
// status rather than logged chronologically.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_issue_severities() {
    return array('high', 'medium', 'low');
}

function lumn_ut_dev_notes_issue_statuses() {
    return array('open', 'mitigated', 'resolved');
}

function lumn_ut_dev_notes_format_issue($post) {
    $severity = get_post_meta($post->ID, 'lumn_ut_severity', true);
    $status = get_post_meta($post->ID, 'lumn_ut_status', true);
    $opened_date = get_post_meta($post->ID, 'lumn_ut_opened_date', true);

    return array(
        'id' => (int) $post->ID,
        'title' => $post->post_title,
        'body' => $post->post_content,
        'severity' => in_array($severity, lumn_ut_dev_notes_issue_severities(), true) ? $severity : 'low',
        'status' => in_array($status, lumn_ut_dev_notes_issue_statuses(), true) ? $status : 'open',
        'opened_date' => $opened_date !== '' ? $opened_date : substr($post->post_date, 0, 10),
        'mitigation_note' => get_post_meta($post->ID, 'lumn_ut_mitigation_note', true),
    );
}

function lumn_ut_dev_notes_get_issues() {
    $posts = get_posts(array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'meta_key' => 'lumn_ut_note_type',
        'meta_value' => 'issue',
        'posts_per_page' => -1,
        'no_found_rows' => true,
    ));

    $issues = array_map('Lumn\Utilities\lumn_ut_dev_notes_format_issue', $posts);

    $severity_rank = array_flip(lumn_ut_dev_notes_issue_severities());
    usort($issues, function ($a, $b) use ($severity_rank) {
        $rank_a = isset($severity_rank[$a['severity']]) ? $severity_rank[$a['severity']] : 99;
        $rank_b = isset($severity_rank[$b['severity']]) ? $severity_rank[$b['severity']] : 99;
        if ($rank_a !== $rank_b) {
            return $rank_a <=> $rank_b;
        }
        return strcmp($b['opened_date'], $a['opened_date']);
    });

    return $issues;
}

add_action('admin_post_lumn_ut_dn_save_issue', 'Lumn\Utilities\lumn_ut_dev_notes_handle_save_issue');
function lumn_ut_dev_notes_handle_save_issue() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_save_issue');

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    if ($title === '') {
        lumn_ut_dev_notes_redirect('', __('A title is required.', 'lumn-utilities'));
    }

    $severity = isset($_POST['severity']) ? sanitize_key(wp_unslash($_POST['severity'])) : 'low';
    if (!in_array($severity, lumn_ut_dev_notes_issue_severities(), true)) {
        $severity = 'low';
    }

    $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'open';
    if (!in_array($status, lumn_ut_dev_notes_issue_statuses(), true)) {
        $status = 'open';
    }

    $opened_date = isset($_POST['opened_date']) ? wp_unslash($_POST['opened_date']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opened_date)) {
        $opened_date = current_time('Y-m-d');
    }

    $body = isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '';
    $mitigation_note = isset($_POST['mitigation_note']) ? sanitize_textarea_field(wp_unslash($_POST['mitigation_note'])) : '';

    $issue_id = isset($_POST['issue_id']) ? absint($_POST['issue_id']) : 0;
    $is_own_issue_post = $issue_id && get_post_type($issue_id) === LUMN_UT_DEV_NOTES_CPT && get_post_meta($issue_id, 'lumn_ut_note_type', true) === 'issue';

    $postarr = array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $body,
    );

    if ($is_own_issue_post) {
        $postarr['ID'] = $issue_id;
        $result = wp_update_post($postarr, true);
    } else {
        $result = wp_insert_post($postarr, true);
    }

    if (is_wp_error($result)) {
        lumn_ut_dev_notes_redirect('', $result->get_error_message());
    }

    update_post_meta($result, 'lumn_ut_note_type', 'issue');
    update_post_meta($result, 'lumn_ut_severity', $severity);
    update_post_meta($result, 'lumn_ut_status', $status);
    update_post_meta($result, 'lumn_ut_opened_date', $opened_date);
    update_post_meta($result, 'lumn_ut_mitigation_note', $mitigation_note);

    lumn_ut_dev_notes_redirect('issue_saved');
}

add_action('admin_post_lumn_ut_dn_delete_issue', 'Lumn\Utilities\lumn_ut_dev_notes_handle_delete_issue');
function lumn_ut_dev_notes_handle_delete_issue() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }

    $issue_id = isset($_GET['issue_id']) ? absint($_GET['issue_id']) : 0;
    check_admin_referer('lumn_ut_dn_delete_issue_' . $issue_id);

    if ($issue_id && get_post_type($issue_id) === LUMN_UT_DEV_NOTES_CPT && get_post_meta($issue_id, 'lumn_ut_note_type', true) === 'issue') {
        wp_trash_post($issue_id);
    }

    lumn_ut_dev_notes_redirect('issue_deleted');
}

// ---------------------------------------------------------------------
// Activity log - append-only, one post per entry.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_get_log_query($paged) {
    return new \WP_Query(array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'meta_key' => 'lumn_ut_note_type',
        'meta_value' => 'log',
        'posts_per_page' => 20,
        'paged' => max(1, $paged),
        'orderby' => 'date',
        'order' => 'DESC',
    ));
}

add_action('admin_post_lumn_ut_dn_add_log_entry', 'Lumn\Utilities\lumn_ut_dev_notes_handle_add_log_entry');
function lumn_ut_dev_notes_handle_add_log_entry() {
    if (!current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_dn_add_log_entry');

    $body = isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '';
    if (trim(wp_strip_all_tags($body)) === '') {
        lumn_ut_dev_notes_redirect('', __('An entry needs some text.', 'lumn-utilities'));
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

    $result = wp_insert_post(array(
        'post_type' => LUMN_UT_DEV_NOTES_CPT,
        'post_status' => 'publish',
        'post_title' => $title !== '' ? $title : wp_trim_words(wp_strip_all_tags($body), 8),
        'post_content' => $body,
    ), true);

    if (is_wp_error($result)) {
        lumn_ut_dev_notes_redirect('', $result->get_error_message());
    }

    update_post_meta($result, 'lumn_ut_note_type', 'log');

    lumn_ut_dev_notes_redirect('log_added');
}

// ---------------------------------------------------------------------
// Shared redirect helper for every admin-post handler above.
// ---------------------------------------------------------------------

function lumn_ut_dev_notes_redirect($notice, $error = '') {
    $args = array('page' => LUMN_UT_DEV_NOTES_PAGE_SLUG);
    if ($notice !== '') {
        $args['lumn_ut_dn_notice'] = $notice;
    }
    if ($error !== '') {
        $args['lumn_ut_dn_error'] = rawurlencode($error);
    }
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
}

// ---------------------------------------------------------------------
// Menu registration - the third capability check (add_submenu_page's
// $capability argument) lives here; the page callback itself repeats the
// check as its first line (admin/dev-notes-page.php), and every
// admin-post handler and REST route above repeats it a third way. A
// hidden menu item alone is not access control.
// ---------------------------------------------------------------------

add_action('admin_menu', function () {
    add_submenu_page(
        'lumn-ut-shortcode-settings',
        __('Developers', 'lumn-utilities'),
        __('Developers', 'lumn-utilities'),
        LUMN_UT_DEV_NOTES_CAPABILITY,
        LUMN_UT_DEV_NOTES_PAGE_SLUG,
        'Lumn\Utilities\lumn_ut_dev_notes_page_callback'
    );
});

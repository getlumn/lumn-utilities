<?php
namespace Lumn\Utilities;

/**
 * Tech input: the three fields a technician fills in on a site for the
 * fleet pipeline to read - notes, a "worth a conversation" flag, and a
 * suppression date.
 *
 * These are OBSERVATIONS, not reactions to a verdict. The pipeline
 * computes a site's tier centrally and writes it to the Google Sheet; it
 * is never sent back here and is never displayed in this plugin. A tech
 * records what they know when they know it, and the Sheet joins the two.
 *
 * Kept separate from register/dev-notes.php on purpose. The Site Profile
 * is a description of the client; this is a running commentary on the
 * site, it is read by a different audience (sales, via the Sheet), and it
 * has its own capability - see LUMN_UT_TECH_INPUT_CAPABILITY below.
 *
 * Rendering lives in admin/dev-notes-page.php, the same register/ + admin/
 * split used everywhere else in this plugin.
 */

// ---------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------

const LUMN_UT_TECH_INPUT_OPTION = 'lumn_ut_tech_input';

/**
 * Its own capability, deliberately.
 *
 * The build plan left "who can edit these fields" open: the Developers tab
 * is restricted to company_super_admin, but notes need to be editable by
 * whichever tech actually did the work, who may well not be one.
 *
 * This resolves nothing by itself - the capability is currently granted to
 * exactly the same role, so behaviour today is unchanged. What it buys is
 * that widening access later means adding one role to
 * LUMN_UT_TECH_INPUT_ROLES and bumping the caps version, rather than
 * loosening the whole Developers tab to get at three fields.
 */
const LUMN_UT_TECH_INPUT_CAPABILITY = 'lumn_manage_tech_input';

// Roles granted the capability above. Add to this list (and bump
// LUMN_UT_TECH_INPUT_CAPS_VERSION) to widen who can record observations.
function lumn_ut_tech_input_roles() {
    return array(LUMN_UT_DEV_NOTES_ROLE);
}

// Bump to re-run the grant against every site. Never decrement. Mirrors
// LUMN_UT_DEV_NOTES_CAPS_VERSION.
const LUMN_UT_TECH_INPUT_CAPS_VERSION = 1;

const LUMN_UT_TECH_INPUT_CAPS_VERSION_OPTION = 'lumn_ut_tech_input_caps_version';

// Two quarters. The Sheet marks a note stale past this, and the edit form
// says so up front, so a tech knows the shelf life while they are writing.
const LUMN_UT_TECH_INPUT_STALE_DAYS = 182;

// ---------------------------------------------------------------------
// Capability grant - idempotent, re-checked on admin_init for the same
// reason lumn_ut_dev_notes_ensure_capability() is: the role can be
// created by another system after this plugin is already active.
// ---------------------------------------------------------------------

function lumn_ut_tech_input_ensure_capability() {
    $granted = array();

    foreach (lumn_ut_tech_input_roles() as $role_name) {
        $role = get_role($role_name);
        if (!$role) {
            continue;
        }
        if (!$role->has_cap(LUMN_UT_TECH_INPUT_CAPABILITY)) {
            $role->add_cap(LUMN_UT_TECH_INPUT_CAPABILITY);
        }
        $granted[] = $role_name;
    }

    // Only record the version once every configured role actually existed
    // to receive the grant, so a role created later still gets it.
    if (count($granted) === count(lumn_ut_tech_input_roles())) {
        update_option(LUMN_UT_TECH_INPUT_CAPS_VERSION_OPTION, LUMN_UT_TECH_INPUT_CAPS_VERSION);
    }
}

function lumn_ut_tech_input_maybe_ensure_capability() {
    $current = (int) get_option(LUMN_UT_TECH_INPUT_CAPS_VERSION_OPTION, 0);
    if ($current >= LUMN_UT_TECH_INPUT_CAPS_VERSION) {
        return;
    }
    lumn_ut_tech_input_ensure_capability();
}
add_action('admin_init', 'Lumn\Utilities\lumn_ut_tech_input_maybe_ensure_capability');

// Super admins always qualify, matching how the Developers tab behaves.
function lumn_ut_tech_input_current_user_can() {
    return current_user_can(LUMN_UT_TECH_INPUT_CAPABILITY)
        || current_user_can(LUMN_UT_DEV_NOTES_CAPABILITY);
}

// ---------------------------------------------------------------------
// The fields
// ---------------------------------------------------------------------

// tech_notes_author and tech_notes_updated_at are stored but not editable:
// they are set from the save itself, never from the form.
function lumn_ut_tech_input_fields() {
    return array(
        'tech_notes' => 'textarea',
        'tech_flag' => 'bool',
        'tech_flag_reason' => 'text',
        'suppress_until' => 'date',
    );
}

function lumn_ut_tech_input_defaults() {
    return array(
        'tech_notes' => '',
        'tech_notes_author' => '',
        'tech_notes_updated_at' => '',
        'tech_flag' => '0',
        'tech_flag_reason' => '',
        'suppress_until' => '',
    );
}

function lumn_ut_tech_input_get() {
    $stored = get_option(LUMN_UT_TECH_INPUT_OPTION, array());
    return wp_parse_args(is_array($stored) ? $stored : array(), lumn_ut_tech_input_defaults());
}

function lumn_ut_tech_input_sanitize($input) {
    $out = array();

    foreach (lumn_ut_tech_input_fields() as $key => $type) {
        $raw = isset($input[$key]) ? $input[$key] : '';
        if (!is_string($raw)) {
            $raw = '';
        }

        switch ($type) {
            case 'textarea':
                $out[$key] = sanitize_textarea_field($raw);
                break;
            case 'bool':
                $out[$key] = ($raw === '1' || $raw === 'on' || $raw === 'true') ? '1' : '0';
                break;
            case 'date':
                $out[$key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
                break;
            default:
                $out[$key] = sanitize_text_field($raw);
                break;
        }
    }

    // A reason with no flag is noise in the Sheet - it reads as a live
    // recommendation next to a site nobody flagged.
    if ($out['tech_flag'] !== '1') {
        $out['tech_flag_reason'] = '';
    }

    return $out;
}

/**
 * Save, stamping authorship onto the note.
 *
 * The build plan says "stores author and timestamp on every save". Taken
 * literally that would re-stamp the note whenever a tech only ticked the
 * flag or set a suppression date, which would quietly reset the staleness
 * clock on a note nobody had touched - and the stale marker in the Sheet
 * is the one thing keeping `tech_notes` honest. So the stamp follows the
 * NOTE TEXT: it moves when the text changes, and holds otherwise.
 *
 * Clearing the note clears its provenance with it, rather than leaving an
 * author attached to an empty string.
 */
function lumn_ut_tech_input_save($sanitized, $author_id = 0) {
    $existing = lumn_ut_tech_input_get();
    $merged = array_merge($existing, $sanitized);

    $note_changed = $sanitized['tech_notes'] !== $existing['tech_notes'];

    if ($note_changed) {
        if ($sanitized['tech_notes'] === '') {
            $merged['tech_notes_author'] = '';
            $merged['tech_notes_updated_at'] = '';
        } else {
            $author_id = $author_id ? (int) $author_id : get_current_user_id();
            $user = $author_id ? get_userdata($author_id) : null;

            // display_name, not user_email: this string is read by sales in
            // a spreadsheet and PII is in scope for the pipeline. Falls
            // back to the login, never to an address.
            $merged['tech_notes_author'] = $user ? $user->display_name : '';
            $merged['tech_notes_updated_at'] = gmdate('c');
        }
    }

    update_option(LUMN_UT_TECH_INPUT_OPTION, $merged, false);
    return $merged;
}

// True while a suppression is in force. The pipeline makes this call
// itself too - this copy exists so the admin card can say so plainly.
function lumn_ut_tech_input_is_suppressed($tech = null) {
    $tech = $tech === null ? lumn_ut_tech_input_get() : $tech;
    if (empty($tech['suppress_until'])) {
        return false;
    }
    return strtotime($tech['suppress_until'] . ' 23:59:59 UTC') > time();
}

// Age of the note in days, or null if there is no note or no timestamp.
function lumn_ut_tech_input_note_age_days($tech = null) {
    $tech = $tech === null ? lumn_ut_tech_input_get() : $tech;
    if (empty($tech['tech_notes']) || empty($tech['tech_notes_updated_at'])) {
        return null;
    }
    $updated = strtotime($tech['tech_notes_updated_at']);
    if (!$updated) {
        return null;
    }
    return (int) floor((time() - $updated) / DAY_IN_SECONDS);
}

function lumn_ut_tech_input_note_is_stale($tech = null) {
    $age = lumn_ut_tech_input_note_age_days($tech);
    return $age !== null && $age > LUMN_UT_TECH_INPUT_STALE_DAYS;
}

// ---------------------------------------------------------------------
// Save handler
// ---------------------------------------------------------------------

add_action('admin_post_lumn_ut_tech_input_save', 'Lumn\Utilities\lumn_ut_tech_input_handle_save');
function lumn_ut_tech_input_handle_save() {
    if (!lumn_ut_tech_input_current_user_can()) {
        wp_die(esc_html__('You do not have permission to do this.', 'lumn-utilities'));
    }
    check_admin_referer('lumn_ut_tech_input_save');

    lumn_ut_tech_input_save(lumn_ut_tech_input_sanitize(wp_unslash($_POST)));

    lumn_ut_dev_notes_redirect('tech_input_saved');
}

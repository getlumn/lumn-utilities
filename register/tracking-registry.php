<?php
namespace Lumn\Utilities;

/**
 * LUMN Tracking / SEO Tools - central registries.
 *
 * This is Step 1 of the LUMN Tracking / SEO Tools system: the architectural
 * foundation later steps build the actual tracking events on top of. See
 * docs/TRACKING.md for the full developer guide, including the hard
 * opt-in requirement every function in this system must respect.
 *
 * Everything a future feature needs to know - which toggles exist, which
 * event names are valid, what parameters each event accepts, which
 * parameter keys are never allowed through - lives in this one file, so it
 * can't drift between the settings UI, the feature-flag API, the PHP push
 * helper, and the front-end JS.
 */

const LUMN_UT_TRACKING_OPTION = 'lumn_ut_tracking_settings';
const LUMN_UT_TRACKING_SETTINGS_GROUP = 'lumn_ut_tracking_settings_group';
const LUMN_UT_TRACKING_PAGE_SLUG = 'lumn-ut-tracking';
const LUMN_UT_TRACKING_CAPABILITY = 'edit_pages';
const LUMN_UT_TRACKING_SCRIPT_HANDLE = 'lumn-ut-tracking';

/**
 * Canonical list of individual feature toggles, keyed by the option key
 * each is stored under (inside LUMN_UT_TRACKING_OPTION, alongside 'master').
 * This is the single source of truth for:
 * - which feature keys lumn_ut_tracking_feature_enabled() recognizes
 *   (anything not listed here always resolves to disabled - see
 *   register/tracking.php)
 * - the settings UI (register/tracking.php builds one checkbox per entry,
 *   in this array's order)
 * - which events (below) each toggle gates - see each event's 'feature'
 *   key in lumn_ut_tracking_event_registry()
 *
 * 'implemented' is purely informational for the admin UI ("Coming soon"
 * badge) - it does not affect enforcement. A feature with no events wired
 * up yet is already inert simply because nothing calls
 * lumn_ut_tracking_push_event() or LumnTracking.pushEvent() for it.
 */
function lumn_ut_tracking_feature_registry() {
    return array(
        'phone_click_tracking' => array(
            'label' => __('Phone Click Tracking', 'lumn-utilities'),
            'description' => __('Sends a standardized lumn_phone_click event when a visitor clicks a telephone (tel:) link. Never includes the phone number itself.', 'lumn-utilities'),
            'implemented' => true,
        ),
        'email_click_tracking' => array(
            'label' => __('Email Click Tracking', 'lumn-utilities'),
            'description' => __('Sends a standardized lumn_email_click event when a visitor clicks a mailto: link. Never includes the email address itself.', 'lumn-utilities'),
            'implemented' => true,
        ),
        'appointment_click_tracking' => array(
            'label' => __('Appointment Click Tracking', 'lumn-utilities'),
            'description' => __('Sends a standardized lumn_appointment_click event for appointment/booking links - either explicitly tagged with data-lumn-event, or automatically recognized from this site\'s configured Appointments link(s).', 'lumn-utilities'),
            'implemented' => true,
        ),
        'directions_click_tracking' => array(
            'label' => __('Directions Click Tracking', 'lumn-utilities'),
            'description' => __('Sends a standardized lumn_directions_click event when a visitor clicks a Google Maps, Apple Maps, Bing Maps, Waze, or other recognized directions link. Never includes the full destination URL.', 'lumn-utilities'),
            'implemented' => true,
        ),
        'event_tracking' => array(
            'label' => __('Explicit Event Tracking', 'lumn-utilities'),
            'description' => __('Enables the data-lumn-event / data-lumn-location / data-lumn-component markup mechanism, so any element can be explicitly marked for tracking. Each element still also requires its resolved event\'s own feature toggle above (e.g. an explicit phone_click element still requires Phone Click Tracking).', 'lumn-utilities'),
            'implemented' => true,
        ),
        'form_tracking' => array(
            'label' => __('Form Tracking', 'lumn-utilities'),
            'description' => __('Sends a standardized lumn_form_submit event when a supported form plugin (Gravity Forms, Formidable Forms) successfully submits. Also requires enabling the specific provider, and the specific form, below - form metadata only (form ID/type/name/provider), never submitted field values.', 'lumn-utilities'),
            'implemented' => true,
        ),
        'download_tracking' => array(
            'label' => __('Download Tracking', 'lumn-utilities'),
            'description' => __('lumn_file_download - clicks on downloadable file links.', 'lumn-utilities'),
            'implemented' => false,
        ),
        'video_tracking' => array(
            'label' => __('Video Tracking', 'lumn-utilities'),
            'description' => __('lumn_video_start / lumn_video_complete - embedded video engagement.', 'lumn-utilities'),
            'implemented' => false,
        ),
        'external_link_tracking' => array(
            'label' => __('External Link Tracking', 'lumn-utilities'),
            'description' => __('lumn_external_link - clicks that navigate off-site.', 'lumn-utilities'),
            'implemented' => false,
        ),
        'debugger' => array(
            'label' => __('Debugger', 'lumn-utilities'),
            'description' => __('Logs every LUMN event to the browser console - both fired and suppressed (e.g. "detected but feature disabled") - and dispatches lumn:tracking:event / lumn:tracking:suppressed browser events for a future on-page debugger to observe. Never affects the data layer itself, and produces no console output when off.', 'lumn-utilities'),
            'implemented' => true,
        ),
    );
}

/**
 * Canonical list of supported form-plugin providers for LUMN Form
 * Tracking, keyed by the provider identifier used as the
 * 'lumn_form_provider' event param and as the second half of a
 * 'form_tracking_{provider}' settings key. Single source of truth for:
 * - which provider keys lumn_ut_tracking_form_provider_enabled()
 *   recognizes (see register/tracking.php)
 * - the settings UI's provider toggles (register/form-tracking.php)
 * - the per-form config key prefix ('{provider}:{form_id}', see
 *   register/form-tracking.php)
 *
 * Adding a new provider here (plus a `form_tracking_{provider}` toggle it
 * implies) is step 1 of adding a new form-plugin adapter - see
 * docs/TRACKING.md "Adding a new form provider".
 */
function lumn_ut_tracking_form_provider_registry() {
    return array(
        'gravity_forms' => array(
            'label' => __('Gravity Forms', 'lumn-utilities'),
        ),
        'formidable_forms' => array(
            'label' => __('Formidable Forms', 'lumn-utilities'),
        ),
    );
}

/**
 * Standardized 'lumn_form_type' values an administrator can assign to a
 * form (register/form-tracking.php's per-form configuration UI). Purely
 * safe, admin-chosen metadata describing what a form is *for* - never
 * inferred from submitted field content. An unrecognized/legacy value
 * saved by an older version of this list falls back to 'other' wherever
 * it's read (fail-safe, same pattern as every other registry here).
 */
function lumn_ut_tracking_form_type_registry() {
    return array(
        'appointment' => __('Appointment', 'lumn-utilities'),
        'contact' => __('Contact', 'lumn-utilities'),
        'consultation' => __('Consultation', 'lumn-utilities'),
        'newsletter' => __('Newsletter', 'lumn-utilities'),
        'insurance' => __('Insurance', 'lumn-utilities'),
        'employment' => __('Employment', 'lumn-utilities'),
        'other' => __('Other', 'lumn-utilities'),
    );
}

// All-false defaults for every recognized key, including the master
// switch and one 'form_tracking_{provider}' key per registered form
// provider. This is the fail-closed baseline lumn_ut_get_tracking_settings()
// merges stored values onto, so a missing or unrecognized key can never
// resolve to "on".
function lumn_ut_tracking_default_settings() {
    $defaults = array('master' => false);
    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        $defaults[$key] = false;
    }
    foreach (lumn_ut_tracking_form_provider_registry() as $key => $meta) {
        $defaults['form_tracking_' . $key] = false;
    }
    return $defaults;
}

// Parameters every LUMN event carries automatically, in addition to
// whatever event-specific parameters are listed in its registry entry
// below. 'event', 'lumn_event_category', and 'lumn_event_action' are
// computed by the push helpers themselves (never caller-supplied), so
// they are not part of this allowlist.
function lumn_ut_tracking_base_event_params() {
    return array('lumn_location', 'lumn_component', 'lumn_page_type');
}

/**
 * Canonical LUMN event specification: naming convention lumn_[object]_[action],
 * one entry per event constant. 'params' lists every parameter key (beyond
 * the base params above) that event is allowed to carry - this is the
 * allowlist lumn_ut_tracking_push_event() and LumnTracking.pushEvent()
 * enforce. Nothing implements these events yet (see the 'implemented' flags
 * above); this registry exists so later steps add an entry here once,
 * rather than inventing event names/params ad hoc at each call site.
 *
 * Do NOT add a param here that could carry patient-submitted or otherwise
 * sensitive data (name, email, phone, address, DOB, insurance, message
 * body, medical/treatment details, etc.) - see
 * lumn_ut_tracking_forbidden_param_keys() below, which is enforced even on
 * params declared here as a second layer of defense.
 */
function lumn_ut_tracking_event_registry() {
    return array(
        'LUMN_FORM_SUBMIT' => array(
            'name' => 'lumn_form_submit',
            'feature' => 'form_tracking',
            'category' => 'lead',
            'action' => 'form_submit',
            'description' => __('A supported form was successfully submitted. Metadata about the submission only - never field values.', 'lumn-utilities'),
            'params' => array(
                'lumn_form_type', 'lumn_form_id', 'lumn_form_name', 'lumn_form_provider',
                'lumn_location_id', 'lumn_location_name',
            ),
        ),
        'LUMN_PHONE_CLICK' => array(
            'name' => 'lumn_phone_click',
            'feature' => 'phone_click_tracking',
            'category' => 'lead',
            'action' => 'phone_click',
            'description' => __('A tel: link was clicked.', 'lumn-utilities'),
            'params' => array(),
        ),
        'LUMN_APPOINTMENT_CLICK' => array(
            'name' => 'lumn_appointment_click',
            'feature' => 'appointment_click_tracking',
            'category' => 'lead',
            'action' => 'appointment_click',
            'description' => __('An appointment/booking link was clicked.', 'lumn-utilities'),
            'params' => array(),
        ),
        'LUMN_DIRECTIONS_CLICK' => array(
            'name' => 'lumn_directions_click',
            'feature' => 'directions_click_tracking',
            'category' => 'lead',
            'action' => 'directions_click',
            'description' => __('A directions/map link was clicked.', 'lumn-utilities'),
            'params' => array(),
        ),
        'LUMN_EMAIL_CLICK' => array(
            'name' => 'lumn_email_click',
            'feature' => 'email_click_tracking',
            'category' => 'lead',
            'action' => 'email_click',
            'description' => __('A mailto: link was clicked.', 'lumn-utilities'),
            'params' => array(),
        ),
        'LUMN_FILE_DOWNLOAD' => array(
            'name' => 'lumn_file_download',
            'feature' => 'download_tracking',
            'category' => 'engagement',
            'action' => 'file_download',
            'description' => __('A downloadable file link was clicked.', 'lumn-utilities'),
            'params' => array('lumn_file_name', 'lumn_file_type'),
        ),
        'LUMN_VIDEO_START' => array(
            'name' => 'lumn_video_start',
            'feature' => 'video_tracking',
            'category' => 'engagement',
            'action' => 'video_start',
            'description' => __('An embedded video began playing.', 'lumn-utilities'),
            'params' => array('lumn_video_title', 'lumn_video_provider'),
        ),
        'LUMN_VIDEO_COMPLETE' => array(
            'name' => 'lumn_video_complete',
            'feature' => 'video_tracking',
            'category' => 'engagement',
            'action' => 'video_complete',
            'description' => __('An embedded video reached its completion threshold.', 'lumn-utilities'),
            'params' => array('lumn_video_title', 'lumn_video_provider', 'lumn_video_percent'),
        ),
        'LUMN_EXTERNAL_LINK' => array(
            'name' => 'lumn_external_link',
            'feature' => 'external_link_tracking',
            'category' => 'engagement',
            'action' => 'external_link_click',
            'description' => __('A link that navigates off-site was clicked.', 'lumn-utilities'),
            'params' => array('lumn_destination_domain'),
        ),
    );
}

// Looks up one event definition by its dataLayer event name (e.g.
// 'lumn_phone_click') rather than its registry key (e.g.
// 'LUMN_PHONE_CLICK'). Returns null when unrecognized.
function lumn_ut_tracking_event_by_name($event_name) {
    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        if ($event['name'] === $event_name) {
            return array_merge($event, array('key' => $key));
        }
    }
    return null;
}

/**
 * Hard denylist of parameter key names that must never reach the data
 * layer, regardless of what an event's registry entry above declares as
 * allowed. This is a second, independent layer behind the per-event
 * allowlist (see lumn_ut_tracking_push_event() in register/tracking.php)
 * specifically so that a future event definition that mistakenly declares
 * one of these as an allowed param is still blocked at push time, rather
 * than relying solely on whoever edits the registry to remember the rule.
 *
 * Keys are compared case-insensitively and exactly (no substring matching)
 * so legitimate metadata keys like 'lumn_form_name' or 'lumn_video_title'
 * are never caught by a broad match on 'name' or 'title'.
 */
function lumn_ut_tracking_forbidden_param_keys() {
    return array(
        'name', 'first_name', 'last_name', 'full_name', 'patient_name',
        'email', 'email_address',
        'phone', 'phone_number', 'telephone',
        'address', 'street', 'street_address', 'city', 'state', 'zip', 'postal_code',
        'dob', 'date_of_birth', 'birthdate', 'birthday',
        'ssn', 'social_security_number',
        'insurance', 'insurance_provider', 'insurance_id', 'policy_number',
        'message', 'comments', 'comment', 'notes', 'note',
        'medical', 'medical_history', 'treatment', 'diagnosis', 'condition', 'symptom', 'symptoms',
        'password', 'credit_card', 'card_number', 'cvv',
    );
}

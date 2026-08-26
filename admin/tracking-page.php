<?php
namespace Lumn\Utilities;

/**
 * Rendering for the "SEO & Tracking" admin page. Kept separate from
 * register/tracking.php (settings registration + feature-flag API + data
 * layer) so each file stays focused, the same split used for the Practice
 * Locations page (admin/locations-page.php + register/locations.php).
 */

function lumn_ut_tracking_page_callback() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    echo '<div class="lumn-ut-admin-settings-wrap wrap lumn-ut-tracking-page">';
    lumn_ut_render_admin_header(__('Opt-in event tracking and SEO tooling. Everything here is off until you turn it on.', 'lumn-utilities'));

    lumn_ut_render_tracking_intro();

    echo '<form class="lumn-ut-admin-settings-form" method="post" action="options.php">';
    settings_errors();
    settings_fields(LUMN_UT_TRACKING_SETTINGS_GROUP);
    do_settings_sections(LUMN_UT_TRACKING_SETTINGS_GROUP);
    submit_button();
    echo '</form>';

    echo '</div>';
}

function lumn_ut_render_tracking_intro() {
    echo '<div class="lumn-utilites-admin-accordion">';
    echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">' . esc_html__('How this works', 'lumn-utilities') . '</span><span class="plus">+</span><span class="minus">-</span></div>';
    echo '<div class="lumn-utilites-admin-accordion-content">';

    echo '<p>' . esc_html__('LUMN Tracking is a standardization layer, not a replacement for your existing analytics. It never creates a GTM container, tag, or trigger; never creates a GA4 property; never sends anything to Google directly; and never touches a GTM/GA4 configuration that is already on this site.', 'lumn-utilities') . '</p>';

    echo '<table class="lumn-utilites-table">';
    echo '<tr><th>' . esc_html__('WordPress', 'lumn-utilities') . '</th><td>' . esc_html__('LUMN Utilities generates a standardized lumn_* event', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>&darr;</th><td></td></tr>';
    echo '<tr><th>' . esc_html__('window.dataLayer', 'lumn-utilities') . '</th><td>' . esc_html__('The event is pushed to the data layer already on this site', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>&darr;</th><td></td></tr>';
    echo '<tr><th>' . esc_html__('Your existing GTM container', 'lumn-utilities') . '</th><td>' . esc_html__('Decides what (if anything) happens next - GA4, Google Ads, or nothing at all', 'lumn-utilities') . '</td></tr>';
    echo '</table>';

    echo '<p><strong>' . esc_html__('Patient data safety:', 'lumn-utilities') . '</strong> ' . esc_html__('LUMN events only ever carry metadata (which form, which button, which page section) - never a submitted field value. A name, email, phone number, address, message, or medical/insurance detail can never be sent through this system, even by a future developer mistake - the restriction is enforced in code, not left to convention.', 'lumn-utilities') . '</p>';

    echo '<p>' . esc_html__('See docs/TRACKING.md in the plugin for the full developer specification: event naming convention, standard parameters, the feature-flag API, and how future tracking features (forms, phone/appointment/directions/email clicks, downloads, video, external links) should be built on this foundation.', 'lumn-utilities') . '</p>';

    echo '</div>';
    echo '</div>';
}

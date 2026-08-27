<?php
namespace Lumn\Utilities;

/**
 * Rendering for the "SEO & Tracking" admin page. Kept separate from
 * register/tracking.php (settings registration + feature-flag API + data
 * layer) and register/tracking-config.php (central configuration model,
 * reset/export/import/presets) so each file stays focused, the same split
 * used for the Practice Locations page (admin/locations-page.php +
 * register/locations.php).
 *
 * Three tabs (Step 6):
 * - Dashboard (default): a consolidated, read-only view of what this site
 *   is actually configured to do, sourced entirely from
 *   lumn_ut_tracking_get_full_config() - never a second copy of any
 *   setting.
 * - Configure Tracking: the full settings form (unchanged from Steps 1-5,
 *   now including the Step 6 Per-Event Controls and Global URL Exclusions
 *   sections registered elsewhere).
 * - Import / Export: config portability between LUMN sites.
 */

function lumn_ut_tracking_page_callback() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    $valid_tabs = array('dashboard', 'configure', 'importexport');
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
    if (!in_array($tab, $valid_tabs, true)) {
        $tab = 'dashboard';
    }

    echo '<div class="lumn-ut-admin-settings-wrap wrap lumn-ut-tracking-page">';
    lumn_ut_render_admin_header(__('Opt-in event tracking and SEO tooling. Everything here is off until you turn it on.', 'lumn-utilities'));

    lumn_ut_render_tracking_notices();
    lumn_ut_render_tracking_tab_nav($tab);

    switch ($tab) {
        case 'configure':
            lumn_ut_render_tracking_configure_tab();
            break;
        case 'importexport':
            lumn_ut_render_tracking_importexport_tab();
            break;
        default:
            lumn_ut_render_tracking_dashboard_tab();
            break;
    }

    echo '</div>';
}

function lumn_ut_render_tracking_tab_nav($active) {
    $tabs = array(
        'dashboard' => __('Dashboard', 'lumn-utilities'),
        'configure' => __('Configure Tracking', 'lumn-utilities'),
        'importexport' => __('Import / Export', 'lumn-utilities'),
    );

    echo '<h2 class="nav-tab-wrapper lumn-ut-tracking-debugger-tabs">';
    foreach ($tabs as $key => $label) {
        $url = add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'tab' => $key), admin_url('admin.php'));
        $class = 'nav-tab' . ($key === $active ? ' nav-tab-active' : '');
        echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';
}

function lumn_ut_render_tracking_notices() {
    if (!isset($_GET['lumn_ut_notice'])) {
        if (isset($_GET['lumn_ut_import_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(rawurldecode(wp_unslash($_GET['lumn_ut_import_error']))) . '</p></div>';
        }
        return;
    }

    $notice = sanitize_key(wp_unslash($_GET['lumn_ut_notice']));
    $messages = array(
        'reset' => __('LUMN Tracking configuration has been reset to its safe defaults. Every feature is now off. This did not change any other LUMN Utilities setting, and it never touched your GTM or GA4 configuration.', 'lumn-utilities'),
        'imported' => __('The imported configuration has been applied.', 'lumn-utilities'),
        'preset_applied' => __('The preset has been applied to your feature toggles. Master Tracking itself was not changed - turn it on above if this site should start tracking now.', 'lumn-utilities'),
    );

    if (isset($messages[$notice])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }
}

// ---------------------------------------------------------------------
// Shared pieces
// ---------------------------------------------------------------------

function lumn_ut_render_analytics_disclaimer() {
    echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('LUMN Utilities does not modify your Google Tag Manager container or GA4 configuration.', 'lumn-utilities') . '</strong> ' . esc_html__('It only ever generates standardized browser events that your existing GTM configuration may choose to consume.', 'lumn-utilities') . '</p></div>';
}

// ---------------------------------------------------------------------
// Dashboard tab
// ---------------------------------------------------------------------

function lumn_ut_render_tracking_dashboard_tab() {
    $config = lumn_ut_tracking_get_full_config();

    echo '<h2>' . esc_html__('LUMN SEO & Tracking', 'lumn-utilities') . '</h2>';

    echo '<p><strong>' . esc_html__('Status:', 'lumn-utilities') . '</strong> ';
    if ($config['enabled']) {
        echo '<span class="lumn-ut-health-badge lumn-ut-health-good">✓ ' . esc_html__('LUMN Tracking Enabled', 'lumn-utilities') . '</span>';
    } else {
        echo '<span class="lumn-ut-health-badge lumn-ut-health-info">✗ ' . esc_html__('LUMN Tracking Disabled', 'lumn-utilities') . '</span>';
    }
    echo '</p>';

    $last_modified = lumn_ut_tracking_get_last_modified();
    if ($last_modified) {
        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %s: formatted date/time this configuration was last saved */
            __('Last modified: %s', 'lumn-utilities'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_modified)
        )) . '</p>';
    }

    lumn_ut_render_analytics_disclaimer();

    echo '<h3>' . esc_html__('Features', 'lumn-utilities') . '</h3>';
    lumn_ut_render_feature_status_grid($config);

    echo '<h3>' . esc_html__('Configuration Summary', 'lumn-utilities') . '</h3>';
    lumn_ut_render_configuration_summary();

    $configure_url = add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'tab' => 'configure'), admin_url('admin.php'));
    $debugger_url = admin_url('admin.php?page=' . LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG);
    $catalog_url = add_query_arg(array('page' => LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG, 'tab' => 'catalog'), admin_url('admin.php'));
    $health_url = add_query_arg(array('page' => LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG, 'tab' => 'health'), admin_url('admin.php'));

    echo '<p class="lumn-ut-dashboard-actions">';
    echo '<a class="button button-primary" href="' . esc_url($configure_url) . '">' . esc_html__('Configure Tracking', 'lumn-utilities') . '</a> ';
    echo '<a class="button" href="' . esc_url($debugger_url) . '">' . esc_html__('Debug Tracking', 'lumn-utilities') . '</a> ';
    echo '<a class="button" href="' . esc_url($catalog_url) . '">' . esc_html__('View Event Catalog', 'lumn-utilities') . '</a> ';
    echo '<a class="button" href="' . esc_url($health_url) . '">' . esc_html__('Run Health Check', 'lumn-utilities') . '</a>';
    echo '</p>';

    echo '<h3>' . esc_html__('Presets', 'lumn-utilities') . '</h3>';
    lumn_ut_render_presets_section();

    echo '<h3>' . esc_html__('Reset', 'lumn-utilities') . '</h3>';
    lumn_ut_render_reset_section();
}

function lumn_ut_render_feature_status_grid($config) {
    $rows = array(
        array('phone_click_tracking', __('Phone Tracking', 'lumn-utilities'), $config['phone']['enabled']),
        array('email_click_tracking', __('Email Tracking', 'lumn-utilities'), $config['email']['enabled']),
        array('appointment_click_tracking', __('Appointment Tracking', 'lumn-utilities'), $config['appointment']['enabled']),
        array('directions_click_tracking', __('Directions Tracking', 'lumn-utilities'), $config['directions']['enabled']),
        array('form_tracking', __('Form Tracking', 'lumn-utilities'), $config['forms']['enabled']),
        array('download_tracking', __('Download Tracking', 'lumn-utilities'), $config['downloads']['enabled']),
        array('external_link_tracking', __('External Link Tracking', 'lumn-utilities'), $config['external_links']['enabled']),
        array('video_tracking', __('Video Tracking', 'lumn-utilities'), $config['videos']['enabled']),
        array('cta_classification', __('Automatic CTA Detection', 'lumn-utilities'), $config['automatic_cta']['enabled']),
    );
    $recommended = lumn_ut_tracking_recommended_features();

    echo '<table class="widefat striped lumn-ut-status-checks"><tbody>';
    foreach ($rows as $row) {
        list($feature_key, $label, $on) = $row;
        echo '<tr><td>' . esc_html($label) . ($feature_key === 'phone_click_tracking' ? ' <span class="description">' . esc_html__('(also covers SMS)', 'lumn-utilities') . '</span>' : '') . '</td>';
        echo '<td>' . ($on
            ? '<span class="lumn-ut-health-badge lumn-ut-health-good">' . esc_html__('ON', 'lumn-utilities') . '</span>'
            : '<span class="lumn-ut-health-badge lumn-ut-health-info">' . esc_html__('OFF', 'lumn-utilities') . '</span>') . '</td>';
        echo '<td>' . (in_array($feature_key, $recommended, true) ? '<span class="description">' . esc_html__('Recommended', 'lumn-utilities') . '</span>' : '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description">' . esc_html__('"Recommended" is only a suggestion shown here - it never changes what is actually enabled. Apply a preset below, or use Configure Tracking, to change anything.', 'lumn-utilities') . '</p>';
}

function lumn_ut_render_configuration_summary() {
    $summary = lumn_ut_tracking_configuration_summary();

    if (!$summary['enabled']) {
        echo '<p>' . esc_html__('Tracking is disabled. LUMN is not generating any events on this site right now.', 'lumn-utilities') . '</p>';
        return;
    }

    $on_rows = array_values(array_filter($summary['rows'], function ($r) {
        return $r['on'];
    }));
    $off_rows = array_values(array_filter($summary['rows'], function ($r) {
        return !$r['on'];
    }));

    echo '<p>' . esc_html__('Tracking is enabled.', 'lumn-utilities') . '</p>';

    echo '<p><strong>' . esc_html__('LUMN will currently track:', 'lumn-utilities') . '</strong></p>';
    if (empty($on_rows)) {
        echo '<p class="description">' . esc_html__('Nothing yet - every individual feature and form below is still off.', 'lumn-utilities') . '</p>';
    } else {
        echo '<ul class="lumn-ut-summary-list">';
        foreach ($on_rows as $row) {
            echo '<li>✓ ' . esc_html($row['label']) . '</li>';
        }
        echo '</ul>';
    }

    echo '<p><strong>' . esc_html__('LUMN will NOT currently track:', 'lumn-utilities') . '</strong></p>';
    if (empty($off_rows)) {
        echo '<p class="description">' . esc_html__('Everything above is on.', 'lumn-utilities') . '</p>';
    } else {
        echo '<ul class="lumn-ut-summary-list">';
        foreach ($off_rows as $row) {
            echo '<li>○ ' . esc_html($row['label']) . '</li>';
        }
        echo '</ul>';
    }
}

// ---------------------------------------------------------------------
// Presets
// ---------------------------------------------------------------------

function lumn_ut_render_presets_section() {
    $presets = lumn_ut_tracking_presets();
    $previewing = isset($_GET['preset_preview']) ? sanitize_key(wp_unslash($_GET['preset_preview'])) : '';

    echo '<table class="widefat striped"><tbody>';
    foreach ($presets as $key => $preset) {
        $preview_url = add_query_arg(array('page' => LUMN_UT_TRACKING_PAGE_SLUG, 'tab' => 'dashboard', 'preset_preview' => $key), admin_url('admin.php'));
        echo '<tr><td><strong>' . esc_html($preset['label']) . '</strong><br /><span class="description">' . esc_html($preset['description']) . '</span></td>';
        echo '<td><a class="button" href="' . esc_url($preview_url) . '">' . esc_html__('Preview', 'lumn-utilities') . '</a></td></tr>';
    }
    echo '</tbody></table>';

    if ($previewing === '' || !isset($presets[$previewing])) {
        return;
    }

    $diff = lumn_ut_tracking_preset_diff($previewing);

    echo '<div class="lumn-ut-admin-settings-section">';
    echo '<h4>' . esc_html(sprintf(
        /* translators: %s: preset name, e.g. "Standard" */
        __('Preview: %s preset', 'lumn-utilities'),
        $presets[$previewing]['label']
    )) . '</h4>';

    if (empty($diff)) {
        echo '<p>' . esc_html__('Your current feature toggles already match this preset - applying it would change nothing.', 'lumn-utilities') . '</p>';
        return;
    }

    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Setting', 'lumn-utilities') . '</th><th>' . esc_html__('Current', 'lumn-utilities') . '</th><th>' . esc_html__('After applying', 'lumn-utilities') . '</th></tr></thead><tbody>';
    foreach ($diff as $row) {
        echo '<tr><td>' . esc_html($row['label']) . '</td><td>' . ($row['from'] ? esc_html__('ON', 'lumn-utilities') : esc_html__('OFF', 'lumn-utilities')) . '</td><td><strong>' . ($row['to'] ? esc_html__('ON', 'lumn-utilities') : esc_html__('OFF', 'lumn-utilities')) . '</strong></td></tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Apply this preset? This only changes the feature toggles listed above - it never touches Master Tracking.', 'lumn-utilities')) . '\');">';
    wp_nonce_field('lumn_ut_apply_preset');
    echo '<input type="hidden" name="action" value="lumn_ut_apply_preset" />';
    echo '<input type="hidden" name="preset" value="' . esc_attr($previewing) . '" />';
    submit_button(__('Apply Preset', 'lumn-utilities'), 'primary', 'submit', false);
    echo '</form>';
    echo '</div>';
}

// ---------------------------------------------------------------------
// Reset
// ---------------------------------------------------------------------

function lumn_ut_render_reset_section() {
    echo '<p>' . esc_html__('Returns every LUMN tracking setting on this page (feature toggles, form mappings, classification settings, per-event overrides, exclusions) to its safe, all-off default.', 'lumn-utilities') . '</p>';
    echo '<ul class="lumn-ut-summary-list">';
    echo '<li>' . esc_html__('Disables LUMN tracking entirely.', 'lumn-utilities') . '</li>';
    echo '<li>' . esc_html__('Does not modify GTM.', 'lumn-utilities') . '</li>';
    echo '<li>' . esc_html__('Does not modify GA4.', 'lumn-utilities') . '</li>';
    echo '<li>' . esc_html__('Does not delete or change any other LUMN Utilities setting (Practice Locations, shortcode settings, etc.).', 'lumn-utilities') . '</li>';
    echo '</ul>';

    $url = wp_nonce_url(add_query_arg(array('action' => 'lumn_ut_reset_tracking'), admin_url('admin-post.php')), 'lumn_ut_reset_tracking');
    echo '<p><a class="button button-secondary" href="' . esc_url($url) . '" onclick="return confirm(\'' . esc_js(__('Reset all LUMN tracking configuration to its safe defaults? This turns every feature off. This cannot be undone (though you can Export your current configuration first).', 'lumn-utilities')) . '\');">' . esc_html__('Reset LUMN Tracking Settings', 'lumn-utilities') . '</a></p>';
}

// ---------------------------------------------------------------------
// Configure Tracking tab
// ---------------------------------------------------------------------

function lumn_ut_render_tracking_configure_tab() {
    lumn_ut_render_tracking_intro();

    echo '<form class="lumn-ut-admin-settings-form" method="post" action="options.php">';
    settings_errors();
    settings_fields(LUMN_UT_TRACKING_SETTINGS_GROUP);
    do_settings_sections(LUMN_UT_TRACKING_SETTINGS_GROUP);
    submit_button();
    echo '</form>';
}

function lumn_ut_render_tracking_intro() {
    echo '<div class="lumn-utilities-admin-accordion">';
    echo '<div class="lumn-utilities-admin-accordion-header"><span class="icon-title">' . esc_html__('How this works', 'lumn-utilities') . '</span><span class="plus">+</span><span class="minus">-</span></div>';
    echo '<div class="lumn-utilities-admin-accordion-content">';

    echo '<p>' . esc_html__('LUMN Tracking is a standardization layer, not a replacement for your existing analytics. It never creates a GTM container, tag, or trigger; never creates a GA4 property; never sends anything to Google directly; and never touches a GTM/GA4 configuration that is already on this site.', 'lumn-utilities') . '</p>';

    echo '<table class="lumn-utilities-table">';
    echo '<tr><th>' . esc_html__('WordPress', 'lumn-utilities') . '</th><td>' . esc_html__('LUMN Utilities identifies an action and generates a standardized lumn_* event', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>&darr;</th><td></td></tr>';
    echo '<tr><th>' . esc_html__('window.dataLayer', 'lumn-utilities') . '</th><td>' . esc_html__('The event is pushed to the data layer already on this site', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>&darr;</th><td></td></tr>';
    echo '<tr><th>' . esc_html__('Your existing GTM container', 'lumn-utilities') . '</th><td>' . esc_html__('Listens for that event, and decides what (if anything) happens next - GA4 receives it only if GTM is configured to send it', 'lumn-utilities') . '</td></tr>';
    echo '</table>';

    echo '<p><strong>' . esc_html__('Patient data safety:', 'lumn-utilities') . '</strong> ' . esc_html__('LUMN events only ever carry metadata (which form, which button, which page section) - never a submitted field value. A name, email, phone number, address, message, or medical/insurance detail can never be sent through this system, even by a future developer mistake - the restriction is enforced in code, not left to convention.', 'lumn-utilities') . '</p>';

    echo '<p>' . esc_html__('Every tracking feature listed below - phone, email, SMS, appointment, and directions click tracking; downloads; external links; video engagement; automatic CTA classification; the explicit data-lumn-event markup mechanism; and form submission tracking for Gravity Forms and Formidable Forms - is implemented and available now. Everything defaults to off.', 'lumn-utilities') . '</p>';

    echo '<p>' . esc_html__('See docs/TRACKING.md in the plugin for the full developer specification: automatic vs. explicit detection, supported data-lumn-* attributes, event naming convention, standard parameters, the feature-flag API, tracking overrides/exclusions, and recommended GTM triggers for each event.', 'lumn-utilities') . '</p>';

    $debugger_url = admin_url('admin.php?page=' . LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG);
    $dashboard_url = admin_url('admin.php?page=' . LUMN_UT_TRACKING_PAGE_SLUG);
    echo '<p>' . sprintf(
        /* translators: 1: link to the Dashboard tab, 2: link to the Tracking Debugger admin page */
        esc_html__('Want a plain-language summary of what this site is currently set up to do? See the %1$s. Need to see events as they happen, browse every event in one place, or check this site\'s tracking configuration? Visit %2$s.', 'lumn-utilities'),
        '<a href="' . esc_url($dashboard_url) . '">' . esc_html__('Dashboard', 'lumn-utilities') . '</a>',
        '<a href="' . esc_url($debugger_url) . '">' . esc_html__('Tracking Debugger', 'lumn-utilities') . '</a>'
    ) . '</p>';

    echo '</div>';
    echo '</div>';
}

// ---------------------------------------------------------------------
// Import / Export tab
// ---------------------------------------------------------------------

function lumn_ut_render_tracking_importexport_tab() {
    echo '<h2>' . esc_html__('Import / Export', 'lumn-utilities') . '</h2>';
    echo '<p>' . esc_html__('Reproduce this site\'s LUMN tracking configuration on another LUMN site. Only tracking configuration is included - never passwords, API keys, nonces, submitted form data, or any other WordPress user information.', 'lumn-utilities') . '</p>';

    echo '<h3>' . esc_html__('Export', 'lumn-utilities') . '</h3>';
    $export_url = wp_nonce_url(add_query_arg(array('action' => 'lumn_ut_export_tracking'), admin_url('admin-post.php')), 'lumn_ut_export_tracking');
    echo '<p><a class="button button-primary" href="' . esc_url($export_url) . '">' . esc_html__('Export Configuration (lumn-tracking-config.json)', 'lumn-utilities') . '</a></p>';

    echo '<h3>' . esc_html__('Import', 'lumn-utilities') . '</h3>';

    $import_step = isset($_GET['lumn_ut_import_step']) ? sanitize_key(wp_unslash($_GET['lumn_ut_import_step'])) : '';
    $pending = $import_step === 'preview' ? get_transient(lumn_ut_tracking_import_transient_key()) : false;

    if (is_array($pending)) {
        lumn_ut_render_import_preview($pending);
        return;
    }

    echo '<p class="description">' . esc_html__('Nothing is applied on upload - the file is validated first, then you\'ll see exactly what would change before anything is saved.', 'lumn-utilities') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
    wp_nonce_field('lumn_ut_validate_import');
    echo '<input type="hidden" name="action" value="lumn_ut_validate_import" />';
    echo '<p><input type="file" name="lumn_ut_import_file" accept="application/json,.json" required /></p>';
    submit_button(__('Validate', 'lumn-utilities'), 'secondary', 'submit', false);
    echo '</form>';
}

function lumn_ut_render_import_preview($clean) {
    $diff = lumn_ut_tracking_import_diff($clean);

    echo '<div class="lumn-ut-admin-settings-section">';
    echo '<h4>' . esc_html__('Preview Changes', 'lumn-utilities') . '</h4>';

    if (empty($diff)) {
        echo '<p>' . esc_html__('This file validated successfully, but every setting it contains already matches this site\'s current configuration - applying it would change nothing.', 'lumn-utilities') . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Setting', 'lumn-utilities') . '</th><th>' . esc_html__('Current', 'lumn-utilities') . '</th><th>' . esc_html__('Imported', 'lumn-utilities') . '</th></tr></thead><tbody>';
        foreach ($diff as $row) {
            echo '<tr><td>' . esc_html($row['label']) . '</td><td>' . ($row['from'] ? esc_html__('ON', 'lumn-utilities') : esc_html__('OFF', 'lumn-utilities')) . '</td><td><strong>' . ($row['to'] ? esc_html__('ON', 'lumn-utilities') : esc_html__('OFF', 'lumn-utilities')) . '</strong></td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Apply this imported configuration? This will overwrite your current LUMN tracking settings with the values shown above.', 'lumn-utilities')) . '\');">';
    wp_nonce_field('lumn_ut_apply_import');
    echo '<input type="hidden" name="action" value="lumn_ut_apply_import" />';
    submit_button(__('Apply Configuration', 'lumn-utilities'), 'primary', 'submit', false);
    echo '</form>';
    echo '</div>';
}

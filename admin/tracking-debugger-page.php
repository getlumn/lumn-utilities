<?php
namespace Lumn\Utilities;

/**
 * Rendering for the "Tracking Debugger" admin page (Debugger / Event
 * Catalog / Health Check / GTM Guide tabs). Kept separate from
 * register/tracking-debugger.php (activation, enqueue, health-check
 * logic, GTM recipe data) the same way admin/tracking-page.php is split
 * from register/tracking.php.
 */

function lumn_ut_tracking_debugger_page_callback() {
    if (!current_user_can(LUMN_UT_TRACKING_CAPABILITY)) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lumn-utilities'));
    }

    $valid_tabs = array('debugger', 'catalog', 'health', 'gtm');
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'debugger';
    if (!in_array($tab, $valid_tabs, true)) {
        $tab = 'debugger';
    }

    echo '<div class="lumn-ut-admin-settings-wrap wrap lumn-ut-tracking-page lumn-ut-tracking-debugger-page">';
    lumn_ut_render_admin_header(__('Debug live events, browse the event catalog, check tracking health, and get GTM setup guidance.', 'lumn-utilities'));

    lumn_ut_render_debugger_tab_nav($tab);

    switch ($tab) {
        case 'catalog':
            lumn_ut_render_catalog_tab();
            break;
        case 'health':
            lumn_ut_render_health_tab();
            break;
        case 'gtm':
            lumn_ut_render_gtm_tab();
            break;
        default:
            lumn_ut_render_debugger_tab();
            break;
    }

    echo '</div>';
}

function lumn_ut_render_debugger_tab_nav($active) {
    $tabs = array(
        'debugger' => __('Debugger', 'lumn-utilities'),
        'catalog' => __('Event Catalog', 'lumn-utilities'),
        'health' => __('Health Check', 'lumn-utilities'),
        'gtm' => __('GTM Guide', 'lumn-utilities'),
    );

    echo '<h2 class="nav-tab-wrapper lumn-ut-tracking-debugger-tabs">';
    foreach ($tabs as $key => $label) {
        $url = add_query_arg(array('page' => LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG, 'tab' => $key), admin_url('admin.php'));
        $class = 'nav-tab' . ($key === $active ? ' nav-tab-active' : '');
        echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';
}

// ---------------------------------------------------------------------
// Debugger tab
// ---------------------------------------------------------------------

function lumn_ut_render_debugger_tab() {
    $settings = lumn_ut_get_tracking_settings();
    $overlay_on = lumn_ut_debug_overlay_user_enabled();

    echo '<h2>' . esc_html__('LUMN Tracking Debugger', 'lumn-utilities') . '</h2>';
    echo '<p>' . esc_html__('Turns on a small panel on this site\'s front end - visible only to you, while logged in as an administrator - that shows LUMN events in real time as you click around and submit forms.', 'lumn-utilities') . '</p>';

    echo '<p><strong>' . esc_html__('Status:', 'lumn-utilities') . '</strong> ';
    if ($overlay_on) {
        echo '<span class="lumn-ut-health-badge lumn-ut-health-good">' . esc_html__('● Debugging ON', 'lumn-utilities') . '</span>';
    } else {
        echo '<span class="lumn-ut-health-badge lumn-ut-health-info">' . esc_html__('● Debugging OFF', 'lumn-utilities') . '</span>';
    }
    echo '</p>';

    $toggle_url = lumn_ut_debug_overlay_toggle_url(!$overlay_on);
    $button_label = $overlay_on ? __('Disable Debugging', 'lumn-utilities') : __('Enable Debugging', 'lumn-utilities');
    echo '<p><a class="button button-primary" href="' . esc_url($toggle_url) . '">' . esc_html($button_label) . '</a></p>';

    if ($overlay_on) {
        echo '<p class="description">' . esc_html__('Now visit any front-end page on this site (while logged in) - a debugger panel will appear showing recent events, letting you send a test event, and letting you scan the current page for LUMN-trackable elements.', 'lumn-utilities') . '</p>';
    }

    echo '<h3>' . esc_html__('Status Checks', 'lumn-utilities') . '</h3>';
    echo '<table class="lumn-utilites-table lumn-ut-status-checks">';
    lumn_ut_render_status_row(__('LUMN Tracking (master)', 'lumn-utilities'), !empty($settings['master']));
    foreach (lumn_ut_tracking_feature_registry() as $key => $meta) {
        if (empty($meta['implemented'])) {
            continue;
        }
        lumn_ut_render_status_row($meta['label'], lumn_ut_tracking_feature_enabled($key));
    }
    foreach (lumn_ut_tracking_form_provider_registry() as $provider => $meta) {
        lumn_ut_render_status_row($meta['label'] . ' (' . __('detected', 'lumn-utilities') . ')', lumn_ut_form_tracking_provider_detected($provider));
    }
    echo '</table>';
    echo '<p class="description">' . esc_html__('Data Layer and GTM detection are shown live in the front-end panel itself (they depend on the actual page, not this admin screen). Detecting GTM never causes LUMN to interact with it.', 'lumn-utilities') . '</p>';

    if (empty($settings['master']) || empty($settings['debugger'])) {
        echo '<div class="notice notice-warning inline"><p>' . esc_html__('Master Tracking and/or the Debugger feature toggle are off (see Feature Toggles on the SEO & Tracking page). The front-end panel will still appear once you enable it above, but its Recent Events feed will stay empty until both of those are also on.', 'lumn-utilities') . '</p></div>';
    }
}

function lumn_ut_render_status_row($label, $ok) {
    echo '<tr><td>' . ($ok
        ? '<span class="lumn-ut-health-badge lumn-ut-health-good">✓</span>'
        : '<span class="lumn-ut-health-badge lumn-ut-health-info">✗</span>') . '</td><td>' . esc_html($label) . '</td></tr>';
}

// ---------------------------------------------------------------------
// Event Catalog tab
// ---------------------------------------------------------------------

function lumn_ut_render_catalog_tab() {
    echo '<h2>' . esc_html__('LUMN Event Catalog', 'lumn-utilities') . '</h2>';
    echo '<p>' . esc_html__('Every event LUMN Utilities can generate, read directly from the event registry - the exact same source of truth the tracking code itself uses. Expand an event for its parameters and recommended GTM trigger.', 'lumn-utilities') . '</p>';

    $features = lumn_ut_tracking_feature_registry();

    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        $feature_label = isset($features[$event['feature']]['label']) ? $features[$event['feature']]['label'] : $event['feature'];
        $enabled = lumn_ut_tracking_feature_enabled($event['feature']);

        echo '<div class="lumn-utilites-admin-accordion lumn-ut-catalog-item">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title"><code>' . esc_html($event['name']) . '</code> ';
        echo '<span class="lumn-ut-tracking-badge">' . esc_html($event['category']) . '</span>';
        if ($enabled) {
            echo ' <span class="lumn-ut-tracking-badge lumn-ut-tracking-badge-ok">' . esc_html__('Active', 'lumn-utilities') . '</span>';
        }
        echo '</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';

        echo '<p>' . esc_html($event['description']) . '</p>';

        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>' . esc_html__('Category', 'lumn-utilities') . '</th><td>' . esc_html($event['category']) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Action', 'lumn-utilities') . '</th><td>' . esc_html($event['action']) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Requires', 'lumn-utilities') . '</th><td>' . esc_html($feature_label) . ' - ' . ($enabled ? esc_html__('currently enabled', 'lumn-utilities') : esc_html__('currently off', 'lumn-utilities')) . '</td></tr>';
        $all_params = array_merge(lumn_ut_tracking_base_event_params(), $event['params']);
        echo '<tr><th>' . esc_html__('Parameters', 'lumn-utilities') . '</th><td>' . esc_html(implode(', ', $all_params)) . '</td></tr>';
        echo '</table>';

        lumn_ut_render_gtm_recipe($key);

        echo '</div>';
        echo '</div>';
    }
}

// ---------------------------------------------------------------------
// GTM recipe (shared by the Catalog and GTM Guide tabs)
// ---------------------------------------------------------------------

function lumn_ut_render_gtm_recipe($event_key) {
    $recipe = lumn_ut_tracking_gtm_recipe($event_key);
    if (!$recipe) {
        return;
    }

    echo '<div class="lumn-ut-gtm-recipe">';
    echo '<h4>' . esc_html__('Recommended GTM Trigger', 'lumn-utilities') . '</h4>';
    echo '<table class="lumn-utilites-table">';
    echo '<tr><th>' . esc_html__('Trigger Type', 'lumn-utilities') . '</th><td>' . esc_html($recipe['trigger_type']) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Custom Event Name', 'lumn-utilities') . '</th><td><code>' . esc_html($recipe['custom_event_name']) . '</code> ' . lumn_ut_render_copy_button($recipe['custom_event_name']) . '</td></tr>';
    echo '</table>';

    if ($recipe['condition']) {
        $example = $recipe['condition']['example_values'][0];
        echo '<p><strong>' . esc_html__('Optional condition:', 'lumn-utilities') . '</strong></p>';
        echo '<p><code>' . esc_html($recipe['condition']['variable']) . '</code> ' . esc_html($recipe['condition']['operator']) . ' <code>' . esc_html($example) . '</code> ' . lumn_ut_render_copy_button($example) . '</p>';
        $rest = array_slice($recipe['condition']['example_values'], 1);
        if (!empty($rest)) {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: %s: comma-separated list of other possible values for this GTM trigger condition */
                __('Other possible values: %s', 'lumn-utilities'),
                implode(', ', $rest)
            )) . '</p>';
        }
    }

    if ($recipe['ga4_note']) {
        echo '<p><strong>' . esc_html__('Possible GA4 mapping:', 'lumn-utilities') . '</strong> ' . esc_html($recipe['ga4_note']) . '</p>';
    }

    echo '</div>';
}

function lumn_ut_render_copy_button($value) {
    return '<button type="button" class="button button-small lumn-ut-copy-btn" data-copy-value="' . esc_attr($value) . '">' . esc_html__('Copy', 'lumn-utilities') . '</button>';
}

// ---------------------------------------------------------------------
// GTM Guide tab
// ---------------------------------------------------------------------

function lumn_ut_render_gtm_tab() {
    echo '<h2>' . esc_html__('GTM Setup Guide', 'lumn-utilities') . '</h2>';

    echo '<div class="lumn-utilites-table-wrap">';
    echo '<table class="lumn-utilites-table">';
    echo '<tr><th>' . esc_html__('1. WordPress', 'lumn-utilities') . '</th><td>' . esc_html__('LUMN Utilities identifies an action and sends a standardized event to the browser data layer.', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>' . esc_html__('2. GTM', 'lumn-utilities') . '</th><td>' . esc_html__('Google Tag Manager listens for that event using a Custom Event trigger.', 'lumn-utilities') . '</td></tr>';
    echo '<tr><th>' . esc_html__('3. Tags', 'lumn-utilities') . '</th><td>' . esc_html__('GTM then decides which tags should fire.', 'lumn-utilities') . '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '<p><strong>' . esc_html__('LUMN Utilities does not directly configure GA4, Google Ads, or other analytics destinations.', 'lumn-utilities') . ' ' . esc_html__('Every recipe below is documentation only - nothing here creates or modifies anything in GTM.', 'lumn-utilities') . '</strong></p>';

    foreach (lumn_ut_tracking_event_registry() as $key => $event) {
        echo '<h3>' . esc_html($event['name']) . '</h3>';
        lumn_ut_render_gtm_recipe($key);
    }
}

// ---------------------------------------------------------------------
// Health Check tab
// ---------------------------------------------------------------------

function lumn_ut_render_health_tab() {
    echo '<h2>' . esc_html__('Tracking Health Check', 'lumn-utilities') . '</h2>';
    echo '<p>' . esc_html__('Checks LUMN\'s own configuration directly. For GTM detection and scanning for data-lumn-event attributes, it also does one lightweight fetch of this site\'s own front page - never anything sent anywhere else.', 'lumn-utilities') . '</p>';

    $ran = isset($_GET['lumn_ut_run_health_check']) && $_GET['lumn_ut_run_health_check'] === '1';
    if ($ran) {
        check_admin_referer('lumn_ut_run_health_check');
    }

    $run_url = wp_nonce_url(add_query_arg(array(
        'page' => LUMN_UT_TRACKING_DEBUGGER_PAGE_SLUG,
        'tab' => 'health',
        'lumn_ut_run_health_check' => '1',
    ), admin_url('admin.php')), 'lumn_ut_run_health_check');

    echo '<p><a class="button button-primary" href="' . esc_url($run_url) . '">' . esc_html__('Run Health Check', 'lumn-utilities') . '</a></p>';

    if (!$ran) {
        echo '<p class="description">' . esc_html__('Nothing runs automatically - click the button above to run the checks, including the one-time front-page fetch.', 'lumn-utilities') . '</p>';
        return;
    }

    $checks = lumn_ut_health_run_checks(true);
    $overall = lumn_ut_health_overall_status($checks);

    echo '<h3>' . esc_html__('Overall', 'lumn-utilities') . ': ' . lumn_ut_render_health_badge($overall) . '</h3>';

    echo '<table class="widefat striped lumn-ut-health-table"><tbody>';
    foreach ($checks as $check) {
        echo '<tr><td class="lumn-ut-health-status-cell">' . lumn_ut_render_health_badge($check['status']) . '</td><td>';
        echo '<strong>' . esc_html($check['label']) . '</strong><br />' . esc_html($check['message']);
        if (empty($check['can_verify'])) {
            echo '<br /><em>' . esc_html__('LUMN Utilities cannot fully verify this from WordPress alone.', 'lumn-utilities') . '</em>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

function lumn_ut_render_health_badge($status) {
    $map = array(
        'good' => array('✓', 'lumn-ut-health-good', __('Good', 'lumn-utilities')),
        'warning' => array('⚠', 'lumn-ut-health-warning', __('Warning', 'lumn-utilities')),
        'error' => array('✗', 'lumn-ut-health-error', __('Error', 'lumn-utilities')),
        'info' => array('•', 'lumn-ut-health-info', __('Info', 'lumn-utilities')),
    );
    $entry = isset($map[$status]) ? $map[$status] : $map['info'];
    return '<span class="lumn-ut-health-badge ' . esc_attr($entry[1]) . '">' . esc_html($entry[0] . ' ' . $entry[2]) . '</span>';
}

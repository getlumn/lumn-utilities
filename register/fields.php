<?php
namespace Lumn\Utilities;

function lumn_ut_register_utilities_fields() {
    // Register [lumn_site_name] field
    register_setting('lumn_ut_shortcode_settings', 'lumn_site_name', lumn_ut_registered_setting_args('lumn_site_name'));
    add_settings_field('lumn_site_name_field', 'Site Name', 'Lumn\Utilities\lumn_ut_site_name_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_info_section');
    // Callback function for the [lumn_site_name] field
    function lumn_ut_site_name_field_callback() {
        $lumn_site_name = get_option('lumn_site_name');
        echo '<input type="text" id="lumn_site_name" name="lumn_site_name" value="' . esc_attr($lumn_site_name) . '" placeholder="Practice Name"/>';
        lumn_ut_shortcode_hint('[lumn_site_name]');
    }

    // Register [lumn_call] field
    register_setting('lumn_ut_shortcode_settings', 'lumn_call', lumn_ut_registered_setting_args('lumn_call'));
    add_settings_field('lumn_call_field', 'Call', 'Lumn\Utilities\lumn_ut_call_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_info_section');
    // Callback function for the [lumn_call] field
    function lumn_ut_call_field_callback() {
        $lumn_call = get_option('lumn_call');
        echo '<input type="tel" id="lumn_call" name="lumn_call" value="' . esc_attr($lumn_call) . '" placeholder="555-555-5555" />';
        lumn_ut_shortcode_hint('[lumn_call]');
    }

    // Register [lumn_txt] field
    register_setting('lumn_ut_shortcode_settings', 'lumn_txt', lumn_ut_registered_setting_args('lumn_txt'));
    add_settings_field('lumn_txt_field', 'Text', 'Lumn\Utilities\lumn_ut_txt_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_info_section');
    // Callback function for the [lumn_txt] field
    function lumn_ut_txt_field_callback() {
        $lumn_txt = get_option('lumn_txt');
        echo '<input type="tel" id="lumn_txt" name="lumn_txt" value="' . esc_attr($lumn_txt) . '" placeholder="555-555-5555" />';
        lumn_ut_shortcode_hint('[lumn_txt]');
    }

    // Register [lumn_fax] field
    register_setting('lumn_ut_shortcode_settings', 'lumn_fax', lumn_ut_registered_setting_args('lumn_fax'));
    add_settings_field('lumn_fax_field', 'Fax', 'Lumn\Utilities\lumn_ut_fax_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_info_section');
    // Callback function for the [lumn_fax] field
    function lumn_ut_fax_field_callback() {
        $lumn_fax = get_option('lumn_fax');
        echo '<input type="tel" id="lumn_fax" name="lumn_fax" value="' . esc_attr($lumn_fax) . '" placeholder="555-555-5555" />';
        lumn_ut_shortcode_hint('[lumn_fax]');
    }

    // Register [lumn_email] field
    register_setting('lumn_ut_shortcode_settings', 'lumn_email', lumn_ut_registered_setting_args('lumn_email'));
    add_settings_field('lumn_email_field', 'Email', 'Lumn\Utilities\lumn_ut_email_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_info_section');
    // Callback function for the [lumn_email] field
    //
    // Once any Practice Location exists, [lumn_email] resolves through
    // that location's own 'email' field (register/locations.php,
    // lumn_ut_get_location_field()) instead of this option - with NO
    // fallback to this option if that field happens to be blank (only a
    // site with zero Practice Locations falls back here at all). So
    // editing this field would have no visible effect and could confuse
    // an admin into thinking their change didn't save, or mask a blank
    // per-location field. Grayed out (readonly, not disabled - a disabled
    // input is never submitted with the form, which would silently blank
    // this option out on every save) with an explanatory note pointing at
    // Practice Locations instead. This option's own value is left
    // untouched either way - it's exactly what a NEW site with no
    // locations yet still falls back to.
    function lumn_ut_email_field_callback() {
        $lumn_email = get_option('lumn_email');
        $has_locations = function_exists('Lumn\Utilities\lumn_ut_get_locations') && !empty(lumn_ut_get_locations());
        $readonly_attr = $has_locations ? ' readonly="readonly" class="lumn-ut-field-shadowed"' : '';
        echo '<input type="email" id="lumn_email" name="lumn_email" value="' . esc_attr($lumn_email) . '" placeholder="mail@example.com"' . $readonly_attr . ' />';
        lumn_ut_shortcode_hint('[lumn_email]');
        if ($has_locations) {
            $locations_url = admin_url('admin.php?page=lumn-ut-locations');
            echo '<p class="description">' . sprintf(
                /* translators: %s: URL to the Practice Locations admin page */
                esc_html__('Practice Locations are set up on this site, so this site-wide email is no longer used by [lumn_email]. Set each location\'s own Email field instead, under %s.', 'lumn-utilities'),
                '<a href="' . esc_url($locations_url) . '">' . esc_html__('Practice Locations', 'lumn-utilities') . '</a>'
            ) . '</p>';
        }
    }

    register_setting('lumn_ut_shortcode_settings', 'lumn_address_street', lumn_ut_registered_setting_args('lumn_address_street'));
    add_settings_field('lumn_address_street_field', 'Street Address', 'Lumn\Utilities\lumn_ut_address_street_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_address_street] field
    function lumn_ut_address_street_field_callback() {
        $lumn_address_street = get_option('lumn_address_street');
        echo '<input type="text" id="lumn_address_street" name="lumn_address_street" value="' . esc_attr($lumn_address_street) . '" placeholder="123 Elm St." />';
        lumn_ut_shortcode_hint('[lumn_address_street]');
    }

    register_setting('lumn_ut_shortcode_settings', 'lumn_address_street2', lumn_ut_registered_setting_args('lumn_address_street2'));
    add_settings_field('lumn_address_street2_field', 'Street Address Line 2', 'Lumn\Utilities\lumn_ut_address_street2_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_address_street2] field
    function lumn_ut_address_street2_field_callback() {
        $lumn_address_street2 = get_option('lumn_address_street2');
        echo '<input type="text" id="lumn_address_street2" name="lumn_address_street2" value="' . esc_attr($lumn_address_street2) . '" placeholder="Apt 4B" />';
        lumn_ut_shortcode_hint('[lumn_address_street2]');
    }

    register_setting('lumn_ut_shortcode_settings', 'lumn_address_city', lumn_ut_registered_setting_args('lumn_address_city'));
    add_settings_field('lumn_address_city_field', 'City', 'Lumn\Utilities\lumn_ut_address_city_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_address_city] field
    function lumn_ut_address_city_field_callback() {
        $lumn_address_city = get_option('lumn_address_city');
        echo '<input type="text" id="lumn_address_city" name="lumn_address_city" value="' . esc_attr($lumn_address_city) . '" placeholder="Example City" />';
        lumn_ut_shortcode_hint('[lumn_address_city]');
    }

    register_setting('lumn_ut_shortcode_settings', 'lumn_address_state', lumn_ut_registered_setting_args('lumn_address_state'));
    add_settings_field('lumn_address_state_field', 'State', 'Lumn\Utilities\lumn_ut_address_state_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_address_state] field
    function lumn_ut_address_state_field_callback() {
        $lumn_address_state = get_option('lumn_address_state');
        echo '<input type="text" id="lumn_address_state" name="lumn_address_state" value="' . esc_attr($lumn_address_state) . '" placeholder="UT" />';
        lumn_ut_shortcode_hint('[lumn_address_state]');
    }

    register_setting('lumn_ut_shortcode_settings', 'lumn_address_zip', lumn_ut_registered_setting_args('lumn_address_zip'));
    add_settings_field('lumn_address_zip_field', 'ZIP Code', 'Lumn\Utilities\lumn_ut_address_zip_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_address_zip] field
    function lumn_ut_address_zip_field_callback() {
        $lumn_address_zip = get_option('lumn_address_zip');
        echo '<input type="text" id="lumn_address_zip" name="lumn_address_zip" value="' . esc_attr($lumn_address_zip) . '" placeholder="84123" />';
        lumn_ut_shortcode_hint('[lumn_address_zip]');
    }

    // Register [lumn_map] field
    // Sanitized to a strict Google Maps embed allowlist - see
    // lumn_ut_sanitize_google_maps_embed() in register/functions.php. This is
    // the only option whose value becomes raw markup, so it gets the
    // strictest validation of anything registered here.
    register_setting('lumn_ut_shortcode_settings', 'lumn_map', lumn_ut_registered_setting_args('lumn_map'));
    add_settings_field('lumn_map_field', 'Google Maps Iframe', 'Lumn\Utilities\lumn_ut_map_field_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_practice_address_section');
    // Callback function for the [lumn_map] field
    function lumn_ut_map_field_callback() {
        $lumn_map = get_option('lumn_map');
        echo '<textarea id="lumn_map" name="lumn_map" placeholder="<iframe src=…">' . esc_textarea($lumn_map) . '</textarea>';
        lumn_ut_shortcode_hint('[lumn_map]');
    }

    $lumn_ut_days_of_week = lumn_ut_get_days_of_week();

    foreach ($lumn_ut_days_of_week as $day) {
        register_setting('lumn_ut_shortcode_settings', 'lumn_hours_' . $day, lumn_ut_registered_setting_args('lumn_hours_' . $day));
        add_settings_field('lumn_hours_' . $day . '_field', ucfirst($day) . ' Hours', function() use ($day) {
            lumn_ut_hours_field_callback($day);
        }, 'lumn_ut_shortcode_settings', 'lumn_ut_practice_hours_section');
    }

    // Callback function for each day's hours field
    function lumn_ut_hours_field_callback($day) {
        $lumn_hours = get_option('lumn_hours_' . $day);
        echo '<input type="text" id="lumn_hours_' . $day . '" name="lumn_hours_' . $day . '" value="' . esc_attr($lumn_hours) . '" placeholder="e.g., 8:00 AM - 5:00 PM" />';
        lumn_ut_shortcode_hint('[lumn_hours_' . $day . ']');
    }

    // Register the social URL fields
    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_appointments', lumn_ut_registered_setting_args('lumn_social_url_appointments'));
    add_settings_field('lumn_social_url_appointments', 'Appointments', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_appointments', 'item' => 'appointments', 'placeholder_url' => '/request-an-appointment'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_payments', lumn_ut_registered_setting_args('lumn_social_url_payments'));
    add_settings_field('lumn_social_url_payments', 'Payments', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_payments', 'item' => 'payments', 'placeholder_url' => 'https://www.example.com/'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_facebook', lumn_ut_registered_setting_args('lumn_social_url_facebook'));
    add_settings_field('lumn_social_url_facebook', 'Facebook', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_facebook', 'item' => 'facebook', 'placeholder_url' => 'https://www.facebook.com/example/'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_google', lumn_ut_registered_setting_args('lumn_social_url_google'));
    add_settings_field('lumn_social_url_google', 'Google', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_google', 'item' => 'google', 'placeholder_url' => 'https://maps.app.goo.gl/example'));

    // Dedicated Google Maps / Reviews / Write-a-Review fields (split out from
    // the general-purpose "Google" field above so each has a clear single
    // purpose). Google Maps is the one wired into automatic directions-click
    // detection - see lumn_ut_tracking_known_directions_urls() in
    // register/tracking.php. The general "Google" field above is left as-is
    // for backward compatibility with any existing [lumn_social_url
    // name="google"] usage or /lumn-social-url-google/ links.
    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_googlemaps', lumn_ut_registered_setting_args('lumn_social_url_googlemaps'));
    add_settings_field('lumn_social_url_googlemaps', 'Google Maps', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_googlemaps', 'item' => 'googlemaps', 'placeholder_url' => 'https://maps.app.goo.gl/example'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_googlereviews', lumn_ut_registered_setting_args('lumn_social_url_googlereviews'));
    add_settings_field('lumn_social_url_googlereviews', 'Google Reviews', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_googlereviews', 'item' => 'googlereviews', 'placeholder_url' => 'https://g.page/r/example/reviews'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_googlewritereview', lumn_ut_registered_setting_args('lumn_social_url_googlewritereview'));
    add_settings_field('lumn_social_url_googlewritereview', 'Write a Google Review', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_googlewritereview', 'item' => 'googlewritereview', 'placeholder_url' => 'https://g.page/r/example/review'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_instagram', lumn_ut_registered_setting_args('lumn_social_url_instagram'));
    add_settings_field('lumn_social_url_instagram', 'Instagram', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_instagram', 'item' => 'instagram', 'placeholder_url' => 'https://www.instagram.com/example/'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_linkedin', lumn_ut_registered_setting_args('lumn_social_url_linkedin'));
    add_settings_field('lumn_social_url_linkedin', 'Linkedin', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_linkedin', 'item' => 'linkedin', 'placeholder_url' => 'https://www.linkedin.com/company/example/'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_pinterest', lumn_ut_registered_setting_args('lumn_social_url_pinterest'));
    add_settings_field('lumn_social_url_pinterest', 'Pinterest', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_pinterest', 'item' => 'pinterest', 'placeholder_url' => 'https://www.pinterest.com/example/'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_threads', lumn_ut_registered_setting_args('lumn_social_url_threads'));
    add_settings_field('lumn_social_url_threads', 'Threads', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_threads', 'item' => 'threads', 'placeholder_url' => 'https://www.threads.net/@example'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_tiktok', lumn_ut_registered_setting_args('lumn_social_url_tiktok'));
    add_settings_field('lumn_social_url_tiktok', 'TikTok', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_tiktok', 'item' => 'tiktok', 'placeholder_url' => 'https://www.tiktok.com/@example'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_x', lumn_ut_registered_setting_args('lumn_social_url_x'));
    add_settings_field('lumn_social_url_x', 'X', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_x', 'item' => 'x', 'placeholder_url' => 'https://x.com/example'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_yelp', lumn_ut_registered_setting_args('lumn_social_url_yelp'));
    add_settings_field('lumn_social_url_yelp', 'Yelp', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_yelp', 'item' => 'yelp', 'placeholder_url' => 'https://www.yelp.com/biz/example'));

    register_setting('lumn_ut_shortcode_settings', 'lumn_social_url_youtube', lumn_ut_registered_setting_args('lumn_social_url_youtube'));
    add_settings_field('lumn_social_url_youtube', 'YouTube', 'Lumn\Utilities\lumn_ut_social_url_callback', 'lumn_ut_shortcode_settings', 'lumn_ut_social_section', array('option_name' => 'lumn_social_url_youtube', 'item' => 'youtube', 'placeholder_url' => 'https://www.youtube.com/@example'));

    // Callback function for each social media URL
    function lumn_ut_social_url_callback($args) {
        $option_name = $args['option_name'];
        $item = $args['item'];
        $placeholder_url = $args['placeholder_url'];
        $lumn_social_url = get_option($option_name);

        echo '<input type="text" id="' . $option_name . '" name="' . $option_name . '" value="' . esc_attr($lumn_social_url) . '" placeholder="' . $placeholder_url . '"/>';
        lumn_ut_shortcode_hint('[lumn_social_url name="' . $item . '"]');
        lumn_ut_shortcode_hint(site_url() . '/lumn-social-url-' . $item);
    }

    // Register other shortcodes fields (empty field to prevent errors when registering the section)
    // Not in the practice-data registry (register/field-registry.php) - this
    // is a Settings API placeholder with no real data, not a REST-exposed field.
    register_setting('lumn_ut_shortcode_settings', 'lumn_other_shortcodes_field', array('sanitize_callback' => 'sanitize_text_field'));
    add_settings_field('lumn_other_shortcodes_field', 'empty_field', 'Lumn\Utilities\lumn_ut_other_shortcode_field_callback', 'lumn_ut_shortcode_settings', 'other_shortcodes_section');
    function lumn_ut_other_shortcode_field_callback() {
        return;
    }
}
add_action('admin_init', 'Lumn\Utilities\lumn_ut_register_utilities_fields');

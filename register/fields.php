<?php
namespace Lumn\Utilities2;

function lumn_ut_2_register_utilites_fields() {
    // Register [lumn_site_name] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_site_name');
    add_settings_field('lumn_site_name_field', 'Site Name', 'Lumn\Utilities2\lumn_ut_2_site_name_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_info_section');
    // Callback function for the [lumn_site_name] field
    function lumn_ut_2_site_name_field_callback() {
        $lumn_site_name = get_option('lumn_site_name');
        echo '<input type="text" id="lumn_site_name" name="lumn_site_name" value="' . esc_attr($lumn_site_name) . '" placeholder="Practice Name"/>';
        echo '<div class="lumn-shortcode-hint">[lumn_site_name]</div>';
    }

    // Register [lumn_call] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_call');
    add_settings_field('lumn_call_field', 'Call', 'Lumn\Utilities2\lumn_ut_2_call_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_info_section');
    // Callback function for the [lumn_call] field
    function lumn_ut_2_call_field_callback() {
        $lumn_call = get_option('lumn_call');
        echo '<input type="tel" id="lumn_call" name="lumn_call" value="' . esc_attr($lumn_call) . '" placeholder="555-555-5555" />';
        echo '<div class="lumn-shortcode-hint">[lumn_call]</div>';
    }

    // Register [lumn_txt] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_txt');
    add_settings_field('lumn_txt_field', 'Text', 'Lumn\Utilities2\lumn_ut_2_txt_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_info_section');
    // Callback function for the [lumn_txt] field
    function lumn_ut_2_txt_field_callback() {
        $lumn_txt = get_option('lumn_txt');
        echo '<input type="tel" id="lumn_txt" name="lumn_txt" value="' . esc_attr($lumn_txt) . '" placeholder="555-555-5555" />';
        echo '<div class="lumn-shortcode-hint">[lumn_txt]</div>';
    }

    // Register [lumn_fax] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_fax');
    add_settings_field('lumn_fax_field', 'Fax', 'Lumn\Utilities2\lumn_ut_2_fax_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_info_section');
    // Callback function for the [lumn_fax] field
    function lumn_ut_2_fax_field_callback() {
        $lumn_fax = get_option('lumn_fax');
        echo '<input type="tel" id="lumn_fax" name="lumn_fax" value="' . esc_attr($lumn_fax) . '" placeholder="555-555-5555" />';
        echo '<div class="lumn-shortcode-hint">[lumn_fax]</div>';
    }

    // Register [lumn_email] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_email');
    add_settings_field('lumn_email_field', 'Email', 'Lumn\Utilities2\lumn_ut_2_email_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_info_section');
    // Callback function for the [lumn_email] field
    function lumn_ut_2_email_field_callback() {
        $lumn_email = get_option('lumn_email');
        echo '<input type="email" id="lumn_email" name="lumn_email" value="' . esc_attr($lumn_email) . '" placeholder="mail@example.com" />';
        echo '<div class="lumn-shortcode-hint">[lumn_email]</div>';
    }

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_address_street');
    add_settings_field('lumn_address_street_field', 'Street Address', 'Lumn\Utilities2\lumn_ut_2_address_street_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_address_street] field
    function lumn_ut_2_address_street_field_callback() {
        $lumn_address_street = get_option('lumn_address_street');
        echo '<input type="text" id="lumn_address_street" name="lumn_address_street" value="' . esc_attr($lumn_address_street) . '" placeholder="123 Elm St." />';
        echo '<div class="lumn-shortcode-hint">[lumn_address_street]</div>';
    }

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_address_street2');
    add_settings_field('lumn_address_street2_field', 'Street Address Line 2', 'Lumn\Utilities2\lumn_ut_2_address_street2_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_address_street2] field
    function lumn_ut_2_address_street2_field_callback() {
        $lumn_address_street2 = get_option('lumn_address_street2');
        echo '<input type="text" id="lumn_address_street2" name="lumn_address_street2" value="' . esc_attr($lumn_address_street2) . '" placeholder="Apt 4B" />';
        echo '<div class="lumn-shortcode-hint">[lumn_address_street2]</div>';
    }

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_address_city');
    add_settings_field('lumn_address_city_field', 'City', 'Lumn\Utilities2\lumn_ut_2_address_city_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_address_city] field
    function lumn_ut_2_address_city_field_callback() {
        $lumn_address_city = get_option('lumn_address_city');
        echo '<input type="text" id="lumn_address_city" name="lumn_address_city" value="' . esc_attr($lumn_address_city) . '" placeholder="Example City" />';
        echo '<div class="lumn-shortcode-hint">[lumn_address_city]</div>';
    }

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_address_state');
    add_settings_field('lumn_address_state_field', 'State', 'Lumn\Utilities2\lumn_ut_2_address_state_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_address_state] field
    function lumn_ut_2_address_state_field_callback() {
        $lumn_address_state = get_option('lumn_address_state');
        echo '<input type="text" id="lumn_address_state" name="lumn_address_state" value="' . esc_attr($lumn_address_state) . '" placeholder="UT" />';
        echo '<div class="lumn-shortcode-hint">[lumn_address_state]</div>';
    }

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_address_zip');
    add_settings_field('lumn_address_zip_field', 'ZIP Code', 'Lumn\Utilities2\lumn_ut_2_address_zip_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_address_zip] field
    function lumn_ut_2_address_zip_field_callback() {
        $lumn_address_zip = get_option('lumn_address_zip');
        echo '<input type="text" id="lumn_address_zip" name="lumn_address_zip" value="' . esc_attr($lumn_address_zip) . '" placeholder="84123" />';
        echo '<div class="lumn-shortcode-hint">[lumn_address_zip]</div>';
    }

    // Register [lumn_map] field
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_map');
    add_settings_field('lumn_map_field', 'Google Maps Iframe', 'Lumn\Utilities2\lumn_ut_2_map_field_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_address_section');
    // Callback function for the [lumn_map] field
    function lumn_ut_2_map_field_callback() {
        $lumn_map = get_option('lumn_map');
        echo '<textarea id="lumn_map" name="lumn_map" placeholder="<iframe src=…">' . esc_textarea($lumn_map) . '</textarea>';
        echo '<div class="lumn-shortcode-hint">[lumn_map]</div>';
    }

    $lumn_ut_2_days_of_week = lumn_ut_2_get_days_of_week();

    foreach ($lumn_ut_2_days_of_week as $day) {
        register_setting('lumn_ut_2_shortcode_settings', 'lumn_hours_' . $day);
        add_settings_field('lumn_hours_' . $day . '_field', ucfirst($day) . ' Hours', function() use ($day) {
            lumn_ut_2_hours_field_callback($day);
        }, 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_practice_hours_section');
    }

    // Callback function for each day's hours field
    function lumn_ut_2_hours_field_callback($day) {
        $lumn_hours = get_option('lumn_hours_' . $day);
        echo '<input type="text" id="lumn_hours_' . $day . '" name="lumn_hours_' . $day . '" value="' . esc_attr($lumn_hours) . '" placeholder="e.g., 8:00 AM - 5:00 PM" />';
        echo '<div class="lumn-shortcode-hint">[lumn_hours_' . $day . ']</div>';
    }

    // Register the social URL fields
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_appointments');
    add_settings_field('lumn_social_url_appointments', 'Appointments', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_appointments', 'item' => 'appointments', 'placeholder_url' => '/request-an-appointment'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_facebook');
    add_settings_field('lumn_social_url_facebook', 'Facebook', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_facebook', 'item' => 'facebook', 'placeholder_url' => 'https://www.facebook.com/example/'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_google');
    add_settings_field('lumn_social_url_google', 'Google', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_google', 'item' => 'google', 'placeholder_url' => 'https://maps.app.goo.gl/example'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_instagram');
    add_settings_field('lumn_social_url_instagram', 'Instagram', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_instagram', 'item' => 'instagram', 'placeholder_url' => 'https://www.instagram.com/example/'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_linkedin');
    add_settings_field('lumn_social_url_linkedin', 'Linkedin', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_linkedin', 'item' => 'linkedin', 'placeholder_url' => 'https://www.linkedin.com/company/example/'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_pinterest');
    add_settings_field('lumn_social_url_pinterest', 'Pinterest', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_pinterest', 'item' => 'pinterest', 'placeholder_url' => 'https://www.pinterest.com/example/'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_threads');
    add_settings_field('lumn_social_url_threads', 'Threads', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_threads', 'item' => 'threads', 'placeholder_url' => 'https://www.threads.net/@example'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_tiktok');
    add_settings_field('lumn_social_url_tiktok', 'TikTok', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_tiktok', 'item' => 'tiktok', 'placeholder_url' => 'https://www.tiktok.com/@example'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_x');
    add_settings_field('lumn_social_url_x', 'X', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_x', 'item' => 'x', 'placeholder_url' => 'https://x.com/example'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_yelp');
    add_settings_field('lumn_social_url_yelp', 'Yelp', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_yelp', 'item' => 'yelp', 'placeholder_url' => 'https://www.yelp.com/biz/example'));

    register_setting('lumn_ut_2_shortcode_settings', 'lumn_social_url_youtube');
    add_settings_field('lumn_social_url_youtube', 'YouTube', 'Lumn\Utilities2\lumn_ut_2_social_url_callback', 'lumn_ut_2_shortcode_settings', 'lumn_ut_2_social_section', array('option_name' => 'lumn_social_url_youtube', 'item' => 'youtube', 'placeholder_url' => 'https://www.youtube.com/@example'));

    // Callback function for each social media URL
    function lumn_ut_2_social_url_callback($args) {
        $option_name = $args['option_name'];
        $item = $args['item'];
        $placeholder_url = $args['placeholder_url'];
        $lumn_social_url = get_option($option_name);

        echo '<input type="text" id="' . $option_name . '" name="' . $option_name . '" value="' . esc_attr($lumn_social_url) . '" placeholder="' . $placeholder_url . '"/>';
        echo '<div class="lumn-shortcode-hint">[lumn_social_url name="' . $item . '"]</div>';
        echo '<div class="lumn-shortcode-hint">' . site_url() . '/lumn-social-url-' . $item . '</div>';
    }

    // Register other shortcodes fields (empty field to prevent errors when registering the section)
    register_setting('lumn_ut_2_shortcode_settings', 'lumn_other_shortcodes_field');
    add_settings_field('lumn_other_shortcodes_field', 'empty_field', 'Lumn\Utilities2\lumn_ut_2_other_shortcode_field_callback', 'lumn_ut_2_shortcode_settings', 'other_shortcodes_section');
    function lumn_ut_2_other_shortcode_field_callback() {
        return;
    }
}
add_action('admin_init', 'Lumn\Utilities2\lumn_ut_2_register_utilites_fields');
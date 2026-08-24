<?php
namespace Lumn\Utilities;

// Define the days of the week array
$lumn_ut_days_of_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

// Helper function to get the days of the week
function lumn_ut_get_days_of_week() {
    return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
}

// Helper function to convert svg/xml files and return them in base64 format
function lumn_ut_svg_to_base64 ($filepath){ 
    $fullfilepath = LUMN_UTILITIES_PLUGIN_PATH . $filepath;
    if (file_exists($fullfilepath)){
        $filetype = pathinfo($fullfilepath, PATHINFO_EXTENSION);
        if ($filetype==='svg'){
            $filetype .= '+xml';
        }
        $get_img = file_get_contents($fullfilepath);
        return 'data:image/' . $filetype . ';base64,' . base64_encode($get_img );
    }
}

// Validate and rebuild a Google Maps embed iframe from untrusted input.
// Only a src pointing at a Google Maps embed URL survives; everything else
// about the tag (dimensions, attributes) is regenerated from fixed safe
// defaults rather than passed through, so nothing besides the map location
// itself is attacker-controlled. Returns '' when the input isn't a
// recognizable Google Maps embed.
function lumn_ut_sanitize_google_maps_embed($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        return '';
    }

    if (!preg_match('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/is', $raw, $matches)) {
        return '';
    }

    $src = html_entity_decode($matches[2], ENT_QUOTES);
    $parts = wp_parse_url($src);

    if (empty($parts['host']) || empty($parts['scheme'])) {
        return '';
    }

    if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return '';
    }

    $host = strtolower($parts['host']);
    $allowed_hosts = array('www.google.com', 'google.com', 'maps.google.com');
    if (!in_array($host, $allowed_hosts, true)) {
        return '';
    }

    $path = isset($parts['path']) ? $parts['path'] : '';
    if (strpos($path, '/maps/embed') !== 0) {
        return '';
    }

    $clean_src = esc_url_raw($src);
    if ($clean_src === '') {
        return '';
    }

    return sprintf(
        '<iframe src="%s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
        esc_url($clean_src)
    );
}

// Shared branded header for the plugin's admin screens (settings + Practice
// Locations). Inlines the bundled fish mark (rather than an <img>) so its
// fill can be recolored via CSS currentColor, and pulls the version from
// index.php's header comment so it never needs updating by hand here.
function lumn_ut_render_admin_header($subtitle = '') {
    $fish_svg_path = LUMN_UTILITIES_PLUGIN_PATH . 'svgs/lumn-fish.svg';
    $fish_svg = file_exists($fish_svg_path) ? file_get_contents($fish_svg_path) : '';
    $version = lumn_ut_get_plugin_version();

    echo '<div class="lumn-ut-admin-header">';
    echo '<div class="lumn-ut-admin-header-brand">';
    echo '<span class="lumn-ut-admin-header-icon">' . $fish_svg . '</span>';
    echo '<span class="lumn-ut-admin-header-wordmark">LUMN <span>Utilities</span></span>';
    if ($version) {
        echo '<span class="lumn-ut-admin-header-version">v' . esc_html($version) . '</span>';
    }
    echo '</div>';
    if ($subtitle) {
        echo '<p class="lumn-ut-admin-header-subtitle">' . esc_html($subtitle) . '</p>';
    }
    echo '</div>';
}

// Helper function to check if a shortcode's "html_tag" attribute input value is valid
function lumn_ut_check_html_tag_value($value) {
    if($value) {
        $tags = array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div', 'strong', 'em', 'i', 'b');
        if(in_array($value, $tags)) {
            return true;
        }
        else {
            return false;
        }
    }
    else {
        return false;
    }
}

// Add a menu item to the admin dashboard
function lumn_ut_shortcode_settings_add_admin_menu() {
    add_menu_page('LUMN Utilites', 'LUMN Utilites', 'edit_pages', 'lumn-ut-shortcode-settings', '', lumn_ut_svg_to_base64('svgs/lumn-fish.svg'), 26);
    add_submenu_page('lumn-ut-shortcode-settings', 'LUMN Shortcodes', 'LUMN Shortcodes', 'edit_pages', 'lumn-ut-shortcode-settings', 'Lumn\Utilities\lumn_ut_shortcode_settings_options_page_callback');
}
add_action('admin_menu', 'Lumn\Utilities\lumn_ut_shortcode_settings_add_admin_menu');

// Define the shortcode settings options page
function lumn_ut_shortcode_settings_options_page_callback() {
    ?>
    <div class="lumn-ut-admin-settings-wrap wrap">
        <?php lumn_ut_render_admin_header('Shortcode settings for practice info, address, hours, and social links.'); ?>
        <form class="lumn-ut-admin-settings-form" method="post" action="options.php">
            <?php
                settings_errors();
                settings_fields('lumn_ut_shortcode_settings');
                do_settings_sections('lumn_ut_shortcode_settings');
            ?>
        </form>
    </div>
    <?php
}
<?php
namespace Lumn\Utilities2;

// Define the days of the week array
$lumn_ut_2_days_of_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

// Helper function to get the days of the week
function lumn_ut_2_get_days_of_week() {
    return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
}

// Helper function to convert svg/xml files and return them in base64 format
function lumn_ut_2_svg_to_base64 ($filepath){ 
    $fullfilepath = LUMN_UTILITIES_2_PLUGIN_PATH . $filepath;
    if (file_exists($fullfilepath)){
        $filetype = pathinfo($fullfilepath, PATHINFO_EXTENSION);
        if ($filetype==='svg'){
            $filetype .= '+xml';
        }
        $get_img = file_get_contents($fullfilepath);
        return 'data:image/' . $filetype . ';base64,' . base64_encode($get_img );
    }
}

// Helper function to check if a shortcode's "html_tag" attribute input value is valid
function lumn_ut_2_check_html_tag_value($value) {
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
function lumn_ut_2_shortcode_settings_add_admin_menu() {
    add_menu_page('LUMN Utilites 2', 'LUMN Utilites 2', 'edit_pages', 'lumn-ut-2-shortcode-settings', '', lumn_ut_2_svg_to_base64('svgs/lumn-fish.svg'), 26);
    add_submenu_page('lumn-ut-2-shortcode-settings', 'LUMN Shortcodes', 'LUMN Shortcodes', 'edit_pages', 'lumn-ut-2-shortcode-settings', 'Lumn\Utilities2\lumn_ut_2_shortcode_settings_options_page_callback');
}
add_action('admin_menu', 'Lumn\Utilities2\lumn_ut_2_shortcode_settings_add_admin_menu');

// Define the shortcode settings options page
function lumn_ut_2_shortcode_settings_options_page_callback() {
    ?>
    <div class="lumn-ut-2-admin-settings-wrap wrap">
        <h2><?php echo get_admin_page_title(); ?></h2>
        <form class="lumn-ut-2-admin-settings-form" method="post" action="options.php">
            <?php
                settings_errors();
                settings_fields('lumn_ut_2_shortcode_settings');
                do_settings_sections('lumn_ut_2_shortcode_settings');
            ?>
        </form>
    </div>
    <?php
}
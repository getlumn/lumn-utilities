<?php
namespace Lumn\Utilities;

function lumn_ut_register_utilites_sections() {
    // Register practice information section
    add_settings_section('lumn_ut_practice_info_section', 'General Business Information', 'Lumn\Utilities\lumn_ut_practice_info_section_callback', 'lumn_ut_shortcode_settings', array('before_section' => '<section class="%s">', 'after_section' => '</section>', 'section_class' => 'lumn-ut-2-admin-settings-section practice-info-shortcodes has-submit-button'));

    // Callback function for the practice information section
    function lumn_ut_practice_info_section_callback() {
        submit_button();
        echo '<div class="settings-container">';
        echo '<p>Enter the business information below:</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How to Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<p><strong>Each shortcode below accepts the following attributes:</strong></p>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_site_name html_tag="h2"]</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Register practice address section
    add_settings_section('lumn_ut_practice_address_section', 'Business Address', 'Lumn\Utilities\lumn_ut_practice_address_section_callback', 'lumn_ut_shortcode_settings', array('before_section' => '<section class="%s">', 'after_section' => '</section>', 'section_class' => 'lumn-ut-2-admin-settings-section practice-address-shortcodes has-submit-button'));

    // Callback function for the practice address section
    function lumn_ut_practice_address_section_callback() {
        submit_button();
        echo '<div class="settings-container">';
        echo '<p>Enter the business address info below:</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How to Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>[lumn_address]</h3>';
        echo '<p>Outputs business address in specified format.</p>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>singleline</strong></td><td>Display the hours on one line.</td><td>true, false</td><td>false</td></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_address singleline="true"]</p>';
        echo '<h3>Individual Shortcodes</h3>';
        echo '<p><strong>Each shortcode below accepts the following attributes:</strong></p>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_address_street html_tag="p"]</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Register practice hours section
    add_settings_section('lumn_ut_practice_hours_section', 'Business Hours', 'Lumn\Utilities\lumn_ut_practice_hours_section_callback', 'lumn_ut_shortcode_settings', array('before_section' => '<section class="%s">', 'after_section' => '</section>', 'section_class' => 'lumn-ut-2-admin-settings-section practice-hours-shortcodes has-submit-button'));

    // Callback function for the practice hours section
    function lumn_ut_practice_hours_section_callback() {
        submit_button();
        echo '<div class="settings-container">';
        echo '<p>Enter the business hours below:</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How to Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>[lumn_hours]</h3>';
        echo '<p>Outputs business hours in specified format.</p>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>format</strong></td><td>Display hours in different formats.</td><td>list, table, none (plain text)</td><td>none (blank)</td></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag (when not using list or table format).</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '<tr><td><strong>abbreviate</strong></td><td>Abbreviate the day names.</td><td>true, false</td><td>false</td></tr>';
        echo '<tr><td><strong>grouped</strong></td><td>Group consecutive days with the same hours.</td><td>true, false</td><td>false</td></tr>';
        echo '<tr><td><strong>hide_closed</strong></td><td>Hide days that are not set or set to "Closed".</td><td>true, false</td><td>false</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_hours format="table" abbreviate="true" grouped="true" hide_closed="true"]</p>';
        echo '<h3>Individual Shortcodes</h3>';
        echo '<p><strong>Each shortcode below accepts the following attributes:</strong></p>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_hours_monday html_tag="p"]</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Register social section
    add_settings_section('lumn_ut_social_section', 'Social Links', 'Lumn\Utilities\lumn_ut_social_section_callback', 'lumn_ut_shortcode_settings', array('before_section' => '<section class="%s">', 'after_section' => '</section>', 'section_class' => 'lumn-ut-2-admin-settings-section social-shortcodes has-submit-button'));

    // Callback function for the social section
    function lumn_ut_social_section_callback() {
        submit_button();
        echo '<div class="settings-container">';
        echo '<p>Enter the social link URLs below:</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How To Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>Shortcodes</h3>';
        echo '<p>Used to return individual social link urls.</p>';
        echo '<p><strong>[lumn_social_url name="social_name"]</strong></p>';
        echo '<h3>Redirects</h3>';
        echo '<p>Predefined redirects that can be used in places where shortcodes are not accepted.</p>';
        echo '<p><strong>/lumn-social-url-social_name</strong></p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Register other shortcodes Section
    add_settings_section('lumn_ut_other_shortcodes_section', 'Other Shortcodes', 'Lumn\Utilities\lumn_ut_other_shortcodes_section_callback', 'lumn_ut_shortcode_settings', array('before_section' => '<section class="%s">', 'after_section' => '</section>', 'section_class' => 'lumn-ut-2-admin-settings-section other-shortcodes'));

    // Callback function for the other shortcodes
    function lumn_ut_other_shortcodes_section_callback() {
        echo '<h3>[lumn_copyright]</h3>';
        echo '<p>Used to output the copyright information for the site footer.</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How To Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>[lumn_copyright]</h3>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_copyright html_tag="p"]</p>';
        echo '</div>';
        echo '</div>';

        echo '<h3>[lumn_year]</h3>';
        echo '<p>Used to output the current year.</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How To Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>[lumn_year]</h3>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>html_tag</strong></td><td>Wrap in an HTML tag.</td><td>p, h1, h2, h3, h4, h5, h6, span, div, strong, em, i, b</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_year html_tag="p"]</p>';
        echo '</div>';
        echo '</div>';

        echo '<h3>[lumn_svg]</h3>';
        echo '<p>Used to display SVG icons.</p>';
        echo '<div class="lumn-utilites-admin-accordion">';
        echo '<div class="lumn-utilites-admin-accordion-header"><span class="icon-title">How To Use</span><span class="plus">+</span><span class="minus">-</span></div>';
        echo '<div class="lumn-utilites-admin-accordion-content">';
        echo '<h3>[lumn_svg]</h3>';
        echo '<table class="lumn-utilites-table">';
        echo '<tr><th>Attribute</th><th>Description</th><th>Possible Values</th><th>Default Value</th></tr>';
        echo '<tr><td><strong>name</strong></td><td>Outputs a predefined SVG from the plugin svgs folder. See predefined icons list below.</td><td>Any valid SVG file name</td><td>none (blank)</td></tr>';
        echo '<tr><td><strong>src</strong></td><td>Outputs any SVG file by specifying the source URL.</td><td>Any valid URL</td><td>none (blank)</td></tr>';
        echo '</table>';
        echo '<p><strong>Example Usage:</strong></p>';
        echo '<p>[lumn_svg name="phone"]</p>';
        echo '<p>[lumn_svg src="/wp-content/uploads/2023/03/flower.svg"]</p>';

        echo '<h4>Predefined Icons</h4>';
        echo '<div class="predefined-icons">';

        // Output all of the icons in the svgs folder
        $directory = LUMN_UTILITIES_PLUGIN_PATH . 'svgs';

        // Get a list of all files in the directory
        $files = scandir($directory);

        // Loop through the files and output the filename without the extension
        foreach ($files as $file) {
            // Check that the file is an SVG file
            if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                $fileName = pathinfo($file, PATHINFO_FILENAME);
                // Output the filename without the extension and wrap it
                echo '<div class="icon-wrap"><span>' . $fileName . '</span>' . do_shortcode('[lumn_svg name="' . $fileName . '"]</div>');
            }
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    }
add_action('admin_init', 'Lumn\Utilities\lumn_ut_register_utilites_sections');
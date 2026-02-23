<?php
namespace Lumn\Utilities;

$lumn_ut_days_of_week = lumn_ut_get_days_of_week();

// Define the [lumn_site_name] shortcode
function lumn_ut_site_name_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_site_name = esc_html(get_option('lumn_site_name'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_site_name . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_site_name;
    }
}
add_shortcode('lumn_site_name', 'Lumn\Utilities\lumn_ut_site_name_shortcode');

// Define the [lumn_call] shortcode
function lumn_ut_call_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_call = esc_html(get_option('lumn_call'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_call . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_call;
    }
}
add_shortcode('lumn_call', 'Lumn\Utilities\lumn_ut_call_shortcode');

// Define the [lumn_txt] shortcode
function lumn_ut_txt_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_txt = esc_html(get_option('lumn_txt'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_txt . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_txt;
    }
}
add_shortcode('lumn_txt', 'Lumn\Utilities\lumn_ut_txt_shortcode');

// Define the [lumn_fax] shortcode
function lumn_ut_fax_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_fax = esc_html(get_option('lumn_fax'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_fax . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_fax;
    }
}
add_shortcode('lumn_fax', 'Lumn\Utilities\lumn_ut_fax_shortcode');

// Define the [lumn_email] shortcode
function lumn_ut_email_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_email = esc_html(get_option('lumn_email'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_email . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_email;
    }
}
add_shortcode('lumn_email', 'Lumn\Utilities\lumn_ut_email_shortcode');

// Define the [lumn_address] shortcode
function lumn_ut_address_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'singleline' => false, // Display the address on a single line
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_street = esc_html(get_option('lumn_address_street'));
    $lumn_address_street2 = esc_html(get_option('lumn_address_street2'));
    $lumn_address_city = esc_html(get_option('lumn_address_city'));
    $lumn_address_state = esc_html(get_option('lumn_address_state'));
    $lumn_address_zip = esc_html(get_option('lumn_address_zip'));

    $address_parts = array_filter(array(
        $lumn_address_street,
        $lumn_address_street2,
        $lumn_address_city . ', ' . $lumn_address_state . ' ' . $lumn_address_zip
    ));

    if ($value['singleline']) {
        $address = implode(', ', $address_parts);
        if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
            return '<' . $value['html_tag'] . '>' . $address . '</' . $value['html_tag'] . '>';
        } else {
            return $address;
        }
    } else {
        $address = implode('<br>', $address_parts);
        if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
            return '<' . $value['html_tag'] . '>' . $address . '</' . $value['html_tag'] . '>';
        } else {
            return $address;
        }
    }
}
add_shortcode('lumn_address', 'Lumn\Utilities\lumn_ut_address_shortcode');

// Define the [lumn_address_street] shortcode
function lumn_ut_address_street_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_street = esc_html(get_option('lumn_address_street'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_address_street . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_address_street;
    }
}
add_shortcode('lumn_address_street', 'Lumn\Utilities\lumn_ut_address_street_shortcode');

// Define the [lumn_address_street2] shortcode
function lumn_ut_address_street2_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_street2 = esc_html(get_option('lumn_address_street2'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_address_street2 . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_address_street2;
    }
}
add_shortcode('lumn_address_street2', 'Lumn\Utilities\lumn_ut_address_street2_shortcode');

// Define the [lumn_address_city] shortcode
function lumn_ut_address_city_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_city = esc_html(get_option('lumn_address_city'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_address_city . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_address_city;
    }
}
add_shortcode('lumn_address_city', 'Lumn\Utilities\lumn_ut_address_city_shortcode');

// Define the [lumn_address_state] shortcode
function lumn_ut_address_state_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_state = esc_html(get_option('lumn_address_state'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_address_state . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_address_state;
    }
}
add_shortcode('lumn_address_state', 'Lumn\Utilities\lumn_ut_address_state_shortcode');

// Define the [lumn_address_zip] shortcode
function lumn_ut_address_zip_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_address_zip = esc_html(get_option('lumn_address_zip'));

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $lumn_address_zip . '</' . $value['html_tag'] . '>';
    } else {
        return $lumn_address_zip;
    }
}
add_shortcode('lumn_address_zip', 'Lumn\Utilities\lumn_ut_address_zip_shortcode');

// Define the [lumn_map] shortcode
function lumn_ut_map_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_map = get_option('lumn_map');

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '><div class="lumn-google-map-container">' . $lumn_map . '</div></' . $value['html_tag'] . '>';
    } else {
        return '<div class="lumn-google-map-container">' . $lumn_map . '</div>';
    }
}
add_shortcode('lumn_map', 'Lumn\Utilities\lumn_ut_map_shortcode');

// Define the [lumn_hours] shortcode
function lumn_ut_hours_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'format' => '', // 'list', 'table', or plain text (default)
        'html_tag' => '', // Wrap in an HTML tag
        'abbreviate' => false, // Abbreviate days of the week
        'grouped' => false, // Group consecutive days with the same hours
        'hide_closed' => false // Hide days that are not set or set to 'Closed'
    ), $atts );

    // Ensure boolean values are correctly interpreted
    $value['abbreviate'] = filter_var($value['abbreviate'], FILTER_VALIDATE_BOOLEAN);
    $value['grouped'] = filter_var($value['grouped'], FILTER_VALIDATE_BOOLEAN);
    $value['hide_closed'] = filter_var($value['hide_closed'], FILTER_VALIDATE_BOOLEAN);

    $hours = [];
    $lumn_ut_days_of_week = lumn_ut_get_days_of_week();

    foreach ($lumn_ut_days_of_week as $day) {
        $hours[$day] = esc_html(get_option('lumn_hours_' . $day)) ?: 'Closed';
    }

    if ($value['grouped']) {
        $grouped_hours = [];
        $current_group = [];
        $previous_hour = '';

        foreach ($hours as $day => $hour) {
            if ($hour === $previous_hour && (!$value['hide_closed'] || $hour !== 'Closed')) {
                $current_group[] = $day;
            } else {
                if (!empty($current_group)) {
                    $grouped_hours[] = [
                        'days' => $current_group,
                        'hour' => $previous_hour
                    ];
                }
                $current_group = [$day];
                $previous_hour = $hour;
            }
        }

        if (!empty($current_group)) {
            $grouped_hours[] = [
                'days' => $current_group,
                'hour' => $previous_hour
            ];
        }

        $hours = $grouped_hours;
    }

    if ($value['hide_closed'] && !$value['grouped']) {
        $hours = array_filter($hours, function($hour) {
            return $hour !== 'Closed';
        });
    }

    if ($value['abbreviate']) {
        if ($value['grouped']) {
            foreach ($hours as &$group) {
                $group['days'] = array_map(function($day) {
                    return substr($day, 0, 3);
                }, $group['days']);
            }
        } else {
            $days_of_week = array_map(function($day) {
                return substr($day, 0, 3);
            }, array_keys($hours));
            $hours = array_combine($days_of_week, $hours);
        }
    }

    if ($value['format'] === 'table') {
        $output = '<table class="lumn-hours">';
        foreach ($hours as $day => $hour) {
            if ($value['grouped']) {
                $days = ucfirst($hour['days'][0]);
                if (count($hour['days']) > 1) {
                    $days .= ' - ' . ucfirst(end($hour['days']));
                }
                if (!$value['hide_closed'] || $hour['hour'] !== 'Closed') {
                    $output .= '<tr><td>' . $days . '</td><td>' . $hour['hour'] . '</td></tr>';
                }
            } else {
                $output .= '<tr><td>' . ucfirst($day) . '</td><td>' . $hour . '</td></tr>';
            }
        }
        $output .= '</table>';
    } elseif ($value['format'] === 'list') {
        $output = '<ul class="lumn-hours">';
        foreach ($hours as $day => $hour) {
            if ($value['grouped']) {
                $days = ucfirst($hour['days'][0]);
                if (count($hour['days']) > 1) {
                    $days .= ' - ' . ucfirst(end($hour['days']));
                }
                if (!$value['hide_closed'] || $hour['hour'] !== 'Closed') {
                    $output .= '<li>' . $days . ': ' . $hour['hour'] . '</li>';
                }
            } else {
                $output .= '<li>' . ucfirst($day) . ': ' . $hour . '</li>';
            }
        }
        $output .= '</ul>';
    } else {
        $output = '';
        foreach ($hours as $day => $hour) {
            if ($value['grouped']) {
                $days = ucfirst($hour['days'][0]);
                if (count($hour['days']) > 1) {
                    $days .= ' - ' . ucfirst(end($hour['days']));
                }
                if (!$value['hide_closed'] || $hour['hour'] !== 'Closed') {
                    $output .= $days . ': ' . $hour['hour'] . '<br>';
                }
            } else {
                $output .= ucfirst($day) . ': ' . $hour . '<br>';
            }
        }
    }

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $output . '</' . $value['html_tag'] . '>';
    } else {
        return $output;
    }
}
add_shortcode('lumn_hours', 'Lumn\Utilities\lumn_ut_hours_shortcode');

// Define shortcodes for each day's hours
foreach ($lumn_ut_days_of_week as $day) {
    add_shortcode('lumn_hours_' . $day, function($atts = '') use ($day) {
        $value = shortcode_atts(array(
            'html_tag' => '' // Wrap in an HTML tag
        ), $atts);

        $lumn_hours = esc_html(get_option('lumn_hours_' . $day));

        if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
            return '<' . $value['html_tag'] . '>' . $lumn_hours . '</' . $value['html_tag'] . '>';
        } else {
            return $lumn_hours;
        }
    });
}

// Define the [lumn_social_url] shortcode
function lumn_ut_social_url_shortcode( $atts = '' ) {
	$value = shortcode_atts( array(
        'name' => ''
    ), $atts );

    $url = get_option('lumn_social_url_' . $value['name']);
    if($url) {
        return $url;
    }
    else {
        return '';
    }
}
add_shortcode('lumn_social_url', 'Lumn\Utilities\lumn_ut_social_url_shortcode');

// Define the [lumn_svg] shortcode
function lumn_ut_icons_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'name' => '',
        'src' => ''
    ), $atts );

    if ( $value['src'] ) {
        $link = str_replace("http://" . $_SERVER['HTTP_HOST'] , "", $value['src']);
        $link = str_replace("https://" . $_SERVER['HTTP_HOST'] , "", $value['src']);
    
        if (strpos($link, 'svg') !== false) {
            $filename = ABSPATH . esc_attr($link);
    
            if (file_exists($filename)) {
                $svg_code = file_get_contents($filename);
            }
        }
    } 
    else if ( $value['name'] ) {
        $svg_path = '/svgs/';
        $svg_file = LUMN_UTILITIES_PLUGIN_PATH . $svg_path . $value['name'] . '.svg';
        if ( file_exists( $svg_file ) ) {
            $svg_code = file_get_contents( $svg_file );
        }
    }

    return isset( $svg_code ) ? $svg_code : '';
}
add_shortcode( 'lumn_svg', 'Lumn\Utilities\lumn_ut_icons_shortcode' );

// Define the [lumn_copyright] shortcode
function lumn_ut_copyright_shortcode( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $lumn_site_name = esc_html(get_option('lumn_site_name'));
    $year = date('Y');

    $content = $lumn_site_name . ' &copy; ' . $year . ' | Propelled by <a href="https://getlumn.com/" target="_blank" rel="noopener">LUMN ' . lumn_ut_icons_shortcode(array('name' => 'lumn-fish')) . '</a>';

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $content . '</' . $value['html_tag'] . '>';
    } else {
        return $content;
    }
}
add_shortcode( 'lumn_copyright', 'Lumn\Utilities\lumn_ut_copyright_shortcode' );

// Define the [lumn_year] shortcode
function lumn_ut_current_year( $atts = '' ) {
    $value = shortcode_atts( array(
        'html_tag' => '' // Wrap in an HTML tag
    ), $atts );

    $year = date('Y');

    if ($value['html_tag'] && lumn_ut_check_html_tag_value($value['html_tag'])) {
        return '<' . $value['html_tag'] . '>' . $year . '</' . $value['html_tag'] . '>';
    } else {
        return $year;
    }
}
add_shortcode('lumn_year', 'Lumn\Utilities\lumn_ut_current_year');
<?php
namespace Lumn\Utilities;

/**
 * Backward compatibility with the two predecessor plugins this one replaces
 * on existing LUMN sites:
 *  - lumn-utilities-2 already uses the exact same option names, shortcode
 *    names, and /lumn-social-url-* redirects as this plugin, so it needs
 *    nothing here - deleting it and activating this plugin is already a
 *    no-op for the front end.
 *  - LUMN-Utilites-OLD ("DCMO Utilities") predates both and used a
 *    different option schema (dcmo_* options, a single freeform address
 *    field, a single freeform hours field) and registered both dcmo_ and
 *    lumn_ shortcode aliases. This file lets that plugin be deleted too,
 *    without migrating any data: existing dcmo_* option rows are simply
 *    read as a last-resort fallback when nothing has been entered under
 *    this plugin's own schema.
 *
 * Deliberately not mentioned anywhere in the admin "How to Use" docs - this
 * exists purely to keep old content working silently, not to be a
 * documented feature of this plugin going forward.
 */

// DCMO Utilities' default social item list, used by [dcmo_social_links] /
// [lumn_social_links]. Only defined if a theme hasn't already defined it
// (DCMO Utilities used the same guard on the same hook), so an existing
// theme override still takes effect.
add_action('after_setup_theme', 'Lumn\Utilities\lumn_ut_setup_legacy_social_items');
function lumn_ut_setup_legacy_social_items() {
    if (!defined('DCMO_SOCIAL_ITEMS')) {
        define('DCMO_SOCIAL_ITEMS', array(
            'facebook', 'yelp', 'twitter', 'instagram', 'tiktok', 'youtube', 'google', 'blog',
        ));
    }
}

// Maps a location field key to the old DCMO Utilities option it replaced -
// the last fallback tier after the modern per-site lumn_* option, for a
// site that only ever ran the old plugin. 'map' isn't listed here since it
// needs sanitizing rather than a plain get_option() - see
// lumn_ut_legacy_dcmo_get() below.
function lumn_ut_legacy_dcmo_option_map() {
    return array(
        'practice_name' => 'dcmo_site_name',
        'phone' => 'dcmo_call',
        'text_phone' => 'dcmo_txt',
        'fax' => 'dcmo_fax',
        'email' => 'dcmo_email',
    );
}

// Reads a single-value DCMO Utilities option as a last-resort fallback.
// 'map' is re-validated through the same Google Maps allowlist the admin
// form applies on save, since the DCMO plugin never sanitized it - it was
// stored as raw, untrusted iframe HTML.
function lumn_ut_legacy_dcmo_get($field_key) {
    if ($field_key === 'map') {
        return lumn_ut_sanitize_google_maps_embed(get_option('dcmo_map'));
    }

    $map = lumn_ut_legacy_dcmo_option_map();
    return isset($map[$field_key]) ? get_option($map[$field_key]) : '';
}

// [dcmo_*] shortcode aliases - same handlers as their [lumn_*] equivalents.
// Most of these already resolve through the legacy fallbacks above with no
// further work; [dcmo_address], [dcmo_hours], and [dcmo_social_url] get
// dedicated fallback logic in their shared [lumn_*] handlers below, since
// DCMO Utilities stored that data in an incompatible shape (a single
// freeform field instead of structured fields).
add_shortcode('dcmo_site_name', 'Lumn\Utilities\lumn_ut_site_name_shortcode');
add_shortcode('dcmo_call', 'Lumn\Utilities\lumn_ut_call_shortcode');
add_shortcode('dcmo_txt', 'Lumn\Utilities\lumn_ut_txt_shortcode');
add_shortcode('dcmo_fax', 'Lumn\Utilities\lumn_ut_fax_shortcode');
add_shortcode('dcmo_email', 'Lumn\Utilities\lumn_ut_email_shortcode');
add_shortcode('dcmo_map', 'Lumn\Utilities\lumn_ut_map_shortcode');
add_shortcode('dcmo_address', 'Lumn\Utilities\lumn_ut_address_shortcode');
add_shortcode('dcmo_hours', 'Lumn\Utilities\lumn_ut_hours_shortcode');
add_shortcode('dcmo_social_url', 'Lumn\Utilities\lumn_ut_social_url_shortcode');
add_shortcode('dcmo_svg', 'Lumn\Utilities\lumn_ut_icons_shortcode');

// [dcmo_social_links] / [lumn_social_links] - DCMO Utilities' icon-linked
// social list (a <ul> of icon links, each shown only if its "show icon"
// checkbox was on and it had a URL). Not part of this plugin's own schema
// (no per-icon show/hide setting exists here), so this reads the DCMO
// options directly rather than the modern lumn_social_url_* options.
function lumn_ut_social_links_shortcode() {
    lumn_ut_setup_legacy_social_items();

    $has_items = false;
    $output = '<ul class="dcmo-social-links">';
    foreach (DCMO_SOCIAL_ITEMS as $item) {
        $option_icon = 'dcmo_social_icon_' . $item;
        $option_url = 'dcmo_social_url_' . $item;
        $show_icon_option_name = 'dcmo_social_show_icon_checkbox_' . $item;

        if (get_option($show_icon_option_name) != 1 || !get_option($option_url)) {
            continue;
        }

        $has_items = true;
        $icon_src = get_option($option_icon);
        $icon = $icon_src ? lumn_ut_icons_shortcode(array('src' => $icon_src)) : lumn_ut_icons_shortcode(array('name' => $item));
        $output .= '<li><a href="' . esc_url(get_option($option_url)) . '" rel="nofollow" target="_blank" aria-label="' . esc_attr($item) . '">' . $icon . '</a></li>';
    }
    $output .= '</ul>';

    return $has_items ? $output : '';
}
add_shortcode('dcmo_social_links', 'Lumn\Utilities\lumn_ut_social_links_shortcode');
add_shortcode('lumn_social_links', 'Lumn\Utilities\lumn_ut_social_links_shortcode');

<?php
namespace Lumn\Utilities;

/**
 * Reimplements the 7 custom Gutenberg blocks bundled with LUMN-Utilites-OLD
 * ("DCMO Utilities" - dcmo-blocks/), so that plugin can be deleted without
 * breaking pages built with them. The old blocks were registered through
 * the separate Genesis Custom Blocks plugin under the block name
 * "genesis-custom-blocks/dcmo-ut-*"; these are registered here as plain
 * native WordPress blocks under those exact same names (see the block.json
 * file in each blocks/dcmo-ut-* subdirectory), so existing saved content
 * parses and renders identically without requiring Genesis Custom Blocks to
 * be installed. Four of the seven (su-accordion, su-animation, su-lightbox,
 * su-lightbox-content) still depend on the separate Shortcodes Ultimate
 * plugin for their actual shortcode output ([su_accordion] etc.) - that
 * dependency already existed before and is unchanged by this file.
 *
 * Deliberately not mentioned anywhere in the admin "How to Use" docs, same
 * as register/legacy-compat.php - this exists purely to keep old content
 * working silently, not to be a documented feature of this plugin.
 */

// Genesis Custom Blocks stored "inner_blocks" control fields as a plain
// string attribute (raw block-comment markup, not real nested WP blocks -
// GCB blocks that have such a field are always self-closing/empty in
// post_content, with everything in the JSON attributes). This renders that
// string through do_blocks() so it works whether the stored string is
// already-rendered HTML (a no-op passthrough) or raw "<!-- wp:... -->"
// block markup (rendered properly) - either way, both possibilities that
// GCB could have produced are handled correctly.
function lumn_ut_render_legacy_block_content($value) {
    return is_string($value) ? do_blocks($value) : '';
}

add_action('init', 'Lumn\Utilities\lumn_ut_register_legacy_blocks');
function lumn_ut_register_legacy_blocks() {
    if (!function_exists('register_block_type')) {
        return;
    }

    $block_slugs = array(
        'dcmo-ut-hyperlink',
        'dcmo-ut-service-highlight',
        'dcmo-ut-su-accordion',
        'dcmo-ut-su-animation',
        'dcmo-ut-su-lightbox',
        'dcmo-ut-su-lightbox-content',
        'dcmo-ut-slick-slider',
    );

    foreach ($block_slugs as $slug) {
        register_block_type(LUMN_UTILITIES_PLUGIN_PATH . 'blocks/' . $slug);
    }
}

// Enqueue the (vanilla JS, no build step) editor script that gives these
// blocks an edit() experience in the block editor - see
// admin/legacy-blocks-editor.js.
add_action('enqueue_block_editor_assets', 'Lumn\Utilities\lumn_ut_legacy_blocks_editor_assets');
function lumn_ut_legacy_blocks_editor_assets() {
    $script_path = LUMN_UTILITIES_PLUGIN_PATH . 'admin/legacy-blocks-editor.js';
    wp_enqueue_script(
        'lumn-ut-legacy-blocks-editor',
        plugins_url('/admin/legacy-blocks-editor.js', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'),
        array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'),
        file_exists($script_path) ? filemtime($script_path) : null,
        true
    );
}

// DCMO Utilities' bundled Slick carousel assets, needed by
// [genesis-custom-blocks/dcmo-ut-slick-slider]. Reads the same
// 'dcmo_enable_slick' option that plugin used (default on, matching its own
// default-options behavior) rather than introducing a new setting.
add_action('after_setup_theme', 'Lumn\Utilities\lumn_ut_legacy_set_default_options');
function lumn_ut_legacy_set_default_options() {
    if (get_option('dcmo_enable_slick') === false) {
        add_option('dcmo_enable_slick', 1);
    }
}

add_action('wp_enqueue_scripts', 'Lumn\Utilities\lumn_ut_legacy_slick_assets');
function lumn_ut_legacy_slick_assets() {
    if (!get_option('dcmo_enable_slick')) {
        return;
    }
    wp_enqueue_script('slick', plugins_url('slick/slick.min.js', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'), array('jquery'), '', true);
    wp_enqueue_style('slick-css', plugins_url('slick/slick.css', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'), array());
    wp_enqueue_style('slick-css-default', plugins_url('slick/slick-theme.css', LUMN_UTILITIES_PLUGIN_PATH . 'index.php'), array());
}

// DCMO Utilities' bundled block patterns (dcmo-blocks/patterns/*.html) -
// mostly compositions of core blocks, ported as-is under the same slugs so
// any saved "pattern template" reference (wp:pattern {"slug":"dcmo-patterns/..."})
// or previously-inserted content built from them keeps resolving. Two
// (text-and-video, video-full) reference the dcmo-ut-su-lightbox block
// registered above.
add_action('init', 'Lumn\Utilities\lumn_ut_register_legacy_block_patterns');
function lumn_ut_register_legacy_block_patterns() {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    register_block_pattern_category('dcmo-patterns', array('label' => __('LUMN Patterns', 'lumn-utilities')));
    register_block_pattern_category('dcmo-pattern-templates', array('label' => __('LUMN Full Page Patterns', 'lumn-utilities')));

    $patterns = array(
        'hero' => __('LUMN Hero Pattern', 'lumn-utilities'),
        'text-and-video' => __('LUMN Text and Video', 'lumn-utilities'),
        'colored-columns' => __('LUMN Colored Columns', 'lumn-utilities'),
        'background-and-textbox' => __('LUMN Background and Textbox', 'lumn-utilities'),
        'background-and-textbox-v2' => __('LUMN Background and Textbox V2', 'lumn-utilities'),
        'centered-text' => __('LUMN Centered Text', 'lumn-utilities'),
        'centered-text-v2' => __('LUMN Centered Text V2', 'lumn-utilities'),
        'text-and-aligned-imgs' => __('LUMN Text and Aligned Images', 'lumn-utilities'),
        'video-full' => __('LUMN Full Width Video', 'lumn-utilities'),
        'icon-boxes' => __('LUMN Icon Boxes', 'lumn-utilities'),
        'text-columns' => __('LUMN Text Columns', 'lumn-utilities'),
        'text-columns-v2' => __('LUMN Text Columns V2', 'lumn-utilities'),
        'text-columns-v3' => __('LUMN Text Columns V3', 'lumn-utilities'),
        'text-columns-v4' => __('LUMN Text Columns V4', 'lumn-utilities'),
        'media-text-v1-left' => __('LUMN Media and Text Left V1', 'lumn-utilities'),
        'media-text-v1-right' => __('LUMN Media and Text Right V1', 'lumn-utilities'),
        'media-text-v2-left' => __('LUMN Media and Text Left V2', 'lumn-utilities'),
        'media-text-v2-left-white' => __('LUMN Media and Text Left V2 White', 'lumn-utilities'),
        'media-text-v2-right' => __('LUMN Media and Text Right V2', 'lumn-utilities'),
        'highlight-columns' => __('LUMN Highlight Columns', 'lumn-utilities'),
        'highlight-columns-v2' => __('LUMN Highlight Columns V2', 'lumn-utilities'),
    );

    foreach ($patterns as $slug => $title) {
        $full_slug = 'dcmo-patterns/' . $slug;
        if (\WP_Block_Patterns_Registry::get_instance()->is_registered($full_slug)) {
            continue;
        }
        $file = LUMN_UTILITIES_PLUGIN_PATH . 'patterns/' . $slug . '.html';
        if (!file_exists($file)) {
            continue;
        }
        register_block_pattern($full_slug, array(
            'title' => $title,
            'content' => file_get_contents($file),
            'categories' => array('dcmo-patterns'),
        ));
    }
}

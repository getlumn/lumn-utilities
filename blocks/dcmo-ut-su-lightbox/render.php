<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-su-lightbox]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why. Like the
// original, this depends on the separate Shortcodes Ultimate plugin (for
// [su_lightbox]) being active - that was already true before, nothing
// changes here.
//
// 'src' is intentionally not esc_url()'d - for the 'inline' lightbox type it
// holds a CSS selector (e.g. "#my-popup"), not a URL, exactly as the
// original field's help text describes.
$type = isset($attributes['type']) ? $attributes['type'] : '';
$src = isset($attributes['src']) ? $attributes['src'] : '';
$class_name = isset($attributes['className']) ? $attributes['className'] : '';
$inner_content = lumn_ut_legacy_inner_content($content, isset($attributes['inner-content']) ? $attributes['inner-content'] : '');

$shortcode = '[su_lightbox type="' . esc_attr($type) . '" src="' . esc_attr($src) . '" class="' . esc_attr($class_name) . ' dcmo-ut-su-lightbox dcmo-ut-block"]'
    . $inner_content
    . '[/su_lightbox]';

echo do_shortcode($shortcode);

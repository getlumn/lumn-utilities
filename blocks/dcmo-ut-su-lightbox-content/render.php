<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-su-lightbox-content]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why. Like the
// original, this depends on the separate Shortcodes Ultimate plugin (for
// [su_lightbox_content]) being active - that was already true before,
// nothing changes here.
$id = isset($attributes['id']) ? $attributes['id'] : '';
$class_name = isset($attributes['className']) ? $attributes['className'] : '';
$lightbox_content = lumn_ut_legacy_inner_content($content, isset($attributes['content']) ? $attributes['content'] : '');

$shortcode = '[su_lightbox_content id="' . esc_attr($id) . '" class="dcmo-ut-su-lightbox-content dcmo-ut-block ' . esc_attr($class_name) . '"]'
    . $lightbox_content
    . '[/su_lightbox_content]';

echo do_shortcode($shortcode);

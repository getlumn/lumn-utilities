<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-su-animation]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why. Like the
// original, this depends on the separate Shortcodes Ultimate plugin (for
// [su_animate]) being active - that was already true before, nothing
// changes here.
$animation = isset($attributes['animation']) ? $attributes['animation'] : '';
$duration = isset($attributes['duration']) ? (int) $attributes['duration'] : 1;
$delay = isset($attributes['delay']) ? (int) $attributes['delay'] : 0;
$inline = isset($attributes['inline']) ? $attributes['inline'] : 'no';
$class = isset($attributes['class']) ? $attributes['class'] : '';
$inner_content = isset($attributes['inner-content']) ? $attributes['inner-content'] : '';

$shortcode = '[su_animate type="' . esc_attr($animation) . '" duration="' . esc_attr($duration) . '" delay="' . esc_attr($delay) . '" inline="' . esc_attr($inline) . '" class="dcmo-ut-su-animation dcmo-ut-block ' . esc_attr($class) . '"]'
    . lumn_ut_render_legacy_block_content($inner_content)
    . '[/su_animate]';

echo do_shortcode($shortcode);

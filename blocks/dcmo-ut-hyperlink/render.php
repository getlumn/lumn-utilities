<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-hyperlink]
// block.php template (dcmo-blocks/blocks/dcmo-ut-hyperlink/block.php), kept
// backward compatible with content saved by that older plugin - see
// register/legacy-blocks.php for how/why.
$link = isset($attributes['link']) ? $attributes['link'] : '';
$link_target = isset($attributes['link-target']) ? $attributes['link-target'] : '_self';
$class_name = isset($attributes['className']) ? $attributes['className'] : '';
$inner_content = lumn_ut_legacy_inner_content($content, isset($attributes['inner-content']) ? $attributes['inner-content'] : '');

printf(
    '<a href="%s" target="%s" class="%s dcmo-ut-hyperlink dcmo-ut-block">%s</a>',
    esc_url($link),
    esc_attr($link_target),
    esc_attr($class_name),
    $inner_content
);

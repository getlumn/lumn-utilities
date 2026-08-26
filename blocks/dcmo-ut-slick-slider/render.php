<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-slick-slider]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why (including the
// bundled slick/ assets this depends on, enqueued from that file). Slider
// settings are intentionally echoed as raw JS into the slick() call below,
// same as the original - this is an admin-authored settings field, not
// user input.
$block_name = 'dcmo-ut-slick';
$block_suffix = wp_rand(1000, 9999);
$block_id = $block_name . '-' . $block_suffix;

$slick_slide = lumn_ut_legacy_inner_content($content, isset($attributes['slick-slide']) ? $attributes['slick-slide'] : '');
$slider_class = isset($attributes['slider-class']) ? $attributes['slider-class'] : '';
$slider_settings = isset($attributes['slider-settings']) ? $attributes['slider-settings'] : '';
$left_arrow = isset($attributes['left-arrow']) ? (int) $attributes['left-arrow'] : 0;
$right_arrow = isset($attributes['right-arrow']) ? (int) $attributes['right-arrow'] : 0;
$arrow_alignment = isset($attributes['arrow-alignment']) ? $attributes['arrow-alignment'] : 'dcmo-slick-block-arrows-middle';
$arrow_topbottom_distance = isset($attributes['arrow-topbottom-distance']) ? (int) $attributes['arrow-topbottom-distance'] : 0;
$arrow_topbottom_distance_unit = isset($attributes['arrow-topbottom-distance-unit']) ? $attributes['arrow-topbottom-distance-unit'] : 'px';
$arrow_edge_distance = isset($attributes['arrow-edge-distance']) ? (int) $attributes['arrow-edge-distance'] : 0;
$arrow_edge_distance_unit = isset($attributes['arrow-edge-distance-unit']) ? $attributes['arrow-edge-distance-unit'] : 'px';
$slider_leftright_margin = isset($attributes['slider-leftright-margin']) ? (int) $attributes['slider-leftright-margin'] : 0;
$slider_leftright_margin_unit = isset($attributes['slider-leftright-margin-unit']) ? $attributes['slider-leftright-margin-unit'] : 'px';
$class_name = isset($attributes['className']) ? $attributes['className'] : '';

$left_arrow_url = $left_arrow ? wp_get_attachment_image_url($left_arrow, 'full') : '';
$left_arrow_meta = $left_arrow ? wp_get_attachment_metadata($left_arrow) : array();
$right_arrow_url = $right_arrow ? wp_get_attachment_image_url($right_arrow, 'full') : '';
$right_arrow_meta = $right_arrow ? wp_get_attachment_metadata($right_arrow) : array();
?>

<div id="<?php echo esc_attr($block_id); ?>" class="genesis-custom-block dcmo-ut-block <?php echo esc_attr($block_name . ' ' . $block_id); ?> <?php echo esc_attr($arrow_alignment); ?> <?php echo esc_attr($class_name); ?>">
    <div class="dcmo-slick-block-slider <?php echo esc_attr($slider_class); ?>">
        <?php echo $slick_slide; ?>
    </div>
</div>

<style type="text/css">
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-top .dcmo-slick-block-slider,
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-middle .dcmo-slick-block-slider,
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-bottom .dcmo-slick-block-slider
    {
        <?php echo $slider_leftright_margin ? 'margin-left: ' . $slider_leftright_margin . $slider_leftright_margin_unit . ';' : ''; ?>
        <?php echo $slider_leftright_margin ? 'margin-right: ' . $slider_leftright_margin . $slider_leftright_margin_unit . ';' : ''; ?>
    }
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-top .dcmo-slick-block-slider {
        margin-top: 30px;
        <?php echo $arrow_topbottom_distance ? 'margin-top: ' . $arrow_topbottom_distance . $arrow_topbottom_distance_unit . ';' : ''; ?>
    }
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-top .dcmo-slick-block-slider .slick-prev,
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-top .dcmo-slick-block-slider .slick-next
    {
        top: -30px;
        <?php echo $arrow_topbottom_distance ? 'top: -' . $arrow_topbottom_distance . $arrow_topbottom_distance_unit . ';' : ''; ?>
        transform: none;
    }
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-bottom .dcmo-slick-block-slider {
        margin-bottom: 30px;
        <?php echo $arrow_topbottom_distance ? 'margin-bottom: ' . $arrow_topbottom_distance . $arrow_topbottom_distance_unit . ';' : ''; ?>
    }
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-bottom .dcmo-slick-block-slider .slick-prev,
    .<?php echo esc_html($block_id); ?>.dcmo-slick-block-arrows-bottom .dcmo-slick-block-slider .slick-next
    {
        top: auto;
        bottom: -30px;
        <?php echo $arrow_topbottom_distance ? 'bottom: -' . $arrow_topbottom_distance . $arrow_topbottom_distance_unit . ';' : ''; ?>
        transform: none;
    }
    .<?php echo esc_html($block_id); ?> .dcmo-slick-block-slider .slick-prev {
        <?php if ($left_arrow) { ?>
            width: auto;
            height: auto;
        <?php } ?>
        <?php echo $arrow_edge_distance ? 'left: ' . $arrow_edge_distance . $arrow_edge_distance_unit . ';' : ''; ?>
        z-index: 1;
    }
    .<?php echo esc_html($block_id); ?> .dcmo-slick-block-slider .slick-next {
        <?php if ($right_arrow) { ?>
            width: auto;
            height: auto;
        <?php } ?>
        <?php echo $arrow_edge_distance ? 'right: ' . $arrow_edge_distance . $arrow_edge_distance_unit . ';' : ''; ?>
        z-index: 1;
    }
    .<?php echo esc_html($block_id); ?> .dcmo-slick-block-slider .slick-prev::before {
        <?php if ($left_arrow && $left_arrow_url) { ?>
            background-image: url(<?php echo esc_url($left_arrow_url); ?>);
            <?php echo !empty($left_arrow_meta['width']) ? 'width: ' . (int) $left_arrow_meta['width'] . 'px;' : ''; ?>
            <?php echo !empty($left_arrow_meta['height']) ? 'height: ' . (int) $left_arrow_meta['height'] . 'px;' : ''; ?>
            content: '';
            display: block;
            background-position: center;
            background-size: contain;
            background-repeat: no-repeat;
        <?php } else { ?>
            color: #000;
        <?php } ?>
    }
    .<?php echo esc_html($block_id); ?> .dcmo-slick-block-slider .slick-next::before {
        <?php if ($right_arrow && $right_arrow_url) { ?>
            background-image: url(<?php echo esc_url($right_arrow_url); ?>);
            <?php echo !empty($right_arrow_meta['width']) ? 'width: ' . (int) $right_arrow_meta['width'] . 'px;' : ''; ?>
            <?php echo !empty($right_arrow_meta['height']) ? 'height: ' . (int) $right_arrow_meta['height'] . 'px;' : ''; ?>
            content: '';
            display: block;
            background-position: center;
            background-size: contain;
            background-repeat: no-repeat;
        <?php } else { ?>
            color: #000;
        <?php } ?>
    }
</style>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $("#<?php echo esc_js($block_id); ?> .dcmo-slick-block-slider").slick({
            <?php echo $slider_settings; ?>
        });
    });
</script>

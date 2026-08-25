<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-service-highlight]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why.
$block_name = 'dcmo-ut-service-highlight';
$block_suffix = wp_rand(1000, 9999);
$block_id = $block_name . '-' . $block_suffix;

$custom_class = isset($attributes['className']) ? $attributes['className'] : '';
$text_color = isset($attributes['text-color']) ? $attributes['text-color'] : '';
$heading_color = isset($attributes['heading-color']) ? $attributes['heading-color'] : '';
$heading_color_hover = isset($attributes['heading-color-hover']) ? $attributes['heading-color-hover'] : '';
$service_highlight_height = isset($attributes['service-highlight-height']) ? (int) $attributes['service-highlight-height'] : 0;
$service_highlight_border_radius = isset($attributes['border-radius']) ? (int) $attributes['border-radius'] : 0;
$background_image = isset($attributes['background-image']) ? (int) $attributes['background-image'] : 0;
$service_text_overlay = isset($attributes['service-text-overlay']) ? $attributes['service-text-overlay'] : '';
$service_text_overlay_hover = isset($attributes['service-text-overlay-hover']) ? $attributes['service-text-overlay-hover'] : '';
$service_heading = isset($attributes['service-heading']) ? $attributes['service-heading'] : '';
$service_hover_content = isset($attributes['service-hover-content']) ? $attributes['service-hover-content'] : '';
?>
<div id="<?php echo esc_attr($block_id); ?>" class="dcmo-ut-block <?php echo esc_attr($block_name . ' ' . $block_id . ' ' . $custom_class); ?>" style="
<?php echo $service_highlight_height ? 'min-height: ' . $service_highlight_height . 'px;' : ''; ?>
<?php echo $service_highlight_border_radius ? 'border-radius: ' . $service_highlight_border_radius . 'px;' : ''; ?>
<?php echo $background_image ? 'background-image: url(' . esc_url(wp_get_attachment_url($background_image)) . ');' : ''; ?>
">
    <div class="dcmo-service-text">
        <h3 class="dcmo-service-heading"><?php echo esc_html($service_heading); ?></h3>
        <div class="dcmo-service-text-expand" style="
            <?php echo $text_color ? 'color: ' . esc_html($text_color) . ';' : ''; ?>
        ">
            <?php echo lumn_ut_render_legacy_block_content($service_hover_content); ?>
        </div>
    </div>
</div>

<style type="text/css">
    .<?php echo esc_html($block_id); ?> .dcmo-service-text {
        <?php echo $service_text_overlay ? 'background-color: ' . esc_html($service_text_overlay) . ';' : ''; ?>
    }
    .<?php echo esc_html($block_id); ?>:hover .dcmo-service-text,
    .<?php echo esc_html($block_id); ?>:focus .dcmo-service-text
    {
        <?php echo $service_text_overlay_hover ? 'background-color: ' . esc_html($service_text_overlay_hover) . ';' : 'background-color: transparent;'; ?>
    }
    .<?php echo esc_html($block_id); ?> .dcmo-service-text h3 {
        <?php echo $heading_color ? 'color: ' . esc_html($heading_color) . ';' : ''; ?>
    }
    .<?php echo esc_html($block_id); ?>:hover .dcmo-service-text h3,
    .<?php echo esc_html($block_id); ?>:focus .dcmo-service-text h3
    {
        <?php echo $heading_color_hover ? 'color: ' . esc_html($heading_color_hover) . ';' : ''; ?>
    }
</style>

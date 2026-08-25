<?php
namespace Lumn\Utilities;

// Reimplementation of DCMO Utilities' [genesis-custom-blocks/dcmo-ut-su-accordion]
// block.php template, kept backward compatible with content saved by that
// older plugin - see register/legacy-blocks.php for how/why. Like the
// original, this depends on the separate Shortcodes Ultimate plugin (for
// [su_accordion]/[su_spoiler]) being active - that was already true before,
// nothing changes here.
$style = isset($attributes['style']) ? $attributes['style'] : 'default';
$icon = isset($attributes['icon']) ? $attributes['icon'] : 'plus';
$rows = isset($attributes['spoiler']) && is_array($attributes['spoiler']) ? $attributes['spoiler'] : array();

$shortcode = '[su_accordion]';
foreach ($rows as $row) {
    $title = isset($row['title']) ? wp_kses_post($row['title']) : '';
    $content = isset($row['content']) ? wp_kses_post($row['content']) : '';
    $shortcode .= '[su_spoiler title="' . $title . '" style="' . esc_attr($style) . '" icon="' . esc_attr($icon) . '"]' . $content . '[/su_spoiler]';
}
$shortcode .= '[/su_accordion]';
?>
<div class="dcmo-ut-su-accordion dcmo-ut-block">
    <div class="dcmo-su-accordion__container">
        <?php echo do_shortcode($shortcode); ?>
    </div>
</div>

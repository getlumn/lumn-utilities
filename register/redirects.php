<?php
namespace Lumn\Utilities;

/**
 * Handles /lumn-social-url-{name} redirects, e.g. /lumn-social-url-payments/.
 * An optional trailing path segment selects a per-location override, e.g.
 * /lumn-social-url-appointments/downtown/ (accepts a location slug or numeric
 * ID, same as the `location` shortcode attribute). Falls back to the
 * site-wide lumn_social_url_{name} option whenever there's no trailing
 * segment, the location doesn't resolve, or it has no override set for that
 * link - so this never produces a broken/empty redirect that a site-level
 * link would otherwise have served.
 */
function lumn_ut_social_url_redirects() {
	$current_url = $_SERVER['REQUEST_URI'];

	foreach (lumn_ut_social_url_names() as $name) {
		$path = 'lumn-social-url-' . $name;
		$pos = strpos($current_url, $path);
		if ($pos === false) {
			continue;
		}

		$after = substr($current_url, $pos + strlen($path));
		$after = strtok($after, '?'); // drop any query string
		$location_ref = trim($after, "/ \t\n\r\0\x0B");

		$target_url = '';
		if ($location_ref !== '') {
			$location = lumn_ut_resolve_location($location_ref);
			$override_key = $name . '_url';
			if ($location && !empty($location[$override_key])) {
				$target_url = $location[$override_key];
			}
		}

		if ($target_url === '') {
			$target_url = get_option('lumn_social_url_' . $name);
		}

		if ($target_url) {
			wp_redirect($target_url, 301);
			exit;
		}
	}
}

// Hook into template_redirect
add_action('template_redirect', 'Lumn\Utilities\lumn_ut_social_url_redirects');
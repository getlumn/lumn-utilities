<?php
namespace Lumn\Utilities;

function lumn_ut_social_url_redirects() {
	// Get the current URL path
	$current_url = $_SERVER['REQUEST_URI'];

	// Define the redirects
	$redirects = array(
		'lumn-social-url-payments' => get_option('lumn_social_url_payments'),
		'lumn-social-url-appointments' => get_option('lumn_social_url_appointments'),
		'lumn-social-url-facebook' => get_option('lumn_social_url_facebook'),
		'lumn-social-url-google' => get_option('lumn_social_url_google'),
		'lumn-social-url-instagram' => get_option('lumn_social_url_instagram'),
		'lumn-social-url-linkedin' => get_option('lumn_social_url_linkedin'),
		'lumn-social-url-pinterest' => get_option('lumn_social_url_pinterest'),
		'lumn-social-url-threads' => get_option('lumn_social_url_threads'),
		'lumn-social-url-tiktok' => get_option('lumn_social_url_tiktok'),
		'lumn-social-url-x' => get_option('lumn_social_url_x'),
		'lumn-social-url-yelp' => get_option('lumn_social_url_yelp'),
		'lumn-social-url-youtube' => get_option('lumn_social_url_youtube')
	);

	// Check if the current URL matches any of the redirects
	foreach ($redirects as $path => $target_url) {
		if (strpos($current_url, $path) !== false) {
			// Perform the redirect
			wp_redirect($target_url, 301);
			exit;
		}
	}
}

// Hook into template_redirect
add_action('template_redirect', 'Lumn\Utilities\lumn_ut_social_url_redirects');
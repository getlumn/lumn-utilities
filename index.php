<?php
/*
Plugin Name: LUMN Utilities
Plugin URI: https://getlumn.com
Description: A set of custom shortcodes and tools for LUMN sites.
Version: 4.7.1
Author: LUMN
Author URI: https://getlumn.com
License: GPL2
*/
namespace Lumn\Utilities;

// Define the plugin path
define( 'LUMN_UTILITIES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Register Functions
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/functions.php');

// Register Sections
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/sections.php');

// Register the practice-data field registry (single source of truth shared
// by the settings form and the REST API - must load before both)
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/field-registry.php');

// Register Fields
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/fields.php');

// Register Shortcodes
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/shortcodes.php');

// Backward compatibility with predecessor plugins (LUMN-Utilites-OLD,
// lumn-utilities-2) - see register/legacy-compat.php for what this covers.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/legacy-compat.php');

// Reimplements LUMN-Utilites-OLD's custom Gutenberg blocks natively - see
// register/legacy-blocks.php.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/legacy-blocks.php');

// Register Redirects
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/redirects.php');

// Register Settings
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/settings.php');

// Register Practice Locations
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'admin/locations-page.php');
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/locations.php');

// Register the LUMN Tracking / SEO Tools foundation - a feature-flag API
// and safe data-layer abstraction that is entirely opt-in (master switch
// defaults to OFF; see docs/TRACKING.md).
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/tracking-registry.php');
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'admin/tracking-page.php');
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/tracking.php');

// Provider-agnostic LUMN Form Tracking (Gravity Forms, Formidable Forms)
// - loads only its own hooks for whichever provider is actually
// installed/active; see register/form-tracking.php.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/form-tracking.php');

// LUMN Engagement Tracking - configuration for automatic download/
// external-link/CTA classification (downloads, external links, video,
// and automatic appointment-CTA classification are otherwise handled
// generically by the Feature Toggles already registered above); see
// register/engagement-tracking.php.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/engagement-tracking.php');

// Central tracking configuration model, dashboard support, and
// administration tooling (per-event overrides, global URL exclusions,
// reset, export/import, presets) - Step 6; see register/tracking-config.php.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/tracking-config.php');

// Tracking Debugger, Event Catalog, Health Checker, and GTM Guide - an
// admin-only diagnostic layer on top of the tracking system above. The
// front-end debug overlay only ever loads for an authorized, explicitly
// activated administrator; see register/tracking-debugger.php.
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'admin/tracking-debugger-page.php');
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/tracking-debugger.php');

// Register the lumn/v1 REST API
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/rest.php');
register_activation_hook(__FILE__, 'Lumn\Utilities\lumn_ut_rest_ensure_capability');

// Enqueue admin scripts and styles.
// Versioned by filemtime() rather than left blank: with no $ver, WP falls
// back to the current WP core version as the cache-busting query string,
// which doesn't change between plugin deploys - so browsers (and any
// HTTP-layer/CDN cache) keep serving a stale copy of these assets across
// every update until WP core itself is upgraded. filemtime() changes on
// every deploy, so it busts the cache every time these files change.
function lumn_ut_admin_scripts() {
    $styles_path = LUMN_UTILITIES_PLUGIN_PATH . 'admin/admin-styles.css';
    $scripts_path = LUMN_UTILITIES_PLUGIN_PATH . 'admin/admin-scripts.js';
    wp_enqueue_style( 'lumn-ut-admin-styles', plugins_url( '/admin/admin-styles.css' , __FILE__ ), array(), file_exists( $styles_path ) ? filemtime( $styles_path ) : null );
    wp_enqueue_script( 'lumn-ut-admin-scripts', plugins_url( '/admin/admin-scripts.js' , __FILE__ ), array( 'jquery' ), file_exists( $scripts_path ) ? filemtime( $scripts_path ) : null );
}
add_action( 'admin_enqueue_scripts', 'Lumn\Utilities\lumn_ut_admin_scripts' );

// Public facing styles
function lumn_ut_public_scripts() {
    $public_styles_path = LUMN_UTILITIES_PLUGIN_PATH . 'styles.css';
    wp_enqueue_style( 'lumn-ut-styles', plugins_url( 'styles.css' , __FILE__ ), array(), file_exists( $public_styles_path ) ? filemtime( $public_styles_path ) : null );
}
add_action( 'wp_enqueue_scripts', 'Lumn\Utilities\lumn_ut_public_scripts' );

// Include the Plugin Update Checker library
require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/getlumn/lumn-utilities/',
    __FILE__,
    'lumn-utilities'
);

// Optional: use main branch for updates
$updateChecker->setBranch('main');

<?php
/*
Plugin Name: LUMN Utilites
Plugin URI: https://getlumn.com
Description: A set of custom shortcodes and tools for LUMN sites.
Version: 3.2.2
Author: LUMN
Author URI: https://getlumn.com
License: GPL2
*/
namespace Lumn\Utilities;

// Define the plugin path
define( 'LUMN_UTILITIES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Maintenance module defaults — used automatically on every install.
// Per-site overrides can be saved via Maintenance > Google Sheets Integration Settings.
define( 'LUMN_MAINTENANCE_WEBHOOK_URL', 'https://script.google.com/a/macros/getlumn.com/s/AKfycbz-NuesdjceuFUPlEp-2d6d9M5yk3xwno056OmVn_Edf886m7qYMm-66zUTCYCUUGtw/exec' );
define( 'LUMN_MAINTENANCE_API_KEY',     '0e21555311a8da05a81d894ebee8258e75aa522fcfbcbc28920d86bd5bf25bbe' );

// Register Functions
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/functions.php');

// Register Sections
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/sections.php');

// Register Fields
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'register/fields.php');

// Register Shortcodes
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/shortcodes.php');

// Register Redirects
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/redirects.php');

// Register Settings
require_once(LUMN_UTILITIES_PLUGIN_PATH. 'register/settings.php');

// Maintenance Module
require_once(LUMN_UTILITIES_PLUGIN_PATH . 'includes/maintenance/maintenance.php');

// Enqueue admin scripts and styles
function lumn_ut_admin_scripts() {
    wp_enqueue_style( 'lumn-ut-admin-styles', plugins_url( '/admin/admin-styles.css' , __FILE__ ));
    wp_enqueue_script( 'lumn-ut-admin-scripts', plugins_url( '/admin/admin-scripts.js' , __FILE__ ), array( 'jquery' ) );
}
add_action( 'admin_enqueue_scripts', 'Lumn\Utilities\lumn_ut_admin_scripts' );

// Public facing styles
function lumn_ut_public_scripts() {
    wp_enqueue_style( 'lumn-ut-styles', plugins_url( 'styles.css' , __FILE__ ));
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

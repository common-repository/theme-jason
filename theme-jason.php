<?php
/**
 * Plugin Name: Theme Jason
 * Text Domain: theme-jason
 * Domain Path: /languages
 * Plugin URI: https://themejason.com
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Description: The secret sauce to using all the fun styles on themejason.com.
 * Requires PHP: 7.0
 * Requires At Least: 5.9
 * Version: 1.0.4
 * Text Domain: theme-jason
 * Domain Path: /languages
 *
 * @package ThemeJason
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'THEME_JASON_DIRECTORY_ROOT', __DIR__ );
define( 'THEME_JASON_DIRECTORY_URL', plugin_dir_url( __FILE__ ) );
define( 'THEME_JASON_PLUGIN_VERSION', '1.0.4' );

/**
 * Inits the Theme Jason plugin.
 *
 * @return void
 */
function theme_jason_init() {
	require_once THEME_JASON_DIRECTORY_ROOT . '/classes/admin/Admin.php';
	require_once THEME_JASON_DIRECTORY_ROOT . '/classes/front/Front.php';
	new ThemeJason\Classes\Admin\Admin();
	new ThemeJason\Classes\Front\Front();
}
theme_jason_init();


/**
 * Loads the plugin text domain for translation.
 *
 * @return void
 */
function theme_jason_load_plugin_textdomain() {
	load_plugin_textdomain(
		'theme-jason',
		false,
		dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
	);
}
add_action( 'plugins_loaded', 'theme_jason_load_plugin_textdomain' );

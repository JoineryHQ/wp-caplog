<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://joineryhq.com
 * @since             1.0.0
 * @package           Caplog
 *
 * @wordpress-plugin
 * Plugin Name:       Capabilities Logger
 * Plugin URI:        https://github.com/JoineryHQ/wp-caplog/tree/v0.0.2
 * Description:       Log all changes to WordPress role capabilities
 * Version:           0.0.2
 * Author:            Allen Shaw
 * Author URI:        https://joineryhq.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       caplog
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-plugin.php';
require plugin_dir_path(__FILE__) . 'includes/class-logViewer.php';
require plugin_dir_path(__FILE__) . 'includes/class-util.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_caplog() {

	$plugin = new CaplogPlugin();
	$plugin->run();

}
run_caplog();

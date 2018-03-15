<?php
/**
 * Plugin Name:     Lock Your Updates Plugins/Themes Manager
 * Plugin URI:      https://wordpress.org/plugins/lock-your-updates/
 * Description:     Allows you to lock your plugins and themes from being updated and keep notes on why they're being locked.
 * Version:         1.0
 * Author:          Rachel Cherry
 * Author URI:      https://bamadesigner.com
 * Text Domain:     lock-your-updates
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:     /languages
 * GitHub URI:      https://github.com/bamadesigner/lock-your-updates
 *
 * @package         Lock_Your_Updates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/
require_once( plugin_dir_path( __FILE__ ) . 'public/class-lock-your-updates.php' );

// Will load instance of the plugin.
add_action( 'plugins_loaded', array( 'Lock_Your_Updates', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/
if ( is_admin() ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/class-lock-your-updates-admin.php' );
	add_action( 'plugins_loaded', array( 'Lock_Your_Updates_Admin', 'get_instance' ) );

}

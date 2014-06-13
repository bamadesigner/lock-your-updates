<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Lock_Your_Updates
 * @author    Rachel Carden <contactwpdreamer@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wpdreamer.com
 * @copyright 2014 Rachel Carden
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// The options this plugin uses
$lyu_options = array(
	'lock_your_updates_locked_plugins',
	'lock_your_updates_locked_themes',
	'lock_your_updates_plugins_notes',
	'lock_your_updates_themes_notes',
	);

if ( is_multisite() ) {

	// Delete site options
	foreach( $lyu_options as $option_name ) {
		delete_site_option( $option_name );
	}

	// Get blogs data to delete options on each site
	if ( $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A ) ) {

		foreach ( $blogs as $blog ) {
			
			switch_to_blog( $blog[ 'blog_id' ] );
			
			// Delete options
			foreach( $lyu_options as $option_name ) {
				delete_option( $option_name );
			}
			
			restore_current_blog();
			
		}
		
	}

} else {

	// Delete options
	foreach( $lyu_options as $option_name ) {
		delete_option( $option_name );
	}
			
}
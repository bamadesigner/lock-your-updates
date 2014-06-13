<?php

/**
 * @package   Lock_Your_Updates
 * @author    Rachel Carden <contactwpdreamer@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wpdreamer.com
 * @copyright 2014 Rachel Carden
 */

/**
 * @package Lock_Your_Updates
 * @author  Rachel Carden <contactwpdreamer@gmail.com>
 */
class Lock_Your_Updates {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since 1.0
	 * @var string
	 */
	const VERSION = '1.0';

	/**
	 * Unique identifiers for the plugin.
	 *
	 * The plugin slug is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since 1.0
	 * @var string
	 */
	public $plugin_name = 'Lock Your Updates Plugins/Themes Manager';
	public $plugin_slug = 'lock-your-updates';
	public $plugin_basename = NULL;
	
	/**
	 * Is this plugin network active?
	 *
	 * @since 1.0
	 * @var boolean
	 */
	public $is_network_active = false;
	
	/**
	 * Instance of this class.
	 *
	 * @since 1.0
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since 1.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		
		// Set the plugin basename
		$this->plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		
		// Define whether or not the plugin is network active
		$this->is_network_active = is_multisite() && ( $network_plugins = get_site_option( 'active_sitewide_plugins' ) ) && array_key_exists( $this->plugin_basename, $network_plugins ) ? true : false;

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0
	 * @return object - A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since 1.0
	 * @return array|false - The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {
		global $wpdb;

		// return an array of blog ids
		return $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs WHERE archived = '0' AND spam = '0' AND deleted = '0'" );

	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

}

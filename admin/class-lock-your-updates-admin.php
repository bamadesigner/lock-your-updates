<?php

/**
 * Lock Your Updates admin class
 *
 * @package   Lock_Your_Updates_Admin
 * @author    Rachel Carden <contactwpdreamer@gmail.com>
 * @license   GPL-2.0+
 * @link      http://wpdreamer.com
 * @copyright 2014 Rachel Carden
 */

/**
 * @package Lock_Your_Updates_Admin
 * @author  Rachel Carden <contactwpdreamer@gmail.com>
 */
class Lock_Your_Updates_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since 1.0
	 * @var object
	 */
	protected static $instance = null;
	
	/**
	 * The name of the options
	 * stored by the plugin.
	 *
	 * @since 1.0
	 * @var array
	 */
	public $options = array(
		// Stores the locked plugin(s) settings
		'locked_plugins'	=> 'lock_your_updates_locked_plugins',
		// Stores the locked theme(s) settings 
		'locked_themes'		=> 'lock_your_updates_locked_themes',
		// Stores the plugins notes
		'plugins_notes'		=> 'lock_your_updates_plugins_notes',
		// Stores the themes notes
		'themes_notes'		=> 'lock_your_updates_themes_notes',
		);
		
	/**
	 * If running multisite, and in network admin,
	 * will hold active plugins and themes info by blog ID.
	 *
	 * @since 1.0
	 * @var array
	 */
	public $active_by_blog = array();

	/**
	 * Initialize the plugin by loading admin scripts
	 * and styles and adding a settings page and menu.
	 *
	 * @since 1.0
	 */
	private function __construct() {
	
		// Get plugin class and data
		$this->lock_your_updates = Lock_Your_Updates::get_instance();
		
		// These filters are what disables plugins and themes from being updated
		$this->add_disable_updates_filter( 'plugins' );
		$this->add_disable_updates_filter( 'themes');
		
		// Gets active themes and plugins data for the network admin
		add_action( 'load-plugins.php', array( $this, 'set_active_plugins_themes_by_site' ), 1 );
		add_action( 'load-themes.php', array( $this, 'set_active_plugins_themes_by_site' ), 1 );
		
		// Processes the locking and unlocking of plugins and themes
		add_action( 'load-plugins.php', array( $this, 'lock_unlock_plugins_themes' ), 2 );
		add_action( 'load-themes.php', array( $this, 'lock_unlock_plugins_themes' ), 2 );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add columns to plugin and theme tables
		add_filter( 'manage_plugins-network_columns', array( $this, 'manage_plugins_themes_columns' ), 1000 );
		add_filter( 'manage_plugins_columns', array( $this, 'manage_plugins_themes_columns' ), 1000 );
		add_filter( 'manage_themes-network_columns', array( $this, 'manage_plugins_themes_columns' ), 1000 );
		
		// Add values to custom plugin and theme table columns
		add_action( 'manage_plugins_custom_column', array( $this, 'manage_plugins_themes_custom_column' ), 1, 3 );
		add_action( 'manage_themes_custom_column', array( $this, 'manage_plugins_themes_custom_column' ), 1, 3 );
		
		// Add links to plugins row actions
		add_filter( 'network_admin_plugin_action_links', array( $this, 'plugins_action_links' ), 20, 4 );
		add_filter( 'plugin_action_links', array( $this, 'plugins_action_links' ), 20, 4 );
		
		// Add links to themes row actions
		add_filter( 'theme_action_links', array( $this, 'themes_action_links' ), 20, 3 );
		
		// Allows us to print our own messages after theme rows
		add_action( 'after_theme_row', array( $this, 'after_theme_plugin_row' ), 1, 3 );
		add_action( 'after_plugin_row', array( $this, 'after_theme_plugin_row' ), 1, 3 );
		
		// A few filters for dealing with the plugin's options
		foreach( $this->options as $option_name ) {
		
			// Sanitizes our options when they're retrieved	
			add_filter( 'option_' . $option_name, array( $this, 'sanitize_get_option' ), 1 );
			add_filter( 'site_option_' . $option_name, array( $this, 'sanitize_get_option' ), 1 );
			
			// Sanitizes our options before they're saved
			add_filter( 'pre_update_option_' . $option_name, array( $this, 'sanitize_pre_update_option' ), 1, 2 );
			add_filter( 'pre_update_site_option_' . $option_name, array( $this, 'sanitize_pre_update_option' ), 1, 2 );

		}
		
		// Allows us to tweak the update data
		add_filter( 'wp_get_update_data', array( $this, 'filter_update_data' ), 1, 2 );
		
		// Process bulk actions
		add_action( 'admin_init', array( $this, 'process_bulk_actions' ), 1 );
		
		// AJAX function to retrieve item data
		add_action( 'wp_ajax_lock_your_updates_get_item_data', array( $this, 'wp_ajax_get_item_data' ) );
		
		// AJAX function to retrieve theme action buttons
		add_action( 'wp_ajax_lock_your_updates_get_theme_action_buttons', array( $this, 'wp_ajax_get_theme_action_buttons' ) );

		// AJAX function to save item note
		add_action( 'wp_ajax_lock_your_updates_save_item_notes', array( $this, 'wp_ajax_save_item_notes' ) );
				
	}

	/**
	 * Determines whether or not an item type is locked
	 * from being updated.
	 *
	 * @since 1.0
	 * @param string - $item_type - the item type: plugins or themes
	 * @param string - $item_id - the plugin or theme identifier
	 * @return boolean - true if item update is locked, false if unlocked
	 */
	public function is_item_locked( $item_type, $item_id ) {
	
		// The correct use is 'plugins' with an 's' at the end
		if ( strcasecmp( $item_type, 'plugin' ) == 0 )
			$item_type = 'plugins';
		
		// The correct use is 'themes' with an 's' at the end
		else if ( strcasecmp( $item_type, 'theme' ) == 0 )
			$item_type = 'themes';	
		
		// Get locked types from settings
		if ( $locked_types = $this->get_option( $this->options[ "locked_{$item_type}" ] ) ) {
		
			if ( in_array( $item_id, $locked_types ) )
				return true;
				
		}
		
		return false;
		
	}
	
	/**
	 * Retrieves the item's stored notes.
	 *
	 * @since 1.0
	 * @param string - $item_type - the item type: plugins or themes
	 * @param string - $item_id - the plugin or theme identifier
	 * @return string - the item notes
	 */
	public function get_item_notes( $item_type, $item_id ) {

		if ( $saved_notes = $this->get_option( $this->options[ "{$item_type}_notes" ] ) ) {
			
			/**
			 * First, we esc_textarea() and decode
			 * the HTML entities that creates.
			 * 
			 * Next, we run stripslashes() twice to remove
			 * all the slashes from the single and double quotes.
			 */
			if ( isset( $saved_notes[ $item_id ] ) ) {
				return stripslashes( stripslashes( html_entity_decode( esc_textarea( $saved_notes[ $item_id ] ), ENT_QUOTES ) ) );
			}
		
		}
		
		return NULL;
		
	}
	
	/**
	 * This function figures out which plugins and themes
	 * are active and on which sites.
	 *
	 * Stores data in $this->active_by_blog variable.
	 *
	 * This function is invoked by the load-plugins.php'
	 * and 'load-plugins.php' filter.
	 *	
	 * @since 1.0
	 * @global $wpdb
	 */
	public function set_active_plugins_themes_by_site() {
		global $wpdb;
		
		// We only need this data if multisite and we're in the network admin
		if ( ! ( is_multisite() && is_network_admin() ) )
			return;
			
		// What are we trying to process? 'plugins' or 'themes'
		if ( preg_match( '/^load\-(plugins|themes)\.php$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) ) ) {
			
			// Stores active by blog information to use when needed
			$this->active_by_blog[ $type ] = array();
			
			// Get info for all public blogs in the network
			$public_blogs = wp_get_sites( array( 'public' => true, 'limit' => false ) );
					
			// Store original $wpdb blog ID so we can reset afterwards
			$original_wpdb_blog_id = $wpdb->blogid;
			
			// Loop through each blog in the network
			foreach( $public_blogs as $this_blog ) {
			
				// Set blog id so $wpdb will know which table to tweak
				$wpdb->set_blog_id( $this_blog[ 'blog_id' ] );
				
				// Get all the blog details
				$this_blog = get_blog_details( $this_blog[ 'blog_id' ] );
							
				// Get data
				$this->active_by_blog[ $type ][ $this_blog->blog_id ] = (object) array(
					'domain'		=> $this_blog->domain,
					'siteurl'		=> $this_blog->siteurl,
					'blogname'		=> $this_blog->blogname,
					'active_plugins'=> maybe_unserialize( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins' LIMIT 1" ) ),
					'active_theme'	=> $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'stylesheet' LIMIT 1" ),
					);
			
			}
			
			// Reset $wpdb blog ID
			$wpdb->set_blog_id( $original_wpdb_blog_id );
			
		}
		
	}
	
	/**
	 * Adds the filter that disables updates
	 * for a plugin or theme.
	 *
	 * @since 1.0
	 * @param string - $type - the type of updates - 'plugins' or 'themes'
	 */
	public function add_disable_updates_filter( $type ) {
	
		// Must be 'plugins' or 'themes'
		if ( ! in_array( $type, array( 'plugins', 'themes' ) ) )
			return;
			
		add_filter( "site_transient_update_{$type}", array( $this, 'disable_plugin_theme_updates' ), 1 );
		
	}
	
	/**
	 * Removes the filter that disables updates
	 * for a plugin or theme.
	 *
	 * @since 1.0
	 * @param string - $type - the type of updates - 'plugins' or 'themes'
	 */
	public function remove_disable_updates_filter( $type ) {
	
		// Must be 'plugins' or 'themes'
		if ( ! in_array( $type, array( 'plugins', 'themes' ) ) )
			return;
			
		remove_filter( "site_transient_update_{$type}", array( $this, 'disable_plugin_theme_updates' ), 1 );
		
	}
	
	/**
	 * Filtering these transient values is what
	 * disables plugins and themes from being updated.
	 *
	 * This function is invoked by the 'site_transient_update_plugins'
	 * and 'site_transient_update_themes' filters.
	 *
	 * @since 1.0
	 * @param mixed $transient_value - value of transient option being filtered
	 * @param mixed - filtered transient value
	 */
	public function disable_plugin_theme_updates( $transient_value ) {

		/**
		 * Figure out which type we're retrieving.
		 * The only options are 'plugins' and 'themes'.
		 */
		if ( preg_match( '/^site\_transient\_update\_(plugins|themes)$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) ) ) {
			
			// Get locked types from settings
			if ( $locked_types = $this->get_option( $this->options[ "locked_{$type}" ] ) ) {
			
				// Remove locked types from value in order to disable
				foreach( $locked_types as $locked_type ) {
				
					if ( isset( $transient_value->response[ $locked_type ] ) )
						unset( $transient_value->response[ $locked_type ] );
						
				}
				
			}
		
		}
		
		return $transient_value;
		
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
	 * Register and enqueue admin-specific style sheet.
	 *
	 * This function is invoked by the 'admin_enqueue_scripts' filter.
	 *
	 * @since 1.0
	 * @param string - $page - The page being viewed
	 * @return null - Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles( $page ) {
		
		// Only on plugins and themes pages
		if ( 'plugins.php' != $page && 'themes.php' != $page )
			return;
		
		// Only enqueue styles where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return;
			
		// Enqueue our styles for non-multisite themes
		if ( ! $this->lock_your_updates->is_network_active && 'themes.php' == $page )
			wp_enqueue_style( $this->lock_your_updates->plugin_slug .'-admin-themes', plugins_url( 'assets/css/admin-themes.css', __FILE__ ), array(), Lock_Your_Updates::VERSION );
		
		// Enqueue our styles for list tables
		else
			wp_enqueue_style( $this->lock_your_updates->plugin_slug .'-admin-list-table', plugins_url( 'assets/css/admin-list-table.css', __FILE__ ), array(), Lock_Your_Updates::VERSION );

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * This function is invoked by the 'admin_enqueue_scripts' filter.
	 *
	 * @since 1.0
	 * @param string - $page - The page being viewed
	 * @return null - Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts( $page ) {
	
		// Only on plugins and themes pages
		if ( 'plugins.php' != $page && 'themes.php' != $page )
			return;
			
		// Only enqueue styles where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return;
		
		// Enqueue our admin script	for non-multisite themes
		if ( ! $this->lock_your_updates->is_network_active && 'themes.php' == $page )
			wp_enqueue_script( $this->lock_your_updates->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin-themes.js', __FILE__ ), array( 'jquery' ), Lock_Your_Updates::VERSION );
		
		// Enqueue our admin script	for list tables
		else
			wp_enqueue_script( $this->lock_your_updates->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin-list-table.js', __FILE__ ), array( 'jquery' ), Lock_Your_Updates::VERSION );
		
		// Figure out which type we're viewing
		$type = ( 'plugins.php' == $page ) ? 'plugins' : 'themes';
		$singular_type = 'plugins' == $type ? 'plugin' : 'theme';
		
		// We need some data in our script
		wp_localize_script( $this->lock_your_updates->plugin_slug . '-admin-script', 'lock_your_updates', array(
			'type'					=> "{$type}",
			"can_update_{$type}"	=> current_user_can( "update_{$type}" ),
			'is_multisite'			=> is_multisite(),
			'is_network_admin'		=> is_network_admin(),
			'is_network_active'		=> $this->lock_your_updates->is_network_active,
			'lock_updates_text'		=> __( 'Lock Updates', $this->lock_your_updates->plugin_slug ),
			'unlock_updates_text'	=> __( 'Unlock Updates', $this->lock_your_updates->plugin_slug ),
			'notes_for'				=> __( 'Notes For', $this->lock_your_updates->plugin_slug ),
			'update_available'		=> __( 'Update Available', $this->lock_your_updates->plugin_slug ),
			'type_locked'			=> sprintf( __( 'This %s is locked and cannot be updated.', $this->lock_your_updates->plugin_slug ), $singular_type ),
			'type_unlocked'			=> sprintf( __( 'This %s is unlocked and can be updated.', $this->lock_your_updates->plugin_slug ), $singular_type ),
			'checkmark_url'			=> plugins_url( 'assets/images/green-check-mark.svg', __FILE__ ),
			'confirmations'			=> array(
				'empty'	=> array(
					'lock-update'				=> sprintf( __( 'A %1$s was not provided to be locked.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'lock-selected-updates'		=> sprintf( __( 'No %1$s were selected to be locked.', $this->lock_your_updates->plugin_slug ), $type ),
					'unlock-update'				=> sprintf( __( 'No %1$s were selected to be unlocked.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'unlock-selected-updates'	=> sprintf( __( 'A %1$s was not provided to be unlocked.', $this->lock_your_updates->plugin_slug ), $type ),
				),
				'error' => array(
					'lock-update'				=> sprintf( __( 'There seems to have been an error and I don\'t think your %1$s has been locked. Please try again.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'lock-selected-updates'		=> sprintf( __( 'There seems to have been an error and I don\'t think all or any of your %1$s have been locked. Please try again.', $this->lock_your_updates->plugin_slug ), $type ),
					'unlock-update'				=> sprintf( __( 'There seems to have been an error and I don\'t think your %1$s has been unlocked. Please try again.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'unlock-selected-updates'	=> sprintf( __( 'There seems to have been an error and I don\'t think all or any of your %1$s have been unlocked. Please try again.', $this->lock_your_updates->plugin_slug ), $type ),
				),
				'success' => array(
					'lock-update'				=> sprintf( __( 'The %1$s has been locked.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'lock-selected-updates'		=> sprintf( __( 'The %1$s have been locked.', $this->lock_your_updates->plugin_slug ), $type ),
					'unlock-update'				=> sprintf( __( 'The %1$s has been unlocked.', $this->lock_your_updates->plugin_slug ), $singular_type ),
					'unlock-selected-updates'	=> sprintf( __( 'The %1$s have been unlocked.', $this->lock_your_updates->plugin_slug ), $type ),
				),
			),
			'errors'				=> array(
				'load_notes' => sprintf( __( 'There seems to have been an error loading your notes for this %1$s. Refreshing the page might fix the problem. If the error persists, please post your issue in the support forum.', $this->lock_your_updates->plugin_slug ), $singular_type ),
				'save_notes' => sprintf( __( 'There seems to have been an error saving your notes for this %1$s. Refreshing the page might fix the problem. If the error persists, please post your issue in the support forum.', $this->lock_your_updates->plugin_slug ), $singular_type ),
			)
		) );

	}
	
	/**
	 * Add columns to plugins and themes tables.
	 *
	 * This function is invoked by the 'manage_plugins-network_columns'
	 * 'manage_plugins_columns', and 'manage_themes-network_columns' filter.
	 *
	 * @since 1.0
	 * @param array - $columns - plugins or themes columns data
	 * @return array - $columns - filtered plugins or themes columns data
	 */
	public function manage_plugins_themes_columns( $columns ) {
	
		// Only add columns where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return $columns;
			
		/**
		 * Figure out which type we're displaying: must be
		 * 'plugins' or 'themes' and user must have the capability
		 * to update that type.
		 */
		if ( preg_match( '/^manage\_(plugins|themes)(\-network)?\_columns$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) )
			&& current_user_can( 'update_' . $type ) ) {
			
			// Lock Your Updates column
			$columns[ $this->lock_your_updates->plugin_slug ] = __( 'Lock Your Updates', $this->lock_your_updates->plugin_slug );
			
			// Active Sites column only for multisite network
			if ( is_multisite() && is_network_admin() ) {
			
				// We need the singular type for the column header
				$singular_type = 'plugins' == $type ? 'plugin' : 'theme';
			
				$columns[ "{$this->lock_your_updates->plugin_slug}-active-sites" ] = sprintf( __( 'Where The %1$s Is Active', $this->lock_your_updates->plugin_slug ), ucfirst( $singular_type ) );
				
			}
		
		}
	
		return $columns;
		
	}
	
	/**
	 * Add values to custom plugin and theme table columns.
	 *
	 * This function is invoked by the 'manage_plugins_custom_column'
	 * and 'manage_themes_custom_column' filter.
	 *
	 * @since 1.0
	 * @param string - $column_name - the index of the column we're managing
	 * @param string - $item_id - the item's identifier
	 * @param string - $item_data - the item's data
	 * @global $page, $s, $status
	 */
	public function manage_plugins_themes_custom_column( $column_name, $item_id, $item_data ) {
		global $page, $s, $status;
		
		// Only add columns where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return;
		
		// Only editing our custom columns
		if ( ! preg_match( '/^' . str_replace( array( '-', '_', '.' ), array( '\-', '\_', '\.' ), $this->lock_your_updates->plugin_slug ) . '/i', $column_name ) )
			return;
			
		// Make sure we have a item identifier
		if ( ! ( isset( $item_id ) && ! empty( $item_id ) ) )
			return;
			
		/**
		 * Figure out which type we're displaying: must be
		 * 'plugins' or 'themes' and user must have the capability
		 * to update that type.
		 */
		if ( preg_match( '/^manage\_(plugins|themes)\_custom\_column$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) )
			&& current_user_can( "update_{$type}" ) ) {
			
			switch( $column_name ) {
		
				// Lock Your Updates column
				case $this->lock_your_updates->plugin_slug:
				
					// Is the item locked?
					$is_item_locked = $this->is_item_locked( $type, $item_id );

					// Get action URL
					if ( $action_url = $this->get_lock_unlock_url( $type, $item_id, $is_item_locked ) ) {
					
						// We need the singular type for the text
						$singular_type = 'plugins' == $type ? 'plugin' : 'theme';
						
						// Set action wrapper classes
						$action_wrapper_classes = array( 'icon-wrapper', 'action', ( $is_item_locked ? 'locked' : 'unlocked' ) );
							
						// Create the action URL text
						$action_url_text = $is_item_locked ? sprintf( __( 'This %1$s is locked and cannot be updated. Click here to unlock this %1$s.', $this->lock_your_updates->plugin_slug ), $singular_type ) : sprintf( __( 'This %1$s is unlocked and can be updated. Click here to lock this %1$s.', $this->lock_your_updates->plugin_slug ), $singular_type );
					
						// Add action icon
						?><div class="<?php echo implode( ' ', $action_wrapper_classes ); ?>">
							<a class="icon" href="<?php echo $action_url; ?>" title="<?php echo esc_attr( $action_url_text ); ?>"><?php echo $action_url_text; ?></a>
						</div><?php
						
						// Set notes wrapper classes
						$notes_wrapper_classes = array( 'icon-wrapper', 'notes', 'hide-if-no-js', ( $is_item_locked ? 'locked' : 'unlocked' ) );
						
						// Determines if there are notes or not. If not, adds 'empty' class to icon.
						$empty_notes = ( ( $saved_notes = $this->get_item_notes( $type, $item_id ) ) && ! empty( $saved_notes ) ) ? false : true;
						
						if ( $empty_notes )
							$notes_wrapper_classes[] = 'empty';
							
						// Get notes URL
						$notes_url = $this->get_edit_notes_url( $type, $item_id );
							
						// Create the notes URL text
						$notes_url_text = sprintf( __( 'Edit the notes for this %1$s', $this->lock_your_updates->plugin_slug ), $singular_type );
						
						// Add notes icon
						?> <div class="<?php echo implode( ' ', $notes_wrapper_classes ); ?>">
							<div class="lines"></div>
							<a class="icon lock-your-updates-edit-notes" href="<?php echo $notes_url; ?>" title="<?php echo esc_attr( $notes_url_text ); ?>"><?php echo $notes_url_text; ?></a>
						</div><?php
					
					}
					
					break;
				
				// Active Sites column only for multisite network	
				case "{$this->lock_your_updates->plugin_slug}-active-sites":
				
					switch( $type ) {
					
						case 'plugins':
						
							// Will hold list of active sites to print
							$print_active_sites = array();
							
							// Go through each blog and see if this plugin is active
							foreach( $this->active_by_blog[ 'plugins' ] as $this_blog_id => $this_blog ) {
							
								if ( ! ( isset( $this_blog->active_plugins ) && in_array( $item_id, $this_blog->active_plugins ) ) )
									continue;
								
								// Only allow so many characters
								$label_length = 16;
								
								// Create the site label
								$label = strlen( $this_blog->blogname ) > $label_length ? substr( $this_blog->blogname, 0, $label_length ) . '...' : $this_blog->blogname;
								
								// Add site to print list
								$print_active_sites[] = '<a href="' . get_admin_url( $this_blog_id ) . '">' . $label . '</a>';
								
							}
							
							if ( $is_network_active = is_plugin_active_for_network( $item_id ) ) {
								
								?><span class="lock-your-updates-network-active description"><strong>This plugin is network activated<?php echo (  ! empty( $print_active_sites ) ) ? '</strong>, <span class="lock-your-updates-active-sites">but is also individually active on the following sites:</span>' : '.</strong>'; ?></span><?php
								
							}
							
							if ( ! empty( $print_active_sites ) ) {
							
								?><ul class="lock-your-updates-active-sites"><?php
									foreach( $print_active_sites as $site ) {
										?><li><?php echo $site; ?></li><?php
									}
								?></ul><?php
								
							}
							
							break;
							
						case 'themes':
							
							// Will hold list of active sites to print
							$print_active_sites = array();
							
							// Go through each blog and see it's active theme matches this row's stylesheet
							foreach( $this->active_by_blog[ 'themes' ] as $this_blog_id => $this_blog ) {
							
								if ( $item_id != $this_blog->active_theme )
									continue;
								
								// Only allow so many characters
								$label_length = 17;
								
								// Create the site label
								$label = strlen( $this_blog->blogname ) > $label_length ? substr( $this_blog->blogname, 0, $label_length ) . '...' : $this_blog->blogname;
								
								// Add site to print list
								$print_active_sites[] = '<a href="' . get_admin_url( $this_blog_id ) . '">' . $label . '</a>';
									
							}
							
							if ( ! empty( $print_active_sites ) ) {
							
								?><ul class="lock-your-updates-active-sites"><?php
									foreach( $print_active_sites as $site ) {
										?><li><?php echo $site; ?></li><?php
									}
								?></ul><?php
								
							}
							
							break;
					
					}
				
					break;
								
			}
			
		}
		
	}
	
	/**
	 * Add links to plugin row actions.
	 *
	 * This function is invoked by the 'network_admin_plugin_action_links'
	 * and 'plugin_action_links' filter.
	 *
	 * @since 1.0
	 * @param array - $actions - All of the row actions
	 * @param string - $plugin_file - The name of the row's plugin file
	 * @param array - $plugin_data - The row's plugin data
	 * @param string - $context - AKA the page's status, or what's being viewed
	 * @return array - $actions - The filtered row actions
	 */
	public function plugins_action_links( $actions, $plugin_file, $plugin_data, $context ) {
		
		// Is the plugin locked?
		$is_plugin_locked = $this->is_item_locked( 'plugins', $plugin_file );
				
		// If lock/unlock action link is returned, add to actions
		if ( $action_url = $this->get_lock_unlock_url( 'plugins', $plugin_file, $is_plugin_locked ) ) {
		
			// Will the action be to lock or unlock the plugin?
			$action = $is_plugin_locked ? 'unlock' : 'lock';
			
			// Create the action URL title
			$action_url_title = $is_plugin_locked ? esc_attr__( 'This plugin is locked and cannot be updated. Click here to unlock this plugin.', $this->lock_your_updates->plugin_slug ) : esc_attr__( 'This plugin is unlocked and can be updated. Click here to lock this plugin.', $this->lock_your_updates->plugin_slug );
			
			// Create the action URL text
			$action_url_text = sprintf( __( '%1$s Updates', $this->lock_your_updates->plugin_slug ), ucfirst( $action ) );
		
			// Add action
			$actions[ "{$this->lock_your_updates->plugin_slug}-{$action}-plugins" ] = '<a href="' . $action_url . '" title="' . $action_url_title . '">' . $action_url_text . '</a>';
		
		}
		
		return $actions;
		
	}
	
	/**
	 * Add links to theme row actions.
	 *
	 * This function is invoked by the 'theme_action_links' filter.
	 *
	 * @since 1.0
	 * @param array - $actions - All of the row actions
	 * @param string - $theme - The theme's object data
	 * @param string - $context - AKA the page's status, or what's being viewed
	 * @return array - $actions - The filtered row actions
	 */
	public function themes_action_links( $actions, $theme, $context ) {
	
		// Make sure we have the theme's stylesheet
		if ( ! ( $theme_stylesheet = $theme->get_stylesheet() ) )
			return $actions;
		
		// Is the theme locked?
		$is_theme_locked = $this->is_item_locked( 'themes', $theme_stylesheet );
	
		// If action link is returned, add to actions
		if ( $action_url = $this->get_lock_unlock_url( 'themes', $theme_stylesheet, $is_theme_locked ) ) {
			
			// Will the action be to lock or unlock the theme?
			$action = $is_theme_locked ? 'unlock' : 'lock';
			
			// Create the action URL title
			$action_url_title = $is_theme_locked ? esc_attr__( 'This theme is locked and cannot be updated. Click here to unlock this theme.', $this->lock_your_updates->plugin_slug ) : esc_attr__( 'This theme is unlocked and can be updated. Click here to lock this theme.', $this->lock_your_updates->plugin_slug );
			
			// Create the action URL text
			$action_url_text = sprintf( __( '%1$s Updates', $this->lock_your_updates->plugin_slug ), ucfirst( $action ) );
		
			// Add action
			$actions[ "{$this->lock_your_updates->plugin_slug}-{$action}-themes" ] = '<a href="' . $action_url . '" title="' . $action_url_title . '">' . $action_url_text . '</a>';
		
		}
		
		return $actions;
		
	}
	
	/**
	 * This function mimics the wp_theme_update_row() function.
	 *
	 * This function is invoked by the 'after_theme_row' action.
	 *
	 * @since 1.0
	 * @param string - $item_id - the plugin or theme identifier
	 * @param object - $item_data - the plugin or theme data
	 * @param string - $status - the current theme viewing status
	 */
	public function after_theme_plugin_row( $item_id, $item_data, $status ) {
	
		// Only add rows where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return;
	
		// If the current filter is not 'after_theme_row' or 'after_plugin_row', get out of here
		if ( ! ( preg_match( '/^after\_(plugin|theme)\_row$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugin', 'theme' ) ) ) )
			return;
		
		// Convert type to plural
		$type = ( 'plugin' == $type ) ? 'plugins' : 'themes';
					
		/**
		 * We only need to add this row if this plugin or theme is
		 * locked and the user has to permission to update plugins or themes.
		 */
		if ( ! ( $this->is_item_locked( $type, $item_id ) && current_user_can( "update_{$type}" ) ) )
			return;
			
		/**
		 * Get the original update data so we can tell if
		 * this needs to be updated, even though its locked.
		 */
		$original = $this->get_original_update_data( $type );
		
		// If not included, get out of here
		if ( ! isset( $original->response[ $item_id ] ) )
			return;
		
		// Get the list table for table data
		$wp_list_table = ( 'plugins' == $type ) ? _get_list_table( 'WP_Plugins_List_Table' ) : _get_list_table( 'WP_MS_Themes_List_Table' );
		
		/**
		 * Make sure we have the proper file name
		 * for the data-file component.
		 *
		 * For themes, we're already good to go. But for
		 * plugins, we have to do a little tweaking because
		 * the file name includes the plugin base file.
		 *
		 * This method is what WordPress uses to create the 
		 * HTML ID for the table row.
		 */
		$item_html_id = ( 'plugins' == $type && isset( $item_data[ 'Name' ] ) ) ? sanitize_title( $item_data[ 'Name' ] ) : $item_id;
		
		?><tr id="lock-your-updates-update-<?php echo $item_html_id; ?>-row" class="plugin-update-tr lock-your-updates-update-tr" data-file="<?php echo $item_html_id; ?>">
			<td colspan="<?php echo $wp_list_table->get_column_count(); ?>" class="plugin-update colspanchange">
				<div class="update-message"><?php
				
					echo $this->get_update_message( $type, $item_id, $item_data[ 'Name' ] );;
				
				?></div>
			</td>
		</tr><?php
		
	}
	
	/**
	 * Returns the update message for the plugin or theme.
	 *
	 * Used on the plugins and themes list table and on
	 * the themes overlay.
	 *
	 * @since 1.0
	 * @param string - $item_type - the item's type: 'plugins' or 'themes'
	 * @param string - $item_id - the item's identifier
	 * @param string - $item_name - the name of the item
	 */
	public function get_update_message( $item_type, $item_id, $item_name ) {
	
		// We need the singular type for the text
		$singular_type = ( 'plugins' == $item_type ) ? 'plugin' : 'theme';
	
		// Start message					
		$message = sprintf( __( 'There is a new version of %1$s available but this %2$s is locked from being updated.', $this->lock_your_updates->plugin_slug  ), $item_name, $singular_type );
		
		// Get the action URL
		if ( $action_url = $this->get_lock_unlock_url( $item_type, $item_id, true ) ) {
		
			// Create action title text
			$action_title_text = sprintf( esc_attr__( 'This %1$s is locked and cannot be updated. Click here to unlock this %1$s.', $this->lock_your_updates->plugin_slug ), $singular_type );
			
			// Print action link
			$message .= ' <a href="' . $action_url . '" title="' . $action_title_text . '">' . sprintf( __( 'Unlock this %1$s', $this->lock_your_updates->plugin_slug  ), $singular_type ) . '</a>';
			
		}
		
		return $message;
		
	}
	
	/**
	 * This allows us to retrieve the original update
	 * data about plugins or themes without being filtered
	 * by our plugin so we can tell what needs to be updated
	 * even though we're blocking it.
	 *
	 * @since 1.0
	 * @param string - $type - $type - the type of updates - 'plugins' or 'themes'
	 * @return mixed - the (unfiltered by this plugin) site transient value
	 */
	public function get_original_update_data( $type ) {
		
		// Must be 'plugins' or 'themes'
		if ( ! in_array( $type, array( 'plugins', 'themes' ) ) )
			return false;
			
		// First we need to remove our filter
		$this->remove_disable_updates_filter( $type );
		
		// Get the original update data
		$original = get_site_transient( "update_{$type}" );
		
		// Add the filter back into the workflow
		$this->add_disable_updates_filter( $type );
		
		return $original;
	
	}
	
	/**
	 * Allows us to always check for multisite
	 * first when retrieving our settings.
	 *
	 * @since 1.0
	 * @param string $option - the name of the option we're retrieving
	 * @return string - the option value
	 */
	public function get_option( $option ) {
		
		if ( is_multisite() )
			return get_site_option( $option, array() );
		else
			return get_option( $option, array() );
	
	}
	
	/**
	 * Allows us to always check for multisite
	 * first when updating our settings.
	 *
	 * @since 1.0
	 * @param string $option - the name of the option we're updating
	 * @param mixed $value - the value of the option we're updating
	 * @return boolean - true if the value was updated and false if it was not
	 */
	public function update_option( $option, $value ) {
	
		/**
		 * If they can't update a plugin or theme, then they
		 * can't lock or unlock a plugin or theme update.
		 */
		if ( ( preg_match( '/^lock\_your\_updates\_locked\_(plugins|themes)$/i', $option, $matches )
			|| preg_match( '/^lock\_your\_updates\_(plugins|themes)\_notes$/i', $option, $matches ) )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) )
			&& ! current_user_can( "update_{$type}" ) ) {
			
			return false;
			
		}
		
		if ( is_multisite() )
			return update_site_option( $option, $value );
		else
			return update_option( $option, $value );
	
	}
	
	/**
	 * Sanitizes our options when they're being retrieved.
	 *
	 * Makes sure plugin and theme options are arrays.
	 *
	 * This function is invoked by the {'option_' . $option_name}
	 * and {'site_option_' . $option_name} filters.
	 *
	 * @since 1.0
	 * @param mixed - $value - the option's saved value
	 * @return mixed - the sanitized option value
	 */
	public function sanitize_get_option( $value ) {
	
		// Makes sure value is an array
		if ( ! empty( $value ) && ! is_array( $value ) )
			$value = array( $value );
		else if ( ! is_array( $value ) )
			$value = array();
					
		// Sort alphabetically but keep indexes
		asort( $value );
			
		return $value;
		
	}
	
	/**
	 * Sanitizes our options before they're saved.
	 *
	 * Makes sure options are arrays and sorted
	 * alphabetically.
	 *
	 * This function is invoked by the {'pre_update_option_' . $option_name}
	 * and {'pre_update_option_' . $option_name} filters.
	 *
	 * @since 1.0
	 * @param mixed - $value - the option's new value to be saved
	 * @param mixed - $old_value - the option's current/old saved value
	 * @return mixed - the sanitized value to be saved
	 */
	public function sanitize_pre_update_option( $value, $old_value ) {
	
		/**
		 * Only gonna mess with it if
		 * there's something to mess with.
		 *
		 * Otherwise, save as NULL because we
		 * don't want to store an empty array.
		 *
		 * I hate serialized empty arrays.
		 */
		if ( ! empty( $value ) ) {
		
			// Make sure it's an array
			if ( ! is_array( $value ) )
				$value = array( $value );
			
			// Sort alphabetically but keep indexes
			asort( $value );
			
		} else {
		
			// This keeps us from storing an empty array.
			$value = NULL;
			
		}
		
		return $value;
	
	}
	
	/**
	 * This filter will allow us to tweak the update data
	 * so we can change the count number to include plugins
	 * and themes that are locked.
	 *
	 * This function is invoked by the 'wp_get_update_data' filter.
	 *
	 * @since 1.0
	 * @param array - $update_data
	 *     @type array   $counts       An array of counts for available plugin, theme, and WordPress updates.
	 *     @type string  $update_title Titles of available updates.
	 * @param array - $titles - An array of update counts and UI strings for available updates.
	 */
	public function filter_update_data( $update_data, $titles ) {
		
		// Set counts array
		$counts = &$update_data[ 'counts' ];
		
		// Change the plugins and themes count
		foreach( array( 'plugins', 'themes' ) as $type ) {
			if ( current_user_can( "update_{$type}" ) ) {
			
				// Get the original data (not being filtered by us)
				$update = $this->get_original_update_data( $type );
				
				// Update count
				if ( ! empty( $update->response ) )
					$counts[ $type ] = count( $update->response );
					
			}
		}
		
		// Figure out the new total count
		$count_total = 0;
		foreach( $counts as $item => $item_count ) {
		
			// Add everything but 'total' to the count
			if ( 'total' != $item )
				$count_total += $item_count;
				
		}
		
		// Update the total count
		$counts[ 'total' ] = $count_total;
		
		return $update_data;
	
	}
	
	/**
	 * Processes the 'update', 'lock' and 'unlock'
	 * bulk actions.
	 *
	 * For 'update', even though locked plugins and themes
	 * will not update in bulk updates because they
	 * are removed the list, they will still show up
	 * in the "Updating" list because their names
	 * exist in $_GET or $_POST.
	 *
	 * This filter removes their names from $_GET
	 * or $_POST before the update process begins
	 * so their name will not show up in the list.
	 *
	 * @since 1.0
	 */
	public function process_bulk_actions() {
	
		// Make sure we verify the bulk update request and determine type
		$type = NULL;
		
		if ( isset( $_POST[ '_wpnonce' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'bulk-themes' ) ) {
		
			// Makes sure that a user was referred from another admin page.
			check_admin_referer( 'bulk-themes' );
			
			// Set the type
			$type = 'themes';
			
		} else if ( isset( $_POST[ '_wpnonce' ] ) && wp_verify_nonce( $_POST[ '_wpnonce' ], 'bulk-plugins' ) ) {
		
			// Makes sure that a user was referred from another admin page.
			check_admin_referer( 'bulk-plugins' );
			
			// Set the type
			$type = 'plugins';
			
		}
			
		// If no type, then get out of here
		if ( empty( $type ) )
			return;
			
		// We need the singular type for the text
		$singular_type = 'plugins' == $type ? 'plugin' : 'theme';
			
		// We're only processing specific actions
		$valid_actions = array( 'lock-selected-updates', 'unlock-selected-updates', 'update-selected' );
		if ( ! ( isset( $_POST[ 'action' ] ) && ( $action_long = $_POST[ 'action' ] ) && in_array( $action_long, $valid_actions ) ) )
			return;
			
		// Convert action to single word - 'update', 'lock', 'unlock'
		$action = preg_match( '/^([^\-]+)/i', $action_long, $action_matches ) && isset( $action_matches ) && isset( $action_matches[1] ) && ! empty( $action_matches[1] ) ? $action_matches[1] : $action;
		
		// Make sure we found a valid match
		if ( ! in_array( $action, array( 'lock', 'unlock', 'update' ) ) )
			return;
			
		// Build our redirect URL
		$redirect_url = is_network_admin() ? network_admin_url( "{$type}.php" ) : admin_url( "{$type}.php" );
		
		// Add type status
		if ( $status = isset( $_POST[ "{$singular_type}_status" ] ) && ! empty( $_POST[ "{$singular_type}_status" ] ) ? $_POST[ "{$singular_type}_status" ] : NULL )
			$redirect_url = add_query_arg( array( "{$singular_type}_status" => $status ), $redirect_url );
			
		// Add page number
		if ( $paged = isset( $_POST[ 'paged' ] ) && ! empty( $_POST[ 'paged' ] ) ? $_POST[ 'paged' ] : NULL )
			$redirect_url = add_query_arg( array( 'paged' => $paged ), $redirect_url );
			
		// Add search term
		if ( $search = isset( $_POST[ 's' ] ) && ! empty( $_POST[ 's' ] ) ? $_POST[ 's' ] : NULL )
			$redirect_url = add_query_arg( array( 's' => $search ), $redirect_url );
			
		// Make sure you have permission to update this type
		if ( ! current_user_can( "update_{$type}" ) ) {
		
			switch ( $action ) {
			
				case 'update':
					wp_die( sprintf( __( 'You do not have sufficient permissions to update %1$s for this site.', $this->lock_your_updates->plugin_slug ), $type ) . ' <a href="' . $redirect_url . '">' . sprintf( __( 'Return to the %1$s page', $this->lock_your_updates->plugin_slug ), $type ) . '</a>' );
					
				case 'lock':
				case 'unlock':
					wp_die( sprintf( __( 'You do not have sufficient permissions to %1$s %2$s updates for this site.', $this->lock_your_updates->plugin_slug ), $action, $singular_type ) . ' <a href="' . $redirect_url . '">' . sprintf( __( 'Return to the %1$s page', $this->lock_your_updates->plugin_slug ), $type ) . '</a>' );
					
			}
					
		}
					
		// Get the selected plugins or themes
		if ( isset( $_GET[ $type ] ) )
			$selected_types = explode( ',', $_GET[ $type ] );
		elseif ( isset( $_POST[ 'checked' ] ) )
			$selected_types = (array) $_POST[ 'checked' ];
		else
			$selected_types = array();
		
		// In case they came from the URL
		$selected_types = array_map( 'urldecode', $selected_types );
		
		// Add action to redirect URL
		$redirect_url = add_query_arg( 'action', $action_long, $redirect_url );
		
		// Only process if it's not empty
		if ( empty( $selected_types ) ) {
		
			// The selected types were empty
			$redirect_url = add_query_arg( 'empty', true, $redirect_url );
			
			// Redirect to admin page
			wp_redirect( $redirect_url );
			exit;
		
		} else {
		
			// get locked types from settings
			$locked_types = $this->get_option( $this->options[ "locked_{$type}" ] );
			
			switch( $action ) {
			
				case 'update':
			
					// Remove locked types so they won't show up in the update list
					foreach( $selected_types as $selected_index => $selected_type ) {
					
						if ( in_array( $selected_type, $locked_types ) )
							unset( $selected_types[ $selected_index ] );							
							
					}
					
					// Re-assign the selected plugins or themes
					if ( isset( $_GET[ $type ] ) )
						$_GET[ $type ] = ! empty( $selected_types ) ? implode( ',', $selected_types ) : NULL;
					else if ( isset( $_POST[ 'checked' ] ) )
						$_POST[ 'checked' ] = ! empty( $selected_types ) ? $selected_types : array();
						
					break;
			
				case 'lock':
				case 'unlock':
				
					if ( 'lock' == $action ) {
				
						// Go through each selected types and lock
						foreach( $selected_types as $file_name ) {
						
							// If not already locked, add to locked types
							if ( ! in_array( $file_name, $locked_types ) )
								$locked_types[] = $file_name;
						
						}
						
					} else if ( 'unlock' == $action ) {
					
						// Go through each selected types and unlock
						foreach( $selected_types as $file_name ) {
						
							// If locked, remove from locked types
							$type_index = array_search( $file_name, $locked_types );
							
							if ( isset( $type_index ) && is_int( $type_index ) && $type_index >= 0 )
								unset( $locked_types[ $type_index ] );
								
						}
					
					}
					
					// Update locked types
					$update_option = $this->update_option( $this->options[ "locked_{$type}" ], $locked_types );
					
					if ( $update_option ) {
						
						// It was a success!
						$redirect_url = add_query_arg( 'success', true, $redirect_url );
			
						// Redirect to admin page
						wp_redirect( $redirect_url );
						exit;
						
					} else {
					
						/**
						 * update_option() will return false if the
						 * value wasn't changed so check to make sure
						 * the action was successful before sending
						 * an error message.
						 */
						$all_were_successful = true;
						
						// Go through each type and see if action was successful
						foreach( $selected_types as $file_name ) {
						
							// Check to see if the item is locked
							$is_item_locked = $this->is_item_locked( $type, $file_name );
						
							if ( 'lock' == $action && ! $is_item_locked ) {
								$all_were_successful = false;
								break;
							} else if ( 'unlock' == $action && $is_item_locked ) {
								$all_were_successful = false;
								break;
							}
							
						}
						
						// Redirect with success message
						if ( $all_were_successful ) {
						
							// It was a success!
							$redirect_url = add_query_arg( 'success', true, $redirect_url );
							
							// Redirect to admin page
							wp_redirect( $redirect_url );
							exit;
						
						}
						
						// Redirect with error message
						else {
						
							// There seems to have been an error
							$redirect_url = add_query_arg( 'error', true, $redirect_url );
							
							// Redirect to admin page
							wp_redirect( $redirect_url );
							exit;
							
						}
							
					}
					
					break;
					
			}
			
		}
		
	}
	
	/**
	 * Returns the lock/unlock URL for plugins and themes,
	 * dependent upon its current locked/unlocked status.
	 *
	 * @since 1.0
	 * @global $page, $s, $status
	 * @param string - $item_type - the item's type: 'plugins' or 'themes'
	 * @param string - $item_id - the item's identifier
	 * @param boolean - allows you to pass a boolean on whether the item is locked beforehand
	 * @return string - the plugin or theme's lock or unlock URL
	 */
	private function get_lock_unlock_url( $item_type, $item_id, $is_item_locked = NULL ) {
		global $page, $s, $status;
		
		// Only add actions where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return NULL;
			
		/**
		 * Make sure the type is correct.
		 *
		 * Also, if they can't update the plugin or theme, then they can't
		 * lock or unlock the plugin or them update.
		 */
		if ( ! in_array( $item_type, array( 'plugins', 'themes' ) ) || ! current_user_can( "update_{$item_type}" ) )
			return NULL;
			
		// Make sure we have a identifier
		if ( ! isset( $item_id ) || empty( $item_id ) )
			return NULL;
			
		/**
		 * Is the item locked?
		 *
		 * We only need to check if data wasn't already provided.
		 */
		if ( ! ( isset( $is_item_locked ) && is_bool( $is_item_locked ) ) )
			$is_item_locked = $this->is_item_locked( $item_type, $item_id );
	
		// Will the action be to lock or unlock the item?
		$action = $is_item_locked ? 'unlock' : 'lock';
		
		// Build out full action
		$full_action = $action . '-your-updates-' . $item_type;
		
		// Start building action URL
		$action_url = is_network_admin() ? network_admin_url( "{$item_type}.php" ) : admin_url( "{$item_type}.php" );
		
		// Add the action and type parameters
		$action_url = add_query_arg( array( 'action' => $full_action, $item_type => $item_id ), $action_url );
		
		// Add type status
		if ( isset( $status ) && ! empty( $status ) ) {
		
			if ( strcasecmp( 'plugins', $item_type ) == 0 )
				$action_url = add_query_arg( array( 'plugin_status' => $status ), $action_url );
			else if ( strcasecmp( 'themes', $item_type ) == 0 )
				$action_url = add_query_arg( array( 'theme_status' => $status ), $action_url );
				
		}
			
		// Add page number
		if ( isset( $page ) && ! empty( $page ) )
			$action_url = add_query_arg( array( 'paged' => $page ), $action_url );
			
		// Add search term
		if ( isset( $s ) && ! empty( $s ) )
			$action_url = add_query_arg( array( 's' => $s ), $action_url );
		
		// Turn action url into nonce URL and return
		return wp_nonce_url( $action_url, $full_action . '-' . $item_id );
			
	}
	
	/**
	 * Returns the edit notes URL for plugins and themes.
	 *
	 * @since 1.0
	 * @param string - $item_type - the item's type: 'plugins' or 'themes'
	 * @param string - $item_id - the item's identifier
	 * @return string - the plugin or theme's edit notes URL
	 */
	private function get_edit_notes_url( $item_type, $item_id ) {
	
		// Only add actions where we need them
		if ( ( $this->lock_your_updates->is_network_active && ! is_network_admin() ) || ( ! $this->lock_your_updates->is_network_active && is_network_admin() ) )
			return NULL;
			
		/**
		 * Make sure the type is correct.
		 *
		 * Also, if they can't update the plugin or theme, then they can't
		 * lock or unlock the plugin or them update.
		 */
		if ( ! in_array( $item_type, array( 'plugins', 'themes' ) ) || ! current_user_can( "update_{$item_type}" ) )
			return NULL;
			
		// Make sure we have a identifier
		if ( ! isset( $item_id ) || empty( $item_id ) )
			return NULL;
			
		// Start building notes URL
		$notes_url = is_network_admin() ? network_admin_url( "{$item_type}.php" ) : admin_url( "{$item_type}.php" );

		// Add the action and type parameters
		$notes_url = add_query_arg( array( 'action' => "lock-your-updates-edit-notes-{$item_type}", $item_type => $item_id ), $notes_url );
		
		// Turn notes url into nonce URL and return
		return wp_nonce_url( $notes_url, "lock-your-updates-edit-notes-{$item_type}-{$item_id}" );
	
	}
	
	/**
	 * Processes the locking and unlocking of plugins and themes.
	 *
	 * Is run when lock and unlock actions are called
	 * from plugins.php and themes.php.
	 *
	 * This function is invoked by the 'load-plugins.php'
	 * and 'load-themes.php' filter. 
	 *
	 * @since 1.0
	 */
	public function lock_unlock_plugins_themes() {
	
		// What are we trying to process? 'plugins' or 'themes'
		if ( preg_match( '/^load\-(plugins|themes)\.php$/i', current_filter(), $matches )
			&& isset( $matches ) && isset( $matches[1] )
			&& ( $type = strtolower( $matches[1] ) )
			&& in_array( $type, array( 'plugins', 'themes' ) ) ) {
			
			// Make sure we have an action and its valid (lock or unlock only)
			if ( ! ( ( $action = isset( $_REQUEST[ 'action' ] ) && ! empty( $_REQUEST[ 'action' ] ) && preg_match( '/^((un)?lock)\-your\-updates\-' . $type . '$/i', $_REQUEST[ 'action' ], $action_matches ) ? $_REQUEST[ 'action' ] : NULL ) && isset( $action_matches ) && isset( $action_matches[1] ) && ( $action = $action_matches[1] ) && in_array( $action, array( 'lock', 'unlock' ) ) ) )
				return;
				
			// We need the singular type for the text
			$singular_type = 'plugins' == $type ? 'plugin' : 'theme';
				
			// Start building redirect URL
			$redirect_url = is_network_admin() ? network_admin_url( "{$type}.php" ) : admin_url( "{$type}.php" );
			
			// We can't have these parameters on the themes page
			if ( 'themes' != $type ) {
			
				// Add type status
				if ( $status = isset( $_REQUEST[ "{$singular_type}_status" ] ) && ! empty( $_REQUEST[ "{$singular_type}_status" ] ) ? $_REQUEST[ "{$singular_type}_status" ] : NULL )
					$redirect_url = add_query_arg( array( "{$singular_type}_status" => $status ), $redirect_url );
				
				// Add page number
				if ( $paged = isset( $_REQUEST[ 'paged' ] ) && ! empty( $_REQUEST[ 'paged' ] ) ? $_REQUEST[ 'paged' ] : NULL )
					$redirect_url = add_query_arg( array( 'paged' => $paged ), $redirect_url );
				
				// Add search term
				if ( $search = isset( $_REQUEST[ 's' ] ) && ! empty( $_REQUEST[ 's' ] ) ? $_REQUEST[ 's' ] : NULL )
					$redirect_url = add_query_arg( array( 's' => $search ), $redirect_url );
					
			}
				
			// Make sure we have a item file name
			if ( ! ( $file_name = isset( $_REQUEST[ $type ] ) && ! empty( $_REQUEST[ $type ] ) ? $_REQUEST[ $type ] : NULL ) )
				wp_die( sprintf( __( 'Uh oh. It looks like you want to %1$s a %2$s but I don\'t know which %2$s you\'d like to %1$s.', $this->lock_your_updates->plugin_slug ), $action, $singular_type ) . ' <a href="' . $redirect_url . '">' . sprintf( __( 'Return to the %1$s page', $this->lock_your_updates->plugin_slug ), $type ) . '</a>' );
			
			// Add type name
			$redirect_url = add_query_arg( $singular_type, $file_name, $redirect_url );
			
			// What should the nonce action be for verification?
			$nonce_action = $action . '-your-updates-' . $type . '-' . $file_name;
			
			// If nonce doesn't verify, redirect to admin page
			if ( ! wp_verify_nonce( $_REQUEST[ '_wpnonce' ], $nonce_action  ) ) {
			
				wp_redirect( $redirect_url );
				exit;
				
			}
			
			// Makes sure that a user was referred from another admin page.
			check_admin_referer( $nonce_action );
			
			// If multisite, you can only update in the network admin.
			if ( is_multisite() && $this->lock_your_updates->is_network_active && ! is_network_admin() )
				wp_die( sprintf( __( 'In a WordPress Multisite, %1$s can only be updated from the network admin so therefore %2$s updates can only be locked from the network admin.', $this->lock_your_updates->plugin_slug ), $type, $singular_type ) . ' <a href="' . $redirect_url . '">' . sprintf( __( 'Return to the %1$s page', $this->lock_your_updates->plugin_slug ), $type ) . '</a>' );
			
			/**
			 * If they can't update a plugin or theme,
			 * then they can't lock or unlock a plugin or theme update.
			 */
			if ( ! current_user_can( "update_{$type}" ) )
				wp_die( sprintf( __( 'You do not have sufficient permissions to %1$s %2$s updates for this site.', $this->lock_your_updates->plugin_slug ), $action, $singular_type ) . ' <a href="' . $redirect_url . '">' . sprintf( __( 'Return to the %1$s page', $this->lock_your_updates->plugin_slug ), $type ) . '</a>' );
				
			// Get currently locked plugins or themes
			$locked_types = $this->get_option( $this->options[ "locked_{$type}" ] );
						
			switch( $action ) {
			
				case 'lock':
				
					// If not already locked, add to locked types
					if ( ! in_array( $file_name, $locked_types ) )
						$locked_types[] = $file_name;				
					
					break;
					
				case 'unlock':
				
					// If locked, remove from locked types
					$type_index = array_search( $file_name, $locked_types );
					
					if ( isset( $type_index ) && is_int( $type_index ) && $type_index >= 0 )
						unset( $locked_types[ $type_index ] );
						
					break;
					
			}
						
			// Add action to URL
			if ( 'themes' != $type )
				$redirect_url = add_query_arg( 'action', "$action-update", $redirect_url );
			
			// Update locked types
			if ( $this->update_option( $this->options[ "locked_{$type}" ], $locked_types ) ) {
			
				// It was a success!
				if ( 'themes' != $type )
					$redirect_url = add_query_arg( 'success', true, $redirect_url );
				
				// Redirect to admin page
				wp_redirect( $redirect_url );
				exit;
				
			} else {
				
				/**
				 * update_option() will return false if the
				 * value wasn't changed so check to make sure
				 * the action was successful before sending
				 * an error message.
				 */
				$is_item_locked = $this->is_item_locked( $type, $file_name );
				
				// Lock was successful
				if ( 'lock' == $action && $is_item_locked ) {
				
					// It was a success!
					if ( 'themes' != $type )
						$redirect_url = add_query_arg( 'success', true, $redirect_url );
				
					// Redirect to admin page				
					wp_redirect( $redirect_url );
					exit;
					
				}
				
				// Unlock was successful
				else if ( 'unlock' == $action && ! $is_item_locked ) {
				
					// It was a success!
					if ( 'themes' != $type )
						$redirect_url = add_query_arg( 'success', true, $redirect_url );
				
					// Redirect to admin page
					wp_redirect( $redirect_url );
					exit;
					
				}
					
				// There was an error
				else {
				
					// There seems to have been an error
					if ( 'themes' != $type )
						$redirect_url = add_query_arg( 'error', true, $redirect_url );
				
					// Redirect to admin page
					wp_redirect( $redirect_url );
					exit;
					
				}
					
			}
					
		}
	
	}
	
	/**
	 * AJAX function to retrieve item data.
	 *
	 * @since 1.0
	 */
	public function wp_ajax_get_item_data() {
	
		// Must have a file name, type, and nonce.
		if ( ( $item_id = isset( $_POST[ 'item_id' ] ) && ! empty( $_POST[ 'item_id' ] ) ? urldecode( $_POST[ 'item_id' ] ) : NULL )
			&& ( $item_type = isset( $_POST[ 'item_type' ] ) && ! empty( $_POST[ 'item_type' ] ) ? $_POST[ 'item_type' ] : NULL )
			&& in_array( $item_type, array( 'plugins', 'themes' ) ) ) {
			
			// Is the nonce required?
			$nonce_required = isset( $_POST[ 'nonce_required' ] ) && 'true' == $_POST[ 'nonce_required' ] ? true : false;
			
			// See if a nonce is set
			$nonce = isset( $_POST[ 'nonce' ] ) && ! empty( $_POST[ 'nonce' ] ) ? $_POST[ 'nonce' ] : NULL;
			
			// If nonce is required but the nonce is empty
			if ( $nonce_required && ( ! isset( $nonce ) || empty( $nonce ) ) ) {
			
				echo json_encode( array( 'passed_nonce' => false ) );
				
			// If nonce is required, then it has to verify
			} else if ( $nonce_required && ! wp_verify_nonce( $nonce, "lock-your-updates-edit-notes-{$item_type}-{$item_id}" ) ) {
			
				echo json_encode( array( 'passed_nonce' => false ) );
				
			} else {
			
				// Build the data to return
				$item_data = array();
				
				// Only return nonce verification if nonce is required
				if ( $nonce_required )
					$item_data[ 'passed_nonce' ] = true;
				
				// Get the item note
				$item_data[ 'notes' ] = $this->get_item_notes( $item_type, $item_id );
				
				// We'll need the item name for later
				$item_name = NULL;
				
				if ( 'plugins' == $item_type ) {
				
					// Get all plugin info
					$plugins = get_plugins();
					
					// Find the particular plugin
					if ( array_key_exists( $item_id, $plugins )
						&& isset( $plugins[ $item_id ] )
						&& ( $plugin = $plugins[ $item_id ] ) ) {
						
						// We need the item name for later
						$item_name = $plugin[ 'Name' ];
						
						// Set item data
						$item_data = array_merge( array(
							'name' => $item_name,
							'description' => $plugin[ 'Description' ],
							'version' => $plugin[ 'Version' ],
							'author' => $plugin[ 'Author' ],
							'authoruri' => $plugin[ 'AuthorURI' ],
							'locked' => $this->is_item_locked( $item_type, $item_id ),
						), $item_data );
						
					}
					
				} else if ( 'themes' == $item_type ) {
							
					if ( $theme = wp_get_theme( $item_id ) ) {
					
						// We need the item name for later
						$item_name = $theme->get( 'Name' );
						
						// Set item data
						$item_data = array_merge( array(
							'name' => $item_name,
							'description' => $theme->get( 'Description' ),
							'version' => $theme->get( 'Version' ),
							'author' => $theme->get( 'Author' ),
							'authoruri' => $theme->get( 'AuthorURI' ),
							'locked' => $this->is_item_locked( $item_type, $item_id ),
						), $item_data );
							
					}
					
				}
				
				/**
				 * Get the original update data so we can tell if
				 * this needs to be updated, even though it might be locked.
				 */
				$original = $this->get_original_update_data( $item_type );
				
				// Is an update available?
				$item_data[ 'update_available' ] = array_key_exists( $item_id, $original->response );
				
				// If we're supposed to get the message, add the message
				$item_data[ 'update_message' ] = ( isset( $_POST[ 'get_update_message' ] ) && 'true' == $_POST[ 'get_update_message' ] ) ? $this->get_update_message( $item_type, $item_id, $item_name ) : NULL;
				
				// Clean out errors
				if ( ob_get_length() )
					ob_end_clean();
				
				// Return data to AJAX
				echo json_encode( $item_data );
				
			}
			
		}
		
		die();
	
	}
	
	/**
	 * AJAX function to retrieve theme actions.
	 *
	 * @since 1.0
	 */
	public function wp_ajax_get_theme_action_buttons() {
	
		// Must have a theme ID
		if ( $theme_id = isset( $_POST[ 'theme_id' ] ) && ! empty( $_POST[ 'theme_id' ] ) ? $_POST[ 'theme_id' ] : NULL ) {
		
			// Is the theme locked?
			$is_locked = $this->is_item_locked( 'themes', $theme_id );
			
			// Get action URL
			if ( $action_url = $this->get_lock_unlock_url( 'themes', $theme_id, $is_locked ) ) {
			
				// Set action button classes
				$action_button_classes = array( 'action', 'button', 'button-secondary', ( $is_locked ? 'locked' : 'unlocked' ) );
					
				// Create the action button text
				$action_button_text = $is_locked ? __( 'This theme is locked and cannot be updated. Click here to unlock this %1$s.', $this->lock_your_updates->plugin_slug ) : __( 'This theme is unlocked and can be updated. Click here to lock this %1$s.', $this->lock_your_updates->plugin_slug );
				
				// What action is needed?
				$action = $is_locked ? 'Unlock' : 'Lock';
				
				// Add action button
				?><a class="<?php echo implode( ' ', $action_button_classes ); ?>" href="<?php echo $action_url; ?>" title="<?php echo esc_attr( $action_button_text ); ?>"><?php echo sprintf( __( '%s Updates', $this->lock_your_updates->plugin_slug ), $action ); ?></a><?php
				
				// Set notes button classes
				$notes_button_classes = array( 'notes', 'lock-your-updates-edit-notes', 'button', 'button-secondary', ( $is_locked ? 'locked' : 'unlocked' ) );
				
				// Determines if there are notes or not. If not, adds 'empty' class to icon.
				$empty_notes = ( ( $theme_notes = $this->get_item_notes( 'themes', $theme_id ) ) && ! empty( $theme_notes ) ) ? false : true;
				
				if ( $empty_notes )
					$notes_button_classes[] = 'empty';
					
				// Get notes URL
				$notes_url = $this->get_edit_notes_url( 'themes', $theme_id );
						
				// Create the notes URL text
				$notes_url_text = __( 'Edit the notes for this theme', $this->lock_your_updates->plugin_slug );
					
				// Add notes button
				?><a class="<?php echo implode( ' ', $notes_button_classes ); ?>" href="<?php echo $notes_url; ?>" title="<?php echo esc_attr( $notes_url_text ); ?>"><?php _e( 'Edit Notes', $this->lock_your_updates->plugin_slug ); ?></a><?php
				
			}
					
		}
		
		die();
		
	}
	
	/**
	 * AJAX function to save item notes.
	 *
	 * @since 1.0
	 */
	public function wp_ajax_save_item_notes() {
	
		// Must have a item id, item type, and nonce.
		if ( ( $item_id = isset( $_POST[ 'item_id' ] ) && ! empty( $_POST[ 'item_id' ] ) ? urldecode( $_POST[ 'item_id' ] ) : NULL )
			&& ( $item_type = isset( $_POST[ 'item_type' ] ) && ! empty( $_POST[ 'item_type' ] ) ? $_POST[ 'item_type' ] : NULL )
			&& in_array( $item_type, array( 'plugins', 'themes' ) )
			&& ( $nonce = isset( $_POST[ 'nonce' ] ) && ! empty( $_POST[ 'nonce' ] ) ? $_POST[ 'nonce' ] : NULL ) ) {
			
			if ( ! wp_verify_nonce( $nonce, "lock-your-updates-edit-notes-{$item_type}-{$item_id}" ) ) {
			
				echo json_encode( array( 'passed_nonce' => false ) );
				
			} else {
			
				// Build the data to return
				$item_data = array( 'passed_nonce' => true );
				
				// Get the item notes. It's a text area so we need to sanitize.
				$item_notes = array_key_exists( 'item_notes', $_POST ) ? sanitize_text_field( $_POST[ 'item_notes' ] ) : NULL;
				
				// Get saved notes so we can edit them.
				$saved_notes = $this->get_option( $this->options[ "{$item_type}_notes" ] );
				
				// Update the item notes.
				$saved_notes[ $item_id ] = $item_notes;
				
				// Update all notes.
				if ( $this->update_option( $this->options[ "{$item_type}_notes" ], $saved_notes ) ) {
				
					/**
					 * This means the notes passed the nonce test,
					 * were updated/changed and were saved.
					 */
					$item_data = array_merge( $item_data, array(
						'saved_note'	=> true,
						'updated_note'	=> true,
						'item_notes'	=> $item_notes,
						) );
					
				} else {
				
					/**
					 * update_option() will return false if the
					 * value wasn't changed so check to make sure
					 * the action was successful before sending
					 * an error message.
					 */
					$saved_notes = $this->get_option( $this->options[ "{$item_type}_notes" ] );
					
					/**
					 * This means the item's notes didn't technically
					 * update because it had the same value.
					 */
					if ( isset( $saved_notes[ $item_id ] ) && $item_notes == $saved_notes[ $item_id ] ) {
					
						$item_data = array_merge( $item_data, array(
							'saved_note'	=> true,
							'updated_note'	=> false,
							'item_notes'	=> $item_notes,
							) );
							
					}
					
					/**
					 * This means there was an error.
					 *
					 * The item's notes didnt update and
					 * they do not have the same value.
					 */
					else {
					
						$item_data = array_merge( $item_data, array(
							'saved_note'	=> false,
							'updated_note'	=> false,
							'item_notes'	=> $item_notes,
							) );
							
					}
					
				}
				
				// Clean out errors
				if ( ob_get_length() )
					ob_end_clean();
				
				// Return data to AJAX
				echo json_encode( $item_data );
				
			}
			
		}
		
		die();
	
	}

}

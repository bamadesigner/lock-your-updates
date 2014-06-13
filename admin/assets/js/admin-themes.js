(function ( $ ) {
	"use strict";

	$(function () {
	
		// Only use this script when we need it
		if ( ( lock_your_updates.is_network_active && ! lock_your_updates.is_network_admin ) || ( ! lock_your_updates.is_network_active && lock_your_updates.is_network_admin ) )
			return;
			
		// Don't do anything unless the user can 'update_plugins' or 'update_themes'
		if ( ! lock_your_updates[ 'can_update_' + lock_your_updates.type ] )
			return;

		/**
		 * Runs when something is inserted into the parent theme
		 * overlay div, i.e. when the actual theme overlay is created.
		 */
		jQuery( '.theme-overlay' ).bind( 'DOMNodeInserted', function() {
			
			/**
			 * Check to make sure actual .theme-overlay is up and running.
			 *
			 * Cannot simply look for a <div class="theme-overlay active">
			 * because some versions of the theme overlay do not have the
			 * active class but, instead, have a small-screenshot class.
			 */
			if ( jQuery( '.theme-overlay' ).children( '.theme-overlay' ).length > 0 ) {
				
				lock_your_updates_change_theme_overlay();
				
			}
			
		});
		
		function lock_your_updates_change_theme_overlay() {
		
			// Get URL parameters
			var $url_parameters = window.location.search.replace( /\?/i, '' ).split( '&' );
			
			// Get theme ID from URL
			var $theme_id = null;
			jQuery.each( $url_parameters, function( $index, $value ) {
				var $this_parameter = $value.split( '=' );
				if ( 'theme' == $this_parameter[0].toLowerCase() )
					$theme_id = $this_parameter[1];
			});
			
			// Make sure we have a theme ID			
			if ( $theme_id === undefined || $theme_id == null || $theme_id == '' )
				return;
			
			// Set the theme overlay
			var $theme_overlay = undefined;
			
			if ( jQuery( '.theme-overlay.active' ).length > 0 )
				$theme_overlay = jQuery( '.theme-overlay.active' );
			else if ( jQuery( '.theme-overlay' ).children( '.theme-overlay' ).length > 0 )
				$theme_overlay = jQuery( '.theme-overlay' ).children( '.theme-overlay' ).first();
				
			/**
			 * Make sure the theme overlay is defined.
			 *
			 * Adding our own custom class allows
			 * us to test if we've already run our code
			 * to keep it from running multiple times.
			 *
			 * This works because the theme overlay element
			 * is re-created everytime the theme changes.
			 */
			if ( $theme_overlay === undefined || $theme_overlay == null || $theme_overlay.hasClass( 'lock-your-updates-themes' ) )
				return;
				
			// Add our custom class right off the bat before the function is invoked again.
			$theme_overlay.addClass( 'lock-your-updates-themes' );
			
			// Get some theme overlay elements
			var $theme_info = $theme_overlay.find( '.theme-about .theme-info' );
			var $theme_author = $theme_info.find( '.theme-author' );
			var $theme_name = $theme_info.find( '.theme-name' );
			var $theme_description = $theme_info.find( '.theme-description' );
			
			// Get the item/notes data	
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				async: false,
				cache: false,
				data: {
					action: 'lock_your_updates_get_item_data',
					item_type: lock_your_updates.type,
					item_id: $theme_id,
					nonce_required: false,
					get_update_message: true,
				},
				error: function( $jqXHR, $textStatus, $errorThrown ) {
				
					/**
					 * @TODO Show an error message?
					 *
					 * Don't want to use alert because I dont
					 * want the error message to be annoying.
					 */
					return;
					
				},
				success: function( $data, $textStatus, $jqXHR ) {
					
					// Data is required.
					if ( ! $data || typeof $data === undefined || $data == null ) {
					
						/**
						 * @TODO Show an error message?
						 *
						 * Don't want to use alert because I dont
						 * want the error message to be annoying.
						 */
						return;
						
					}
					
					// We must know whether or not it's locked.
					else if ( ! ( typeof $data.locked !== undefined && $data.locked != null ) ) {
					
						/**
						 * @TODO Show an error message?
						 *
						 * Don't want to use alert because I dont
						 * want the error message to be annoying.
						 */
						return;
						
					}
					
					/**
					 * If it doesn't already exist, add a header
					 * stating whether the theme is locked or unlocked.
					 */
					if ( $theme_overlay.find( '.lock-your-updates-themes-header' ).length == 0 ) {
					
						// Create new header
						var $new_theme_header = jQuery( '<h4 class="lock-your-updates-themes-header"></h4>' );
						
						// Add class and message dependent upon lock status
						if ( $data.locked ) {
						
							$new_theme_header.addClass( 'locked' );
							$new_theme_header.append( lock_your_updates.type_locked );
						
						} else {
						
							$new_theme_header.addClass( 'unlocked' );
							$new_theme_header.append( lock_your_updates.type_unlocked );
						
						}
						
						/**
						 * Add our header information after the
						 * the theme author. If the theme author
						 * doesn't exist, add after the theme
						 * name. If the theme name doesn't exist,
						 * add to beginning of theme info element.
						 */
						if ( $theme_author.length > 0 )
							$theme_author.after( $new_theme_header );
						else if ( $theme_name.length > 0 )
							$theme_name.after( $new_theme_header );
						else if ( $theme_info.length > 0 )
							$theme_info.prepend( $new_theme_header );
							
						/**
						 * Add update message for themes who have an
						 * update but are locked.
						 *
						 * Only add if locked (and therefore message
						 * shouldn't exit, there's an update available,
						 * there's a message and the message doesn't
						 * already exist.
						 */
						if ( $data.locked !== undefined && $data.locked === true
							&& $data.update_available !== undefined && $data.update_available === true
							&& $data.update_message !== undefined && $data.update_message != ''
							&& $theme_info.find( '.theme-update-message' ).length == 0 ) {
							
							// Add message
							jQuery( '<div class="theme-update-message lock-your-updates-theme-update-message"><h4 class="theme-update">' + lock_your_updates.update_available + '</h4><p><strong>' + $data.update_message + '</strong></p></div>' ).insertAfter( $new_theme_header );
							
						}
						
					}
					
				},
				complete: function( $jqXHR, $textStatus ) {}
			});
			
			/**
			 * These are the two rows that hold
			 * the theme action buttons. One row
			 * is always hidden, dependent upon
			 * whether the theme is active or not.
			 */
			var $theme_actions = $theme_overlay.find( '.theme-actions' );
			var $active_theme = $theme_actions.find( '.active-theme' );
			var $inactive_theme = $theme_actions.find( '.inactive-theme' );
			
			/**
			 * This ajax call will return the HTML
			 * for our custom action buttons.
			 *
			 * We only need to run the code if the
			 * actions do not already exist.
			 */
			if ( $active_theme.find( '.lock-updates-themes-actions' ).length == 0
				|| $inactive_theme.find( '.lock-updates-themes-actions' ).length == 0 ) {
			
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'html',
					async: false,
					cache: false,
					data: {
						action: 'lock_your_updates_get_theme_action_buttons',
						theme_id: $theme_id
					},
					error: function( $jqXHR, $textStatus, $errorThrown ) {},
					success: function( $actions_html ) {
					
						/**
						 * If the function returned the action buttons HTML,
						 * add buttons to theme actions areas.
						 */
						if ( $actions_html != undefined && $actions_html != null && $actions_html != '' ) {
						
							// Create action buttons element
							var $lyu_themes_actions = jQuery( '<div class="lock-updates-themes-actions"></div>' ).append( $actions_html );
								
							// Only add action buttons element if it doesn't already exist
							if ( $active_theme.find( '.lock-updates-themes-actions' ).length == 0 )
								$lyu_themes_actions.clone().appendTo( $active_theme );
								
							if ( $inactive_theme.find( '.lock-updates-themes-actions' ).length == 0 )
								$lyu_themes_actions.clone().appendTo( $inactive_theme );
							
							jQuery( '.lock-updates-themes-actions .lock-your-updates-edit-notes' ).on( 'click', function( $event ) {
								$event.preventDefault();
								
								// Build array of edit notes arguments.
								var $args = {};
								var $parts = jQuery( this ).attr( 'href' ).replace( /[?&]+([^=&]+)=([^&]*)/gi, function( $m, $key, $value ) {
									$args[ $key ] = $value;
								});
								
								$theme_overlay.lock_your_updates_edit_theme_note( $args );
								
							});
							
						}
						
					}
				});
				
			}
			
		}
		
		/**
		 * This function is invoked by the active theme overlay.
		 *
		 * The $args are provided via the "edit notes" link.
		 */
		jQuery.fn.lock_your_updates_edit_theme_note = function( $args ) {
		
			// If the edit area already exists, get out of here
			if ( jQuery( '.lock-your-updates-themes-edit-notes-area' ).length > 0 )
				return;
		
			// The action, type and nonce are required.
			if ( ! ( 'action' in $args && ( 'lock-your-updates-edit-notes-' + lock_your_updates.type ) == $args.action
				&& lock_your_updates.type in $args && $args[ lock_your_updates.type ] != ''
				&& '_wpnonce' in $args && $args._wpnonce != '' ) ) {
				
				// Show load error message
				alert( lock_your_updates.errors.load_notes );
				return;
				
			} else {
			
				// Was invoked by theme overlay
				var $theme_overlay = jQuery( this );
				
				// Get the item/notes data	
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					async: false,
					cache: false,
					data: {
						action: 'lock_your_updates_get_item_data',
						item_type: lock_your_updates.type,
						item_id: $args[ lock_your_updates.type ],
						nonce_required: true,
						nonce: $args._wpnonce
					},
					error: function( $jqXHR, $textStatus, $errorThrown ) {
					
						// Show load error message
						alert( lock_your_updates.errors.load_notes );
						return;
						
					},
					success: function( $data, $textStatus, $jqXHR ) {
						
						// Data is required.
						if ( ! $data || typeof $data === undefined || $data == null ) {
						
							// Show load error message
							alert( lock_your_updates.errors.load_notes );
							return;
							
						}
							
						// User must pass the nonce test.
						if ( ! ( typeof $data.passed_nonce !== undefined && $data.passed_nonce != null && $data.passed_nonce ) ) {
						
							// Show load error message
							alert( lock_your_updates.errors.load_notes );
							return;
						
						}
						
						// Data must have the item's name and whether or not it's locked.
						else if ( ! ( typeof $data.name !== undefined && $data.name != null && typeof $data.locked !== undefined && $data.locked != null ) ) {
						
							// Show load error message
							alert( lock_your_updates.errors.load_notes );
							return;
							
						}
						
						// Get theme header info
						var $theme_header = $theme_overlay.find( '.lock-your-updates-themes-header' );
						
						// Create edit notes element
						var $lyu_edit_theme_notes = jQuery( '<div class="lock-your-updates-themes-edit-notes-area"></div>' );
						
						// Designate whether the item is locked or not
						if ( $data.locked )
							$lyu_edit_theme_notes.addClass( 'locked' );
						else
							$lyu_edit_theme_notes.addClass( 'unlocked' );
							
						// Add icon to put in the background
						$lyu_edit_theme_notes.append( '<div class="icon"></div>' );
						
						// Add header for notes
						$lyu_edit_theme_notes.append( '<h3><span class="notes-for">' + lock_your_updates.notes_for + '</span> ' + $data.name + '</h3>' );
						
						// Add subheader for notes
						if ( $data.locked )
							$lyu_edit_theme_notes.append( '<h4 class="locked">' + lock_your_updates.type_locked + '</h4>' );
						else
							$lyu_edit_theme_notes.append( '<h4 class="unlocked">' + lock_your_updates.type_unlocked + '</h4>' );
						
						// Create text area
						var $lyu_edit_theme_notes_textarea = jQuery( '<textarea></textarea>' );
						
						// Get the item's saved notes and add to textarea
						if ( $data.notes != undefined && $data.notes != null )
							$lyu_edit_theme_notes_textarea.val( $data.notes );
						
						// Add textarea
						$lyu_edit_theme_notes.append( $lyu_edit_theme_notes_textarea );
						
						// Create save button
						var $lyu_edit_theme_notes_save_button = jQuery( '<a class="button-primary save alignleft" href="" accesskey="s">Save</a>' );
						
						// When you click the link to save the notes
						$lyu_edit_theme_notes_save_button.on( 'click', function( $event ) {
							$event.preventDefault();
							
							$theme_overlay.lock_your_updates_save_theme_note( $args, $lyu_edit_theme_notes_textarea.val() );
							
						
						});
							
						// Add save button
						$lyu_edit_theme_notes.append( $lyu_edit_theme_notes_save_button );
						
						// Create cancel button
						var $lyu_edit_theme_notes_cancel_button = jQuery( '<a class="button-secondary cancel alignleft" href="" accesskey="c">Cancel</a>' );
						
						// When you click the link to cancel any edits
						$lyu_edit_theme_notes_cancel_button.on( 'click', function( $event ) {
							$event.preventDefault();
							
							$theme_overlay.lock_your_updates_close_edit_theme_note();
						
						});
							
						// Add cancel button
						$lyu_edit_theme_notes.append( $lyu_edit_theme_notes_cancel_button );
						
						// hide the original header
						$theme_header.hide();
	
						// add the edit notes box
						$lyu_edit_theme_notes.hide().insertAfter( $theme_header ).fadeIn();
						
					},
					complete: function( $jqXHR, $textStatus ) {}
				});
			
			}
			
			return;
			
		}
		
		/**
		 * This function is invoked by the plugin's
		 * or theme's original row in the list table.
		 */
		jQuery.fn.lock_your_updates_save_theme_note = function( $args, $notes ) {
		
			// The action, type and nonce are required.
			if ( ! ( 'action' in $args && ( 'lock-your-updates-edit-notes-' + lock_your_updates.type ) == $args.action
				&& lock_your_updates.type in $args && $args[ lock_your_updates.type ] != ''
				&& '_wpnonce' in $args && $args._wpnonce != '' ) ) {
				
				return;
				
			} else {
			
				// Was invoked by theme overlay
				var $theme_overlay = jQuery( this );
			
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					async: false,
					cache: false,
					data: {
						action: 'lock_your_updates_save_item_notes',
						item_type: lock_your_updates.type,
						item_id: $args[ lock_your_updates.type ],
						nonce: $args._wpnonce,
						item_notes: $notes,
					},
					error: function( $jqXHR, $textStatus, $errorThrown ) {
					
						// Alert an error message
						alert( lock_your_updates.errors.save_notes );
					
					},
					success: function( $data, $textStatus, $jqXHR ) {
					
						// Data is required.
						if ( ! $data || typeof $data === undefined || $data == null ) {
						
							// Alert an error message
							alert( lock_your_updates.errors.save_notes );
							return;
							
						}
							
						// User must pass the nonce test.
						if ( ! ( typeof $data.passed_nonce !== undefined && $data.passed_nonce != null && $data.passed_nonce ) ) {
						
							// Alert an error message
							alert( lock_your_updates.errors.save_notes );
							return;
							
						}
						
						// This means the notes did not save.
						if ( ! ( $data.saved_note != undefined && $data.saved_note != null && $data.saved_note ) ) {
						
							// Alert an error message
							alert( lock_your_updates.errors.save_notes );
							return;
							
						}
													
						/**
						 * The notes were saved and all is well
						 * so add the fading checkmark and close
						 * the edit notes area.
						 */
						
						// Get the position of the edit area for adding the checkmark
						var $lyu_edit_notes_area = $theme_overlay.find( '.lock-your-updates-themes-edit-notes-area' );
						var $lyu_checkmark_left = $lyu_edit_notes_area.offset().left;
						var $lyu_checkmark_top = $lyu_edit_notes_area.offset().top;
						
						// Close edit notes area
						$theme_overlay.lock_your_updates_close_edit_theme_note();
						
						// Create saved checkmark
						var $lyu_checkmark = jQuery( '<div class="lock-your-updates-checkmark"></div>' ).css({
							'left': $lyu_checkmark_left + 'px',
							'top': $lyu_checkmark_top + 'px'
						}).hide();
						
						// Add checkmark to theme overlay and animate
						$lyu_checkmark.appendTo( jQuery( 'body' ) ).show().animate({
						 	opacity: 0,
						 }, 1000, function() {
						 
						 	// Remove the checkmark
						 	$lyu_checkmark.remove();
						 	
						 });
						 
					},
					complete: function( $jqXHR, $textStatus ) {}
				});
			
			}
		
		}
		
		/**
		 * This function is invoked by the active theme overlay.
		 */
		jQuery.fn.lock_your_updates_close_edit_theme_note = function() {
		
			// Was invoked by theme overlay
			var $theme_overlay = jQuery( this );
		
			// Remove the edit notes box
			$theme_overlay.find( '.lock-your-updates-themes-edit-notes-area' ).remove();
			
			// Fade in the original header
			$theme_overlay.find( '.lock-your-updates-themes-header' ).fadeIn();
		
		}
		
	});
		
}(jQuery));
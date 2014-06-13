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
		 * Find all the update rows and tweak actual theme/plugin row.
		 */
		jQuery( 'table.plugins tr.lock-your-updates-update-tr' ).each( function() {
		
			// Get file (plugin or theme) name
			var $file = jQuery( this ).data( 'file' );
			if ( $file !== undefined && $file !== null ) {
				
				// Add 'update' class to theme row
				jQuery( 'tr#' + $file ).addClass( 'update' );
			
			}
		
		});
		
		/**
		 * Add confirmation messages.
		 *
		 * WordPress won't let us add messages in core
		 * so we have to do so by script.
		 */
		 
	 	/**
	 	 * Nothing below goes on non-multisite themes
	 	 * pages because they dont use the list table.
	 	 */
		if ( ! lock_your_updates.is_multisite && 'themes' == lock_your_updates.type )
			return;
			
		// Make sure the wrap header exists
		if ( jQuery( '.wrap > h2' ).length > 0 ) {
		
			// Figure out action and message
			var $action = '';
			var $message_type = '';
			var $message = '';
			
			// Figure out parameters
			var $params = window.location.search.substring(1);
			$params = $params.split( '&' );
			jQuery.each( $params, function( $index, $value ) {
			
				var $pair = $value.split( '=' );
				if ( $pair.length >= 2 ) {
				
					if ( 'action' == $pair[0] )
						$action = $pair[1];
					
					else if ( 'empty' == $pair[0] ) {
					
						$message_type = 'empty';
						$message = $pair[1];
						
					} else if ( 'error' == $pair[0] ) {
					
						$message_type = 'error';
						$message = $pair[1];
						
					} else if ( 'success' == $pair[0] ) {
					
						$message_type = 'success';
						$message = $pair[1];
						
					}
				
				}
			
			});
			
			// Create and add messages
			if ( $action != '' && $message_type != '' && $message != '' ) {
				
				// This will hold our message to show
				var $message_to_show = '';
				var $message_class = ( 'success' == $message_type ) ? 'updated' : 'error';
				
				if ( $message_type in lock_your_updates.confirmations && $action in lock_your_updates.confirmations[ $message_type ] )
					$message_to_show = lock_your_updates.confirmations[ $message_type ][ $action ];
				
				if ( $message_to_show != '' )
					jQuery( '.wrap > h2' ).after( '<div id="message" class="' + $message_class + '"><p>' + $message_to_show + '</p></div>' );
					
			}
			
		}
		
		/**
		 * Add to bulk actions.
		 *
		 * WordPress won't let use add to bulk actions in core
		 * so we have to do so by script.
		 */
	 	
	 	// Make sure the actions exist
		if ( jQuery( '.bulkactions' ).length > 0 ) {
		
			// Add to actions dropdown
			jQuery( '.bulkactions' ).find( 'select[name="action"]' ).append( '<option value="lock-selected-updates">' + lock_your_updates.lock_updates_text + '</option><option value="unlock-selected-updates">' + lock_your_updates.unlock_updates_text + '</option>' );
			
		}
		
		/**
		 * Find the list table and activate the note icons.
		 *
		 * Can't include 'themes' as a class for the table because
		 * the themes page uses 'plugins' as a class.
		 */
		var $wp_list_table = jQuery( 'table.wp-list-table' );
		var $wp_list_table_body = $wp_list_table.find( 'tbody' );
		$wp_list_table_body.children( 'tr' ).each( function() {
		
			// When you click any links to edit the notes.
			jQuery( this ).find( '.lock-your-updates-edit-notes' ).on( 'click', function( $event ) {
				$event.preventDefault();
				
				// Build array of edit notes arguments.
				var $args = {};
				var $parts = jQuery( this ).attr( 'href' ).replace( /[?&]+([^=&]+)=([^&]*)/gi, function( $m, $key, $value ) {
					$args[ $key ] = $value;
				});
								
				jQuery( this ).closest( 'tr' ).lock_your_updates_list_table_edit_note( $args );
			
			});
		
		});
		
		/**
		 * This function is invoked by the plugin's
		 * or theme's original row in the list table.
		 *
		 * The $args are provided via the "edit notes" link.
		 */
		jQuery.fn.lock_your_updates_list_table_edit_note = function( $args ) {
		
			// The action, type and nonce are required.
			if ( ! ( 'action' in $args && ( 'lock-your-updates-edit-notes-' + lock_your_updates.type ) == $args.action
				&& lock_your_updates.type in $args && $args[ lock_your_updates.type ] != ''
				&& '_wpnonce' in $args && $args._wpnonce != '' ) ) {
				
				return;
				
			} else {
			
				// Get item information.
				var $item_row = jQuery( this );
				
				// We gotta have a row ID
				if ( $item_row.attr( 'id' ) == '' )
					return;
				
				// We need to know how many <td>s we need
				var $item_row_children_count = $item_row.children().length;
								
				// This is the HTML ID used for this item
				var $item_html_id = $item_row.attr( 'id' );
				
				// Is there an update row?
				var $update_row = undefined;
				if ( jQuery( '#lock-your-updates-update-' + $item_html_id + '-row' ).length > 0 )
					$update_row = jQuery( '#lock-your-updates-update-' + $item_html_id + '-row' );
				else if ( $item_row.next().hasClass( 'plugin-update-tr' ) )
					$update_row = $item_row.next();
				
				// Create edit notes ID
				var $edit_notes_row_id = 'lock-your-updates-edit-' + $item_html_id + '-notes';
								
				// If the row already exists
				if ( jQuery( 'tr#' + $edit_notes_row_id ).length > 0 )
					return;
					
				// Create the inline edit note row.
				var $edit_notes_row = jQuery( '<tr id="' + $edit_notes_row_id + '" class="lock_your_updates_inline_edit_notes inline-edit-row"></tr>' );
				
				// Add active class
				if ( $item_row.hasClass( 'active' ) )
					$edit_notes_row.addClass( 'active' );
					
				// Add update class
				if ( $item_row.hasClass( 'update' ) )
					$edit_notes_row.addClass( 'update' );
				
				// Create row wrapper
				var $edit_notes_row_td_wrapper = jQuery( '<td class="wrapper loading"></td>' );
				
				// Set height to match item row so row shows up immediately
				$edit_notes_row_td_wrapper.height( $item_row.outerHeight() );
				
				// Make sure the row wrapper fits the entire row
				if ( $item_row_children_count > 1 )
					$edit_notes_row_td_wrapper.attr( 'colspan', $item_row_children_count );
					
				// Add wrapper
				$edit_notes_row.append( $edit_notes_row_td_wrapper );
				
				// Hide the original item row.
				$item_row.hide();
				
				// If update row exists, add edit notes row after update row
				if ( $update_row !== undefined && $update_row.length > 0 ) {
				
					// Insert edit notes row
					$edit_notes_row.hide().insertAfter( $update_row ).fadeIn();
					
					// Hide update row
					$update_row.hide();
					
				}
				
				// Insert edit notes row after the original item row.
				else
					$edit_notes_row.hide().insertAfter( $item_row ).fadeIn();
					
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
					
						// Show an error message
						$item_row.lock_your_updates_list_table_edit_notes_error();
						return;
						
					},
					success: function( $data, $textStatus, $jqXHR ) {
						
						// Data is required.
						if ( ! $data || typeof $data === undefined || $data == null ) {
						
							// Show an error message
							$item_row.lock_your_updates_list_table_edit_notes_error();
							return;
							
						}
							
						// User must pass the nonce test.
						if ( ! ( typeof $data.passed_nonce !== undefined && $data.passed_nonce != null && $data.passed_nonce ) ) {
						
							// Show an error message
							$item_row.lock_your_updates_list_table_edit_notes_error();
							return;
						
						}
						
						// Data must have the item's name and whether or not it's locked.
						else if ( ! ( typeof $data.name !== undefined && $data.name != null && typeof $data.locked !== undefined && $data.locked != null ) ) {
						
							// Show an error message
							$item_row.lock_your_updates_list_table_edit_notes_error();
							return;
							
						}
							
						// Create main table
						var $edit_notes_row_table = jQuery( '<table cellpadding="0" cellspacing="0" border="0"></table>' );
						
						// Add row
						$edit_notes_row_table.append( '<tr></tr>' );
							
						// Create icon td
						var $edit_notes_row_td_icon = jQuery( '<td class="icon"></td>' );
						
						// Add icon
						if ( $data.locked )
							$edit_notes_row_td_icon.append( '<div class="locked-update-icon"></div>' );
						else
							$edit_notes_row_td_icon.append( '<div class="unlocked-update-icon"></div>' );
						
						// Add icon to table
						$edit_notes_row_table.find( 'tr' ).append( $edit_notes_row_td_icon ).fadeIn();
							
						// create main td
						var $edit_notes_row_td_main = jQuery( '<td class="main"></td>' );
						
						// add header
						$edit_notes_row_td_main.append( '<h3><span class="notes-for">' + lock_your_updates.notes_for + '</span> ' + $data.name + '</h3>' );
						
						if ( $data.locked )
							$edit_notes_row_td_main.append( '<h4 class="locked">' + lock_your_updates.type_locked + '</h4>' );
						else
							$edit_notes_row_td_main.append( '<h4 class="unlocked">' + lock_your_updates.type_unlocked + '</h4>' );
						
						// Create text area
						var $edit_notes_row_textarea = jQuery( '<textarea></textarea>' );
						
						// Get the item's saved notes and add to textarea.
						if ( $data.notes != undefined && $data.notes != null )
							$edit_notes_row_textarea.val( $data.notes );
						
						// Add text area to main area
						$edit_notes_row_td_main.append( $edit_notes_row_textarea );
						
						// Create save button
						var $edit_notes_row_save_button = jQuery( '<a class="button-primary save alignleft" href="" accesskey="s">Save</a>' );
						
						// When you click the link to save the notes.
						$edit_notes_row_save_button.on( 'click', function( $event ) {
							$event.preventDefault();
							
							$item_row.lock_your_updates_list_table_save_note( $args, $edit_notes_row_textarea.val() );
						
						});
						
						// Add save button to main area
						$edit_notes_row_td_main.append( $edit_notes_row_save_button );
						
						// Create cancel button
						var $edit_notes_row_cancel_button = jQuery( '<a class="button-secondary cancel alignleft" href="" accesskey="c">Cancel</a>' );
						
						// When you click the link to cancel any edits.
						$edit_notes_row_cancel_button.on( 'click', function( $event ) {
							$event.preventDefault();
							
							$item_row.lock_your_updates_list_table_close_edit_note();
						
						});
						
						// Add cancel button to main area
						$edit_notes_row_td_main.append( $edit_notes_row_cancel_button );
						
						// Add main area
						$edit_notes_row_table.find( 'tr' ).append( $edit_notes_row_td_main );
						
						// Add table to td wrapper
						$edit_notes_row_td_wrapper.append( $edit_notes_row_table );
						
						// Reset wrapper height and remove loading class
						$edit_notes_row_td_wrapper.removeClass( 'loading' ).css( 'height', 'auto' );
						
					},
					complete: function( $jqXHR, $textStatus ) {}
				});
				
			}
			
		}
		
		/**
		 * This function is invoked by the plugin's
		 * or theme's original row in the list table.
		 */
		jQuery.fn.lock_your_updates_list_table_save_note = function( $args, $notes ) {
		
			// The action, type and nonce are required.
			if ( ! ( 'action' in $args && ( 'lock-your-updates-edit-notes-' + lock_your_updates.type ) == $args.action
				&& lock_your_updates.type in $args && $args[ lock_your_updates.type ] != ''
				&& '_wpnonce' in $args && $args._wpnonce != '' ) ) {
				
				return;
				
			} else {
			
				// Get item information.
				var $item_row = jQuery( this );
				
				// We gotta have a row ID
				if ( $item_row.attr( 'id' ) == '' )
					return;
					
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
						 * The notes were saved and all is
						 * well so update the notes icon
						 * and close the inline edit row.
						 */
						if ( 'item_notes' in $data ) {
							
							// Get the notes icon
							var $notes_icon = $item_row.find( 'td.column-lock-your-updates' ).find( '.icon-wrapper.notes' );
							
							// Add or remove 'empty' class
							if ( $data.item_notes != null && $data.item_notes != '' )
								$notes_icon.removeClass( 'empty' );
							else
								$notes_icon.addClass( 'empty' );
								
						}
						
						// Close inline edit row
						$item_row.lock_your_updates_list_table_close_edit_note();
						
						// Create saved checkmark
						var $checkmark = jQuery( '<div class="lock-your-updates-checkmark"></div>' ).hide();
						
						// Show saved checkmark
						$checkmark.appendTo( jQuery( 'body' ) ).css({ 'width': $item_row.outerWidth(), 'height': $item_row.outerHeight(), 'left': $item_row.offset().left +'px', 'top': $item_row.offset().top + 'px', 'background-size': $item_row.outerHeight() + 'px' }).show().animate({
						 	opacity: 0,
						 }, 1000, function() {
						 
						 	// Remove the checkmark
						 	$checkmark.remove();
						 	
						 });
						 
					},
					complete: function( $jqXHR, $textStatus ) {}
				});
			
			}
		
		}
		
		/**
		 * This function is invoked by the plugin's
		 * or theme's original row in the list table.
		 */
		jQuery.fn.lock_your_updates_list_table_close_edit_note = function() {
		
			// Get item information.
			var $item_row = jQuery( this );
			var $item_html_id = $item_row.attr( 'id' );
			
			// Remove the inline edit row.
			jQuery( 'tr#lock-your-updates-edit-' + $item_html_id + '-notes' ).remove();
			
			// Show the original item row.
			$item_row.fadeIn();
			
			// Show the update row, if exists
			if ( jQuery( '#lock-your-updates-update-' + $item_html_id + '-row' ).length > 0 )
				jQuery( '#lock-your-updates-update-' + $item_html_id + '-row' ).fadeIn();
			else if ( $item_row.next().hasClass( 'plugin-update-tr' ) )
				$item_row.next().fadeIn();
				
		}
		
		/**
		 * This function is invoked by the plugin's
		 * or theme's original row in the list table.
		 */
		jQuery.fn.lock_your_updates_list_table_edit_notes_error = function( $message ) {
		
			// Get item information.
			var $item_row = jQuery( this );
			var $item_html_id = $item_row.attr( 'id' );
			
			// This will hold the error message
			var $edit_notes_wrapper = jQuery( 'tr#lock-your-updates-edit-' + $item_html_id + '-notes td.wrapper' );
			
			// If a message wasn't sent, use a default.
			if ( $message === undefined || $message == '' )
				$message = lock_your_updates.errors.load_notes;
				
			// Create close button
			var $close_button = jQuery( '<a class="button-secondary close" href="" accesskey="c">Close this message</a>' );
			
			// When you click the link to close the error message
			$close_button.on( 'click', function( $event ) {
				$event.preventDefault();
				
				$item_row.lock_your_updates_list_table_close_edit_note();
			
			});
			
			// Add message and close button to wrapper
			$edit_notes_wrapper.append( '<p>' + $message + '</p>' ).append( $close_button ).removeClass( 'loading' ).addClass( 'error' );
		
		}
				
	});
		
}(jQuery));
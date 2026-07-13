<?php
/**
 * Speed Doctor Uninstall Script.
 *
 * This file is run when the plugin is deleted from the WordPress admin panel.
 *
 * @package Speed Doctor
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete settings option.
delete_option( 'spdr_settings' );

// Remove Gzip/Expires rules from .htaccess if they exist.
$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );
if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	insert_with_markers( $htaccess_file, 'SpeedDoctor', array() );
}

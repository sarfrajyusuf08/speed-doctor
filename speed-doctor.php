<?php
/**
 * Plugin Name:       Speed Doctor
 * Plugin URI:        https://wpdoctorpro.com/products/speed-doctor
 * Description:       A lightweight performance optimization plugin for WordPress.
 * Version:           1.0.0
 * Author:            WP Doctor Pro
 * Text Domain:       speed-doctor
 * Domain Path:       /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'SPDR_VERSION', '1.0.0' );
define( 'SPDR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPDR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin activation callback.
 */
function spdr_activate() {
	// Initialize default settings if they do not exist.
	$default_settings = array(
		'page_cache'      => 0,
		'db_optimization' => 0,
		'lazy_load'       => 0,
	);

	if ( false === get_option( 'spdr_settings' ) ) {
		update_option( 'spdr_settings', $default_settings );
	}

	// Schedule the hourly cache cleanup cron if not already scheduled.
	if ( ! wp_next_scheduled( 'spdr_clean_expired_cache' ) ) {
		wp_schedule_event( time(), 'hourly', 'spdr_clean_expired_cache' );
	}

	// Schedule the weekly DB cleanup cron if not already scheduled.
	if ( ! wp_next_scheduled( 'spdr_db_optimization_cron' ) ) {
		wp_schedule_event( time(), 'weekly', 'spdr_db_optimization_cron' );
	}
}
register_activation_hook( __FILE__, 'spdr_activate' );

/**
 * Plugin deactivation callback.
 */
function spdr_deactivate() {
	// Clear scheduled cron events to prevent orphaned tasks.
	wp_clear_scheduled_hook( 'spdr_clean_expired_cache' );
	wp_clear_scheduled_hook( 'spdr_db_optimization_cron' );
}
register_deactivation_hook( __FILE__, 'spdr_deactivate' );

// Load Core Plugin Class.
require_once SPDR_PATH . 'includes/class-spdr.php';

/**
 * Get instance and run the plugin.
 */
function spdr_run() {
	SPDR::get_instance();
}
spdr_run();



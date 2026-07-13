<?php
/**
 * Core Plugin Class.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return SPDR
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies() {
		require_once SPDR_PATH . 'admin/admin-page.php';
		require_once SPDR_PATH . 'includes/class-spdr-cache.php';
		require_once SPDR_PATH . 'includes/class-spdr-db.php';
		require_once SPDR_PATH . 'includes/class-spdr-assets.php';
		require_once SPDR_PATH . 'includes/class-spdr-media.php';
		require_once SPDR_PATH . 'includes/class-spdr-admin.php';
		require_once SPDR_PATH . 'includes/class-spdr-htaccess.php';
		require_once SPDR_PATH . 'includes/class-spdr-purge-helper.php';
	}

	/**
	 * Initialize plugin components.
	 */
	public function init() {
		// Initialize Modules.
		SPDR_Cache::get_instance();
		SPDR_DB::get_instance();
		SPDR_Assets::get_instance();
		SPDR_Media::get_instance();

		// Load admin configurations on administration screens and AJAX triggers
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			SPDR_Admin::get_instance();
		}

		// Schedule Database Cleanup WP-Cron dynamically.
		$options  = get_option( 'spdr_settings' );
		$schedule = isset( $options['db_cleanup_schedule'] ) ? $options['db_cleanup_schedule'] : 'disabled';
		$current  = wp_get_schedule( 'spdr_db_cleanup_cron' );

		if ( 'disabled' !== $schedule ) {
			if ( $current !== $schedule ) {
				wp_clear_scheduled_hook( 'spdr_db_cleanup_cron' );
				wp_schedule_event( time(), $schedule, 'spdr_db_cleanup_cron' );
			}
		} else {
			if ( false !== $current ) {
				wp_clear_scheduled_hook( 'spdr_db_cleanup_cron' );
			}
		}
	}

	/**
	 * Format bytes to human readable format.
	 *
	 * @param int $bytes Number of bytes.
	 * @return string Formatted size.
	 */
	public static function format_bytes( $bytes ) {
		if ( $bytes <= 0 ) {
			return '0 B';
		}
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$i     = floor( log( $bytes, 1024 ) );
		return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ $i ];
	}
}

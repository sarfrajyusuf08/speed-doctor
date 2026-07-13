<?php
/**
 * Speed Doctor Htaccess rewrite manager.
 *
 * @package Speed Doctor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR_Htaccess {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR_Htaccess
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return SPDR_Htaccess
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
	private function __construct() {}

	/**
	 * Write page cache rewrite rules to .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function write_rules() {
		// Only run on Apache/LiteSpeed.
		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) && false === strpos( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) ) ) {
			return false;
		}

		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );

		if ( ! is_writable( $htaccess_file ) && ! is_writable( dirname( $htaccess_file ) ) ) {
			return false;
		}

		$options = get_option( 'spdr_settings' );
		$mobile_cache = ! empty( $options['mobile_cache'] ) ? 1 : 0;
		$logged_in_cache = ! empty( $options['logged_in_cache'] ) ? 1 : 0;

		$rules = array();
		$rules[] = '<IfModule mod_rewrite.c>';
		$rules[] = '    RewriteEngine On';
		$rules[] = '    RewriteBase /';
		$rules[] = '    ';
		$rules[] = '    # Exclude POST requests';
		$rules[] = '    RewriteCond %{REQUEST_METHOD} !POST';
		$rules[] = '    ';
		$rules[] = '    # Exclude Query Strings';
		$rules[] = '    RewriteCond %{QUERY_STRING} !.+';

		// Exclude logged-in users if logged-in caching is disabled
		if ( ! $logged_in_cache ) {
			$rules[] = '    # Exclude Logged-in Users';
			$rules[] = '    RewriteCond %{HTTP:Cookie} !wordpress_logged_in';
		}

		// Exclude mobile user agents if mobile caching is disabled
		if ( ! $mobile_cache ) {
			$rules[] = '    # Exclude Mobile Users';
			$rules[] = '    RewriteCond %{HTTP_USER_AGENT} !(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge\s|maemo|midp|mmp|mobile.+firefox|netfront|opera\sm(ob|in)i|palm(os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows\sce|xda|xiino [NC]';
		}

		// Rewrite rule for subpages with trailing slash
		$rules[] = '    # Serve cached pages with trailing slash';
		$rules[] = '    RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speed-doctor/html/$1index.html -f';
		$rules[] = '    RewriteRule ^(.*)/$ "/wp-content/cache/speed-doctor/html/$1index.html" [L]';

		// Rewrite rule for subpages without trailing slash
		$rules[] = '    # Serve cached pages without trailing slash';
		$rules[] = '    RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speed-doctor/html/$1/index.html -f';
		$rules[] = '    RewriteRule ^(.*)$ "/wp-content/cache/speed-doctor/html/$1/index.html" [L]';

		// Rewrite rule for homepage
		$rules[] = '    # Serve homepage cache';
		$rules[] = '    RewriteCond %{REQUEST_URI} ^/$';
		$rules[] = '    RewriteCond %{DOCUMENT_ROOT}/wp-content/cache/speed-doctor/html/index.html -f';
		$rules[] = '    RewriteRule ^$ "/wp-content/cache/speed-doctor/html/index.html" [L]';

		$rules[] = '</IfModule>';

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return insert_with_markers( $htaccess_file, 'SpeedDoctorCache', $rules );
	}

	/**
	 * Remove rewrite rules from .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_rules() {
		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );
		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return false;
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return insert_with_markers( $htaccess_file, 'SpeedDoctorCache', array() );
	}
}

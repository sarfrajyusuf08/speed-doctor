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

		// Calculate server-side absolute caching folder path
		$cache_dir_path = wp_normalize_path( WP_CONTENT_DIR . '/cache/speed-doctor/html/' );

		// Calculate relative subdirectory prefix and target path
		$site_path       = wp_parse_url( home_url(), PHP_URL_PATH );
		$site_path_clean = $site_path ? '/' . trim( $site_path, '/' ) : '';
		$sub_dir_prefix  = $site_path_clean ? trim( $site_path_clean, '/' ) . '/' : '';
		$target_prefix   = rtrim( $site_path_clean, '/' ) . '/wp-content/cache/speed-doctor/html/' . $sub_dir_prefix;

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
		$rules[] = '    RewriteCond ' . $cache_dir_path . $sub_dir_prefix . '$1index.html -f';
		$rules[] = '    RewriteRule ^(.*)/$ "' . $target_prefix . '$1index.html" [L]';

		// Rewrite rule for subpages without trailing slash
		$rules[] = '    # Serve cached pages without trailing slash';
		$rules[] = '    RewriteCond ' . $cache_dir_path . $sub_dir_prefix . '$1/index.html -f';
		$rules[] = '    RewriteRule ^(.*)$ "' . $target_prefix . '$1/index.html" [L]';

		// Rewrite rule for homepage
		$rules[] = '    # Serve homepage cache';
		$rules[] = '    RewriteCond %{REQUEST_URI} ^' . ( $site_path_clean ? $site_path_clean : '' ) . '/?$';
		$rules[] = '    RewriteCond ' . $cache_dir_path . $sub_dir_prefix . 'index.html -f';
		$rules[] = '    RewriteRule ^$ "' . $target_prefix . 'index.html" [L]';

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

	/**
	 * Write GZIP compression and pre-compressed asset serving rules to .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function write_gzip_rules() {
		// Only run on Apache/LiteSpeed.
		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) && false === strpos( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) ) ) {
			return false;
		}

		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );

		if ( ! is_writable( $htaccess_file ) && ! is_writable( dirname( $htaccess_file ) ) ) {
			return false;
		}

		$rules = array();

		// 1. Deflate on-the-fly compression for dynamic content
		$rules[] = '<IfModule mod_deflate.c>';
		$rules[] = '    AddType x-font/woff .woff';
		$rules[] = '    AddType x-font/ttf .ttf';
		$rules[] = '    AddOutputFilterByType DEFLATE image/svg+xml';
		$rules[] = '    AddOutputFilterByType DEFLATE text/plain';
		$rules[] = '    AddOutputFilterByType DEFLATE text/html';
		$rules[] = '    AddOutputFilterByType DEFLATE text/xml';
		$rules[] = '    AddOutputFilterByType DEFLATE text/css';
		$rules[] = '    AddOutputFilterByType DEFLATE text/javascript';
		$rules[] = '    AddOutputFilterByType DEFLATE application/xml';
		$rules[] = '    AddOutputFilterByType DEFLATE application/xhtml+xml';
		$rules[] = '    AddOutputFilterByType DEFLATE application/rss+xml';
		$rules[] = '    AddOutputFilterByType DEFLATE application/javascript';
		$rules[] = '    AddOutputFilterByType DEFLATE application/x-javascript';
		$rules[] = '    AddOutputFilterByType DEFLATE application/x-font-ttf';
		$rules[] = '    AddOutputFilterByType DEFLATE x-font/ttf';
		$rules[] = '    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject';
		$rules[] = '    AddOutputFilterByType DEFLATE font/opentype font/ttf font/eot font/otf';
		$rules[] = '</IfModule>';

		// 2. Direct serving of pre-compressed combined/minified assets (.css.gz / .js.gz)
		$rules[] = '<IfModule mod_rewrite.c>';
		$rules[] = '    RewriteEngine On';
		$rules[] = '    ';
		$rules[] = '    # Check if client accepts gzip encoding';
		$rules[] = '    RewriteCond %{HTTP:Accept-encoding} gzip';
		$rules[] = '    ';
		$rules[] = '    # Verify if pre-compressed asset clone exists';
		$rules[] = '    RewriteCond %{REQUEST_FILENAME}\.gz -s';
		$rules[] = '    ';
		$rules[] = '    # Rewrite asset link to serve gzip clone directly';
		$rules[] = '    RewriteRule ^(.*)\.(css|js)$ $1\.$2\.gz [QSA,L]';
		$rules[] = '    ';
		$rules[] = '    # Configure headers to prevent double compression and enforce correct mime type';
		$rules[] = '    RewriteRule \.css\.gz$ - [T=text/css,E=no-gzip:1,E=FORCE_GZIP]';
		$rules[] = '    RewriteRule \.js\.gz$ - [T=text/javascript,E=no-gzip:1,E=FORCE_GZIP]';
		$rules[] = '    ';
		$rules[] = '    <FilesMatch "\.(css|js)\.gz$">';
		$rules[] = '        Header set Content-Encoding gzip env=FORCE_GZIP';
		$rules[] = '        Header append Vary Accept-Encoding';
		$rules[] = '    </FilesMatch>';
		$rules[] = '</IfModule>';

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return insert_with_markers( $htaccess_file, 'SpeedDoctorGzip', $rules );
	}

	/**
	 * Remove GZIP rewrite rules from .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_gzip_rules() {
		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );
		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return false;
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return insert_with_markers( $htaccess_file, 'SpeedDoctorGzip', array() );
	}
}

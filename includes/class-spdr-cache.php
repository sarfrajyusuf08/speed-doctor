<?php
/**
 * Page Cache Module Class.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR_Cache {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR_Cache
	 */
	private static $instance = null;

	/**
	 * Cache base directory path.
	 *
	 * @var string
	 */
	private $cache_dir;

	/**
	 * Get instance.
	 *
	 * @return SPDR_Cache
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
		$this->cache_dir = wp_normalize_path( WP_CONTENT_DIR . '/cache/speed-doctor/' );
		
		// Setup hooks.
		add_action( 'init', array( $this, 'maybe_setup_cache_dir' ) );
		add_action( 'template_redirect', array( $this, 'serve_cached_page' ), 1 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ) );
		add_action( 'template_redirect', array( $this, 'send_cache_headers' ), 5 );
		add_action( 'spdr_clean_expired_cache', array( $this, 'clean_expired_cache' ) );

		// Purge hooks.
		add_action( 'save_post', array( $this, 'purge_post_cache' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_purge_button' ), 99 );
		add_action( 'admin_init', array( $this, 'handle_admin_bar_purge' ) );
		add_action( 'admin_notices', array( $this, 'show_purge_notice' ) );
	}

	/**
	 * Setup cache directory if it does not exist.
	 */
	public function maybe_setup_cache_dir() {
		// Only run when settings have page cache enabled.
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['page_cache'] ) ) {
			// If disabled, make sure rules are removed.
			$this->remove_htaccess_rules();
			SPDR_Htaccess::get_instance()->remove_rules();
			return;
		}

		if ( ! file_exists( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );
		}

		// Write .htaccess rules when enabled.
		$this->write_htaccess_rules();
		SPDR_Htaccess::get_instance()->write_rules();
	}

	/**
	 * Clean expired cached static HTML files.
	 */
	public function clean_expired_cache() {
		$html_dir = wp_normalize_path( $this->cache_dir . 'html/' );
		if ( ! file_exists( $html_dir ) ) {
			return;
		}

		$options  = get_option( 'spdr_settings' );
		$lifespan = isset( $options['cache_lifespan'] ) ? (int) $options['cache_lifespan'] : 86400;
		$now      = time();

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $html_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $files as $file ) {
				$real_path = $file->getPathname();
				if ( $file->isFile() && $file->getExtension() === 'html' ) {
					$file_age = $now - filemtime( $real_path );
					if ( $file_age > $lifespan ) {
						unlink( $real_path );
					}
				} elseif ( $file->isDir() ) {
					// Clean up empty directories
					$empty = true;
					$dir_files = scandir( $real_path );
					foreach ( $dir_files as $df ) {
						if ( $df !== '.' && $df !== '..' ) {
							$empty = false;
							break;
						}
					}
					if ( $empty ) {
						rmdir( $real_path );
					}
				}
			}
		} catch ( Exception $e ) {
			// Fail-safe default
		}
	}

	/**
	 * Send HTTP cache headers for cacheable pages.
	 */
	public function send_cache_headers() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['page_cache'] ) ) {
			return;
		}

		if ( $this->should_bypass_cache() ) {
			return;
		}

		if ( ! headers_sent() ) {
			header( 'Cache-Control: public, max-age=3600' );
			header( 'Pragma: cache' );
		}
	}

	/**
	 * Write Gzip and Browser Caching rules to .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function write_htaccess_rules() {
		// Only run on Apache/LiteSpeed.
		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) && false === strpos( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) ) ) {
			return false;
		}

		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );

		if ( ! is_writable( $htaccess_file ) && ! is_writable( dirname( $htaccess_file ) ) ) {
			return false;
		}

		$rules = array(
			'<IfModule mod_deflate.c>',
			'    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript application/xml application/json',
			'</IfModule>',
			'<IfModule mod_expires.c>',
			'    ExpiresActive On',
			'    ExpiresDefault "access plus 1 hour"',
			'    ExpiresByType text/html "access plus 1 hour"',
			'    ExpiresByType text/css "access plus 1 month"',
			'    ExpiresByType application/javascript "access plus 1 month"',
			'    ExpiresByType image/gif "access plus 1 month"',
			'    ExpiresByType image/jpeg "access plus 1 month"',
			'    ExpiresByType image/png "access plus 1 month"',
			'</IfModule>',
		);

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		return insert_with_markers( $htaccess_file, 'SpeedDoctor', $rules );
	}

	/**
	 * Remove Gzip and Browser Caching rules from .htaccess.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_htaccess_rules() {
		$htaccess_file = wp_normalize_path( ABSPATH . '.htaccess' );
		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return false;
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		return insert_with_markers( $htaccess_file, 'SpeedDoctor', array() );
	}

	/**
	 * Purge all cached HTML files.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function purge_all_cache() {
		return $this->purge_cache_by_type( 'all' );
	}

	/**
	 * Purge cache by specific type (html, css, js, all).
	 *
	 * @param string $type The cache type.
	 * @return bool True on success.
	 */
	public function purge_cache_by_type( $type ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! file_exists( $this->cache_dir ) ) {
			return true;
		}

		$html_dir   = wp_normalize_path( $this->cache_dir . 'html/' );
		$assets_dir = wp_normalize_path( $this->cache_dir . 'assets/' );

		switch ( $type ) {
			case 'html':
				$this->delete_dir_contents_recursive( $html_dir );
				break;

			case 'css':
				if ( is_dir( $assets_dir ) ) {
					$files = glob( $assets_dir . '*.css' );
					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							if ( is_file( $file ) && 'index.php' !== basename( $file ) ) {
								unlink( $file );
							}
						}
					}
				}
				break;

			case 'js':
				if ( is_dir( $assets_dir ) ) {
					$files = glob( $assets_dir . '*.js' );
					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							if ( is_file( $file ) && 'index.php' !== basename( $file ) ) {
								unlink( $file );
							}
						}
					}
				}
				break;

			case 'all':
			default:
				$this->delete_dir_contents_recursive( $html_dir );
				$this->delete_dir_contents_recursive( $assets_dir );
				break;
		}

		return true;
	}

	/**
	 * Recursively delete folder contents.
	 *
	 * @param string $dir Path to directory.
	 */
	private function delete_dir_contents_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $files as $file ) {
				$real_path = $file->getPathname();
				if ( $file->isDir() ) {
					rmdir( $real_path );
				} else {
					unlink( $real_path );
				}
			}
		} catch ( Exception $e ) {
			// Fail-safe
		}
	}

	/**
	 * Purge cache file for a specific post when updated.
	 *
	 * @param int $post_id Post ID.
	 */
	public function purge_post_cache( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return;
		}

		$url_path  = wp_parse_url( $url, PHP_URL_PATH );
		$path_slug = trim( $url_path, '/' );

		if ( empty( $path_slug ) ) {
			// Homepage
			$dir = wp_normalize_path( $this->cache_dir . 'html/' );
			$files = glob( $dir . 'index*.html' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					unlink( $file );
				}
			}
		} else {
			// Subpage folder
			$dir = wp_normalize_path( $this->cache_dir . 'html/' . $path_slug . '/' );
			if ( is_dir( $dir ) ) {
				$files = glob( $dir . 'index*.html' );
				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						unlink( $file );
					}
				}
			}
		}

		// Also clear homepage cache
		$home_dir = wp_normalize_path( $this->cache_dir . 'html/' );
		$home_files = glob( $home_dir . 'index*.html' );
		if ( is_array( $home_files ) ) {
			foreach ( $home_files as $file ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Add custom selective cache purge dropdown menu to the WP Admin Bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function add_admin_bar_purge_button( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1. Add Parent Node: "Speed Doctor"
		$wp_admin_bar->add_node(
			array(
				'id'    => 'speed-doctor-bar',
				'title' => esc_html__( 'Speed Doctor', 'speed-doctor' ),
				'href'  => admin_url( 'admin.php?page=speed-doctor' ),
			)
		);

		// 2. Submenu: Purge HTML Structure
		$wp_admin_bar->add_node(
			array(
				'id'     => 'spdr-bar-purge-html',
				'title'  => esc_html__( 'Purge HTML Structure', 'speed-doctor' ),
				'parent' => 'speed-doctor-bar',
				'href'   => wp_nonce_url( add_query_arg( 'spdr_action', 'purge_html' ), 'spdr_purge_nonce' ),
			)
		);

		// 3. Submenu: Purge CSS Cache
		$wp_admin_bar->add_node(
			array(
				'id'     => 'spdr-bar-purge-css',
				'title'  => esc_html__( 'Purge CSS Cache', 'speed-doctor' ),
				'parent' => 'speed-doctor-bar',
				'href'   => wp_nonce_url( add_query_arg( 'spdr_action', 'purge_css' ), 'spdr_purge_nonce' ),
			)
		);

		// 4. Submenu: Purge JS Cache
		$wp_admin_bar->add_node(
			array(
				'id'     => 'spdr-bar-purge-js',
				'title'  => esc_html__( 'Purge JS Cache', 'speed-doctor' ),
				'parent' => 'speed-doctor-bar',
				'href'   => wp_nonce_url( add_query_arg( 'spdr_action', 'purge_js' ), 'spdr_purge_nonce' ),
			)
		);

		// 5. Submenu: Purge All
		$wp_admin_bar->add_node(
			array(
				'id'     => 'spdr-bar-purge-all',
				'title'  => esc_html__( 'Purge All Cache', 'speed-doctor' ),
				'parent' => 'speed-doctor-bar',
				'href'   => wp_nonce_url( add_query_arg( 'spdr_action', 'purge_all' ), 'spdr_purge_nonce' ),
			)
		);
	}

	/**
	 * Handle admin bar clear cache trigger.
	 */
	public function handle_admin_bar_purge() {
		if ( isset( $_GET['spdr_action'] ) && in_array( $_GET['spdr_action'], array( 'purge_all', 'purge_html', 'purge_css', 'purge_js' ), true ) ) {
			// Verify permissions and nonce.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'speed-doctor' ) );
			}

			check_admin_referer( 'spdr_purge_nonce' );

			$action = sanitize_text_field( wp_unslash( $_GET['spdr_action'] ) );
			
			switch ( $action ) {
				case 'purge_html':
					$this->purge_cache_by_type( 'html' );
					$msg_type = 'html_purged';
					break;
				case 'purge_css':
					$this->purge_cache_by_type( 'css' );
					$msg_type = 'css_purged';
					break;
				case 'purge_js':
					$this->purge_cache_by_type( 'js' );
					$msg_type = 'js_purged';
					break;
				case 'purge_all':
				default:
					$this->purge_cache_by_type( 'all' );
					$msg_type = 'all_purged';
					break;
			}

			// Redirect back to avoid repeating actions.
			$redirect_url = remove_query_arg( array( 'spdr_action', '_wpnonce' ) );
			wp_safe_redirect( add_query_arg( 'spdr_cache_purged', $msg_type, $redirect_url ) );
			exit;
		}
	}

	/**
	 * Show success notice after cache purge.
	 */
	public function show_purge_notice() {
		if ( isset( $_GET['spdr_cache_purged'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_GET['spdr_cache_purged'] ) );
			switch ( $type ) {
				case 'html_purged':
					$message = esc_html__( 'Speed Doctor HTML cache cleared successfully.', 'speed-doctor' );
					break;
				case 'css_purged':
					$message = esc_html__( 'Speed Doctor CSS cache cleared successfully.', 'speed-doctor' );
					break;
				case 'js_purged':
					$message = esc_html__( 'Speed Doctor JS cache cleared successfully.', 'speed-doctor' );
					break;
				case 'all_purged':
				default:
					$message = esc_html__( 'Speed Doctor cache (HTML, CSS, JS) cleared successfully.', 'speed-doctor' );
					break;
			}
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo $message; ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Start the output buffer to capture HTML.
	 */
	public function start_buffer() {
		// Only run if page caching is enabled in settings.
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['page_cache'] ) ) {
			return;
		}

		// Check bypass rules.
		if ( $this->should_bypass_cache() ) {
			return;
		}

		// Start output buffer with a callback.
		ob_start( array( $this, 'end_buffer' ) );
	}

	/**
	 * Determine if the current request should bypass page caching.
	 *
	 * @return bool True if the request should bypass caching, false otherwise.
	 */
	public function should_bypass_cache() {
		// 1. Check if user is logged in.
		if ( is_user_logged_in() ) {
			$options = get_option( 'spdr_settings' );
			if ( empty( $options['logged_in_cache'] ) ) {
				return true;
			}
		}

		// 2. Check if request method is GET.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		// 3. Skip admin, search, 404, feed, and post previews.
		if ( is_admin() || is_search() || is_404() || is_feed() || is_preview() ) {
			return true;
		}

		// 4. Skip if URL contains query strings.
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			return true;
		}

		// Get request URI safely.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// 5. Exclude common eCommerce pages (WooCommerce, EDD).
		$exclude_patterns = array(
			'/cart/',
			'/checkout/',
			'/my-account/',
			'/addons/',
			'wp-login.php',
			'wp-register.php',
		);

		foreach ( $exclude_patterns as $pattern ) {
			if ( false !== strpos( $request_uri, $pattern ) ) {
				return true;
			}
		}

		// 6. Check for active eCommerce/Comment session cookies.
		if ( ! empty( $_COOKIE ) ) {
			$ignored_cookies = array(
				'comment_author_',
				'wp_postpass_',
				'woocommerce_items_in_cart',
				'woocommerce_cart_hash',
				'wp_woocommerce_session_',
				'edd_items_in_cart',
			);

			foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
				foreach ( $ignored_cookies as $ignored_cookie ) {
					if ( 0 === strpos( $cookie_name, $ignored_cookie ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Output buffer callback to save HTML cache file.
	 *
	 * @param string $buffer The HTML content of the page.
	 * @return string The unmodified HTML content.
	 */
	public function end_buffer( $buffer ) {
		// Do not cache if the buffer is empty or if it's not a 200 OK response.
		if ( empty( $buffer ) || ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) ) {
			return $buffer;
		}

		// Construct current page URL safely.
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $host ) ) {
			return $buffer;
		}

		$url       = $scheme . '://' . $host . $uri;
		$file_path = $this->get_cache_file_path( $url );

		// Run Media Lazy Loading and CDN Rewriting if enabled.
		if ( class_exists( 'SPDR_Media' ) ) {
			$media  = SPDR_Media::get_instance();
			$buffer = $media->lazy_load_media( $buffer );
			$buffer = $media->rewrite_cdn_urls( $buffer );
		}

		// Minify CSS, JS, and HTML if options are enabled and class is loaded.
		if ( class_exists( 'SPDR_Assets' ) ) {
			$assets  = SPDR_Assets::get_instance();
			$options = get_option( 'spdr_settings' );
			if ( ! empty( $options['minify_css'] ) ) {
				$buffer = $assets->process_html_css( $buffer );
			}
			if ( ! empty( $options['minify_js'] ) || ! empty( $options['combine_js'] ) ) {
				$buffer = $assets->process_html_js( $buffer );
			}
			if ( ! empty( $options['delay_js'] ) ) {
				$buffer = $assets->delay_javascript( $buffer );
			}
			if ( ! empty( $options['minify_html'] ) ) {
				$buffer = SPDR_Assets::minify_html( $buffer );
			}
		}

		// Ensure directory exists.
		$dir = dirname( $file_path );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Append a signature comment for verification.
		$signature = sprintf(
			"\n<!-- Speed Doctor Cached Page (Generated: %s) -->",
			esc_html( current_time( 'mysql' ) )
		);
		$cached_content = $buffer . $signature;

		// Write cache file.
		file_put_contents( $file_path, $cached_content );

		return $buffer;
	}

	/**
	 * Get absolute path to cache file for a given URL.
	 *
	 * @param string $url The page URL.
	 * @return string Cache file path.
	 */
	public function get_cache_file_path( $url ) {
		$url_path  = wp_parse_url( $url, PHP_URL_PATH );
		$path_slug = trim( $url_path, '/' );

		$options = get_option( 'spdr_settings' );
		$suffix  = '';

		// Separate mobile cache files
		if ( ! empty( $options['mobile_cache'] ) && wp_is_mobile() ) {
			$suffix .= '-mobile';
		}

		// Separate logged-in cache files
		if ( ! empty( $options['logged_in_cache'] ) && is_user_logged_in() ) {
			$suffix .= '-loggedin';
		}

		if ( empty( $path_slug ) ) {
			$filename = 'index' . $suffix . '.html';
			return wp_normalize_path( $this->cache_dir . 'html/' . $filename );
		} else {
			$filename = 'index' . $suffix . '.html';
			return wp_normalize_path( $this->cache_dir . 'html/' . $path_slug . '/' . $filename );
		}
	}

	/**
	 * Check if cache file exists and is valid.
	 *
	 * @param string $file_path Absolute path to the cache file.
	 * @return bool True if valid cache file exists.
	 */
	public function cache_file_exists( $file_path ) {
		return file_exists( $file_path ) && is_readable( $file_path ) && filesize( $file_path ) > 0;
	}

	/**
	 * Serve the cached HTML page if it exists.
	 */
	public function serve_cached_page() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['page_cache'] ) ) {
			return;
		}

		if ( $this->should_bypass_cache() ) {
			return;
		}

		// Construct current page URL safely.
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $host ) ) {
			return;
		}

		$url       = $scheme . '://' . $host . $uri;
		$file_path = $this->get_cache_file_path( $url );

		if ( $this->cache_file_exists( $file_path ) ) {
			// Send cache headers before output.
			$this->send_cache_headers();

			// Serve the static HTML.
			readfile( $file_path );
			exit;
		}
	}
	/**
	 * Get cache base directory path.
	 *
	 * @return string Cache directory path.
	 */
	public function get_cache_dir() {
		return $this->cache_dir;
	}

	/**
	 * Get the total size of the cache directory in bytes.
	 *
	 * @return int Total size in bytes.
	 */
	public function get_cache_dir_size() {
		if ( ! is_dir( $this->cache_dir ) ) {
			return 0;
		}

		$size = 0;
		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $this->cache_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $files as $file ) {
				if ( $file->isFile() ) {
					$size += $file->getSize();
				}
			}
		} catch ( Exception $e ) {
			// Fail-safe default
			$size = 0;
		}

		return $size;
	}
}

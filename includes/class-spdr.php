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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX action handlers
		add_action( 'wp_ajax_spdr_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_spdr_db_cleanup', array( $this, 'ajax_db_cleanup' ) );
		add_action( 'wp_ajax_spdr_purge_cache', array( $this, 'ajax_purge_cache' ) );
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
	}

	/**
	 * Enqueue admin scripts/styles.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_speed-doctor' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'spdr-admin-styles',
			SPDR_URL . 'assets/css/admin.css',
			array(),
			SPDR_VERSION,
			'all'
		);
	}

	/**
	 * Register admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			esc_html__( 'Speed Doctor', 'speed-doctor' ),
			esc_html__( 'Speed Doctor', 'speed-doctor' ),
			'manage_options',
			'speed-doctor',
			'spdr_admin_page_display',
			'dashicons-performance',
			80
		);
	}

	/**
	 * Register settings API settings.
	 */
	public function register_settings() {
		register_setting(
			'spdr_settings_group',
			'spdr_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'page_cache'      => 0,
					'db_optimization' => 0,
					'minify_html'     => 0,
					'minify_css'      => 0,
					'minify_js'       => 0,
					'combine_js'      => 0,
					'defer_js'        => 0,
					'lazy_load'       => 0,
					'cdn_enable'      => 0,
					'cdn_url'         => '',
					'cdn_exclude'     => '',
					'heartbeat_behavior' => 'default',
					'remove_ver_query' => 0,
					'disable_emojis'   => 0,
				),
			)
		);

		add_settings_section(
			'spdr_settings_section_general',
			esc_html__( 'General Settings', 'speed-doctor' ),
			array( $this, 'settings_section_callback' ),
			'speed-doctor'
		);

		add_settings_field(
			'spdr_field_page_cache',
			esc_html__( 'Page Caching', 'speed-doctor' ),
			array( $this, 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'page_cache',
				'description' => esc_html__( 'Generate static HTML files for faster load times.', 'speed-doctor' ),
			)
		);
	}

	/**
	 * Section callback.
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the general performance settings below.', 'speed-doctor' ) . '</p>';
	}

	/**
	 * Checkbox field callback.
	 */
	public function field_checkbox_callback( $args ) {
		$options = get_option( 'spdr_settings' );
		$id      = $args['label_for'];
		$checked = isset( $options[ $id ] ) ? (int) $options[ $id ] : 0;
		?>
		<label class="spdr-toggle-switch">
			<input 
				type="checkbox" 
				id="<?php echo esc_attr( $id ); ?>" 
				name="spdr_settings[<?php echo esc_attr( $id ); ?>]" 
				value="1" 
				<?php checked( 1, $checked ); ?>
			/>
			<span class="spdr-slider"></span>
		</label>
		<p class="spdr-field-desc"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Text field callback.
	 */
	public function field_text_callback( $args ) {
		$options = get_option( 'spdr_settings' );
		$id      = $args['label_for'];
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : '';
		?>
		<input 
			type="text" 
			id="<?php echo esc_attr( $id ); ?>" 
			name="spdr_settings[<?php echo esc_attr( $id ); ?>]" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text"
		/>
		<p class="spdr-field-desc"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Sanitize callback.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		$checkbox_keys = array(
			'page_cache',
			'db_optimization',
			'minify_html',
			'minify_css',
			'minify_js',
			'combine_js',
			'defer_js',
			'lazy_load',
			'cdn_enable',
			'remove_ver_query',
			'disable_emojis',
		);

		foreach ( $checkbox_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		$sanitized['cdn_url']     = isset( $input['cdn_url'] ) ? sanitize_text_field( $input['cdn_url'] ) : '';
		$sanitized['cdn_exclude'] = isset( $input['cdn_exclude'] ) ? sanitize_text_field( $input['cdn_exclude'] ) : '';

		$allowed_heartbeat = array( 'default', 'throttle', 'disable_frontend', 'disable_everywhere' );
		$sanitized['heartbeat_behavior'] = isset( $input['heartbeat_behavior'] ) && in_array( $input['heartbeat_behavior'], $allowed_heartbeat, true ) ? $input['heartbeat_behavior'] : 'default';

		return $sanitized;
	}

	/**
	 * AJAX callback to save plugin settings.
	 */
	public function ajax_save_settings() {
		// Verify capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		// Verify nonce
		check_ajax_referer( 'spdr_settings_group-options', 'nonce' );

		// Parse settings from raw input data
		$raw_settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		
		// Sanitize options
		$sanitized = $this->sanitize_settings( $raw_settings );

		// Fetch old options before update to check if page cache status changed
		$old_options = get_option( 'spdr_settings' );

		// Save settings in database
		update_option( 'spdr_settings', $sanitized );

		// Flush cache if page cache was changed from enabled to disabled
		if ( ! empty( $old_options['page_cache'] ) && empty( $sanitized['page_cache'] ) ) {
			SPDR_Cache::get_instance()->purge_all_cache();
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully!', 'speed-doctor' ) ) );
	}

	/**
	 * AJAX callback for database cleanup operations.
	 */
	public function ajax_db_cleanup() {
		// Verify capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		// Verify nonce
		check_ajax_referer( 'spdr_db_cleanup_nonce', 'nonce' );

		$db_action = isset( $_POST['db_action'] ) ? sanitize_text_field( $_POST['db_action'] ) : '';
		$db_cleaner = SPDR_DB::get_instance();

		switch ( $db_action ) {
			case 'clean_revisions':
				$count = $db_cleaner->delete_revisions();
				$message = sprintf( esc_html__( 'Obsolete revisions purged successfully! Cleared %d rows.', 'speed-doctor' ), $count );
				break;
			case 'clean_autodrafts':
				$count = $db_cleaner->delete_auto_drafts();
				$message = sprintf( esc_html__( 'Unsaved auto-drafts purged successfully! Cleared %d rows.', 'speed-doctor' ), $count );
				break;
			case 'clean_comments':
				$count = $db_cleaner->delete_comments();
				$message = sprintf( esc_html__( 'Spam and trashed comments purged successfully! Cleared %d rows.', 'speed-doctor' ), $count );
				break;
			case 'clean_transients':
				$count = $db_cleaner->delete_expired_transients();
				$message = sprintf( esc_html__( 'Expired transients purged successfully! Cleared %d rows.', 'speed-doctor' ), $count );
				break;
			case 'optimize_tables':
				$db_cleaner->optimize_tables();
				$message = esc_html__( 'Database tables optimized successfully!', 'speed-doctor' );
				break;
			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid cleanup action.', 'speed-doctor' ) ) );
		}

		wp_send_json_success( array( 'message' => $message ) );
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

	/**
	 * AJAX callback to purge cache files.
	 */
	public function ajax_purge_cache() {
		// Verify capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		// Verify nonce
		check_ajax_referer( 'spdr_purge_nonce', 'nonce' );

		$type = isset( $_POST['purge_type'] ) ? sanitize_text_field( $_POST['purge_type'] ) : '';
		$purged = SPDR_Cache::get_instance()->purge_cache_by_type( $type );

		if ( $purged ) {
			switch ( $type ) {
				case 'html':
					$message = esc_html__( 'HTML Cache (website structure) cleared successfully.', 'speed-doctor' );
					break;
				case 'css':
					$message = esc_html__( 'Minified CSS asset cache cleared successfully.', 'speed-doctor' );
					break;
				case 'js':
					$message = esc_html__( 'Minified/Combined JS asset cache cleared successfully.', 'speed-doctor' );
					break;
				case 'all':
					$message = esc_html__( 'All cache (HTML, CSS, JS) cleared successfully.', 'speed-doctor' );
					break;
				default:
					$message = esc_html__( 'Cache cleared successfully.', 'speed-doctor' );
			}
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to clear cache.', 'speed-doctor' ) ) );
		}
	}
}

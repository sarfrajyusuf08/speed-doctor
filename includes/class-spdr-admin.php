<?php
/**
 * Speed Doctor Admin Handler.
 *
 * @package Speed Doctor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return SPDR_Admin
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
		// Admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_spdr_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_spdr_db_cleanup', array( $this, 'ajax_db_cleanup' ) );
		add_action( 'wp_ajax_spdr_purge_cache', array( $this, 'ajax_purge_cache' ) );
		add_action( 'wp_ajax_spdr_restart_preload', array( $this, 'ajax_restart_preload' ) );
		add_action( 'wp_ajax_spdr_get_preload_status', array( $this, 'ajax_get_preload_status' ) );
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
					'page_cache'          => 0,
					'db_optimization'     => 0,
					'minify_html'         => 0,
					'minify_css'          => 0,
					'minify_js'           => 0,
					'combine_js'          => 0,
					'defer_js'            => 0,
					'lazy_load'           => 0,
					'cdn_enable'          => 0,
					'cdn_url'             => '',
					'cdn_exclude'         => '',
					'heartbeat_behavior'  => 'default',
					'remove_ver_query'    => 0,
					'disable_emojis'      => 0,
					'mobile_cache'        => 0,
					'logged_in_cache'     => 0,
					'cache_lifespan'      => 86400,
					'exclude_css'         => '',
					'exclude_js'          => '',
					'delay_js'            => 0,
					'lazy_load_iframes'   => 0,
					'lcp_exclude_count'        => 1,
					'add_img_dimensions'       => 0,
					'db_cleanup_schedule'      => 'disabled',
					'preload_enable'           => 0,
					'preload_pages_per_minute' => 10,
					'gzip_compression'          => 0,
					'preload_types'            => array( 'homepage', 'posts', 'pages' ),
				),
			)
		);
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
			'mobile_cache',
			'logged_in_cache',
			'delay_js',
			'lazy_load_iframes',
			'add_img_dimensions',
			'preload_enable',
			'gzip_compression',
		);

		foreach ( $checkbox_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		$sanitized['cdn_url']     = isset( $input['cdn_url'] ) ? sanitize_text_field( $input['cdn_url'] ) : '';
		$sanitized['cdn_exclude'] = isset( $input['cdn_exclude'] ) ? sanitize_textarea_field( $input['cdn_exclude'] ) : '';
		$sanitized['exclude_css'] = isset( $input['exclude_css'] ) ? sanitize_textarea_field( $input['exclude_css'] ) : '';
		$sanitized['exclude_js']  = isset( $input['exclude_js'] ) ? sanitize_textarea_field( $input['exclude_js'] ) : '';

		$allowed_heartbeat = array( 'default', 'throttle', 'disable_frontend', 'disable_everywhere' );
		$sanitized['heartbeat_behavior'] = isset( $input['heartbeat_behavior'] ) && in_array( $input['heartbeat_behavior'], $allowed_heartbeat, true ) ? $input['heartbeat_behavior'] : 'default';

		$sanitized['cache_lifespan'] = isset( $input['cache_lifespan'] ) ? intval( $input['cache_lifespan'] ) : 86400;
		$sanitized['lcp_exclude_count'] = isset( $input['lcp_exclude_count'] ) ? intval( $input['lcp_exclude_count'] ) : 1;

		$allowed_schedules = array( 'disabled', 'daily', 'weekly' );
		$sanitized['db_cleanup_schedule'] = isset( $input['db_cleanup_schedule'] ) && in_array( $input['db_cleanup_schedule'], $allowed_schedules, true ) ? $input['db_cleanup_schedule'] : 'disabled';

		$sanitized['preload_pages_per_minute'] = isset( $input['preload_pages_per_minute'] ) ? intval( $input['preload_pages_per_minute'] ) : 10;
		$allowed_types = array( 'homepage', 'posts', 'pages', 'categories', 'tags' );
		$input_types = isset( $input['preload_types'] ) ? (array) $input['preload_types'] : array();
		$sanitized['preload_types'] = array_values( array_intersect( $input_types, $allowed_types ) );

		return $sanitized;
	}

	/**
	 * AJAX callback to save plugin settings.
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		check_ajax_referer( 'spdr_settings_group-options', 'nonce' );

		$raw_settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
		$sanitized = $this->sanitize_settings( $raw_settings );
		$old_options = get_option( 'spdr_settings' );

		update_option( 'spdr_settings', $sanitized );

		// Configure rewrite rules and directories immediately.
		SPDR_Cache::get_instance()->maybe_setup_cache_dir();

		// Configure preload cron job immediately.
		if ( ! empty( $sanitized['preload_enable'] ) ) {
			if ( ! wp_next_scheduled( 'spdr_preload_cron_job' ) ) {
				wp_schedule_event( time(), 'spdr_one_minute', 'spdr_preload_cron_job' );
			}
			// Trigger initial step.
			SPDR_Preload::get_instance()->run_preload_step();
		} else {
			wp_clear_scheduled_hook( 'spdr_preload_cron_job' );
		}

		if ( ! empty( $old_options['page_cache'] ) && empty( $sanitized['page_cache'] ) ) {
			SPDR_Cache::get_instance()->purge_all_cache();
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully!', 'speed-doctor' ) ) );
	}

	/**
	 * AJAX callback for database cleanup operations.
	 */
	public function ajax_db_cleanup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

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
	 * AJAX callback to purge cache files.
	 */
	public function ajax_purge_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

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

	/**
	 * AJAX callback to manually restart cache preloading.
	 */
	public function ajax_restart_preload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		check_ajax_referer( 'spdr_settings_group-options', 'nonce' );

		SPDR_Preload::get_instance()->restart_preload();

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Preloader restarted successfully!', 'speed-doctor' ),
				'state'   => get_option( 'spdr_preload_state' ),
			)
		);
	}

	/**
	 * AJAX callback to fetch cache preloader progress.
	 */
	public function ajax_get_preload_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'speed-doctor' ) ) );
		}

		check_ajax_referer( 'spdr_settings_group-options', 'nonce' );

		$state = get_option( 'spdr_preload_state' );
		if ( ! is_array( $state ) ) {
			$state = array(
				'queue'         => array(),
				'current_index' => 0,
				'total'         => 0,
				'status'        => 'idle',
				'last_run'      => 0,
			);
		}

		wp_send_json_success( $state );
	}
}

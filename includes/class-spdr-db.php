<?php
/**
 * Speed Doctor Database Optimization Module.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPDR_DB
 *
 * Handles database optimization tasks: clearing revisions, auto-drafts, comments, transients, and table optimizations.
 */
class SPDR_DB {

	/**
	 * Singleton instance.
	 *
	 * @var SPDR_DB|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return SPDR_DB
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
		// Initialize database actions.
		add_action( 'admin_init', array( $this, 'register_db_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_cleanup' ) );
		add_action( 'admin_notices', array( $this, 'show_db_notices' ) );

		// Cron actions.
		add_filter( 'cron_schedules', array( $this, 'add_weekly_cron_schedule' ) );
		add_action( 'spdr_db_optimization_cron', array( $this, 'run_automated_optimization' ) );
	}

	/**
	 * Register Settings API fields for Database Doctor.
	 */
	public function register_db_settings() {
		add_settings_field(
			'spdr_field_db_optimization',
			esc_html__( 'Database Doctor', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'db_optimization',
				'description' => esc_html__( 'Enable database cleanup routines and optimizations.', 'speed-doctor' ),
			)
		);
	}

	/**
	 * Delete obsolete post/page revisions.
	 *
	 * @return int Number of deleted revisions.
	 */
	public function delete_revisions() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		// Query to delete revisions.
		$sql = "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'";
		$deleted = $wpdb->query( $sql );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete auto-draft posts.
	 *
	 * @return int Number of deleted auto-drafts.
	 */
	public function delete_auto_drafts() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		// Query to delete auto-draft posts.
		$sql = "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'";
		$deleted = $wpdb->query( $sql );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete spam and trashed comments.
	 *
	 * @return int Number of deleted comments.
	 */
	public function delete_comments() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		// Query to delete spam and trashed comments.
		$sql = "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')";
		$deleted = $wpdb->query( $sql );

		// Clean up orphaned commentmeta.
		$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments})" );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete expired transients.
	 *
	 * @return int Number of deleted transients.
	 */
	public function delete_expired_transients() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$now = time();

		// 1. Delete expired transient values
		$sql_values = "
			DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
			WHERE a.option_name LIKE '_transient_%'
			AND a.option_name NOT LIKE '_transient_timeout_%'
			AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
			AND b.option_value < $now
		";
		$wpdb->query( $sql_values );

		// 2. Delete expired timeout options
		$sql_timeouts = "
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_%'
			AND option_value < $now
		";
		$deleted_timeouts = $wpdb->query( $sql_timeouts );

		return is_int( $deleted_timeouts ) ? $deleted_timeouts : 0;
	}

	/**
	 * Optimize database tables.
	 *
	 * @return int Number of optimized tables.
	 */
	public function optimize_tables() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
		$optimized_count = 0;

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$wpdb->query( "OPTIMIZE TABLE {$table}" );
				$optimized_count++;
			}
		}

		return $optimized_count;
	}

	/**
	 * Add custom weekly schedule to WP-Cron schedules.
	 *
	 * @param array $schedules WP-Cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_weekly_cron_schedule( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800, // 7 days in seconds
			'display'  => esc_html__( 'Once Weekly', 'speed-doctor' ),
		);
		return $schedules;
	}

	/**
	 * Run automated background optimizations via weekly cron.
	 */
	public function run_automated_optimization() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['db_optimization'] ) ) {
			return;
		}

		$this->delete_revisions();
		$this->delete_auto_drafts();
		$this->delete_comments();
		$this->delete_expired_transients();
		$this->optimize_tables();
	}

	/**
	 * Handle manual database cleanup triggers from the Admin UI.
	 */
	public function handle_manual_cleanup() {
		if ( ! isset( $_POST['spdr_db_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'speed-doctor' ) );
		}

		check_admin_referer( 'spdr_db_cleanup_nonce' );

		$action       = sanitize_text_field( wp_unslash( $_POST['spdr_db_action'] ) );
		$redirect_url = remove_query_arg( array( 'spdr_revisions_cleaned', 'spdr_autodrafts_cleaned', 'spdr_comments_cleaned', 'spdr_transients_cleaned', 'spdr_tables_optimized' ) );

		if ( 'clean_revisions' === $action ) {
			$deleted = $this->delete_revisions();
			wp_safe_redirect( add_query_arg( 'spdr_revisions_cleaned', $deleted, $redirect_url ) );
			exit;
		} elseif ( 'clean_autodrafts' === $action ) {
			$deleted = $this->delete_auto_drafts();
			wp_safe_redirect( add_query_arg( 'spdr_autodrafts_cleaned', $deleted, $redirect_url ) );
			exit;
		} elseif ( 'clean_comments' === $action ) {
			$deleted = $this->delete_comments();
			wp_safe_redirect( add_query_arg( 'spdr_comments_cleaned', $deleted, $redirect_url ) );
			exit;
		} elseif ( 'clean_transients' === $action ) {
			$deleted = $this->delete_expired_transients();
			wp_safe_redirect( add_query_arg( 'spdr_transients_cleaned', $deleted, $redirect_url ) );
			exit;
		} elseif ( 'optimize_tables' === $action ) {
			$optimized = $this->optimize_tables();
			wp_safe_redirect( add_query_arg( 'spdr_tables_optimized', $optimized, $redirect_url ) );
			exit;
		}
	}

	/**
	 * Show success notice after revisions cleanup.
	 */
	public function show_db_notices() {
		if ( isset( $_GET['spdr_revisions_cleaned'] ) ) {
			$cleaned_count = intval( $_GET['spdr_revisions_cleaned'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of revisions deleted */
						esc_html( _n( 'Successfully purged %d obsolete post revision.', 'Successfully purged %d obsolete post revisions.', $cleaned_count, 'speed-doctor' ) ),
						$cleaned_count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['spdr_autodrafts_cleaned'] ) ) {
			$cleaned_count = intval( $_GET['spdr_autodrafts_cleaned'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of auto-drafts deleted */
						esc_html( _n( 'Successfully purged %d obsolete auto-draft.', 'Successfully purged %d obsolete auto-drafts.', $cleaned_count, 'speed-doctor' ) ),
						$cleaned_count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['spdr_comments_cleaned'] ) ) {
			$cleaned_count = intval( $_GET['spdr_comments_cleaned'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of spam/trash comments deleted */
						esc_html( _n( 'Successfully purged %d spam or trashed comment.', 'Successfully purged %d spam or trashed comments.', $cleaned_count, 'speed-doctor' ) ),
						$cleaned_count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['spdr_transients_cleaned'] ) ) {
			$cleaned_count = intval( $_GET['spdr_transients_cleaned'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of expired transients deleted */
						esc_html( _n( 'Successfully purged %d expired transient option.', 'Successfully purged %d expired transient options.', $cleaned_count, 'speed-doctor' ) ),
						$cleaned_count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['spdr_tables_optimized'] ) ) {
			$optimized_count = intval( $_GET['spdr_tables_optimized'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of optimized database tables */
						esc_html( _n( 'Successfully optimized %d database table.', 'Successfully optimized %d database tables.', $optimized_count, 'speed-doctor' ) ),
						$optimized_count
					);
					?>
				</p>
			</div>
			<?php
		}
	}
	/**
	 * Get database stats: total size and overhead space in bytes.
	 *
	 * @return array Array containing 'size' and 'overhead' in bytes.
	 */
	public function get_db_stats() {
		global $wpdb;
		
		// Run a query on information_schema or show table status for current DB
		$query = $wpdb->prepare(
			"SHOW TABLE STATUS FROM `%s`",
			DB_NAME
		);
		$tables = $wpdb->get_results( $query, ARRAY_A );

		$size     = 0;
		$overhead = 0;

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				// We filter to target only tables starting with current WordPress prefix
				if ( 0 === strpos( $table['Name'], $wpdb->prefix ) ) {
					$size     += (int) $table['Data_length'] + (int) $table['Index_length'];
					$overhead += (int) $table['Data_free'];
				}
			}
		}

		return array(
			'size'     => $size,
			'overhead' => $overhead,
		);
	}
}

<?php
/**
 * Speed Doctor Cache Preloader Engine.
 *
 * @package Speed Doctor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR_Preload {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR_Preload
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return SPDR_Preload
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
		// Cron worker hook
		add_action( 'spdr_preload_cron_job', array( $this, 'run_preload_step' ) );
	}

	/**
	 * Rebuild the preload queue.
	 */
	public function rebuild_queue() {
		$options = get_option( 'spdr_settings' );
		$types   = isset( $options['preload_types'] ) ? (array) $options['preload_types'] : array( 'homepage', 'posts', 'pages' );
		$urls    = array();

		// 1. Homepage
		if ( in_array( 'homepage', $types, true ) ) {
			$urls[] = home_url( '/' );
		}

		// 2. Posts
		if ( in_array( 'posts', $types, true ) ) {
			$posts = get_posts(
				array(
					'post_type'      => 'post',
					'posts_per_page' => 150, // Cap to 150 recent posts to prevent overloading database
					'post_status'    => 'publish',
				)
			);
			foreach ( $posts as $p ) {
				$urls[] = get_permalink( $p->ID );
			}
		}

		// 3. Pages
		if ( in_array( 'pages', $types, true ) ) {
			$pages = get_posts(
				array(
					'post_type'      => 'page',
					'posts_per_page' => 100, // Cap pages
					'post_status'    => 'publish',
				)
			);
			foreach ( $pages as $pg ) {
				$urls[] = get_permalink( $pg->ID );
			}
		}

		// 4. Categories
		if ( in_array( 'categories', $types, true ) ) {
			$categories = get_terms(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
				foreach ( $categories as $cat ) {
					$urls[] = get_category_link( $cat->term_id );
				}
			}
		}

		// 5. Tags
		if ( in_array( 'tags', $types, true ) ) {
			$tags = get_terms(
				array(
					'taxonomy'   => 'post_tag',
					'hide_empty' => true,
				)
			);
			if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
				foreach ( $tags as $tag ) {
					$urls[] = get_tag_link( $tag->term_id );
				}
			}
		}

		// Filter empty, duplicate and invalid URLs
		$urls = array_filter( array_unique( $urls ) );

		$state = array(
			'queue'         => array_values( $urls ),
			'current_index' => 0,
			'total'         => count( $urls ),
			'status'        => 'active',
			'last_run'      => time(),
		);

		update_option( 'spdr_preload_state', $state );

		return $state;
	}

	/**
	 * Run a single preloading crawler iteration step.
	 */
	public function run_preload_step() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['preload_enable'] ) ) {
			wp_clear_scheduled_hook( 'spdr_preload_cron_job' );
			return;
		}

		$state = get_option( 'spdr_preload_state' );
		if ( ! is_array( $state ) || 'active' !== $state['status'] || $state['current_index'] >= $state['total'] ) {
			$state = $this->rebuild_queue();
		}

		if ( empty( $state['queue'] ) ) {
			$state['status'] = 'finished';
			update_option( 'spdr_preload_state', $state );
			return;
		}

		$rate = isset( $options['preload_pages_per_minute'] ) ? intval( $options['preload_pages_per_minute'] ) : 10;
		$urls_to_crawl = array_slice( $state['queue'], $state['current_index'], $rate );

		if ( empty( $urls_to_crawl ) ) {
			$state['status'] = 'finished';
			update_option( 'spdr_preload_state', $state );
			return;
		}

		// Crawl URLs asynchronously (non-blocking)
		foreach ( $urls_to_crawl as $url ) {
			wp_remote_get(
				$url,
				array(
					'timeout'    => 5,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => false, // Async!
					'headers'     => array(
						'User-Agent' => 'SpeedDoctorPreloadBot',
					),
				)
			);
		}

		$state['current_index'] += count( $urls_to_crawl );
		$state['last_run']       = time();

		if ( $state['current_index'] >= $state['total'] ) {
			$state['status'] = 'finished';
		}

		update_option( 'spdr_preload_state', $state );
	}

	/**
	 * Reset queue and trigger preloading immediately.
	 */
	public function restart_preload() {
		$this->rebuild_queue();
		$this->run_preload_step();

		// Make sure cron job is scheduled.
		if ( ! wp_next_scheduled( 'spdr_preload_cron_job' ) ) {
			wp_schedule_event( time(), 'spdr_one_minute', 'spdr_preload_cron_job' );
		}
	}
}

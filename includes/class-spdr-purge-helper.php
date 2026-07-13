<?php
/**
 * Speed Doctor Selective Purge Helper.
 *
 * @package Speed Doctor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPDR_Purge_Helper {

	/**
	 * Instance of this class.
	 *
	 * @var SPDR_Purge_Helper
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return SPDR_Purge_Helper
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
	 * Purge post cache and all associated archive layout caches.
	 *
	 * @param int $post_id Post ID.
	 */
	public function purge_post_and_related_archives( $post_id ) {
		// Ignore revisions and autosaves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// 1. Purge the post page cache itself.
		$permalink = get_permalink( $post_id );
		if ( $permalink ) {
			$this->purge_url_and_pagination( $permalink );
		}

		// 2. Purge categories assigned to the post.
		$categories = get_the_category( $post_id );
		if ( is_array( $categories ) ) {
			foreach ( $categories as $category ) {
				$cat_link = get_category_link( $category->term_id );
				if ( $cat_link && ! is_wp_error( $cat_link ) ) {
					$this->purge_url_and_pagination( $cat_link );
				}
			}
		}

		// 3. Purge tags assigned to the post.
		$tags = get_the_tags( $post_id );
		if ( is_array( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tag_link = get_tag_link( $tag->term_id );
				if ( $tag_link && ! is_wp_error( $tag_link ) ) {
					$this->purge_url_and_pagination( $tag_link );
				}
			}
		}

		// 4. Purge post author archive layout.
		$author_id = get_post_field( 'post_author', $post_id );
		if ( $author_id ) {
			$author_link = get_author_posts_url( $author_id );
			if ( $author_link ) {
				$this->purge_url_and_pagination( $author_link );
			}
		}

		// 5. Purge custom post type archives (if applicable).
		$post_type = get_post_type( $post_id );
		if ( $post_type && 'post' !== $post_type && 'page' !== $post_type ) {
			$cpt_link = get_post_type_archive_link( $post_type );
			if ( $cpt_link && ! is_wp_error( $cpt_link ) ) {
				$this->purge_url_and_pagination( $cpt_link );
			}
		}

		// 6. Purge home page layout.
		$this->purge_url_and_pagination( home_url( '/' ) );
	}

	/**
	 * Purge a specific URL cache and its paginated subdirectory indexes.
	 *
	 * @param string $url permalink URL.
	 */
	public function purge_url_and_pagination( $url ) {
		if ( empty( $url ) ) {
			return;
		}

		$url_path  = wp_parse_url( $url, PHP_URL_PATH );
		$path_slug = trim( $url_path, '/' );
		$cache_dir = SPDR_Cache::get_instance()->get_cache_dir();

		if ( empty( $path_slug ) ) {
			// Homepage cache files: html/index*.html
			$dir = wp_normalize_path( $cache_dir . 'html/' );
			$files = glob( $dir . 'index*.html' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}

			// Homepage pagination folders: html/page/
			$pagination_dir = wp_normalize_path( $dir . 'page/' );
			if ( is_dir( $pagination_dir ) ) {
				$this->delete_dir_recursive( $pagination_dir );
			}
		} else {
			// Subpage cache files: html/path/slug/index*.html
			$dir = wp_normalize_path( $cache_dir . 'html/' . $path_slug . '/' );
			if ( is_dir( $dir ) ) {
				$files = glob( $dir . 'index*.html' );
				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						if ( is_file( $file ) ) {
							unlink( $file );
						}
					}
				}

				// Subpage pagination folders: html/path/slug/page/
				$pagination_dir = wp_normalize_path( $dir . 'page/' );
				if ( is_dir( $pagination_dir ) ) {
					$this->delete_dir_recursive( $pagination_dir );
				}
			}
		}
	}

	/**
	 * Helper to recursively delete directory.
	 */
	private function delete_dir_recursive( $dir ) {
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
			rmdir( $dir );
		} catch ( Exception $e ) {
			// Fail-safe
		}
	}
}

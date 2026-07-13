<?php
/**
 * Speed Doctor Media & CDN Optimization Module.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPDR_Media
 *
 * Handles native lazy loading, CDN rewrites, and media optimizations.
 */
class SPDR_Media {

	/**
	 * Singleton instance.
	 *
	 * @var SPDR_Media|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return SPDR_Media
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
		// Hook output buffering for dynamic page rendering if media or CDN features are active
		add_action( 'template_redirect', array( $this, 'maybe_start_media_buffer' ), 3 );

		// Hook Heartbeat API optimizer hooks
		$this->init_heartbeat();

		// Query string remover hooks
		add_filter( 'script_loader_src', array( $this, 'remove_asset_query_string' ), 999 );
		add_filter( 'style_loader_src', array( $this, 'remove_asset_query_string' ), 999 );

		// Hook core emoji de-registration hooks
		$this->init_emoji_remover();
	}



	/**
	 * Start dynamic media optimization buffer if caching is bypassed or inactive.
	 */
	public function maybe_start_media_buffer() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['lazy_load'] ) && empty( $options['lazy_load_iframes'] ) && empty( $options['add_img_dimensions'] ) && empty( $options['cdn_enable'] ) ) {
			return;
		}

		// Skip if page caching buffer is active (caching runs filters inside its own buffer callback instead)
		if ( ! empty( $options['page_cache'] ) && ! SPDR_Cache::get_instance()->should_bypass_cache() ) {
			return;
		}

		ob_start( array( $this, 'lazy_load_output_callback' ) );
	}

	/**
	 * Buffer callback for dynamic media processing.
	 *
	 * @param string $buffer HTML markup.
	 * @return string Processed HTML.
	 */
	public function lazy_load_output_callback( $buffer ) {
		$buffer = $this->lazy_load_media( $buffer );
		$buffer = $this->add_image_dimensions( $buffer );
		$buffer = $this->rewrite_cdn_urls( $buffer );
		return $buffer;
	}

	/**
	 * Parse HTML to apply native lazy loading attributes to images, iframes, and videos.
	 *
	 * @param string $html Original HTML markup.
	 * @return string Processed HTML.
	 */
	public function lazy_load_media( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['lazy_load'] ) && empty( $options['lazy_load_iframes'] ) ) {
			return $html;
		}

		// 1. Process <img> tags
		if ( ! empty( $options['lazy_load'] ) ) {
			$lcp_count = isset( $options['lcp_exclude_count'] ) ? (int) $options['lcp_exclude_count'] : 1;
			$counter   = 0;

			$html = preg_replace_callback(
				'/<img\b([^>]*)/is',
				function ( $matches ) use ( &$counter, $lcp_count ) {
					$img_attributes = $matches[1];
					$counter++;

					// Exclude first N images for LCP optimization
					if ( $counter <= $lcp_count ) {
						return $matches[0];
					}

					// Skip if loading attribute is already present
					if ( false === strpos( $img_attributes, ' loading=' ) ) {
						$img_attributes .= ' loading="lazy"';
					}
					return '<img' . $img_attributes;
				},
				$html
			);
		}

		// 2. Process <iframe> tags
		if ( ! empty( $options['lazy_load_iframes'] ) ) {
			$html = preg_replace_callback(
				'/<iframe\b([^>]*)/is',
				function ( $matches ) {
					$iframe_attributes = $matches[1];
					// Skip if loading attribute is already present
					if ( false === strpos( $iframe_attributes, ' loading=' ) ) {
						$iframe_attributes .= ' loading="lazy"';
					}
					return '<iframe' . $iframe_attributes;
				},
				$html
			);
		}

		// 3. Process <video> tags
		if ( ! empty( $options['lazy_load'] ) ) {
			$html = preg_replace_callback(
				'/<video\b([^>]*)/is',
				function ( $matches ) {
					$video_attributes = $matches[1];
					// Skip if preload attribute is already present
					if ( false === strpos( $video_attributes, ' preload=' ) ) {
						$video_attributes .= ' preload="none"';
					}
					return '<video' . $video_attributes;
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Rewrite local asset URLs (CSS, JS, Images) to use the configured CDN hostname.
	 *
	 * @param string $html Original HTML markup.
	 * @return string Processed HTML.
	 */
	public function rewrite_cdn_urls( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['cdn_enable'] ) || empty( $options['cdn_url'] ) ) {
			return $html;
		}

		$cdn_url  = esc_url( rtrim( $options['cdn_url'], '/' ) );
		$home_url = esc_url( rtrim( home_url(), '/' ) );

		// Parse exclusion list
		$exclusions = array();
		if ( ! empty( $options['cdn_exclude'] ) ) {
			$exclusions = array_map( 'trim', explode( ',', $options['cdn_exclude'] ) );
		}

		// Regex to capture local asset URLs in src, href, srcset.
		// We target wp-content and wp-includes directories which hold enqueued static assets.
		$regex = '/(src|href|srcset)=[\'"](' . preg_quote( $home_url, '/' ) . '(?:\/wp-content\/|\/wp-includes\/)[^\'"]+)[\'"]/is';

		$html = preg_replace_callback(
			$regex,
			function ( $matches ) use ( $cdn_url, $home_url, $exclusions ) {
				$attribute = $matches[1];
				$url       = $matches[2];

				// Check exclusions
				foreach ( $exclusions as $exclude ) {
					if ( ! empty( $exclude ) && false !== strpos( $url, $exclude ) ) {
						return $matches[0];
					}
				}

				// If srcset attribute, handle space-separated list of URLs
				if ( 'srcset' === $attribute ) {
					$parts = explode( ',', $url );
					foreach ( $parts as &$part ) {
						$part = trim( $part );
						if ( 0 === strpos( $part, $home_url ) ) {
							$part = str_replace( $home_url, $cdn_url, $part );
						}
					}
					$rewritten_url = implode( ', ', $parts );
				} else {
					$rewritten_url = str_replace( $home_url, $cdn_url, $url );
				}

				return sprintf( '%s="%s"', $attribute, $rewritten_url );
			},
			$html
		);

		return $html;
	}



	/**
	 * Hook Heartbeat settings and filters.
	 */
	private function init_heartbeat() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['heartbeat_behavior'] ) || 'default' === $options['heartbeat_behavior'] ) {
			return;
		}

		$behavior = $options['heartbeat_behavior'];

		if ( 'disable_everywhere' === $behavior ) {
			add_action( 'init', array( $this, 'disable_heartbeat' ), 1 );
		} elseif ( 'disable_frontend' === $behavior ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_heartbeat' ), 1 );
		} elseif ( 'throttle' === $behavior ) {
			add_filter( 'heartbeat_settings', array( $this, 'throttle_heartbeat' ) );
		}
	}

	/**
	 * Deregister heartbeat script.
	 */
	public function disable_heartbeat() {
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * Throttle heartbeat interval.
	 *
	 * @param array $settings Heartbeat settings.
	 * @return array Modified settings.
	 */
	public function throttle_heartbeat( $settings ) {
		$settings['interval'] = 60; // Max allowed interval is 60 seconds
		return $settings;
	}

	/**
	 * Remove version query string (?ver=...) from enqueued stylesheet/script paths.
	 *
	 * @param string $src The asset URL source.
	 * @return string Cleaned asset URL.
	 */
	public function remove_asset_query_string( $src ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['remove_ver_query'] ) ) {
			return $src;
		}

		// Skip if inside WordPress administration dashboard.
		if ( is_admin() ) {
			return $src;
		}

		// Parse and remove the ver query parameter safely.
		if ( false !== strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Hook emoji disabling filters if active.
	 */
	private function init_emoji_remover() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['disable_emojis'] ) ) {
			return;
		}

		// Disable frontend and admin emojis
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// Disable TinyMCE emojis
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );

		// Remove emoji prefetch dns hint
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_dns_prefetch' ), 10, 2 );
	}

	/**
	 * Remove emoji plugin from TinyMCE editors.
	 *
	 * @param array $plugins List of TinyMCE plugins.
	 * @return array Modified list.
	 */
	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}
		return $plugins;
	}

	/**
	 * Remove emoji CDN hostname from DNS prefetching hints.
	 *
	 * @param array  $urls List of prefetch URLs.
	 * @param string $relation_type Relation type (e.g. dns-prefetch).
	 * @return array Modified list.
	 */
	public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = 'https://s.w.org/images/core/emoji/';
			foreach ( $urls as $key => $url ) {
				if ( false !== strpos( $url, $emoji_svg_url ) || false !== strpos( $url, 's.w.org' ) ) {
					unset( $urls[ $key ] );
				}
			}
		}
		return $urls;
	}

	/**
	 * Automatically append width and height attributes to local images if missing.
	 *
	 * @param string $html Original HTML.
	 * @return string Processed HTML.
	 */
	public function add_image_dimensions( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['add_img_dimensions'] ) ) {
			return $html;
		}

		$home_url = home_url();

		$html = preg_replace_callback(
			'/<img\b([^>]*)/is',
			function( $matches ) use ( $home_url ) {
				$attributes = $matches[1];

				// Skip if both width and height are already present
				if ( false !== strpos( $attributes, ' width=' ) && false !== strpos( $attributes, ' height=' ) ) {
					return $matches[0];
				}

				// Extract src attribute
				if ( preg_match( '/src=[\'"]([^\'"]+)[\'"]/i', $attributes, $src_matches ) ) {
					$src = $src_matches[1];

					// Check if local image
					if ( 0 === strpos( $src, $home_url ) || 0 === strpos( $src, '/' ) ) {
						$clean_url = strtok( $src, '?' );

						// Get local path
						if ( 0 === strpos( $clean_url, $home_url ) ) {
							$local_path = ABSPATH . ltrim( str_replace( $home_url, '', $clean_url ), '/' );
						} else {
							$local_path = ABSPATH . ltrim( $clean_url, '/' );
						}

						$local_path = wp_normalize_path( $local_path );

						if ( file_exists( $local_path ) && is_readable( $local_path ) ) {
							$size = getimagesize( $local_path );
							if ( $size ) {
								$width = $size[0];
								$height = $size[1];

								// Inject missing dimensions
								if ( false === strpos( $attributes, ' width=' ) ) {
									$attributes .= ' width="' . intval( $width ) . '"';
								}
								if ( false === strpos( $attributes, ' height=' ) ) {
									$attributes .= ' height="' . intval( $height ) . '"';
								}
							}
						}
					}
				}

				return '<img' . $attributes;
			},
			$html
		);

		return $html;
	}
}

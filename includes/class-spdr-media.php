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
		add_action( 'admin_init', array( $this, 'register_media_settings' ) );
		
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
	 * Register Settings API fields for Media & CDN Optimization.
	 */
	public function register_media_settings() {
		add_settings_field(
			'spdr_field_lazy_load',
			esc_html__( 'Lazy Loading', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'lazy_load',
				'description' => esc_html__( 'Add loading="lazy" to images/iframes and preload="none" to video elements to speed up page rendering.', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_cdn_enable',
			esc_html__( 'CDN Integration', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'cdn_enable',
				'description' => esc_html__( 'Rewrite enqueued static asset URLs to use a custom CDN hostname.', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_cdn_url',
			esc_html__( 'CDN Base URL', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_text_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'cdn_url',
				'description' => esc_html__( 'Enter your CDN domain (e.g. https://cdn.example.com or //cdn.example.com).', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_cdn_exclude',
			esc_html__( 'CDN Exclusions', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_text_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'cdn_exclude',
				'description' => esc_html__( 'Enter comma-separated paths or filenames to exclude from CDN rewriting.', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_heartbeat_behavior',
			esc_html__( 'Heartbeat API Control', 'speed-doctor' ),
			array( $this, 'field_heartbeat_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'heartbeat_behavior',
				'description' => esc_html__( 'Manage the frequency of background AJAX requests made by the WordPress Heartbeat API.', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_remove_ver_query',
			esc_html__( 'Remove Query Strings', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'remove_ver_query',
				'description' => esc_html__( 'Remove version query strings (e.g. ?ver=x.x) from enqueued static styles and scripts.', 'speed-doctor' ),
			)
		);

		add_settings_field(
			'spdr_field_disable_emojis',
			esc_html__( 'Disable Emojis', 'speed-doctor' ),
			array( SPDR::get_instance(), 'field_checkbox_callback' ),
			'speed-doctor',
			'spdr_settings_section_general',
			array(
				'label_for'   => 'disable_emojis',
				'description' => esc_html__( 'Remove default WordPress core emoji scripts, styles, and resource prefetch hints to speed up page loads.', 'speed-doctor' ),
			)
		);
	}

	/**
	 * Start dynamic media optimization buffer if caching is bypassed or inactive.
	 */
	public function maybe_start_media_buffer() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['lazy_load'] ) && empty( $options['cdn_enable'] ) ) {
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
		if ( empty( $options['lazy_load'] ) ) {
			return $html;
		}

		// 1. Process <img> tags
		$html = preg_replace_callback(
			'/<img\b([^>]*)/is',
			function ( $matches ) {
				$img_attributes = $matches[1];
				// Skip if loading attribute is already present
				if ( false === strpos( $img_attributes, ' loading=' ) ) {
					$img_attributes .= ' loading="lazy"';
				}
				return '<img' . $img_attributes;
			},
			$html
		);

		// 2. Process <iframe> tags
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

		// 3. Process <video> tags
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
	 * Render heartbeat behavior dropdown settings field.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_heartbeat_callback( $args ) {
		$options   = get_option( 'spdr_settings' );
		$value     = ! empty( $options['heartbeat_behavior'] ) ? $options['heartbeat_behavior'] : 'default';
		$label_for = $args['label_for'];
		?>
		<select id="<?php echo esc_attr( $label_for ); ?>" name="spdr_settings[<?php echo esc_attr( $label_for ); ?>]" class="spdr-select-dropdown">
			<option value="default" <?php selected( $value, 'default' ); ?>><?php esc_html_e( 'Default WordPress Behavior', 'speed-doctor' ); ?></option>
			<option value="throttle" <?php selected( $value, 'throttle' ); ?>><?php esc_html_e( 'Throttle (Increase interval to 60s)', 'speed-doctor' ); ?></option>
			<option value="disable_frontend" <?php selected( $value, 'disable_frontend' ); ?>><?php esc_html_e( 'Disable on Front-end Only', 'speed-doctor' ); ?></option>
			<option value="disable_everywhere" <?php selected( $value, 'disable_everywhere' ); ?>><?php esc_html_e( 'Disable Everywhere', 'speed-doctor' ); ?></option>
		</select>
		<p class="spdr-field-desc"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
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
}

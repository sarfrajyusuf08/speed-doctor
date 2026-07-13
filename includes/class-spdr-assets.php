<?php
/**
 * Speed Doctor Asset & Minification Optimization Module.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPDR_Assets
 *
 * Handles HTML, CSS, JS minifications, deferring, and concatenation.
 */
class SPDR_Assets {

	/**
	 * Singleton instance.
	 *
	 * @var SPDR_Assets|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return SPDR_Assets
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
		// If minification is enabled, hook into output buffering for non-cached sessions as well
		add_action( 'template_redirect', array( $this, 'maybe_start_minifier_buffer' ), 2 );

		// Defer script hook.
		add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 3 );
	}

	/**
	 * Hook minifier into output buffer if page cache is disabled but minification is active.
	 */
	public function maybe_start_minifier_buffer() {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['minify_html'] ) && empty( $options['minify_css'] ) && empty( $options['minify_js'] ) && empty( $options['combine_js'] ) && empty( $options['delay_js'] ) ) {
			return;
		}

		// Skip if page caching buffer is already active (we will minify inside page cache buffer instead)
		if ( ! empty( $options['page_cache'] ) && ! SPDR_Cache::get_instance()->should_bypass_cache() ) {
			return;
		}

		// Start dynamic minification buffer
		ob_start( array( $this, 'minify_output_callback' ) );
	}

	/**
	 * Output buffer callback for dynamic minification.
	 *
	 * @param string $buffer The HTML content of the page.
	 * @return string Minified HTML content.
	 */
	public function minify_output_callback( $buffer ) {
		$options = get_option( 'spdr_settings' );
		if ( ! empty( $options['minify_css'] ) ) {
			$buffer = $this->process_html_css( $buffer );
		}
		if ( ! empty( $options['minify_js'] ) || ! empty( $options['combine_js'] ) ) {
			$buffer = $this->process_html_js( $buffer );
		}
		if ( ! empty( $options['delay_js'] ) ) {
			$buffer = $this->delay_javascript( $buffer );
		}
		if ( ! empty( $options['minify_html'] ) ) {
			$buffer = self::minify_html( $buffer );
		}
		return $buffer;
	}

	/**
	 * Process HTML to find and minify both inline style tags and external stylesheet links.
	 *
	 * @param string $html Original HTML markup.
	 * @return string Processed HTML.
	 */
	public function process_html_css( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['minify_css'] ) ) {
			return $html;
		}

		// 1. Minify Inline Style Blocks
		$html = preg_replace_callback(
			'/<style\b[^>]*>(.*?)<\/style>/is',
			function( $matches ) {
				$minified = self::minify_css( $matches[1] );
				return str_replace( $matches[1], $minified, $matches[0] );
			},
			$html
		);

		// 2. Minify External Local CSS Links
		$assets_dir = WP_CONTENT_DIR . '/cache/speed-doctor/assets/';
		$assets_url = WP_CONTENT_URL . '/cache/speed-doctor/assets/';

		// Create assets directory if it doesn't exist
		if ( ! file_exists( $assets_dir ) ) {
			wp_mkdir_p( $assets_dir );
		}

		// Regex to find CSS link tags
		$regex_links = '/<link\s+[^>]*href=[\'"]([^\'"]+\.css(?:\?[^\'"]*)?)[\'"][^>]*>/is';

		$html = preg_replace_callback(
			$regex_links,
			function( $matches ) use ( $assets_dir, $assets_url ) {
				$link_tag = $matches[0];
				$css_url  = $matches[1];

				// Check custom user CSS exclusions
				$options = get_option( 'spdr_settings' );
				$exclude_css = isset( $options['exclude_css'] ) ? trim( $options['exclude_css'] ) : '';
				if ( ! empty( $exclude_css ) ) {
					$excludes = array_filter( array_map( 'trim', explode( "\n", $exclude_css ) ) );
					foreach ( $excludes as $exclude ) {
						if ( ! empty( $exclude ) && false !== strpos( $css_url, $exclude ) ) {
							return $link_tag;
						}
					}
				}

				// Skip external files not matching home URL
				$home_url = home_url();
				if ( 0 !== strpos( $css_url, $home_url ) && 0 !== strpos( $css_url, '/' ) ) {
					return $link_tag;
				}

				// Clean URL query strings
				$clean_url = strtok( $css_url, '?' );

				// Get relative path or convert home_url to local path
				if ( 0 === strpos( $clean_url, $home_url ) ) {
					$local_path = ABSPATH . ltrim( str_replace( $home_url, '', $clean_url ), '/' );
				} else {
					$local_path = ABSPATH . ltrim( $clean_url, '/' );
				}

				$local_path = wp_normalize_path( $local_path );

				if ( ! file_exists( $local_path ) || ! is_readable( $local_path ) ) {
					return $link_tag;
				}

				// Generate unique cache filename
				$cache_filename = md5( $clean_url ) . '.css';
				$cache_file_path = $assets_dir . $cache_filename;
				$cache_file_url  = $assets_url . $cache_filename;

				// If cache file doesn't exist, minify and save it
				if ( ! file_exists( $cache_file_path ) ) {
					$css_content = file_get_contents( $local_path );
					if ( $css_content ) {
						$minified_css = self::minify_css( $css_content );
						self::save_cached_asset( $cache_file_path, $minified_css );
					}
				}

				// Replace link tag href with cached minified URL
				if ( file_exists( $cache_file_path ) ) {
					return str_replace( $css_url, $cache_file_url, $link_tag );
				}

				return $link_tag;
			},
			$html
		);

		return $html;
	}

	/**
	 * Process HTML to find, minify, and combine local JavaScript assets.
	 *
	 * @param string $html Original HTML markup.
	 * @return string Processed HTML.
	 */
	public function process_html_js( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['minify_js'] ) && empty( $options['combine_js'] ) ) {
			return $html;
		}

		$assets_dir = WP_CONTENT_DIR . '/cache/speed-doctor/assets/';
		$assets_url = WP_CONTENT_URL . '/cache/speed-doctor/assets/';

		// Create assets directory if it doesn't exist
		if ( ! file_exists( $assets_dir ) ) {
			wp_mkdir_p( $assets_dir );
		}

		// Regex to find script tags with src
		$regex_scripts = '/<script\s+[^>]*src=[\'"]([^\'"]+\.js(?:\?[^\'"]*)?)[\'"][^>]*>\s*<\/script>/is';

		// Match all scripts
		preg_match_all( $regex_scripts, $html, $matches );

		if ( empty( $matches[0] ) ) {
			return $html;
		}

		$local_scripts = array();
		$home_url      = home_url();

		// Check custom user JS exclusions
		$exclude_js = isset( $options['exclude_js'] ) ? trim( $options['exclude_js'] ) : '';
		$excludes = ! empty( $exclude_js ) ? array_filter( array_map( 'trim', explode( "\n", $exclude_js ) ) ) : array();

		foreach ( $matches[1] as $index => $script_url ) {
			// Skip external scripts
			if ( 0 !== strpos( $script_url, $home_url ) && 0 !== strpos( $script_url, '/' ) ) {
				continue;
			}

			// Exclusions check
			$excluded = false;
			foreach ( $excludes as $exclude ) {
				if ( ! empty( $exclude ) && false !== strpos( $script_url, $exclude ) ) {
					$excluded = true;
					break;
				}
			}
			if ( $excluded ) {
				continue;
			}

			// Clean URL query strings
			$clean_url = strtok( $script_url, '?' );

			// Convert to local path
			if ( 0 === strpos( $clean_url, $home_url ) ) {
				$local_path = ABSPATH . ltrim( str_replace( $home_url, '', $clean_url ), '/' );
			} else {
				$local_path = ABSPATH . ltrim( $clean_url, '/' );
			}

			$local_path = wp_normalize_path( $local_path );

			if ( file_exists( $local_path ) && is_readable( $local_path ) ) {
				$local_scripts[] = array(
					'tag'        => $matches[0][$index],
					'url'        => $script_url,
					'clean_url'  => $clean_url,
					'local_path' => $local_path,
				);
			}
		}

		if ( empty( $local_scripts ) ) {
			return $html;
		}

		// Case A: Combine and Minify all local scripts
		if ( ! empty( $options['combine_js'] ) ) {
			// Generate combined hash based on all clean URLs
			$url_list      = array_column( $local_scripts, 'clean_url' );
			$combined_hash = md5( implode( ',', $url_list ) );
			$cache_filename = 'combined-' . $combined_hash . '.js';
			$cache_file_path = $assets_dir . $cache_filename;
			$cache_file_url  = $assets_url . $cache_filename;

			if ( ! file_exists( $cache_file_path ) ) {
				$combined_code = '';
				foreach ( $local_scripts as $script ) {
					$content = file_get_contents( $script['local_path'] );
					if ( $content ) {
						// Append script content with trailing semicolon and newline
						$combined_code .= $content . ";\n";
					}
				}

				if ( ! empty( $options['minify_js'] ) ) {
					$combined_code = self::minify_js( $combined_code );
				}

				self::save_cached_asset( $cache_file_path, $combined_code );
			}

			if ( file_exists( $cache_file_path ) ) {
				// Replace the first local script with the combined script link, and remove all others
				$first_script = true;
				foreach ( $local_scripts as $script ) {
					if ( $first_script ) {
						$combined_tag = sprintf( '<script src="%s"></script>', esc_url( $cache_file_url ) );
						$html = str_replace( $script['tag'], $combined_tag, $html );
						$first_script = false;
					} else {
						$html = str_replace( $script['tag'], '', $html );
					}
				}
			}
		} 
		// Case B: Minify individual scripts (no combination)
		elseif ( ! empty( $options['minify_js'] ) ) {
			foreach ( $local_scripts as $script ) {
				$cache_filename = md5( $script['clean_url'] ) . '.js';
				$cache_file_path = $assets_dir . $cache_filename;
				$cache_file_url  = $assets_url . $cache_filename;

				if ( ! file_exists( $cache_file_path ) ) {
					$content = file_get_contents( $script['local_path'] );
					if ( $content ) {
						$minified = self::minify_js( $content );
						self::save_cached_asset( $cache_file_path, $minified );
					}
				}

				if ( file_exists( $cache_file_path ) ) {
					$minified_tag = str_replace( $script['url'], $cache_file_url, $script['tag'] );
					$html = str_replace( $script['tag'], $minified_tag, $html );
				}
			}
		}

		return $html;
	}

	/**
	 * Minify CSS code content.
	 *
	 * @param string $css Original CSS code.
	 * @return string Minified CSS code.
	 */
	public static function minify_css( $css ) {
		if ( empty( $css ) ) {
			return $css;
		}

		// Remove comments.
		$css = preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Remove spaces around block delimiters and key/value separators.
		$css = preg_replace( '/\s*([{}|:;,])\s*/', '$1', $css );

		// Replace multiple spaces with a single space.
		$css = preg_replace( '/\s+/', ' ', $css );

		// Remove leading/trailing spaces.
		$css = trim( $css );

		return $css;
	}

	/**
	 * Minify JavaScript code content safely.
	 *
	 * @param string $js Original JS code.
	 * @return string Minified JS code.
	 */
	public static function minify_js( $js ) {
		if ( empty( $js ) ) {
			return $js;
		}

		// 1. Remove multi-line comments.
		$js = preg_replace( '/\/\*.*?\*\//s', '', $js );

		// 2. Remove single-line comments.
		$lines = explode( "\n", $js );
		foreach ( $lines as &$line ) {
			$line = preg_replace( '/\s*\/\/.*$/', '', $line );
		}
		$js = implode( "\n", $lines );

		// 3. Replace multiple spaces/tabs with a single space.
		$js = preg_replace( '/[ \t]+/', ' ', $js );

		// 4. Remove spaces around operators where safe.
		$js = preg_replace( '/\s*([{}()\[\]=+\-*\/&|!<>?:;,])\s*/', '$1', $js );

		// 5. Remove empty lines.
		$js = preg_replace( '/\n+/', "\n", $js );

		return trim( $js );
	}

	/**
	 * Minify HTML output safely, preserving special tags.
	 *
	 * @param string $html Original HTML markup.
	 * @return string Minified HTML markup.
	 */
	public static function minify_html( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Placeholders array.
		$placeholders = array();
		$i            = 0;

		// Regex to find pre, code, textarea, and script blocks.
		$regex_tags = '/<(pre|code|textarea|script)\b[^>]*>.*?<\/\\1>/is';

		// Replace protected blocks with placeholders.
		$html = preg_replace_callback(
			$regex_tags,
			function ( $matches ) use ( &$placeholders, &$i ) {
				$placeholder                  = "___SPDR_PLACEHOLDER_{$i}___";
				$placeholders[ $placeholder ] = $matches[0];
				$i++;
				return $placeholder;
			},
			$html
		);

		// Remove HTML comments (except Internet Explorer conditional comments).
		$html = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+\]|<!|>))(?:(?!-->).)*-->/is', '', $html );

		// Remove whitespaces, newlines, and tabs.
		$html = preg_replace( '/\s+/u', ' ', $html );

		// Remove whitespace between tags.
		$html = preg_replace( '/>\s+</u', '><', $html );

		// Replace placeholders back.
		if ( ! empty( $placeholders ) ) {
			$html = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $html );
		}

		return trim( $html );
	}

	/**
	 * Defer non-critical enqueued scripts.
	 *
	 * @param string $tag    The <script> tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified <script> tag.
	 */
	public function add_defer_attribute( $tag, $handle, $src ) {
		// Only run if defer_js is enabled.
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['defer_js'] ) ) {
			return $tag;
		}

		// Never defer in admin dashboard.
		if ( is_admin() ) {
			return $tag;
		}

		// List of critical handles to exclude from deferring.
		$exclude_handles = array(
			'jquery',
			'jquery-core',
			'jquery-migrate',
			'admin-bar',
		);

		// Allow themes or plugins to filter exclusions.
		$exclude_handles = apply_filters( 'spdr_defer_js_exclude_handles', $exclude_handles );

		if ( in_array( $handle, $exclude_handles, true ) ) {
			return $tag;
		}

		// Also check if src contains critical scripts.
		foreach ( $exclude_handles as $exclude ) {
			if ( false !== strpos( $src, $exclude ) ) {
				return $tag;
			}
		}

		// Check custom user JS exclusions
		$options = get_option( 'spdr_settings' );
		$exclude_js = isset( $options['exclude_js'] ) ? trim( $options['exclude_js'] ) : '';
		if ( ! empty( $exclude_js ) ) {
			$excludes = array_filter( array_map( 'trim', explode( "\n", $exclude_js ) ) );
			foreach ( $excludes as $exclude ) {
				if ( ! empty( $exclude ) && false !== strpos( $src, $exclude ) ) {
					return $tag;
				}
			}
		}

		// Add defer attribute safely if not already present.
		if ( false === strpos( $tag, ' src=' ) ) {
			return $tag;
		}

		if ( false === strpos( $tag, ' defer' ) && false === strpos( $tag, ' defer=' ) ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}

		return $tag;
	}

	/**
	 * Rewrite JS script tags to delay their execution until user interaction.
	 *
	 * @param string $html Original HTML.
	 * @return string Processed HTML.
	 */
	public function delay_javascript( $html ) {
		$options = get_option( 'spdr_settings' );
		if ( empty( $options['delay_js'] ) ) {
			return $html;
		}

		// 1. Rewrite script tags with src (exclude critical handles like jquery)
		$exclude_js = isset( $options['exclude_js'] ) ? trim( $options['exclude_js'] ) : '';
		$excludes = ! empty( $exclude_js ) ? array_filter( array_map( 'trim', explode( "\n", $exclude_js ) ) ) : array();
		
		// Add default critical handles to keep render-safe
		$excludes[] = 'jquery';
		$excludes[] = 'jquery-core';
		$excludes[] = 'jquery-migrate';
		$excludes[] = 'admin-bar';
		$excludes[] = 'spdr-toast';

		// Match all script tags with src
		$regex_scripts = '/<script\s+[^>]*src=[\'"]([^\'"]+\.js(?:\?[^\'"]*)?)[\'"][^>]*>\s*<\/script>/is';
		$html = preg_replace_callback(
			$regex_scripts,
			function( $matches ) use ( $excludes ) {
				$tag = $matches[0];
				$src = $matches[1];

				foreach ( $excludes as $exclude ) {
					if ( ! empty( $exclude ) && false !== strpos( $src, $exclude ) ) {
						return $tag;
					}
				}

				// Convert to delayed script
				$delayed_tag = str_replace( ' src=', ' data-spdr-src=', $tag );
				// Remove async or defer if present
				$delayed_tag = str_replace( array( ' async', ' defer' ), '', $delayed_tag );
				return $delayed_tag;
			},
			$html
		);

		// 2. Inject Delay JS Execution script before </body>
		$loader_script = '
<script id="spdr-delay-js-loader">
(function() {
	var interacted = false;
	function triggerDelayedScripts() {
		if (interacted) return;
		interacted = true;
		var events = ["scroll", "click", "mousemove", "keydown", "touchstart"];
		events.forEach(function(e) { window.removeEventListener(e, triggerDelayedScripts); });
		
		var scripts = document.querySelectorAll("script[data-spdr-src]");
		var index = 0;
		function loadNext() {
			if (index >= scripts.length) return;
			var oldScript = scripts[index++];
			var newScript = document.createElement("script");
			Array.from(oldScript.attributes).forEach(function(attr) {
				if (attr.name !== "data-spdr-src" && attr.name !== "src") {
					newScript.setAttribute(attr.name, attr.value);
				}
			});
			newScript.src = oldScript.getAttribute("data-spdr-src");
			newScript.onload = loadNext;
			newScript.onerror = loadNext;
			oldScript.parentNode.replaceChild(newScript, oldScript);
		}
		loadNext();
	}
	var events = ["scroll", "click", "mousemove", "keydown", "touchstart"];
	events.forEach(function(e) { window.addEventListener(e, triggerDelayedScripts, { passive: true }); });
})();
</script>';

		$html = str_replace( '</body>', $loader_script . "\n" . '</body>', $html );

		return $html;
	}

	/**
	 * Save cached asset file and generate pre-compressed gzip version if enabled.
	 *
	 * @param string $file_path Absolute file path on disk.
	 * @param string $content Code content.
	 */
	public static function save_cached_asset( $file_path, $content ) {
		// Save original minified asset.
		file_put_contents( $file_path, $content );

		// Check if gzip option is enabled.
		$options = get_option( 'spdr_settings' );
		if ( ! empty( $options['gzip_compression'] ) && function_exists( 'gzencode' ) ) {
			$gzipped = gzencode( $content, 9 );
			if ( false !== $gzipped ) {
				file_put_contents( $file_path . '.gz', $gzipped );
			}
		}
	}
}

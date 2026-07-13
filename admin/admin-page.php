<?php
/**
 * Admin page display template.
 *
 * @package Speed Doctor
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the admin page.
 */
function spdr_admin_page_display() {
	// Security check: Verify user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'speed-doctor' ) );
	}

	// Get current option values.
	$options             = get_option( 'spdr_settings' );
	$page_cache          = ! empty( $options['page_cache'] ) ? 1 : 0;
	$minify_html         = ! empty( $options['minify_html'] ) ? 1 : 0;
	$minify_css          = ! empty( $options['minify_css'] ) ? 1 : 0;
	$minify_js           = ! empty( $options['minify_js'] ) ? 1 : 0;
	$combine_js          = ! empty( $options['combine_js'] ) ? 1 : 0;
	$defer_js            = ! empty( $options['defer_js'] ) ? 1 : 0;
	$lazy_load           = ! empty( $options['lazy_load'] ) ? 1 : 0;
	$cdn_enable          = ! empty( $options['cdn_enable'] ) ? 1 : 0;
	$cdn_url             = isset( $options['cdn_url'] ) ? $options['cdn_url'] : '';
	$cdn_exclude         = isset( $options['cdn_exclude'] ) ? $options['cdn_exclude'] : '';
	$heartbeat_behavior  = isset( $options['heartbeat_behavior'] ) ? $options['heartbeat_behavior'] : 'default';
	$remove_ver_query    = ! empty( $options['remove_ver_query'] ) ? 1 : 0;
	$disable_emojis      = ! empty( $options['disable_emojis'] ) ? 1 : 0;
	
	// New options
	$mobile_cache        = ! empty( $options['mobile_cache'] ) ? 1 : 0;
	$logged_in_cache     = ! empty( $options['logged_in_cache'] ) ? 1 : 0;
	$cache_lifespan      = isset( $options['cache_lifespan'] ) ? (int) $options['cache_lifespan'] : 86400;
	$exclude_css         = isset( $options['exclude_css'] ) ? $options['exclude_css'] : '';
	$exclude_js          = isset( $options['exclude_js'] ) ? $options['exclude_js'] : '';
	$delay_js            = ! empty( $options['delay_js'] ) ? 1 : 0;
	$lazy_load_iframes   = ! empty( $options['lazy_load_iframes'] ) ? 1 : 0;
	$lcp_exclude_count   = isset( $options['lcp_exclude_count'] ) ? (int) $options['lcp_exclude_count'] : 1;
	$add_img_dimensions  = ! empty( $options['add_img_dimensions'] ) ? 1 : 0;
	$db_cleanup_schedule = isset( $options['db_cleanup_schedule'] ) ? $options['db_cleanup_schedule'] : 'disabled';

	// Preloader options
	$preload_enable           = ! empty( $options['preload_enable'] ) ? 1 : 0;
	$preload_pages_per_minute = isset( $options['preload_pages_per_minute'] ) ? (int) $options['preload_pages_per_minute'] : 10;
	$preload_types            = isset( $options['preload_types'] ) ? (array) $options['preload_types'] : array( 'homepage', 'posts', 'pages' );
	$gzip_compression         = ! empty( $options['gzip_compression'] ) ? 1 : 0;
	?>
	<div class="wrap spdr-admin-wrap" id="spdr-wrap">
		<!-- Theme Detection Script to avoid flash of dark mode (FOUC) -->
		<script>
			(function() {
				var savedTheme = localStorage.getItem('spdr_theme');
				if (savedTheme === 'light') {
					document.getElementById('spdr-wrap').classList.add('spdr-light-mode');
				}
			})();
		</script>

		<!-- Header -->
		<div class="spdr-header">
			<div class="spdr-header-title">
				<h1><?php echo esc_html__( 'Speed Doctor Pro', 'speed-doctor' ); ?> <span class="spdr-version-badge">v<?php echo esc_html( SPDR_VERSION ); ?></span></h1>
				<p class="description">
					<?php echo esc_html__( 'Professional high-performance website optimization engine.', 'speed-doctor' ); ?>
				</p>
			</div>
			<button type="button" class="spdr-theme-toggle" id="spdr-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Theme', 'speed-doctor' ); ?>">
				<!-- Sun Icon -->
				<svg class="spdr-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="5"></circle>
					<line x1="12" y1="1" x2="12" y2="3"></line>
					<line x1="12" y1="21" x2="12" y2="23"></line>
					<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
					<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
					<line x1="1" y1="12" x2="3" y2="12"></line>
					<line x1="21" y1="12" x2="23" y2="12"></line>
					<line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
					<line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
				</svg>
				<!-- Moon Icon -->
				<svg class="spdr-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
				</svg>
			</button>
		</div>

		<!-- Single Unified Form wrapping settings panels -->
		<form method="post" action="options.php" class="spdr-settings-form" id="spdr-main-form">
			<?php settings_fields( 'spdr_settings_group' ); ?>
			
			<div class="spdr-layout">
				<!-- Left Sidebar Navigation -->
				<div class="spdr-sidebar">
					<ul class="spdr-nav-menu">
						<li class="spdr-tab-btn active" data-target="dashboard">
							<span class="dashicons dashicons-dashboard"></span>
							<?php echo esc_html__( 'Dashboard', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="cache">
							<span class="dashicons dashicons-admin-network"></span>
							<?php echo esc_html__( 'Page Cache', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="assets">
							<span class="dashicons dashicons-media-code"></span>
							<?php echo esc_html__( 'File Optimization', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="media">
							<span class="dashicons dashicons-format-image"></span>
							<?php echo esc_html__( 'Media Settings', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="cdn">
							<span class="dashicons dashicons-cloud"></span>
							<?php echo esc_html__( 'CDN Settings', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="heartbeat">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php echo esc_html__( 'Advanced Rules', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="db">
							<span class="dashicons dashicons-database"></span>
							<?php echo esc_html__( 'Database Doctor', 'speed-doctor' ); ?>
						</li>
						<li class="spdr-tab-btn" data-target="info">
							<span class="dashicons dashicons-info"></span>
							<?php echo esc_html__( 'System Info', 'speed-doctor' ); ?>
						</li>
					</ul>
					
					<div class="spdr-sidebar-footer">
						<input type="submit" class="button button-primary spdr-save-btn" value="<?php esc_attr_e( 'Save Settings', 'speed-doctor' ); ?>" />
					</div>
				</div>

				<!-- Right Panel Content Container -->
				<div class="spdr-content">
					
					<!-- Tab 1: Dashboard Panel -->
					<div class="spdr-tab-panel active" id="panel-dashboard">
						<div class="spdr-dashboard-header">
							<!-- Speedometer gauge widget -->
							<div class="spdr-gauge-container">
								<div class="spdr-gauge">
									<svg>
										<circle class="spdr-gauge-circle-bg" cx="85" cy="85" r="70"></circle>
										<circle class="spdr-gauge-circle-val" cx="85" cy="85" r="70" style="stroke-dashoffset: 440;"></circle>
									</svg>
									<div class="spdr-gauge-text">
										<div class="spdr-gauge-score">60</div>
										<div class="spdr-gauge-label"><?php echo esc_html__( 'Speed Score', 'speed-doctor' ); ?></div>
									</div>
								</div>
							</div>
							
							<div class="spdr-welcome-box">
								<h2><?php echo esc_html__( 'Welcome to Speed Doctor Pro', 'speed-doctor' ); ?></h2>
								<p><?php echo esc_html__( 'Configure the modules on the left to safely optimize scripts, page rendering, cache, and database tables for maximum loading performance.', 'speed-doctor' ); ?></p>
							</div>
						</div>

						<div class="spdr-dashboard-grid">
							<!-- Quick Status Cards -->
							<div class="spdr-card">
								<h3><?php echo esc_html__( 'Page Cache', 'speed-doctor' ); ?></h3>
								<p><?php echo esc_html__( 'Caching is:', 'speed-doctor' ); ?> <span class="spdr-status-badge <?php echo $page_cache ? 'active' : 'inactive'; ?>"><?php echo $page_cache ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></span></p>
								<a href="#" class="spdr-trigger-tab link-action" data-tab="cache"><?php echo esc_html__( 'Configure', 'speed-doctor' ); ?> &rarr;</a>
							</div>
							<div class="spdr-card">
								<h3><?php echo esc_html__( 'HTML Minify', 'speed-doctor' ); ?></h3>
								<p><?php echo esc_html__( 'Minifier is:', 'speed-doctor' ); ?> <span class="spdr-status-badge <?php echo $minify_html ? 'active' : 'inactive'; ?>"><?php echo $minify_html ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></span></p>
								<a href="#" class="spdr-trigger-tab link-action" data-tab="assets"><?php echo esc_html__( 'Configure', 'speed-doctor' ); ?> &rarr;</a>
							</div>
							<div class="spdr-card">
								<h3><?php echo esc_html__( 'CSS & JS Minify', 'speed-doctor' ); ?></h3>
								<p><?php echo esc_html__( 'Asset Minification:', 'speed-doctor' ); ?> <span class="spdr-status-badge <?php echo ($minify_css || $minify_js) ? 'active' : 'inactive'; ?>"><?php echo ($minify_css || $minify_js) ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></span></p>
								<a href="#" class="spdr-trigger-tab link-action" data-tab="assets"><?php echo esc_html__( 'Configure', 'speed-doctor' ); ?> &rarr;</a>
							</div>
							<div class="spdr-card">
								<h3><?php echo esc_html__( 'Lazy Loading', 'speed-doctor' ); ?></h3>
								<p><?php echo esc_html__( 'Media deferred:', 'speed-doctor' ); ?> <span class="spdr-status-badge <?php echo $lazy_load ? 'active' : 'inactive'; ?>"><?php echo $lazy_load ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></span></p>
								<a href="#" class="spdr-trigger-tab link-action" data-tab="media"><?php echo esc_html__( 'Configure', 'speed-doctor' ); ?> &rarr;</a>
							</div>
							
							<!-- Purge card spanning entire row -->
							<div class="spdr-card spdr-purge-card" style="grid-column: span 2;">
								<h3><?php echo esc_html__( 'Quick Purge Actions', 'speed-doctor' ); ?></h3>
								<p><?php echo esc_html__( 'Selectively clear your website cache structures or assets below.', 'speed-doctor' ); ?></p>
								<div class="spdr-purge-buttons">
									<button type="button" class="button button-primary spdr-ajax-purge-btn" data-type="html"><?php echo esc_html__( 'Purge HTML Structure', 'speed-doctor' ); ?></button>
									<button type="button" class="button button-secondary spdr-ajax-purge-btn" data-type="css"><?php echo esc_html__( 'Purge CSS Cache', 'speed-doctor' ); ?></button>
									<button type="button" class="button button-secondary spdr-ajax-purge-btn" data-type="js"><?php echo esc_html__( 'Purge JS Cache', 'speed-doctor' ); ?></button>
									<button type="button" class="button button-secondary spdr-ajax-purge-btn dest-btn" data-type="all"><?php echo esc_html__( 'Purge All', 'speed-doctor' ); ?></button>
								</div>
							</div>
						</div>
					</div>

					<!-- Tab 2: Page Cache Settings -->
					<div class="spdr-tab-panel" id="panel-cache">
						<h2><?php echo esc_html__( 'Page Cache Settings', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Page caching generates static HTML files of your site to bypass database queries and render the pages instantly.', 'speed-doctor' ); ?></p>
						
						<div class="spdr-form-grid">
							<!-- Toggle Page Caching -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="page_cache"><?php echo esc_html__( 'Enable Page Caching', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Cache front-end pages as static HTML to speed up guest visits.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="page_cache" name="spdr_settings[page_cache]" value="1" <?php checked( 1, $page_cache ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Mobile Caching -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="mobile_cache"><?php echo esc_html__( 'Mobile Caching', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Generate a dedicated static cache specifically for mobile devices.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="mobile_cache" name="spdr_settings[mobile_cache]" value="1" <?php checked( 1, $mobile_cache ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Logged-in Caching -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="logged_in_cache"><?php echo esc_html__( 'Logged-in User Caching', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Serve cached pages to logged-in WordPress users (Warning: may conflict with personalized dynamic features).', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="logged_in_cache" name="spdr_settings[logged_in_cache]" value="1" <?php checked( 1, $logged_in_cache ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Cache Lifespan -->
							<div class="spdr-option-row select-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="cache_lifespan"><?php echo esc_html__( 'Cache Lifespan', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Specify the automatic cache expiration period.', 'speed-doctor' ); ?></p>
								</div>
								<select id="cache_lifespan" name="spdr_settings[cache_lifespan]" class="spdr-select-dropdown">
									<option value="36000" <?php selected( $cache_lifespan, 36000 ); ?>><?php esc_html_e( '10 Hours', 'speed-doctor' ); ?></option>
									<option value="86400" <?php selected( $cache_lifespan, 86400 ); ?>><?php esc_html_e( '24 Hours (Default)', 'speed-doctor' ); ?></option>
									<option value="259200" <?php selected( $cache_lifespan, 259200 ); ?>><?php esc_html_e( '3 Days', 'speed-doctor' ); ?></option>
									<option value="604800" <?php selected( $cache_lifespan, 604800 ); ?>><?php esc_html_e( '7 Days', 'speed-doctor' ); ?></option>
								</select>
							</div>
						</div>

						<div class="spdr-section-separator" style="margin: 25px 0; border-top: 1px solid rgba(255,255,255,0.08);"></div>
						
						<h3><?php echo esc_html__( 'Cache Preloader Engine', 'speed-doctor' ); ?></h3>
						<p class="panel-desc"><?php echo esc_html__( 'Preload crawls your site structure to automatically generate static HTML cache files in the background, ensuring pages load warm for your first visitors.', 'speed-doctor' ); ?></p>
						
						<div class="spdr-form-grid">
							<!-- Enable Preloader -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="preload_enable"><?php echo esc_html__( 'Enable Preloader', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Schedule background WP-Cron preloading jobs.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="preload_enable" name="spdr_settings[preload_enable]" value="1" <?php checked( 1, $preload_enable ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Preload Crawl Speed -->
							<div class="spdr-option-row select-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="preload_pages_per_minute"><?php echo esc_html__( 'Preload Crawl Rate', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Specify the number of pages crawled per minute (adjust to save CPU).', 'speed-doctor' ); ?></p>
								</div>
								<select id="preload_pages_per_minute" name="spdr_settings[preload_pages_per_minute]" class="spdr-select-dropdown">
									<option value="4" <?php selected( $preload_pages_per_minute, 4 ); ?>><?php esc_html_e( '4 Pages/Min (Recommended for Shared Hosting)', 'speed-doctor' ); ?></option>
									<option value="6" <?php selected( $preload_pages_per_minute, 6 ); ?>><?php esc_html_e( '6 Pages/Min', 'speed-doctor' ); ?></option>
									<option value="10" <?php selected( $preload_pages_per_minute, 10 ); ?>><?php esc_html_e( '10 Pages/Min (Default)', 'speed-doctor' ); ?></option>
									<option value="15" <?php selected( $preload_pages_per_minute, 15 ); ?>><?php esc_html_e( '15 Pages/Min (Recommended for VPS)', 'speed-doctor' ); ?></option>
								</select>
							</div>

							<!-- Preload Target Types -->
							<div class="spdr-option-row full-width-row" style="grid-column: span 2;">
								<div class="spdr-option-info">
									<label class="spdr-option-label"><?php echo esc_html__( 'Preload Target Layouts', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Choose what categories of URLs compile into the crawler queue.', 'speed-doctor' ); ?></p>
									<div class="spdr-checkbox-group" style="display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap;">
										<?php
										$types_list = array(
											'homepage'   => esc_html__( 'Homepage', 'speed-doctor' ),
											'posts'      => esc_html__( 'Posts', 'speed-doctor' ),
											'pages'      => esc_html__( 'Pages', 'speed-doctor' ),
											'categories' => esc_html__( 'Categories', 'speed-doctor' ),
											'tags'       => esc_html__( 'Tags', 'speed-doctor' ),
										);
										foreach ( $types_list as $type_key => $type_label ) {
											$is_checked = in_array( $type_key, $preload_types, true ) ? 'checked' : '';
											echo '<label style="display: flex; align-items: center; gap: 5px; cursor: pointer; color: rgba(255,255,255,0.8);">';
											echo '<input type="checkbox" name="spdr_settings[preload_types][]" value="' . esc_attr( $type_key ) . '" ' . $is_checked . ' />';
											echo esc_html( $type_label );
											echo '</label>';
										}
										?>
									</div>
								</div>
							</div>

							<!-- Preloader Live Progress Widget -->
							<div class="spdr-card spdr-preload-status-card full-width-row" style="grid-column: span 2; margin-top: 15px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 15px; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 20px;">
								<div>
									<h4 style="margin: 0 0 5px 0; font-size: 14px; color: #fff;"><?php echo esc_html__( 'Preload Crawl Status', 'speed-doctor' ); ?></h4>
									<div class="spdr-preload-progress-text" style="font-size: 13px; color: rgba(255,255,255,0.7); margin-bottom: 8px;">
										<?php echo esc_html__( 'Checking status...', 'speed-doctor' ); ?>
									</div>
									<div class="spdr-progress-bar-bg" style="background: rgba(255,255,255,0.1); border-radius: 4px; height: 8px; width: 100%; overflow: hidden; position: relative;">
										<div class="spdr-progress-bar-fill" style="background: #1890ff; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
									</div>
								</div>
								<div>
									<button type="button" class="button button-secondary spdr-restart-preload-btn"><?php echo esc_html__( 'Restart Preload', 'speed-doctor' ); ?></button>
								</div>
							</div>
						</div>
					</div>

					<!-- Tab 3: File Optimization Panel -->
					<div class="spdr-tab-panel" id="panel-assets">
						<h2><?php echo esc_html__( 'File Optimization Settings', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Reduce the size of your HTML, CSS, and JS files by removing whitespace, comments, and combining requests.', 'speed-doctor' ); ?></p>

						<div class="spdr-form-grid">
							<!-- HTML Minification -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="minify_html"><?php echo esc_html__( 'HTML Minification', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Strip whitespace, comments, and empty lines from output HTML.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="minify_html" name="spdr_settings[minify_html]" value="1" <?php checked( 1, $minify_html ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- CSS Minification -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="minify_css"><?php echo esc_html__( 'CSS Minification', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Minify enqueued local CSS stylesheets.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="minify_css" name="spdr_settings[minify_css]" value="1" <?php checked( 1, $minify_css ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- JS Minification -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="minify_js"><?php echo esc_html__( 'JS Minification', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Minify enqueued external JavaScript files.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="minify_js" name="spdr_settings[minify_js]" value="1" <?php checked( 1, $minify_js ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- JS Combination -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="combine_js"><?php echo esc_html__( 'JS Combination', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Combine enqueued local JavaScript files into a single asset.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="combine_js" name="spdr_settings[combine_js]" value="1" <?php checked( 1, $combine_js ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Defer JS -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="defer_js"><?php echo esc_html__( 'Defer JavaScript', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Add defer attribute to enqueued scripts to resolve render-blocking resources.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="defer_js" name="spdr_settings[defer_js]" value="1" <?php checked( 1, $defer_js ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Delay JS -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="delay_js"><?php echo esc_html__( 'Delay JS Execution (PageSpeed Booster)', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Delay JavaScript execution until first user interaction (scroll/click) to improve loading scores.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="delay_js" name="spdr_settings[delay_js]" value="1" <?php checked( 1, $delay_js ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- CSS Exclusions -->
							<div class="spdr-option-row textarea-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="exclude_css"><?php echo esc_html__( 'CSS Exclusions', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'List stylesheets or keywords (one per line) to exclude from minification/combination.', 'speed-doctor' ); ?></p>
								</div>
								<textarea id="exclude_css" name="spdr_settings[exclude_css]" class="spdr-textarea-field" rows="4"><?php echo esc_textarea( $exclude_css ); ?></textarea>
							</div>

							<!-- JS Exclusions -->
							<div class="spdr-option-row textarea-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="exclude_js"><?php echo esc_html__( 'JS Exclusions', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'List script files or keywords (one per line) to exclude from minification/combination/defer.', 'speed-doctor' ); ?></p>
								</div>
								<textarea id="exclude_js" name="spdr_settings[exclude_js]" class="spdr-textarea-field" rows="4"><?php echo esc_textarea( $exclude_js ); ?></textarea>
							</div>

							<!-- GZIP Compression -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="gzip_compression"><?php echo esc_html__( 'GZIP Compression', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Pre-compress assets and configure server-level compression to shrink files by up to 70%.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="gzip_compression" name="spdr_settings[gzip_compression]" value="1" <?php checked( 1, $gzip_compression ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>
						</div>
					</div>

					<!-- Tab 4: Media Settings Panel -->
					<div class="spdr-tab-panel" id="panel-media">
						<h2><?php echo esc_html__( 'Media Optimization Settings', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Configure lazy loading for images and iframes, optimize layout shifts (CLS), and exclude critical above-the-fold content.', 'speed-doctor' ); ?></p>

						<div class="spdr-form-grid">
							<!-- Lazy Load Images -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="lazy_load"><?php echo esc_html__( 'Lazy Load Images', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Inject native loading="lazy" and preload options to speed up page rendering.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="lazy_load" name="spdr_settings[lazy_load]" value="1" <?php checked( 1, $lazy_load ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Lazy Load Iframes -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="lazy_load_iframes"><?php echo esc_html__( 'Lazy Load Iframes & Videos', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Decline rendering YouTube or Google Map embeds until they enter the viewport.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="lazy_load_iframes" name="spdr_settings[lazy_load_iframes]" value="1" <?php checked( 1, $lazy_load_iframes ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Image Dimensions Injector -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="add_img_dimensions"><?php echo esc_html__( 'Add Missing Image Dimensions (CLS Helper)', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Automatically inject width and height attributes to enqueued images to prevent Cumulative Layout Shifts.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="add_img_dimensions" name="spdr_settings[add_img_dimensions]" value="1" <?php checked( 1, $add_img_dimensions ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- LCP Exclusions -->
							<div class="spdr-option-row select-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="lcp_exclude_count"><?php echo esc_html__( 'Skip First N Images from Lazy Loading (LCP Shield)', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Protects Largest Contentful Paint (LCP) by preventing the logo or hero image from being lazy-loaded.', 'speed-doctor' ); ?></p>
								</div>
								<input type="number" id="lcp_exclude_count" name="spdr_settings[lcp_exclude_count]" value="<?php echo esc_attr( $lcp_exclude_count ); ?>" min="0" max="10" class="spdr-number-input" />
							</div>
						</div>
					</div>

					<!-- Tab 5: CDN Settings Panel -->
					<div class="spdr-tab-panel" id="panel-cdn">
						<h2><?php echo esc_html__( 'CDN Integration Settings', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Automatically rewrite local enqueued asset paths to fetch from a Content Delivery Network hostname.', 'speed-doctor' ); ?></p>

						<div class="spdr-form-grid">
							<!-- Enable CDN -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="cdn_enable"><?php echo esc_html__( 'Enable CDN URL Rewriting', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Enable static asset URL rewriting using the CDN host defined below.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="cdn_enable" name="spdr_settings[cdn_enable]" value="1" <?php checked( 1, $cdn_enable ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- CDN URL -->
							<div class="spdr-option-row text-input-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="cdn_url"><?php echo esc_html__( 'CDN Base URL', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Enter your CDN endpoint (e.g., https://cdn.yourdomain.com or //cdn.yourdomain.com).', 'speed-doctor' ); ?></p>
								</div>
								<input type="text" id="cdn_url" name="spdr_settings[cdn_url]" value="<?php echo esc_attr( $cdn_url ); ?>" placeholder="https://cdn.example.com" class="spdr-text-input" />
							</div>

							<!-- CDN Exclusions -->
							<div class="spdr-option-row textarea-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="cdn_exclude"><?php echo esc_html__( 'CDN Exclusions', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Paths, files, or extensions (one per line) to keep on the local domain (e.g. .php, wp-admin).', 'speed-doctor' ); ?></p>
								</div>
								<textarea id="cdn_exclude" name="spdr_settings[cdn_exclude]" class="spdr-textarea-field" rows="4"><?php echo esc_textarea( $cdn_exclude ); ?></textarea>
							</div>
						</div>
					</div>

					<!-- Tab 6: Advanced Rules (Heartbeat & Emojis) -->
					<div class="spdr-tab-panel" id="panel-heartbeat">
						<h2><?php echo esc_html__( 'Advanced Rules', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Manage background AJAX requests, clean up version parameters from urls, and strip out redundant WordPress elements.', 'speed-doctor' ); ?></p>

						<div class="spdr-form-grid">
							<!-- Remove Query Strings -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="remove_ver_query"><?php echo esc_html__( 'Remove Query Strings', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Remove version parameters (e.g. ?ver=x.x) from enqueued styles and scripts.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="remove_ver_query" name="spdr_settings[remove_ver_query]" value="1" <?php checked( 1, $remove_ver_query ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Disable Emojis -->
							<div class="spdr-option-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="disable_emojis"><?php echo esc_html__( 'Disable Core Emojis', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Remove core emoji scripts, styling, and DNS prefetch assets to save HTTP connections.', 'speed-doctor' ); ?></p>
								</div>
								<label class="spdr-toggle-switch">
									<input type="checkbox" id="disable_emojis" name="spdr_settings[disable_emojis]" value="1" <?php checked( 1, $disable_emojis ); ?> />
									<span class="spdr-slider"></span>
								</label>
							</div>

							<!-- Heartbeat Behavior -->
							<div class="spdr-option-row select-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="heartbeat_behavior"><?php echo esc_html__( 'Heartbeat API Control', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Throttling background requests reduces server CPU loads significantly.', 'speed-doctor' ); ?></p>
								</div>
								<select id="heartbeat_behavior" name="spdr_settings[heartbeat_behavior]" class="spdr-select-dropdown">
									<option value="default" <?php selected( $heartbeat_behavior, 'default' ); ?>><?php esc_html_e( 'Default WordPress Behavior', 'speed-doctor' ); ?></option>
									<option value="throttle" <?php selected( $heartbeat_behavior, 'throttle' ); ?>><?php esc_html_e( 'Throttle (Increase interval to 60s)', 'speed-doctor' ); ?></option>
									<option value="disable_frontend" <?php selected( $heartbeat_behavior, 'disable_frontend' ); ?>><?php esc_html_e( 'Disable on Front-end Only', 'speed-doctor' ); ?></option>
									<option value="disable_everywhere" <?php selected( $heartbeat_behavior, 'disable_everywhere' ); ?>><?php esc_html_e( 'Disable Everywhere', 'speed-doctor' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Tab 7: Database Doctor Panel -->
					<div class="spdr-tab-panel" id="panel-db">
						<h2><?php echo esc_html__( 'Database Doctor', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Optimize database table indexing and schedule cleanups to prevent slow queries.', 'speed-doctor' ); ?></p>

						<div class="spdr-form-grid">
							<!-- Auto Cleanup Schedule -->
							<div class="spdr-option-row select-row">
								<div class="spdr-option-info">
									<label class="spdr-option-label" for="db_cleanup_schedule"><?php echo esc_html__( 'Schedule Auto-Cleanup', 'speed-doctor' ); ?></label>
									<p class="spdr-field-desc"><?php echo esc_html__( 'Automatically run database cleanups at the specified frequency.', 'speed-doctor' ); ?></p>
								</div>
								<select id="db_cleanup_schedule" name="spdr_settings[db_cleanup_schedule]" class="spdr-select-dropdown">
									<option value="disabled" <?php selected( $db_cleanup_schedule, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'speed-doctor' ); ?></option>
									<option value="daily" <?php selected( $db_cleanup_schedule, 'daily' ); ?>><?php esc_html_e( 'Daily', 'speed-doctor' ); ?></option>
									<option value="weekly" <?php selected( $db_cleanup_schedule, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'speed-doctor' ); ?></option>
								</select>
							</div>
						</div>

						<h3 style="margin-top: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px;"><?php esc_html_e( 'Manual Database Purging', 'speed-doctor' ); ?></h3>
						<div class="spdr-dashboard-grid" style="grid-template-columns: 1fr; max-width: 600px; margin-top: 15px;">
							
							<!-- Clean Revisions -->
							<div class="spdr-card db-card">
								<div class="db-card-info">
									<h4><?php echo esc_html__( 'Obsolete Post Revisions', 'speed-doctor' ); ?></h4>
									<p><?php echo esc_html__( 'Delete past edit saves of posts and pages.', 'speed-doctor' ); ?></p>
								</div>
								<button type="button" class="button button-primary spdr-ajax-db-btn" data-action="clean_revisions">
									<?php echo esc_html__( 'Purge Revisions', 'speed-doctor' ); ?>
								</button>
							</div>

							<!-- Clean Auto-Drafts -->
							<div class="spdr-card db-card">
								<div class="db-card-info">
									<h4><?php echo esc_html__( 'Unsaved Auto-Drafts', 'speed-doctor' ); ?></h4>
									<p><?php echo esc_html__( 'Purge orphaned draft records.', 'speed-doctor' ); ?></p>
								</div>
								<button type="button" class="button button-primary spdr-ajax-db-btn" data-action="clean_autodrafts">
									<?php echo esc_html__( 'Purge Auto-Drafts', 'speed-doctor' ); ?>
								</button>
							</div>

							<!-- Clean Spam & Trash Comments -->
							<div class="spdr-card db-card">
								<div class="db-card-info">
									<h4><?php echo esc_html__( 'Spam & Trash Comments', 'speed-doctor' ); ?></h4>
									<p><?php echo esc_html__( 'Empty the comment trash and spam inbox.', 'speed-doctor' ); ?></p>
								</div>
								<button type="button" class="button button-primary spdr-ajax-db-btn" data-action="clean_comments">
									<?php echo esc_html__( 'Purge Comments', 'speed-doctor' ); ?>
								</button>
							</div>

							<!-- Clean Transients -->
							<div class="spdr-card db-card">
								<div class="db-card-info">
									<h4><?php echo esc_html__( 'Expired Transients', 'speed-doctor' ); ?></h4>
									<p><?php echo esc_html__( 'Delete old cache objects from options.', 'speed-doctor' ); ?></p>
								</div>
								<button type="button" class="button button-primary spdr-ajax-db-btn" data-action="clean_transients">
									<?php echo esc_html__( 'Purge Transients', 'speed-doctor' ); ?>
								</button>
							</div>

							<!-- Optimize Tables -->
							<div class="spdr-card db-card">
								<div class="db-card-info">
									<h4><?php echo esc_html__( 'Optimize Database Tables', 'speed-doctor' ); ?></h4>
									<p><?php echo esc_html__( 'Run index optimizations to reclaim overhead storage space.', 'speed-doctor' ); ?></p>
								</div>
								<button type="button" class="button button-primary spdr-ajax-db-btn" data-action="optimize_tables">
									<?php echo esc_html__( 'Optimize Tables', 'speed-doctor' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Tab 8: System Info Panel -->
					<div class="spdr-tab-panel" id="panel-info">
						<h2><?php echo esc_html__( 'System Info & Diagnostics', 'speed-doctor' ); ?></h2>
						<p class="panel-desc"><?php echo esc_html__( 'Review storage limits, directory details, database footprint, and system configurations below.', 'speed-doctor' ); ?></p>
						
						<?php
						$cache_size = SPDR_Cache::get_instance()->get_cache_dir_size();
						$db_stats   = SPDR_DB::get_instance()->get_db_stats();
						?>
						<table class="widefat striped spdr-info-table" style="margin-top: 15px;">
							<tbody>
								<tr>
									<td><strong><?php echo esc_html__( 'Plugin Version', 'speed-doctor' ); ?></strong></td>
									<td><?php echo esc_attr( SPDR_VERSION ); ?></td>
								</tr>
								<tr>
									<td><strong><?php echo esc_html__( 'PHP Version', 'speed-doctor' ); ?></strong></td>
									<td><?php echo esc_html( phpversion() ); ?></td>
								</tr>
								<tr>
									<td><strong><?php echo esc_html__( 'Server Software', 'speed-doctor' ); ?></strong></td>
									<td><?php echo isset( $_SERVER['SERVER_SOFTWARE'] ) ? esc_html( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : 'N/A'; ?></td>
								</tr>
								<tr>
									<td><strong><?php echo esc_html__( 'Cache Directory Size', 'speed-doctor' ); ?></strong></td>
									<td><?php echo esc_html( SPDR::format_bytes( $cache_size ) ); ?></td>
								</tr>
								<tr>
									<td><strong><?php echo esc_html__( 'Database Size', 'speed-doctor' ); ?></strong></td>
									<td><?php echo esc_html( SPDR::format_bytes( $db_stats['size'] ) ); ?></td>
								</tr>
								<tr>
									<td><strong><?php echo esc_html__( 'Database Overhead', 'speed-doctor' ); ?></strong></td>
									<td>
										<?php echo esc_html( SPDR::format_bytes( $db_stats['overhead'] ) ); ?>
										<?php if ( $db_stats['overhead'] > 0 ) : ?>
											<span class="spdr-badge error-badge">
												<?php esc_html_e( 'Cleanup Recommended', 'speed-doctor' ); ?>
											</span>
										<?php else : ?>
											<span class="spdr-badge success-badge">
												<?php esc_html_e( 'Optimized', 'speed-doctor' ); ?>
											</span>
										<?php endif; ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

				</div>
			</div>
		</form>
		
		<!-- Animated Toast Notification Container -->
		<div id="spdr-toast" class="spdr-toast"></div>
	</div>

	<!-- Sidebar Tabs & Theme Switch Scripts -->
	<script>
		(function() {
			try {
				console.log("Speed Doctor Pro Script Initializing...");

				// Toast Notification Helper
				function showToast(msg, isSuccess) {
					var toast = document.getElementById('spdr-toast');
					if (!toast) return;
					toast.textContent = msg;
					toast.className = 'spdr-toast show ' + (isSuccess ? 'spdr-toast-success' : 'spdr-toast-error');
					setTimeout(function() {
						toast.classList.remove('show');
					}, 3500);
				}

				// Tab switching logic
				var tabButtons = document.querySelectorAll('.spdr-tab-btn');
				var tabPanels = document.querySelectorAll('.spdr-tab-panel');

				for (var i = 0; i < tabButtons.length; i++) {
					(function(btn) {
						btn.addEventListener('click', function() {
							var target = btn.getAttribute('data-target');

							// Toggle active class on buttons
							for (var j = 0; j < tabButtons.length; j++) {
								tabButtons[j].classList.remove('active');
							}
							btn.classList.add('active');

							// Toggle active class on panels
							for (var k = 0; k < tabPanels.length; k++) {
								tabPanels[k].classList.remove('active');
							}
							var targetPanel = document.getElementById('panel-' + target);
							if (targetPanel) {
								targetPanel.classList.add('active');
							}
						});
					})(tabButtons[i]);
				}

				// Dashboard quick action links
				var triggers = document.querySelectorAll('.spdr-trigger-tab');
				for (var i = 0; i < triggers.length; i++) {
					(function(trigger) {
						trigger.addEventListener('click', function(e) {
							e.preventDefault();
							var tabTarget = trigger.getAttribute('data-tab');
							var targetBtn = document.querySelector('.spdr-tab-btn[data-target="' + tabTarget + '"]');
							if (targetBtn) {
								targetBtn.click();
							}
						});
					})(triggers[i]);
				}

				// Light/Dark mode toggle logic
				var toggleBtn = document.getElementById('spdr-theme-toggle');
				if (toggleBtn) {
					toggleBtn.addEventListener('click', function() {
						var wrap = document.getElementById('spdr-wrap');
						if (wrap) {
							var isLight = wrap.classList.contains('spdr-light-mode');
							if (isLight) {
								wrap.classList.remove('spdr-light-mode');
							} else {
								wrap.classList.add('spdr-light-mode');
							}
							try {
								localStorage.setItem('spdr_theme', isLight ? 'dark' : 'light');
							} catch (storageErr) {
								console.warn('Storage write blocked:', storageErr);
							}
						}
					});
				}

				// Live Speedometer Score Calculator
				function calculateScore() {
					var score = 55; // Base score

					if (document.getElementById('page_cache') && document.getElementById('page_cache').checked) {
						score += 15;
					}
					if (document.getElementById('mobile_cache') && document.getElementById('mobile_cache').checked) {
						score += 5;
					}
					if (document.getElementById('logged_in_cache') && document.getElementById('logged_in_cache').checked) {
						score += 3;
					}
					if (document.getElementById('minify_html') && document.getElementById('minify_html').checked) {
						score += 5;
					}
					if (document.getElementById('minify_css') && document.getElementById('minify_css').checked) {
						score += 5;
					}
					if (document.getElementById('minify_js') && document.getElementById('minify_js').checked) {
						score += 5;
					}
					if (document.getElementById('combine_js') && document.getElementById('combine_js').checked) {
						score += 5;
					}
					if (document.getElementById('defer_js') && document.getElementById('defer_js').checked) {
						score += 5;
					}
					if (document.getElementById('delay_js') && document.getElementById('delay_js').checked) {
						score += 5;
					}
					if (document.getElementById('lazy_load') && document.getElementById('lazy_load').checked) {
						score += 10;
					}
					if (document.getElementById('lazy_load_iframes') && document.getElementById('lazy_load_iframes').checked) {
						score += 4;
					}
					if (document.getElementById('disable_emojis') && document.getElementById('disable_emojis').checked) {
						score += 3;
					}
					if (document.getElementById('remove_ver_query') && document.getElementById('remove_ver_query').checked) {
						score += 3;
					}
					if (document.getElementById('gzip_compression') && document.getElementById('gzip_compression').checked) {
						score += 8;
					}

					// Cap score at 100
					if (score > 100) score = 100;

					// Update numeric UI
					var scoreEl = document.querySelector('.spdr-gauge-score');
					if (scoreEl) {
						scoreEl.textContent = score;
					}

					// Update speedometer arc circle offset
					var circleVal = document.querySelector('.spdr-gauge-circle-val');
					if (circleVal) {
						// Circumference is 2 * Math.PI * 70 = ~440
						var offset = 440 * (1 - score / 100);
						circleVal.style.strokeDashoffset = offset;
					}

					// Gauge Color themes
					var gauge = document.querySelector('.spdr-gauge');
					if (gauge) {
						gauge.classList.remove('score-good', 'score-average', 'score-poor');
						if (score >= 90) {
							gauge.classList.add('score-good');
						} else if (score >= 70) {
							gauge.classList.add('score-average');
						} else {
							gauge.classList.add('score-poor');
						}
					}
				}

				// Attach calculator changes
				var formInputs = document.querySelectorAll('#spdr-main-form input, #spdr-main-form select');
				for (var j = 0; j < formInputs.length; j++) {
					formInputs[j].addEventListener('change', calculateScore);
				}
				calculateScore();

				// AJAX Settings Saving Form Interception
				var settingsForm = document.getElementById('spdr-main-form');
				if (settingsForm) {
					settingsForm.addEventListener('submit', function(e) {
						e.preventDefault();
						var submitBtn = settingsForm.querySelector('.spdr-save-btn');
						var originalText = submitBtn ? submitBtn.value : 'Save Settings';

						if (submitBtn) {
							submitBtn.value = 'Saving...';
							submitBtn.disabled = true;
						}

						var formData = new FormData(settingsForm);
						var data = new URLSearchParams();
						data.append('action', 'spdr_save_settings');
						data.append('nonce', formData.get('_wpnonce'));

						var settingsData = {};
						settingsForm.querySelectorAll('input, select, textarea').forEach(function(input) {
							var name = input.getAttribute('name');
							if (name && name.indexOf('spdr_settings[') === 0) {
								var key = name.substring(14, name.length - 1);
								if (input.type === 'checkbox') {
									if (input.checked) {
										settingsData[key] = input.value;
									}
								} else {
									settingsData[key] = input.value;
								}
							}
						});

						for (var key in settingsData) {
							data.append('settings[' + key + ']', settingsData[key]);
						}

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: data.toString()
						})
						.then(function(res) { return res.json(); })
						.then(function(res) {
							if (submitBtn) {
								submitBtn.value = originalText;
								submitBtn.disabled = false;
							}
							if (res.success) {
								showToast(res.data.message, true);
								setTimeout(function() {
									window.location.reload();
								}, 1000);
							} else {
								showToast(res.data.message || 'Error saving settings.', false);
							}
						})
						.catch(function(err) {
							if (submitBtn) {
								submitBtn.value = originalText;
								submitBtn.disabled = false;
							}
							showToast('Connection error occurred while saving.', false);
						});
					});
				}

				// AJAX Cache Purging Actions Interception
				var purgeButtons = document.querySelectorAll('.spdr-ajax-purge-btn');
				for (var j = 0; j < purgeButtons.length; j++) {
					(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							var type = btn.getAttribute('data-type');
							var originalText = btn.textContent;
							
							btn.textContent = 'Purging...';
							btn.disabled = true;

							var data = new URLSearchParams();
							data.append('action', 'spdr_purge_cache');
							data.append('nonce', '<?php echo wp_create_nonce( "spdr_purge_nonce" ); ?>');
							data.append('purge_type', type);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: data.toString()
							})
							.then(function(res) { return res.json(); })
							.then(function(res) {
								btn.textContent = originalText;
								btn.disabled = false;
								if (res.success) {
									showToast(res.data.message, true);
								} else {
									showToast(res.data.message || 'Purge failed.', false);
								}
							})
							.catch(function() {
								btn.textContent = originalText;
								btn.disabled = false;
								showToast('Connection error occurred during cache purge.', false);
							});
						});
					})(purgeButtons[j]);
				}

				// AJAX Database Cleanup Buttons Interception
				var dbButtons = document.querySelectorAll('.spdr-ajax-db-btn');
				for (var j = 0; j < dbButtons.length; j++) {
					(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							var action = btn.getAttribute('data-action');
							var originalText = btn.textContent;

							btn.textContent = 'Cleaning...';
							btn.disabled = true;

							var data = new URLSearchParams();
							data.append('action', 'spdr_db_cleanup');
							data.append('nonce', '<?php echo wp_create_nonce( "spdr_db_cleanup_nonce" ); ?>');
							data.append('db_action', action);

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: data.toString()
							})
							.then(function(res) { return res.json(); })
							.then(function(res) {
								btn.textContent = originalText;
								btn.disabled = false;
								if (res.success) {
									showToast(res.data.message, true);
								} else {
									showToast(res.data.message || 'Cleanup failed.', false);
								}
							})
							.catch(function() {
								btn.textContent = originalText;
								btn.disabled = false;
								showToast('Connection error occurred during database cleanup.', false);
							});
						});
					})(dbButtons[j]);
				}

				// Cache Preloader Logic
				var preloadFill = document.querySelector('.spdr-progress-bar-fill');
				var preloadText = document.querySelector('.spdr-preload-progress-text');
				var restartPreloadBtn = document.querySelector('.spdr-restart-preload-btn');

				function updatePreloadProgress() {
					if (!preloadText || !preloadFill) return;

					var data = new URLSearchParams();
					data.append('action', 'spdr_get_preload_status');
					data.append('nonce', '<?php echo wp_create_nonce( "spdr_settings_group-options" ); ?>');

					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: data.toString()
					})
					.then(function(res) { return res.json(); })
					.then(function(res) {
						if (res.success) {
							var state = res.data;
							var total = parseInt(state.total) || 0;
							var current = parseInt(state.current_index) || 0;
							var status = state.status || 'idle';
							var percent = total > 0 ? Math.round((current / total) * 100) : 0;

							preloadFill.style.width = percent + '%';

							if (status === 'active') {
								preloadText.innerHTML = 'Preloading cache: <strong>' + current + '</strong> / <strong>' + total + '</strong> pages crawled (' + percent + '% complete).';
								preloadFill.style.backgroundColor = '#1890ff';
							} else if (status === 'finished') {
								preloadText.innerHTML = 'Preload completed! Cached <strong>' + total + '</strong> pages successfully.';
								preloadFill.style.backgroundColor = '#52c41a';
							} else {
								preloadText.innerHTML = 'Preload crawler is currently idle or inactive.';
								preloadFill.style.backgroundColor = 'rgba(255,255,255,0.2)';
							}
						}
					})
					.catch(function(err) {
						console.error('Failed to fetch preload status', err);
					});
				}

				if (restartPreloadBtn) {
					restartPreloadBtn.addEventListener('click', function(e) {
						e.preventDefault();
						var originalText = restartPreloadBtn.textContent;
						restartPreloadBtn.textContent = 'Starting...';
						restartPreloadBtn.disabled = true;

						var data = new URLSearchParams();
						data.append('action', 'spdr_restart_preload');
						data.append('nonce', '<?php echo wp_create_nonce( "spdr_settings_group-options" ); ?>');

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: data.toString()
						})
						.then(function(res) { return res.json(); })
						.then(function(res) {
							restartPreloadBtn.textContent = originalText;
							restartPreloadBtn.disabled = false;
							if (res.success) {
								showToast(res.data.message, true);
								updatePreloadProgress();
							} else {
								showToast(res.data.message || 'Failed to start preloading.', false);
							}
						})
						.catch(function() {
							restartPreloadBtn.textContent = originalText;
							restartPreloadBtn.disabled = false;
							showToast('Connection error occurred while starting preload.', false);
						});
					});
				}

				// Fetch status initially and poll every 10 seconds
				updatePreloadProgress();
				setInterval(updatePreloadProgress, 10000);

			} catch (err) {
				console.error("Speed Doctor JS Error:", err);
			}
		})();
	</script>
	<?php
}

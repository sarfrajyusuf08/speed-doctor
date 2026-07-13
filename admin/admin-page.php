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

	// Get current option values to show in dashboard.
	$options            = get_option( 'spdr_settings' );
	$page_cache         = ! empty( $options['page_cache'] ) ? 1 : 0;
	$minify_html        = ! empty( $options['minify_html'] ) ? 1 : 0;
	$minify_css         = ! empty( $options['minify_css'] ) ? 1 : 0;
	$minify_js          = ! empty( $options['minify_js'] ) ? 1 : 0;
	$combine_js         = ! empty( $options['combine_js'] ) ? 1 : 0;
	$defer_js           = ! empty( $options['defer_js'] ) ? 1 : 0;
	$lazy_load          = ! empty( $options['lazy_load'] ) ? 1 : 0;
	$cdn_enable         = ! empty( $options['cdn_enable'] ) ? 1 : 0;
	$heartbeat_behavior = ! empty( $options['heartbeat_behavior'] ) ? $options['heartbeat_behavior'] : 'default';
	$remove_ver_query   = ! empty( $options['remove_ver_query'] ) ? 1 : 0;
	$disable_emojis     = ! empty( $options['disable_emojis'] ) ? 1 : 0;
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
				<h1><?php echo esc_html__( 'Speed Doctor', 'speed-doctor' ); ?></h1>
				<p class="description">
					<?php echo esc_html__( 'A lightweight performance optimization plugin for WordPress.', 'speed-doctor' ); ?>
				</p>
			</div>
			<button type="button" class="spdr-theme-toggle" id="spdr-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Theme', 'speed-doctor' ); ?>">
				<!-- Sun Icon (visible in dark mode to switch to light mode) -->
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
				<!-- Moon Icon (visible in light mode to switch to dark mode) -->
				<svg class="spdr-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
				</svg>
			</button>
		</div>

		<!-- Layout Body -->
		<div class="spdr-layout">
			<!-- Sidebar -->
			<div class="spdr-sidebar">
				<button type="button" class="spdr-tab-btn active" data-target="dashboard">
					<span class="dashicons dashicons-dashboard"></span>
					<?php echo esc_html__( 'Dashboard', 'speed-doctor' ); ?>
				</button>
				<button type="button" class="spdr-tab-btn" data-target="cache">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php echo esc_html__( 'General Settings', 'speed-doctor' ); ?>
				</button>
				<button type="button" class="spdr-tab-btn" data-target="db">
					<span class="dashicons dashicons-database"></span>
					<?php echo esc_html__( 'Database Doctor', 'speed-doctor' ); ?>
				</button>
				<button type="button" class="spdr-tab-btn" data-target="info">
					<span class="dashicons dashicons-info"></span>
					<?php echo esc_html__( 'System Info', 'speed-doctor' ); ?>
				</button>
			</div>

			<!-- Content Container -->
			<div class="spdr-content">
				
				<!-- Tab 1: Dashboard -->
				<div class="spdr-tab-panel active" id="panel-dashboard">
					<!-- Speedometer widget -->
					<div class="spdr-gauge-container">
						<div class="spdr-gauge">
							<svg>
								<circle class="spdr-gauge-circle-bg" cx="85" cy="85" r="70"></circle>
								<!-- Stroke offset = 440 * (1 - 0.98) = 8.8 -->
								<circle class="spdr-gauge-circle-val" cx="85" cy="85" r="70" style="stroke-dashoffset: 8.8;"></circle>
							</svg>
							<div class="spdr-gauge-text">
								<div class="spdr-gauge-score">98</div>
								<div class="spdr-gauge-label"><?php echo esc_html__( 'Speed Score', 'speed-doctor' ); ?></div>
							</div>
						</div>
					</div>

					<div class="spdr-dashboard-grid">
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Page Cache Status', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Static HTML page caching is currently:', 'speed-doctor' ); ?> <strong><?php echo $page_cache ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'HTML Minification', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Dynamic and static HTML minification is:', 'speed-doctor' ); ?> <strong><?php echo $minify_html ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'CSS Minification', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Stylesheets and inline CSS minification is:', 'speed-doctor' ); ?> <strong><?php echo $minify_css ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'JS Minifier & Combiner', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'JS Minification is:', 'speed-doctor' ); ?> <strong><?php echo $minify_js ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong> | <?php echo esc_html__( 'Combination:', 'speed-doctor' ); ?> <strong><?php echo $combine_js ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Defer JavaScript', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Non-critical JavaScript script deferring is:', 'speed-doctor' ); ?> <strong><?php echo $defer_js ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Lazy Loading Status', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Images, iframes, and video lazy loading is:', 'speed-doctor' ); ?> <strong><?php echo $lazy_load ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'CDN Asset URL Rewrite', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Static CDN assets URL rewriting is:', 'speed-doctor' ); ?> <strong><?php echo $cdn_enable ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Heartbeat Control', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'WordPress Heartbeat API behavior:', 'speed-doctor' ); ?> <strong>
								<?php
								if ( 'throttle' === $heartbeat_behavior ) {
									esc_html_e( 'Throttled (60s)', 'speed-doctor' );
								} elseif ( 'disable_frontend' === $heartbeat_behavior ) {
									esc_html_e( 'Front-end Disabled', 'speed-doctor' );
								} elseif ( 'disable_everywhere' === $heartbeat_behavior ) {
									esc_html_e( 'Disabled Everywhere', 'speed-doctor' );
								} else {
									esc_html_e( 'Default Behavior', 'speed-doctor' );
								}
								?>
							</strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Query String Stripper', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Static assets version query strings removal is:', 'speed-doctor' ); ?> <strong><?php echo $remove_ver_query ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Emoji Cleaner', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'WordPress core emojis de-registration is:', 'speed-doctor' ); ?> <strong><?php echo $disable_emojis ? esc_html__( 'Active', 'speed-doctor' ) : esc_html__( 'Inactive', 'speed-doctor' ); ?></strong></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="cache"><?php echo esc_html__( 'Configure Cache', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Database Status', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Database optimizations are configured and ready to be run.', 'speed-doctor' ); ?></p>
							<a href="#" class="button button-secondary spdr-trigger-tab" data-tab="db"><?php echo esc_html__( 'Clean Database', 'speed-doctor' ); ?></a>
						</div>
						<div class="spdr-card spdr-purge-card" style="grid-column: span 2;">
							<h3><?php echo esc_html__( 'Quick Purge Actions', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Selectively clear your website cache structures or assets below.', 'speed-doctor' ); ?></p>
							<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
								<form method="post" action="" class="spdr-purge-form" style="margin:0;">
									<?php wp_nonce_field( 'spdr_purge_nonce', 'spdr_purge_nonce_field' ); ?>
									<input type="hidden" name="spdr_purge_type" value="html" />
									<button type="submit" class="button button-primary"><?php echo esc_html__( 'Purge HTML Structure', 'speed-doctor' ); ?></button>
								</form>
								<form method="post" action="" class="spdr-purge-form" style="margin:0;">
									<?php wp_nonce_field( 'spdr_purge_nonce', 'spdr_purge_nonce_field' ); ?>
									<input type="hidden" name="spdr_purge_type" value="css" />
									<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Purge CSS Cache', 'speed-doctor' ); ?></button>
								</form>
								<form method="post" action="" class="spdr-purge-form" style="margin:0;">
									<?php wp_nonce_field( 'spdr_purge_nonce', 'spdr_purge_nonce_field' ); ?>
									<input type="hidden" name="spdr_purge_type" value="js" />
									<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Purge JS Cache', 'speed-doctor' ); ?></button>
								</form>
								<form method="post" action="" class="spdr-purge-form" style="margin:0;">
									<?php wp_nonce_field( 'spdr_purge_nonce', 'spdr_purge_nonce_field' ); ?>
									<input type="hidden" name="spdr_purge_type" value="all" />
									<button type="submit" class="button button-secondary" style="border-color:#ef4444 !important; color:#ef4444 !important;"><?php echo esc_html__( 'Purge All', 'speed-doctor' ); ?></button>
								</form>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 2: General Settings -->
				<div class="spdr-tab-panel" id="panel-cache">
					<h2><?php echo esc_html__( 'General Settings', 'speed-doctor' ); ?></h2>
					<form method="post" action="options.php">
						<?php
						settings_fields( 'spdr_settings_group' );
						do_settings_sections( 'speed-doctor' );
						submit_button( esc_html__( 'Save Settings', 'speed-doctor' ) );
						?>
					</form>
				</div>

				<!-- Tab 3: Database Doctor -->
				<div class="spdr-tab-panel" id="panel-db">
					<h2><?php echo esc_html__( 'Database Doctor', 'speed-doctor' ); ?></h2>
					<p><?php echo esc_html__( 'Purge unnecessary database records to optimize database size and speed.', 'speed-doctor' ); ?></p>

					<div style="display: flex; flex-direction: column; gap: 20px; margin-top: 20px; max-width: 500px;">
						<!-- Revisions Card -->
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Clean Revisions', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Obsolete post and page revisions clog up database storage space over time. Clean them up safely here.', 'speed-doctor' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'spdr_db_cleanup_nonce' ); ?>
								<input type="hidden" name="spdr_db_action" value="clean_revisions" />
								<button type="submit" class="button button-primary">
									<?php echo esc_html__( 'Purge All Revisions', 'speed-doctor' ); ?>
								</button>
							</form>
						</div>

						<!-- Auto-Drafts Card -->
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Clean Auto-Drafts', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'WordPress creates temporary auto-drafts while you edit posts. Clean up unsaved auto-drafts here.', 'speed-doctor' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'spdr_db_cleanup_nonce' ); ?>
								<input type="hidden" name="spdr_db_action" value="clean_autodrafts" />
								<button type="submit" class="button button-primary">
									<?php echo esc_html__( 'Purge All Auto-Drafts', 'speed-doctor' ); ?>
								</button>
							</form>
						</div>

						<!-- Comments Card -->
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Clean Spam & Trash Comments', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Comments marked as spam or trashed occupy database table slots. Safely purge them here.', 'speed-doctor' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'spdr_db_cleanup_nonce' ); ?>
								<input type="hidden" name="spdr_db_action" value="clean_comments" />
								<button type="submit" class="button button-primary">
									<?php echo esc_html__( 'Purge Spam & Trash Comments', 'speed-doctor' ); ?>
								</button>
							</form>
						</div>

						<!-- Transients Card -->
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Clean Expired Transients', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Temporary cache options (transients) remain in your options database after they expire. Clean them up here.', 'speed-doctor' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'spdr_db_cleanup_nonce' ); ?>
								<input type="hidden" name="spdr_db_action" value="clean_transients" />
								<button type="submit" class="button button-primary">
									<?php echo esc_html__( 'Purge Expired Transients', 'speed-doctor' ); ?>
								</button>
							</form>
						</div>

						<!-- Optimize Tables Card -->
						<div class="spdr-card">
							<h3><?php echo esc_html__( 'Optimize Database Tables', 'speed-doctor' ); ?></h3>
							<p><?php echo esc_html__( 'Run OPTIMIZE TABLE on your database tables to reclaim unused overhead storage space and improve query performance.', 'speed-doctor' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'spdr_db_cleanup_nonce' ); ?>
								<input type="hidden" name="spdr_db_action" value="optimize_tables" />
								<button type="submit" class="button button-primary">
									<?php echo esc_html__( 'Optimize Database Tables', 'speed-doctor' ); ?>
								</button>
							</form>
						</div>
					</div>
				</div>

				<!-- Tab 4: System Info -->
				<div class="spdr-tab-panel" id="panel-info">
					<h2><?php echo esc_html__( 'System Info', 'speed-doctor' ); ?></h2>
					<?php
					$cache_size = SPDR_Cache::get_instance()->get_cache_dir_size();
					$db_stats   = SPDR_DB::get_instance()->get_db_stats();
					?>
					<table class="widefat striped" style="margin-top: 15px;">
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
										<span style="color: #ef4444; font-weight: bold; margin-left: 10px;">
											(<?php esc_html_e( 'Cleanup Recommended', 'speed-doctor' ); ?>)
										</span>
									<?php else : ?>
										<span style="color: #10b981; font-weight: bold; margin-left: 10px;">
											(<?php esc_html_e( 'Optimized', 'speed-doctor' ); ?>)
										</span>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>
		</div>
		<!-- Animated Toast Notification Container -->
		<div id="spdr-toast" class="spdr-toast"></div>
	</div>

	<!-- Sidebar Tabs & Theme Switch Scripts -->
	<script>
		(function() {
			try {
				console.log("Speed Doctor Admin Script Initializing...");

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

				console.log("Found buttons:", tabButtons.length, "panels:", tabPanels.length);

				for (var i = 0; i < tabButtons.length; i++) {
					(function(btn) {
						btn.addEventListener('click', function() {
							var target = btn.getAttribute('data-target');
							console.log("Tab clicked:", target);

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
								console.log("Activated panel:", 'panel-' + target);
							} else {
								console.warn("Target panel not found:", 'panel-' + target);
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
							console.log("Quick link clicked, triggering tab:", tabTarget);
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
								console.log("Theme toggled to:", isLight ? 'dark' : 'light');
							} catch (storageErr) {
								console.warn('Storage write blocked:', storageErr);
							}
						}
					});
				} else {
					console.warn("Theme toggle button not found");
				}

				// AJAX Settings Saving Form Interception
				var settingsForm = document.querySelector('#panel-cache form');
				if (settingsForm) {
					settingsForm.addEventListener('submit', function(e) {
						e.preventDefault();
						var submitBtn = settingsForm.querySelector('input[type="submit"]');
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

				// AJAX Database Cleanup Forms Interception
				var dbForms = document.querySelectorAll('#panel-db form');
				for (var i = 0; i < dbForms.length; i++) {
					(function(form) {
						form.addEventListener('submit', function(e) {
							e.preventDefault();
							var submitBtn = form.querySelector('button[type="submit"]');
							var originalText = submitBtn ? submitBtn.innerHTML : 'Clean';

							if (submitBtn) {
								submitBtn.innerHTML = 'Cleaning...';
								submitBtn.disabled = true;
							}

							var formData = new FormData(form);
							var data = new URLSearchParams();
							data.append('action', 'spdr_db_cleanup');
							data.append('nonce', formData.get('_wpnonce') || formData.get('spdr_db_cleanup_nonce'));
							data.append('db_action', formData.get('spdr_db_action'));

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: data.toString()
							})
							.then(function(res) { return res.json(); })
							.then(function(res) {
								if (submitBtn) {
									submitBtn.innerHTML = originalText;
									submitBtn.disabled = false;
								}
								if (res.success) {
									showToast(res.data.message, true);
								} else {
									showToast(res.data.message || 'Cleanup failed.', false);
								}
							})
							.catch(function(err) {
								if (submitBtn) {
									submitBtn.innerHTML = originalText;
									submitBtn.disabled = false;
								}
								showToast('Connection error occurred during cleanup.', false);
							});
						});
					})(dbForms[i]);
				}

				// AJAX Cache Purge Forms Interception
				var purgeForms = document.querySelectorAll('.spdr-purge-form');
				for (var i = 0; i < purgeForms.length; i++) {
					(function(form) {
						form.addEventListener('submit', function(e) {
							e.preventDefault();
							var submitBtn = form.querySelector('button[type="submit"]');
							var originalText = submitBtn ? submitBtn.innerHTML : 'Purge';

							if (submitBtn) {
								submitBtn.innerHTML = 'Purging...';
								submitBtn.disabled = true;
							}

							var formData = new FormData(form);
							var data = new URLSearchParams();
							data.append('action', 'spdr_purge_cache');
							data.append('nonce', formData.get('_wpnonce') || formData.get('spdr_purge_nonce_field'));
							data.append('purge_type', formData.get('spdr_purge_type'));

							fetch(ajaxurl, {
								method: 'POST',
								headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
								body: data.toString()
							})
							.then(function(res) { return res.json(); })
							.then(function(res) {
								if (submitBtn) {
									submitBtn.innerHTML = originalText;
									submitBtn.disabled = false;
								}
								if (res.success) {
									showToast(res.data.message, true);
								} else {
									showToast(res.data.message || 'Purge failed.', false);
								}
							})
							.catch(function(err) {
								if (submitBtn) {
									submitBtn.innerHTML = originalText;
									submitBtn.disabled = false;
								}
								showToast('Connection error occurred during cache purge.', false);
							});
						});
					})(purgeForms[i]);
				}

			} catch (err) {
				console.error("Speed Doctor JS Error:", err);
			}
		})();
	</script>
	<?php
}

# Speed Doctor - Master Development Plan (Steps 6–32)

This master document serves as our steady reference guide. It outlines the development roadmap divided into 27 steps. For each step, it details the planning scope, WPCS/Security compliance, review checks, and manual verification instructions.

---

## Part 1: Core & Foundation (Steps 6–9)

### Step 6: Core Settings API Setup
* **Planning**: Set up settings registration. We will register a master option array `spdr_settings` using WordPress `register_setting()`, `add_settings_section()`, and `add_settings_field()`.
* **WPCS & Security Compliance**:
  * Use the prefix `spdr_` for callbacks.
  * Implement sanitization inside the registration callback (e.g., stripping scripts, cast checkbox states to 0 or 1).
* **Review**: Check if options can be safely updated in the database.
* **Manual Verification Tips**: Check the `wp_options` table directly using phpMyAdmin or a CLI query (`SELECT * FROM wp_options WHERE option_name = 'spdr_settings'`) to verify settings are saved correctly in serialized format.

### Step 7: Plugin Activation/Deactivation Hooks
* **Planning**: Define activation and deactivation hooks. Upon activation, initialize default settings. Upon deactivation, flush transients.
* **WPCS & Security Compliance**:
  * Register hooks in `speed-doctor.php` using `register_activation_hook()` and `register_deactivation_hook()`.
  * Ensure callbacks check if WordPress environment is initialized.
* **Review**: Verify no warnings or fatal errors occur on plugin activation/deactivation.
* **Manual Verification Tips**: Navigate to **Dashboard > Plugins**, toggle Activate/Deactivate, and check the PHP error log (`debug.log`) for any output. Verify default option array is created on activation.

### Step 8: Plugin Uninstaller (`uninstall.php`)
* **Planning**: Add `uninstall.php` to delete options and remove any lingering files (like cache directories).
* **WPCS & Security Compliance**:
  * Must check `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;` to prevent unauthorized execution.
* **Review**: Ensure all custom databases and option entries are dropped.
* **Manual Verification Tips**: Check the `wp_options` table, delete the plugin from the WordPress Plugins panel, and verify the option `spdr_settings` is completely removed from the database.

### Step 9: Modular Class Loader (Refactoring)
* **Planning**: Refactor the procedural code in `speed-doctor.php` into an object-oriented class structure. Establish an autoloader or dynamic file requiring system to boot modules.
* **WPCS & Security Compliance**:
  * Class filenames must follow `class-spdr-*.php` naming convention.
  * Use namespace-like class names prefixed with `SPDR_` or `Speed_Doctor_`.
* **Review**: Ensure the admin page rendering and menu structure continue to function identically post-refactor.
* **Manual Verification Tips**: Inspect page loading times and verify that the Speed Doctor menu page loads without PHP errors or warnings in the WP debug log.

---

## Part 2: Page Cache Module (Steps 10–15)

### Step 10: Page Cache - Directory Setup & Cache Check
* **Planning**: Write logic to create the cache directory structure in `/wp-content/cache/speed-doctor/` and functions to check if a cached HTML file exists for a requested URL.
* **WPCS & Security Compliance**:
  * Use `wp_mkdir_p()` for safe directory creation.
  * Use `wp_normalize_path()` to handle Windows and Linux file path differences.
* **Review**: Verify directory creation runs without permission errors.
* **Manual Verification Tips**: Navigate to `/wp-content/cache/` using file explorer or terminal and verify that the `speed-doctor` directory is created.

### Step 11: Page Cache - Output Buffer Hooks
* **Planning**: Use PHP output buffering (`ob_start()`) at the `template_redirect` hook to capture HTML output and write it to the static cache directory upon `shutdown`.
* **WPCS & Security Compliance**:
  * Validate output content before saving.
  * Escape path strings before calling file-writing operations.
* **Review**: Ensure static HTML files are created in the cache folder for public pages.
* **Manual Verification Tips**: Visit a front-end page as an anonymous guest visitor, check the cache folder, and verify that a `.html` file matching the page exists.

### Step 12: Page Cache - Bypass & Exclusions Rules
* **Planning**: Implement logic to skip caching when the user is logged in, when a POST request is made, or when URL contains excluded parameters (like WooCommerce Cart).
* **WPCS & Security Compliance**:
  * Use helper functions like `is_user_logged_in()`.
  * Sanitize request URI using `esc_url_raw()`.
* **Review**: Double-check that no cached page is served to logged-in administrators or users with items in their shopping cart.
* **Manual Verification Tips**: Log into the admin dashboard, visit the home page, and verify that no cached HTML file is generated/served for your admin session.

### Step 13: Page Cache - Cache Purge Mechanics
* **Planning**: Implement functions to delete all cache files or a specific URL cache file when a post is updated. Add an admin bar shortcut.
* **WPCS & Security Compliance**:
  * Use capability checks (`manage_options`) and check nonces before performing a purge.
* **Review**: Check if updating a post clears its cached file.
* **Manual Verification Tips**: Modify a post's title, save it, and verify that the corresponding static HTML file in the cache directory is deleted immediately.

### Step 14: Page Cache - Cron Cleanup
* **Planning**: Setup a WP Cron schedule to clear outdated static files based on user-defined cache lifespan settings.
* **WPCS & Security Compliance**:
  * Safely schedule events using `wp_next_scheduled()` and `wp_schedule_event()`.
* **Review**: Verify the cron event is registered inside WordPress.
* **Manual Verification Tips**: Use a plugin like WP Crontrol to check if the `spdr_clean_expired_cache` cron event is scheduled and runs successfully.

### Step 15: Page Cache - Advanced Headers & Gzip
* **Planning**: Inject Cache-Control headers into PHP responses and add rules to the site's `.htaccess` file for Apache-level speed delivery.
* **WPCS & Security Compliance**:
  * Ensure any file writes to `.htaccess` use WordPress core API functions like `insert_with_markers()`.
* **Review**: Verify Gzip is active and resource headers are present.
* **Manual Verification Tips**: Open Chrome DevTools > Network tab, click on the document request, and verify that the `Content-Encoding: gzip` and `Cache-Control` headers are returned.

---

## Part 3: Database Optimization Module (Steps 16–20)

### Step 16: DB Optimization - Revisions Cleaner
* **Planning**: Build functional logic to search and delete obsolete post revisions from the database.
* **WPCS & Security Compliance**:
  * Always use `$wpdb` securely. Do not write raw SQL injections; prepare values if query input depends on settings.
* **Review**: Check that only older post revisions are deleted, keeping the published posts intact.
* **Manual Verification Tips**: Check the `wp_posts` table for row count where `post_type = 'revision'`. Run the revision cleaner, and verify that the number of revisions decreases while published posts remain untouched.

### Step 17: DB Optimization - Drafts & Trashed Content Cleaner
* **Planning**: Write DB commands to delete spam/trashed comments and auto-drafts.
* **WPCS & Security Compliance**:
  * Perform capability checks before clearing content.
* **Review**: Verify that comments marked as spam are dropped.
* **Manual Verification Tips**: Add a dummy comment, mark it as spam, run the cleaner, and verify that the spam comments count drops to 0.

### Step 18: DB Optimization - Expired Transients Cleaner
* **Planning**: Prune expired transients from the `wp_options` table to clean database clutter.
* **WPCS & Security Compliance**:
  * Use standard SQL query targeting `_transient_timeout_` to identify and remove expired keys.
* **Review**: Check option keys are purged without affecting active configurations.
* **Manual Verification Tips**: Run the transient cleaner and verify that the options count in the database decreases.

### Step 19: DB Optimization - Table Optimizer
* **Planning**: Execute `OPTIMIZE TABLE` commands on all MySQL database tables.
* **WPCS & Security Compliance**:
  * Get table names dynamically from `$wpdb->get_col()` to support custom prefixes safely.
* **Review**: Ensure no tables get locked or corrupt during optimization.
* **Manual Verification Tips**: Verify the completion status message on the admin screen, and inspect the database size before and after optimization.

### Step 20: DB Optimization - Scheduled DB Cron
* **Planning**: Create settings options for scheduled database maintenance via WP-Cron.
* **WPCS & Security Compliance**:
  * Verify the cleanup task is registered securely.
* **Review**: Verify the scheduled events trigger the optimization hooks correctly.
* **Manual Verification Tips**: Schedule optimization to run hourly for testing, check WP Crontrol list, and see if database cleanup operations register in PHP error logs.

---

## Part 4: Minification & Asset Optimization (Steps 21–24)

### Step 21: Asset Optimization - HTML Minifier
* **Planning**: Develop text/regex parsing rules to strip redundant HTML comments, newlines, and double spaces from output buffer.
* **WPCS & Security Compliance**:
  * Exclude `<textarea>` and `<pre>` tags from minification to avoid breaking layout formatting.
* **Review**: Inspect source code of the page to ensure script tags and styling markup are intact.
* **Manual Verification Tips**: View page source in the browser and verify that HTML output is compressed onto single lines without layout breaks.

### Step 22: Asset Optimization - CSS Minifier
* **Planning**: Implement regular expressions to strip comments, line breaks, and whitespace from CSS styles.
* **WPCS & Security Compliance**:
  * Safely cache minified CSS stylesheets.
* **Review**: Verify that page layouts render correctly without styling issues.
* **Manual Verification Tips**: Check the website front-end and inspect layout alignments to ensure CSS minification didn't break layouts.

### Step 23: Asset Optimization - JS Minifier
* **Planning**: Strip comments and whitespace from enqueued scripts while avoiding issues with missing semicolons.
* **WPCS & Security Compliance**:
  * Cache the minified JS files in the uploads folder safely.
* **Review**: Ensure page interactive elements work (e.g. menus, sliders).
* **Manual Verification Tips**: Open the browser's console tab (`F12`) and confirm there are no Javascript syntax errors.

### Step 24: Asset Optimization - JS Async & Defer
* **Planning**: Hook into `script_loader_tag` to add `async` or `defer` attributes to non-essential enqueued scripts.
* **WPCS & Security Compliance**:
  * Do not defer critical scripts like `jquery.js` which might break dependent inline scripts.
* **Review**: Verify script loading orders.
* **Manual Verification Tips**: View the HTML source of the page and verify that enqueued `<script>` tags contain the `defer` or `async` attribute.

---

## Part 5: Media & CDN Optimization (Steps 25–27)

### Step 25: Media Optimization - Image Lazy Loading
* **Planning**: Parse post content to insert `loading="lazy"` and placeholder source parameters to delay image loading.
* **WPCS & Security Compliance**:
  * Apply filters on `the_content` hook.
* **Review**: Verify image loading behavior.
* **Manual Verification Tips**: Scroll down the page in Chrome DevTools > Network (Img tab) and check if image requests trigger dynamically as they enter the viewport.

### Step 26: Media Optimization - Iframe & Video Lazy Loading
* **Planning**: Apply lazy loading parameters to video embeds and iframes.
* **WPCS & Security Compliance**:
  * Sanitize output iframe tags.
* **Review**: Ensure embedded videos load only when scrolled into view.
* **Manual Verification Tips**: Load a page with a YouTube video block, inspect the DOM, and verify the `loading="lazy"` attribute is present on the iframe.

### Step 27: Media Optimization - CDN URL Rewrite
* **Planning**: Replace internal domain URLs for assets (images, CSS, JS) with a user-defined CDN hostname.
* **WPCS & Security Compliance**:
  * Ensure URL transformations are done safely via regex and escape CDN hostname.
* **Review**: Check that assets are loading from the CDN endpoint.
* **Manual Verification Tips**: View page source and check if asset paths are rewritten from `http://mysite.local/wp-content/...` to `http://cdn.mysite.com/wp-content/...`.

---

## Part 6: Miscellaneous Optimizations (Steps 28–29)

### Step 28: Misc Optimizations - Heartbeat API Control
* **Planning**: De-register or throttle the WordPress Heartbeat request interval.
* **WPCS & Security Compliance**:
  * Intercept the heartbeats on the `heartbeat_settings` filter.
* **Review**: Check request frequency.
* **Manual Verification Tips**: Look at Chrome DevTools Network tab, check admin-ajax.php requests, and ensure they trigger less frequently or are disabled.

### Step 29: Misc Optimizations - Query String & Emoji Remover
* **Planning**: Strip query strings (like `?ver=x.x`) from assets and completely disable WP core emoji scripts.
* **WPCS & Security Compliance**:
  * Filter `style_loader_src` and `script_loader_src`.
* **Review**: Check that assets are loaded cleanly and emojis script is removed.
* **Manual Verification Tips**: View the HTML page source, verify no emoji scripts/styles are in the header, and ensure style paths end with `.css` instead of `.css?ver=...`.

---

## Part 7: Administration Dashboard UI (Steps 30–32)

### Step 30: Admin Dashboard UI - Tabbed Settings Interface
* **Planning**: Design a clean, responsive tab-based dashboard layout (Settings, DB, Cache, Media) using custom CSS.
* **WPCS & Security Compliance**:
  * Format CSS safely and restrict rules to the plugin wrap container.
* **Review**: Verify dashboard display look and responsiveness.
* **Manual Verification Tips**: Check the admin panel at different screen sizes to verify layout responsiveness.

### Step 31: Admin Dashboard UI - AJAX Controller
* **Planning**: Create secure WordPress AJAX controllers to toggle module settings and execute cache purges asynchronously.
* **WPCS & Security Compliance**:
  * Always use nonces and `current_user_can( 'manage_options' )` inside AJAX callbacks.
* **Review**: Ensure AJAX responses return secure JSON packets.
* **Manual Verification Tips**: Click the "Clear Cache" button on the UI, watch the Network tab for AJAX request payload and check for success status.

### Step 32: System Info & Summary Panel
* **Planning**: Add a diagnostics dashboard card summarizing cache size, database size, PHP runtime config, and performance scores.
* **WPCS & Security Compliance**:
  * Properly escape system paths and directory output.
* **Review**: Ensure exact sizes match disk storage values.
* **Manual Verification Tips**: Navigate to the Speed Doctor dashboard and check if details like DB size, Cache folder size, and PHP configurations render correctly.

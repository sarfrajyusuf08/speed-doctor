=== Speed Doctor ===
Contributors: sarfrajyusuf
Tags: cache, speed, database, optimize, performance, minification
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Speed Doctor is a lightweight, premium performance optimization plugin that speeds up your WordPress website using page caching, database pruning, and asset minification.

== Description ==

Speed Doctor is a comprehensive, modular speed optimization tool designed to make your site load in under a second. With a state-of-the-art glassmorphism admin dashboard, managing caching, database bloat, and assets has never been easier or more beautiful.

= Key Features =
* **Page Caching**: Generates static HTML files of your dynamic pages, cutting down loading times by avoiding heavy database calls.
* **Gzip & Browser Caching**: Safely injects optimization rules into your .htaccess file for lightning-fast server delivery.
* **Database Doctor**: Purges obsolete post revisions, auto-drafts, comments, expired transients, and optimizes database tables.
* **WP-Cron Automation**: Cleans up expired cache files and optimizes database tables in the background automatically.
* **Light / Dark Mode Dashboard**: An application-like admin interface with beautiful styling and responsive tabs.

== Installation ==

1. Upload the `speed-doctor` directory to your `/wp-content/plugins/` folder.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Click on the 'Speed Doctor' menu in your admin panel to configure caching and run optimizations.

== Frequently Asked Questions ==

= Does it support WooCommerce? =
Yes! Speed Doctor is WooCommerce compatible and automatically bypasses caching on Cart, Checkout, Account pages, and active cart cookie sessions.

= Is it safe to optimize database tables? =
Yes, Speed Doctor uses standard SQL commands like OPTIMIZE TABLE to reclaim unused space. However, it is always a best practice to keep a backup before performing database tasks.

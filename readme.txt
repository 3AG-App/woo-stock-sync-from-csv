=== Woo Stock Sync from CSV ===
Contributors: 3ag
Tags: woocommerce, stock, inventory, csv, sync, automation
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically sync WooCommerce product stock from a CSV URL on a scheduled basis.

== Description ==

Woo Stock Sync from CSV is a premium WordPress plugin that automatically synchronizes your WooCommerce product stock levels from a CSV file hosted at any URL.

**Key Features:**

* **Automatic Sync** - Schedule stock updates from every 5 minutes to weekly
* **Large Catalog Support** - Optimized batch processing for 4000+ products
* **Custom Column Mapping** - Map any CSV column to SKU and quantity fields
* **Auto Delimiter Detection** - Supports comma, semicolon, tab, and pipe delimiters
* **Missing SKU Actions** - Choose what happens when products are not in CSV (ignore, set to 0, or make private)
* **Watchdog Cron** - Automatic recovery if scheduled sync stops working
* **Detailed Logs** - Complete history of all sync operations with statistics
* **Modern UI** - Clean, intuitive admin interface with real-time feedback
* **SSL Options** - Support for self-signed certificates
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage

**Requirements:**

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Valid license key from 3AG

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woo-stock-sync-from-csv`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Stock Sync → License to activate your license key
4. Configure your CSV URL and column mappings in Stock Sync → Settings
5. Enable scheduled sync or run a manual sync from the Dashboard

== Frequently Asked Questions ==

= What CSV format is supported? =

The plugin supports standard CSV files with headers. Delimiters (comma, semicolon, tab, pipe) are automatically detected.

= How do I get a license key? =

Purchase a license at https://3ag.app/products/woo-stock-sync-from-csv

= Can I sync stock for variable products? =

Yes, the plugin supports both simple products and product variations. Each variation should have its own SKU in the CSV.

= What happens if a product SKU is not found in the CSV? =

You can configure this in Settings. Options include: ignore (keep current stock), set stock to 0, or make the product private (and restore when it returns).

== Changelog ==

= 1.0.2 =
* Added static download URL for latest release

= 1.0.1 =
* Fixed missing SKU action feature
* Added catalog visibility control when setting products to private
* Improved product restoration when SKU returns in CSV

= 1.0.0 =
* Initial release
* Automatic stock sync from CSV URL
* Scheduled sync with configurable intervals
* Manual sync trigger
* Custom SKU and quantity column mapping
* Auto delimiter detection
* Missing SKU action settings
* Watchdog cron for reliability
* Comprehensive logging system
* Modern admin interface
* License management via 3AG API
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Woo Stock Sync from CSV.

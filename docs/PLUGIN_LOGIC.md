# WooCommerce Stock Sync from CSV - Plugin Logic Documentation

**Version:** 1.4.0  
**Last Updated:** January 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Sync Logic](#core-sync-logic)
4. [Missing SKU Handling](#missing-sku-handling)
5. [Restore Logic (Private → Public)](#restore-logic-private--public)
6. [License System](#license-system)
7. [Scheduling & Watchdog](#scheduling--watchdog)
8. [Database & Optimization](#database--optimization)
9. [Settings Reference](#settings-reference)
10. [Naming Conventions](#naming-conventions)

---

## Overview

This plugin synchronizes WooCommerce product stock quantities from an external CSV file (URL). It supports:

- **Manual sync** via admin dashboard
- **Scheduled sync** via WordPress cron
- **Watchdog monitoring** to ensure cron reliability
- **Missing SKU handling** (set to zero, set to private, or ignore)
- **Auto-restore** products to public when SKU returns to CSV
- **License validation** via 3AG API
- **Auto-updates** from GitHub releases

---

## Architecture

### File Structure

```
woo-stock-sync-from-csv/
├── woo-stock-sync-from-csv.php    # Main plugin file, singleton WSSC()
├── includes/
│   ├── class-admin.php            # Admin pages, menus, assets
│   ├── class-ajax.php             # AJAX handlers for all actions
│   ├── class-license.php          # License validation via 3AG API
│   ├── class-logs.php             # Logging to custom DB table
│   ├── class-scheduler.php        # Cron scheduling + watchdog
│   ├── class-sync.php             # Core sync engine
│   ├── class-updater.php          # GitHub-based auto-updates
│   └── views/
│       ├── dashboard.php          # Main dashboard view
│       ├── settings.php           # Settings page
│       ├── logs.php               # Logs page
│       └── license.php            # License management page
├── assets/
│   ├── css/admin.css              # Admin styles
│   └── js/admin.js                # Admin JavaScript
└── docs/
    └── PLUGIN_LOGIC.md            # This file
```

### Singleton Pattern

```php
// Access plugin instance anywhere
WSSC()->sync->run();
WSSC()->license->is_valid();
WSSC()->logs->add($data);
WSSC()->scheduler->reschedule();
```

---

## Core Sync Logic

### Sync Flow (`class-sync.php`)

```
1. run($trigger)
   │
   ├── Check license validity
   │   └── If invalid → abort with error
   │
   ├── Fetch CSV from URL
   │   ├── wp_remote_get() with 120s timeout
   │   ├── Optional SSL verification bypass
   │   └── Handle HTTP errors
   │
   ├── Parse CSV
   │   ├── Detect delimiter (comma, semicolon, tab, pipe)
   │   ├── Handle BOM (UTF-8)
   │   ├── Find SKU and Quantity columns by name
   │   └── Build array: SKU => Quantity
   │
   ├── Process in batches (100 SKUs per batch)
   │   └── process_batch()
   │
   ├── Handle missing SKUs (if setting != 'ignore')
   │   └── process_missing_skus()
   │
   └── Log result + reschedule next sync
```

### Batch Processing (`process_batch`)

```php
foreach ($batch as $sku => $quantity) {
    // 1. Get product data (ID, current stock, post_status)
    // 2. If setting is 'private' AND product is private → queue for restore
    // 3. Skip if stock is already the same
    // 4. Update stock via direct SQL (fast)
}

// After batch: restore queued products to public
restore_products_to_public($products_to_publish);
```

### Stock Update (`update_stock_direct`)

Uses direct SQL for 10-15x faster performance:

```sql
-- Update _stock meta
UPDATE wp_postmeta SET meta_value = $qty WHERE post_id = $id AND meta_key = '_stock'

-- Update _stock_status meta
UPDATE wp_postmeta SET meta_value = 'instock'|'outofstock' WHERE ...

-- Update HPOS lookup table (if exists)
UPDATE wp_wc_product_meta_lookup SET stock_quantity = $qty, stock_status = ...
```

**Why direct SQL?**
- `$product->save()` triggers hooks, cache clears, and additional queries per product
- For 4000+ products, this caused 30+ second sync times
- Direct SQL reduces to 2-4 seconds

---

## Missing SKU Handling

### Setting Values

| Option Value | UI Label | Description |
|--------------|----------|-------------|
| `ignore` | Ignore | Do nothing for products not in CSV |
| `zero` | Set Stock to Zero | Set stock to 0 for products not in CSV |
| `private` | Set to Private | Set status to private + catalog visibility to hidden |

### Option Storage

```php
// Saved to database
get_option('wssc_missing_sku_action', 'ignore'); // Returns: 'ignore', 'zero', or 'private'
```

### Detection Logic (`process_missing_skus`)

```
1. Get all SKUs from CSV
2. Get all SKUs from WooCommerce store (published + private products)
3. Find MISSING = Store SKUs - CSV SKUs
4. For each missing SKU:
   - If action == 'zero': update_stock_direct($id, 0)
   - If action == 'private': 
     - $product->set_status('private')
     - $product->set_catalog_visibility('hidden')
     - $product->save()
     - Track in wssc_privatized_products option
```

---

## Restore Logic (Private → Public)

### When Does Restoration Happen?

Products are restored to **publish** status when:
1. Setting is `private` (not `ignore` or `zero`)
2. Product is currently `private`
3. Product's SKU appears in the CSV

### Where Restoration Occurs

**Two places:**

#### 1. In `process_batch()` - During Regular Sync

```php
// Check if we should restore private products to public
$missing_sku_action = get_option('wssc_missing_sku_action', 'ignore');
$should_restore_private = ($missing_sku_action === 'private');  // ← Uses 'private', NOT 'set_private'

foreach ($batch as $sku => $quantity) {
    // If product is private AND setting is 'private' → queue for restore
    if ($should_restore_private && $post_status === 'private') {
        $products_to_publish[] = $product_id;
    }
}

// After batch processing
restore_products_to_public($products_to_publish);
```

#### 2. In `process_missing_skus()` - For Tracked Products

```php
// Get products that were privatized by this plugin
$privatized_by_plugin = get_option('wssc_privatized_products', []);

// Find which ones are back in CSV
$returned_skus = array_intersect(array_keys($privatized_by_plugin), $csv_skus);

foreach ($returned_skus as $sku) {
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->save();
    $this->stats['missing_restored']++;
}
```

### Restoration Method (`restore_products_to_public`)

```php
private function restore_products_to_public($product_ids) {
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        
        if ($product->get_status() !== 'private') {
            continue;
        }
        
        // Restore BOTH status and catalog visibility
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');  // ← "Shop and search results"
        $product->save();
        
        $this->stats['missing_restored']++;
    }
}
```

### Visibility States

| Action | post_status | catalog_visibility |
|--------|-------------|-------------------|
| Make Private | `private` | `hidden` |
| Restore to Public | `publish` | `visible` |

---

## License System

### API Endpoints (3AG)

```
Base URL: https://3ag.app/api/v3

POST /licenses/validate   - Check if license key is valid (returns status)
POST /licenses/activate   - Activate license for domain
POST /licenses/deactivate - Deactivate license from domain
POST /licenses/check      - Check activation status for domain
```

### License Status Values

**API Status Values** (returned from server):
- `active` - License is valid and active
- `paused` - User paused their subscription
- `suspended` - Payment issue or manual suspension
- `expired` - License/subscription has expired
- `cancelled` - Subscription was cancelled

**Client-Only Status Values** (stored locally):
- `invalid` - License key doesn't exist (401 from API)
- `domain_limit` - Activation limit reached (403 with domain limit message)

### Status UI Mapping

| Status | Card Style | Actions Available |
|--------|-----------|-------------------|
| `active` | Green border | Verify, Deactivate |
| `expired` | Yellow border | Renew, Try Different Key |
| `cancelled` | Yellow border | Purchase New, Try Different Key |
| `paused` | Yellow border | Manage Account (resume), Try Different Key |
| `suspended` | Yellow border | Manage Account (fix payment), Try Different Key |
| `domain_limit` | Yellow border | Manage Account (deactivate domains), Try Different Key |
| `invalid` | Red border | Verify (re-check), Remove License, Try Different Key |

### License Check Flow

```
1. User enters license key
2. Plugin calls /licenses/activate with:
   - license_key
   - product_slug: 'woo-stock-sync-from-csv'
   - domain: site domain (without http/www)
3. On success (200/201):
   - Store license key in wssc_license_key
   - Store API status in wssc_license_status
   - Store license data in wssc_license_data
4. On 401 error:
   - Store status as 'invalid' (key doesn't exist)
5. On 403 error:
   - If domain limit message → status = 'domain_limit'
   - Otherwise call /validate to get actual status
   - Store returned status (expired, paused, suspended, cancelled)
6. Daily cron verifies license is still valid
```

### Grace Period

If license check fails due to network error (API down, timeout), the plugin continues working for 7 days before blocking sync. This prevents temporary outages from breaking production sites.

---

## Scheduling & Watchdog

### Available Intervals

| Key | Interval |
|-----|----------|
| `wssc_5min` | 5 minutes |
| `wssc_15min` | 15 minutes |
| `wssc_30min` | 30 minutes |
| `hourly` | 1 hour |
| `wssc_2hours` | 2 hours |
| `wssc_4hours` | 4 hours |
| `wssc_6hours` | 6 hours |
| `wssc_12hours` | 12 hours |
| `daily` | 24 hours |
| `wssc_2days` | 48 hours |
| `weekly` | 7 days |

### Cron Events

```
wssc_sync_event      - Main sync event
wssc_watchdog_check  - Watchdog check (every hour)
wssc_license_check   - License verification (daily)
```

### Watchdog Logic

```php
public function watchdog_check() {
    // 1. Check if sync is enabled
    // 2. Get expected interval from settings
    // 3. Get last sync time
    // 4. If last sync is > 2x interval ago → force sync
    // 5. Reschedule if cron is missing
}
```

---

## Database & Optimization

### Custom Tables

```sql
{prefix}_wssc_logs
- id (bigint, auto increment)
- type (varchar) - 'sync', 'watchdog', 'license'
- trigger_type (varchar) - 'manual', 'scheduled', 'watchdog'
- status (varchar) - 'success', 'error', 'warning'
- message (text)
- stats (longtext) - serialized array
- errors (longtext) - serialized array
- duration (float)
- created_at (datetime) - stored in UTC
```

### Performance Optimizations

1. **Batch Processing**: 100 products per batch
2. **Direct SQL**: Bypass WooCommerce for stock updates
3. **DB Transactions**: Wrap batch in START TRANSACTION / COMMIT
4. **Single Query Lookups**: Get SKU, stock, status in one query
5. **Deferred Cache Clearing**: Once per batch, not per product
6. **HPOS Compatibility**: Updates lookup table if exists

### Time Handling

- All `created_at` timestamps stored in **UTC**: `current_time('mysql', true)`
- JavaScript converts to local time: `new Date(created_at + 'Z').toLocaleString()`

---

## Settings Reference

### Options

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `wssc_csv_url` | string | '' | CSV file URL |
| `wssc_sku_column` | string | 'sku' | Column name for SKU |
| `wssc_quantity_column` | string | 'quantity' | Column name for quantity |
| `wssc_schedule_interval` | string | 'hourly' | Sync frequency |
| `wssc_enabled` | bool | false | Enable scheduled sync |
| `wssc_disable_ssl` | bool | false | Disable SSL verification |
| `wssc_missing_sku_action` | string | 'ignore' | Action for missing SKUs |
| `wssc_license_key` | string | '' | License key |
| `wssc_license_status` | string | '' | 'active' or 'inactive' |
| `wssc_privatized_products` | array | [] | SKU => product_id of privatized products |

---

## Naming Conventions

### Important: Option Value vs Stat Key

| Context | Value | Usage |
|---------|-------|-------|
| Setting option | `'private'` | `get_option('wssc_missing_sku_action') === 'private'` |
| Stat counter | `'missing_set_private'` | `$this->stats['missing_set_private']++` |
| Stat counter (restore) | `'missing_restored'` | `$this->stats['missing_restored']++` |

**Common Bug Pattern:**
```php
// WRONG - mixing up naming
$should_restore = ($action === 'set_private');

// CORRECT - option value is 'private'
$should_restore = ($action === 'private');
```

### AJAX Action Names

| Action | Handler |
|--------|---------|
| `wssc_run_sync` | Run manual sync |
| `wssc_test_connection` | Test CSV URL |
| `wssc_preview_columns` | Get CSV column preview |
| `wssc_save_settings` | Save settings |
| `wssc_activate_license` | Activate license |
| `wssc_deactivate_license` | Deactivate license |
| `wssc_get_logs` | Get logs (paginated) |
| `wssc_clear_logs` | Clear all logs |
| `wssc_get_log_details` | Get single log details |

---

## Flow Diagrams

### Complete Sync Flow

```
┌──────────────────┐
│   Trigger Sync   │
│ (manual/cron)    │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐     ┌─────────────────┐
│  Check License   │────►│ Invalid? ABORT  │
└────────┬─────────┘     └─────────────────┘
         │ Valid
         ▼
┌──────────────────┐     ┌─────────────────┐
│   Fetch CSV      │────►│ Error? Log+Exit │
└────────┬─────────┘     └─────────────────┘
         │ OK
         ▼
┌──────────────────┐     ┌─────────────────┐
│   Parse CSV      │────►│ Error? Log+Exit │
└────────┬─────────┘     └─────────────────┘
         │ OK
         ▼
┌──────────────────┐
│ Process Batches  │
│ (100 SKUs each)  │
└────────┬─────────┘
         │
         ▼ For each SKU in batch
┌──────────────────────────────────────────┐
│ 1. Find product by SKU                   │
│ 2. If private + setting='private' → queue│
│ 3. Skip if stock unchanged               │
│ 4. Update stock (direct SQL)             │
└────────┬─────────────────────────────────┘
         │
         ▼ After batch
┌──────────────────┐
│ Restore queued   │
│ products (WC API)│
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Missing SKU      │
│ handling         │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Log + Reschedule │
└──────────────────┘
```

### Private/Public State Machine

```
                    SKU in CSV
            ┌──────────────────────┐
            │                      │
            ▼                      │
    ┌───────────────┐              │
    │   PUBLISH     │              │
    │   visible     │──────────────┘
    └───────┬───────┘
            │
            │ SKU NOT in CSV
            │ (setting = 'private')
            ▼
    ┌───────────────┐
    │   PRIVATE     │
    │   hidden      │
    └───────┬───────┘
            │
            │ SKU returns to CSV
            │ (next sync)
            ▼
    ┌───────────────┐
    │   PUBLISH     │
    │   visible     │
    └───────────────┘
```

---

## Debugging Tips

### Enable WordPress Debug

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Database Values

```sql
-- Check missing SKU action setting
SELECT option_value FROM wp_options WHERE option_name = 'wssc_missing_sku_action';

-- Check privatized products tracking
SELECT option_value FROM wp_options WHERE option_name = 'wssc_privatized_products';

-- Check product status
SELECT p.ID, p.post_status, pm.meta_value as sku
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_sku' AND pm.meta_value = 'YOUR-SKU';
```

### Check Cron Events

```sql
SELECT option_value FROM wp_options WHERE option_name = 'cron';
```

Or use WP-CLI:
```bash
wp cron event list
```

---

## Version History

| Version | Key Changes |
|---------|-------------|
| 1.2.6 | Code review fixes: uninstall cleanup, scheduled sync lock, SQL prepare, orphan cleanup |
| 1.2.5 | Smart scheduling - settings save no longer resets valid schedules |
| 1.2.4 | Fixed catalog visibility on restore (visible instead of hidden) |
| 1.2.3 | Fixed 'private' vs 'set_private' comparison bug |
| 1.2.2 | Added restore logic for products when SKU returns to CSV |
| 1.2.1 | Fixed UTC storage for log timestamps |
| 1.2.0 | Fixed negative "ago" times in UI |
| 1.1.9 | Version bumps |
| 1.1.8 | Performance: direct SQL for stock updates |
| 1.1.0 | GitHub-based auto-updates |
| 1.0.0 | Initial release |

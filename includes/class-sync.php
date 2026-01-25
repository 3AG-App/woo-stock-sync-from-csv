<?php
/**
 * Stock Sync Engine
 * 
 * Handles the actual CSV fetching and stock synchronization process.
 * Optimized for large datasets (4000+ products/rows).
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSSC_Sync {
    
    /**
     * Batch size for processing
     */
    const BATCH_SIZE = 100;
    
    /**
     * Current sync stats
     */
    private $stats = [
        'total_rows' => 0,
        'processed' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'not_found' => 0,
        'start_time' => 0,
        'end_time' => 0,
    ];
    
    /**
     * Error messages
     */
    private $error_messages = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wssc_sync_event', [$this, 'run_scheduled_sync']);
    }
    
    /**
     * Run scheduled sync
     */
    public function run_scheduled_sync() {
        $this->run('scheduled');
    }
    
    /**
     * Run manual sync
     */
    public function run_manual_sync() {
        return $this->run('manual');
    }
    
    /**
     * Main sync process
     */
    public function run($trigger = 'manual') {
        // Check license
        if (!WSSC()->license->is_valid()) {
            $this->log_error(__('Invalid or expired license. Sync aborted.', 'woo-stock-sync'));
            return [
                'success' => false,
                'message' => __('Invalid or expired license.', 'woo-stock-sync'),
            ];
        }
        
        // Get settings
        $csv_url = get_option('wssc_csv_url');
        $sku_column = get_option('wssc_sku_column', 'sku');
        $qty_column = get_option('wssc_quantity_column', 'quantity');
        
        if (empty($csv_url)) {
            $this->log_error(__('CSV URL is not configured.', 'woo-stock-sync'));
            return [
                'success' => false,
                'message' => __('CSV URL is not configured.', 'woo-stock-sync'),
            ];
        }
        
        // Initialize stats
        $this->stats['start_time'] = microtime(true);
        $this->stats['trigger'] = $trigger;
        
        // Set up error handling
        set_time_limit(0);
        wp_raise_memory_limit('admin');
        
        // Fetch CSV
        $csv_data = $this->fetch_csv($csv_url);
        
        if (!$csv_data['success']) {
            $this->log_sync($trigger, false, $csv_data['message']);
            return $csv_data;
        }
        
        // Parse CSV
        $parsed = $this->parse_csv($csv_data['data'], $sku_column, $qty_column);
        
        if (!$parsed['success']) {
            $this->log_sync($trigger, false, $parsed['message']);
            return $parsed;
        }
        
        $this->stats['total_rows'] = count($parsed['data']);
        
        // Process in batches
        $batches = array_chunk($parsed['data'], self::BATCH_SIZE, true);
        
        foreach ($batches as $batch) {
            $this->process_batch($batch);
        }
        
        // Finalize
        $this->stats['end_time'] = microtime(true);
        $duration = round($this->stats['end_time'] - $this->stats['start_time'], 2);
        
        // Log the sync
        $this->log_sync($trigger, true, null, $duration);
        
        // Re-schedule next sync
        WSSC()->scheduler->reschedule();
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Sync completed. Updated: %d, Skipped: %d, Not found: %d, Errors: %d', 'woo-stock-sync'),
                $this->stats['updated'],
                $this->stats['skipped'],
                $this->stats['not_found'],
                $this->stats['errors']
            ),
            'stats' => $this->stats,
        ];
    }
    
    /**
     * Fetch CSV from URL
     */
    private function fetch_csv($url) {
        $disable_ssl = get_option('wssc_disable_ssl', false);
        
        $response = wp_remote_get($url, [
            'timeout' => 120,
            'sslverify' => !$disable_ssl,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Failed to fetch CSV: %s', 'woo-stock-sync'),
                    $response->get_error_message()
                ),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Failed to fetch CSV. HTTP status: %d', 'woo-stock-sync'),
                    $code
                ),
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return [
                'success' => false,
                'message' => __('CSV file is empty.', 'woo-stock-sync'),
            ];
        }
        
        return [
            'success' => true,
            'data' => $body,
        ];
    }
    
    /**
     * Parse CSV data
     */
    private function parse_csv($csv_content, $sku_column, $qty_column) {
        // Handle BOM
        $csv_content = preg_replace('/^\xEF\xBB\xBF/', '', $csv_content);
        
        // Detect line endings
        $csv_content = str_replace(["\r\n", "\r"], "\n", $csv_content);
        
        $lines = explode("\n", $csv_content);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        
        if (count($lines) < 2) {
            return [
                'success' => false,
                'message' => __('CSV file must have a header row and at least one data row.', 'woo-stock-sync'),
            ];
        }
        
        // Detect delimiter from header line
        $header_line = array_shift($lines);
        $delimiter = $this->detect_delimiter($header_line);
        
        // Parse header
        $header = str_getcsv($header_line, $delimiter);
        $header = array_map('trim', $header);
        $header = array_map('strtolower', $header);
        
        // Find column indices
        $sku_index = array_search(strtolower($sku_column), $header);
        $qty_index = array_search(strtolower($qty_column), $header);
        
        if ($sku_index === false) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('SKU column "%s" not found in CSV. Available columns: %s', 'woo-stock-sync'),
                    $sku_column,
                    implode(', ', $header)
                ),
            ];
        }
        
        if ($qty_index === false) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Quantity column "%s" not found in CSV. Available columns: %s', 'woo-stock-sync'),
                    $qty_column,
                    implode(', ', $header)
                ),
            ];
        }
        
        // Parse data rows
        $data = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line, $delimiter);
            
            if (count($row) <= max($sku_index, $qty_index)) {
                continue;
            }
            
            $sku = trim($row[$sku_index]);
            $qty = trim($row[$qty_index]);
            
            if (empty($sku)) {
                continue;
            }
            
            // Clean up quantity
            $qty = preg_replace('/[^0-9.-]/', '', $qty);
            $qty = intval($qty);
            
            $data[$sku] = max(0, $qty);
        }
        
        return [
            'success' => true,
            'data' => $data,
        ];
    }
    
    /**
     * Detect CSV delimiter
     */
    private function detect_delimiter($line) {
        $delimiters = [
            ',' => 0,
            ';' => 0,
            "\t" => 0,
            '|' => 0,
        ];
        
        foreach ($delimiters as $delimiter => &$count) {
            $count = count(str_getcsv($line, $delimiter));
        }
        
        // Return delimiter that produces most columns
        arsort($delimiters);
        return array_key_first($delimiters);
    }
    
    /**
     * Process a batch of SKU => quantity pairs
     */
    private function process_batch($batch) {
        global $wpdb;
        
        $skus = array_keys($batch);
        
        // Build product lookup
        $products = $this->get_products_by_skus($skus);
        
        foreach ($batch as $sku => $quantity) {
            $this->stats['processed']++;
            
            if (!isset($products[$sku])) {
                $this->stats['not_found']++;
                continue;
            }
            
            $product_id = $products[$sku];
            
            try {
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    $this->stats['not_found']++;
                    continue;
                }
                
                // Check if stock management is enabled
                if (!$product->managing_stock()) {
                    // Enable stock management
                    $product->set_manage_stock(true);
                }
                
                $current_stock = $product->get_stock_quantity();
                
                if ($current_stock === $quantity) {
                    $this->stats['skipped']++;
                    continue;
                }
                
                // Update stock
                $product->set_stock_quantity($quantity);
                $product->save();
                
                $this->stats['updated']++;
                
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->error_messages[] = sprintf(
                    __('Error updating SKU %s: %s', 'woo-stock-sync'),
                    $sku,
                    $e->getMessage()
                );
            }
        }
        
        // Clear caches
        wc_delete_product_transients();
    }
    
    /**
     * Get product IDs by SKUs efficiently
     */
    private function get_products_by_skus($skus) {
        global $wpdb;
        
        $products = [];
        
        if (empty($skus)) {
            return $products;
        }
        
        $placeholders = array_fill(0, count($skus), '%s');
        $placeholders_str = implode(',', $placeholders);
        
        // Check in postmeta (standard WooCommerce)
        $query = $wpdb->prepare(
            "SELECT pm.meta_value as sku, pm.post_id 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_sku' 
             AND pm.meta_value IN ($placeholders_str)
             AND p.post_type IN ('product', 'product_variation')
             AND p.post_status = 'publish'",
            $skus
        );
        
        $results = $wpdb->get_results($query);
        
        foreach ($results as $row) {
            $products[$row->sku] = intval($row->post_id);
        }
        
        // Also check HPOS if available
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            // HPOS compatible lookup for product variations
            $wc_product_meta_lookup = $wpdb->prefix . 'wc_product_meta_lookup';
            if ($wpdb->get_var("SHOW TABLES LIKE '$wc_product_meta_lookup'") === $wc_product_meta_lookup) {
                $query = $wpdb->prepare(
                    "SELECT sku, product_id 
                     FROM {$wc_product_meta_lookup} 
                     WHERE sku IN ($placeholders_str)",
                    $skus
                );
                
                $results = $wpdb->get_results($query);
                
                foreach ($results as $row) {
                    if (!isset($products[$row->sku])) {
                        $products[$row->sku] = intval($row->product_id);
                    }
                }
            }
        }
        
        return $products;
    }
    
    /**
     * Log sync result
     */
    private function log_sync($trigger, $success, $error_message = null, $duration = 0) {
        $log_data = [
            'type' => 'sync',
            'trigger' => $trigger,
            'status' => $success ? 'success' : 'error',
            'message' => $success 
                ? sprintf(
                    __('Sync completed in %s seconds', 'woo-stock-sync'),
                    $duration
                )
                : $error_message,
            'stats' => $this->stats,
            'errors' => $this->error_messages,
        ];
        
        WSSC()->logs->add($log_data);
    }
    
    /**
     * Log error
     */
    private function log_error($message) {
        WSSC()->logs->add([
            'type' => 'sync',
            'status' => 'error',
            'message' => $message,
        ]);
    }
    
    /**
     * Test CSV connection
     */
    public function test_connection($url = null) {
        if (!$url) {
            $url = get_option('wssc_csv_url');
        }
        
        if (empty($url)) {
            return [
                'success' => false,
                'message' => __('CSV URL is empty.', 'woo-stock-sync'),
            ];
        }
        
        // Fetch CSV
        $csv_data = $this->fetch_csv($url);
        
        if (!$csv_data['success']) {
            return $csv_data;
        }
        
        // Try to parse
        $sku_column = get_option('wssc_sku_column', 'sku');
        $qty_column = get_option('wssc_quantity_column', 'quantity');
        
        $parsed = $this->parse_csv($csv_data['data'], $sku_column, $qty_column);
        
        if (!$parsed['success']) {
            return $parsed;
        }
        
        return [
            'success' => true,
            'message' => sprintf(
                __('Connection successful! Found %d products in CSV.', 'woo-stock-sync'),
                count($parsed['data'])
            ),
            'count' => count($parsed['data']),
            'sample' => array_slice($parsed['data'], 0, 5, true),
        ];
    }
    
    /**
     * Get CSV columns preview
     */
    public function preview_columns($url = null) {
        if (!$url) {
            $url = get_option('wssc_csv_url');
        }
        
        if (empty($url)) {
            return [
                'success' => false,
                'message' => __('CSV URL is empty.', 'woo-stock-sync'),
            ];
        }
        
        // Fetch CSV (only first part for preview)
        $disable_ssl = get_option('wssc_disable_ssl', false);
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => !$disable_ssl,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Handle BOM
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        
        $lines = explode("\n", $body);
        
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => __('CSV file is empty.', 'woo-stock-sync'),
            ];
        }
        
        // Detect delimiter
        $delimiter = $this->detect_delimiter($lines[0]);
        
        $header = str_getcsv($lines[0], $delimiter);
        $header = array_map('trim', $header);
        
        // Get sample data
        $sample_rows = [];
        for ($i = 1; $i < min(6, count($lines)); $i++) {
            if (trim($lines[$i]) !== '') {
                $sample_rows[] = str_getcsv($lines[$i], $delimiter);
            }
        }
        
        return [
            'success' => true,
            'columns' => $header,
            'sample' => $sample_rows,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
        ];
    }
}

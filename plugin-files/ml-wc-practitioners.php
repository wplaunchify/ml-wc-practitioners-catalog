<?php
/**
 * Plugin Name: ML WooCommerce Practitioners Catalog
 * Plugin URI: https://github.com/wplaunchify/ml-stuarthoover
 * Description: Import Professional Nutritionals catalog from GitHub repository to WooCommerce. One-click import of 196+ products with images, descriptions, and pricing.
 * Version: 2.1.4
 * Author: Spencer Forman
 * Author URI: https://minutelaunch.ai
 * License: GPL v2 or later
 * Text Domain: ml-wc-practitioners
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('ML_WC_PRACTITIONERS_VERSION', '2.1.4');
define('ML_WC_PRACTITIONERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ML_WC_PRACTITIONERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ML_WC_PRACTITIONERS_GITHUB_REPO', 'wplaunchify/ml-wc-practitioners-catalog');
define('ML_WC_PRACTITIONERS_GITHUB_BRANCH', 'main');

/**
 * ============================================================================
 * TABLE OF CONTENTS
 * ============================================================================
 * 
 * 1. MAIN PLUGIN CLASS
 *    - Singleton pattern
 *    - Initialization hooks
 *    - Dependency checks
 *    - WooCommerce auto-install & setup
 * 
 * 2. ADMIN INTERFACE
 *    - Admin menu registration
 *    - Settings page HTML
 *    - Admin styles and scripts
 * 
 * 3. SETTINGS MANAGEMENT
 *    - GitHub token storage
 *    - Configuration options
 *    - Settings save/retrieve
 * 
 * 4. GITHUB INTEGRATION
 *    - Fetch catalog data from private repo
 *    - Download images with authentication
 *    - API communication methods
 * 
 * 5. CATALOG IMPORT
 *    - Parse product JSON/CSV
 *    - Create WooCommerce products
 *    - Handle product categories
 *    - Set product metadata
 * 
 * 6. IMAGE MANAGEMENT
 *    - Download images from GitHub
 *    - Upload to WordPress media library
 *    - Attach images to products
 *    - Handle image errors
 * 
 * 7. AJAX HANDLERS
 *    - Import catalog action
 *    - Test connection action
 *    - Progress tracking
 * 
 * 8. UTILITY FUNCTIONS
 *    - Logging
 *    - Error handling
 *    - Status messages
 * 
 * 9. ACTIVATION/DEACTIVATION
 *    - Plugin activation
 *    - Plugin deactivation
 *    - Cleanup routines
 * 
 * ============================================================================
 */

/**
 * ============================================================================
 * 1. MAIN PLUGIN CLASS
 * ============================================================================
 */

class ML_WC_Practitioners_Catalog {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Default GitHub token (optional - repository is public)
     * No authentication required for accessing public repositories
     */
    private $default_github_token = '';
    
    /**
     * Log entries for this session
     */
    private $log_entries = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Register all hooks
     */
    private function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_ml_practitioners_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_ml_practitioners_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ml_practitioners_import_catalog', [$this, 'ajax_import_catalog']);
        add_action('wp_ajax_ml_practitioners_import_batch', [$this, 'ajax_import_batch']);
        add_action('wp_ajax_ml_practitioners_get_import_status', [$this, 'ajax_get_import_status']);
        add_action('wp_ajax_ml_practitioners_reset_catalog', [$this, 'ajax_reset_catalog']);
        add_action('wp_ajax_ml_practitioners_go_live', [$this, 'ajax_go_live']);
        
        // Initialization
        add_action('admin_init', [$this, 'check_and_setup_woocommerce']);
    }
    
    /**
     * Check if WooCommerce is active and properly mark setup wizard as complete
     * 
     * IMPORTANT: Do NOT use these filters as they break WooCommerce admin access:
     *   - woocommerce_admin_disabled (completely disables WC admin React app)
     *   - woocommerce_admin_features returning empty array
     * 
     * Instead, we mark the wizard as "completed" using WooCommerce's own options.
     * This is the proper, supported way to skip the wizard.
     * 
     * References:
     *   - https://developer.woocommerce.com/docs/
     *   - https://stackoverflow.com/questions/62775999/how-to-disable-woocommerce-setup-wizard
     *   - https://randomadult.com/disable-woocommerce-setup-wizard/
     */
    public function check_and_setup_woocommerce() {
        // If WooCommerce is active, properly mark wizard as complete
        if (class_exists('WooCommerce')) {
            
            // =====================================================================
            // STEP 1: Mark onboarding profile as "skipped" (the key setting!)
            // This tells WooCommerce the user intentionally skipped the wizard
            // =====================================================================
            update_option('woocommerce_onboarding_profile', ['skipped' => true]);
            
            // =====================================================================
            // STEP 2: Mark all task lists as complete and hidden
            // These control the "finish setup" nags in WC admin
            // =====================================================================
            update_option('woocommerce_task_list_complete', 'yes');
            update_option('woocommerce_task_list_hidden', 'yes');
            update_option('woocommerce_extended_task_list_hidden', 'yes');
            update_option('woocommerce_task_list_welcome_modal_dismissed', 'yes');
            
            // =====================================================================
            // STEP 3: Disable onboarding opt-in and prevent wizard redirect
            // =====================================================================
            update_option('woocommerce_onboarding_opt_in', 'no');
            delete_transient('_wc_activation_redirect');
            
            // =====================================================================
            // STEP 4: Use proper filters (these are SAFE and don't break admin)
            // =====================================================================
            
            // Prevent automatic wizard redirect on activation
            add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');
            
            // Disable marketplace suggestions (upsells)
            add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
            
            // Suppress helper connection notices
            add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
            
            // Hide generic admin notices from WC
            add_filter('woocommerce_show_admin_notice', '__return_false');
            
            // =====================================================================
            // STEP 5: Disable specific onboarding features (not all admin features!)
            // =====================================================================
            add_filter('woocommerce_admin_get_feature_config', function($features) {
                // Only disable onboarding-related features, keep everything else
                $disable = ['onboarding', 'onboarding-tasks', 'homescreen'];
                foreach ($disable as $feature) {
                    if (isset($features[$feature])) {
                        $features[$feature] = false;
                    }
                }
                return $features;
            });
            
            // =====================================================================
            // STEP 6: CSS fallback to hide any remaining wizard UI elements
            // This is cosmetic backup only - the options above do the real work
            // =====================================================================
            add_action('admin_head', function() {
                echo '<style>
                    /* Hide WooCommerce setup wizard and onboarding UI */
                    .woocommerce-profile-wizard,
                    .woocommerce-task-list,
                    .woocommerce-homescreen,
                    .woocommerce-layout__header-tasks-reminder-bar,
                    .woocommerce-onboarding-homepage-notice,
                    .woocommerce-task-card,
                    .woocommerce-welcome-modal,
                    div.notice.woocommerce-message.woocommerce-tracker {
                        display: none !important;
                    }
                </style>';
            });
        }
        
        return true;
    }

/**
 * ============================================================================
 * 2. ADMIN INTERFACE
 * ============================================================================
 */

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            'PN Catalog Import',
            'PN Catalog',
            'manage_options',
            'ml-practitioners-catalog',
            [$this, 'render_admin_page'],
            'dashicons-cart',
            56
        );
    }
    
    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_ml-practitioners-catalog') {
            return;
        }
        
        // Inline styles
        wp_add_inline_style('wp-admin', $this->get_admin_styles());
        
        // Inline scripts
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_admin_scripts());
    }
    
    /**
     * Get admin page styles
     */
    private function get_admin_styles() {
        return "
        .ml-practitioners-wrap {
            max-width: 1200px;
            margin: 20px 0;
        }
        .ml-practitioners-header {
            background: #fff;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .ml-practitioners-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .ml-practitioners-header p {
            margin: 0;
            color: #646970;
            font-size: 14px;
        }
        .ml-practitioners-card {
            background: #fff;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .ml-practitioners-card h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .ml-practitioners-form-group {
            margin: 20px 0;
        }
        .ml-practitioners-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .ml-practitioners-form-group input[type='text'],
        .ml-practitioners-form-group input[type='password'] {
            width: 100%;
            max-width: 600px;
            padding: 8px 12px;
            font-size: 14px;
            font-family: monospace;
        }
        .ml-practitioners-form-group .description {
            margin-top: 8px;
            color: #646970;
            font-size: 13px;
        }
        .ml-practitioners-button {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .ml-practitioners-button:hover {
            background: #135e96;
            color: #fff;
        }
        .ml-practitioners-button.secondary {
            background: #dcdcde;
            color: #2c3338;
        }
        .ml-practitioners-button.secondary:hover {
            background: #c3c4c7;
            color: #2c3338;
        }
        .ml-practitioners-button.success {
            background: #00a32a;
        }
        .ml-practitioners-button.success:hover {
            background: #008a20;
        }
        .ml-practitioners-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .ml-practitioners-status {
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #72aee6;
            background: #f0f6fc;
            display: none;
        }
        .ml-practitioners-status.success {
            border-left-color: #00a32a;
            background: #edfaef;
        }
        .ml-practitioners-status.error {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        .ml-practitioners-status.warning {
            border-left-color: #dba617;
            background: #fcf9e8;
        }
        .ml-practitioners-progress {
            margin: 20px 0;
            display: none;
        }
        .ml-practitioners-progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f1;
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
        .ml-practitioners-progress-fill {
            height: 100%;
            background: #2271b1;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }
        .ml-practitioners-store-status {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .ml-practitioners-status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        .ml-practitioners-status-indicator .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .ml-practitioners-status-indicator.live .status-dot {
            background: #00a32a;
            box-shadow: 0 0 8px rgba(0, 163, 42, 0.6);
        }
        .ml-practitioners-status-indicator.coming-soon .status-dot {
            background: #d63638;
            box-shadow: 0 0 8px rgba(214, 54, 56, 0.6);
        }
        .ml-practitioners-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .ml-practitioners-stat {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 3px;
            text-align: center;
        }
        .ml-practitioners-stat-value {
            font-size: 32px;
            font-weight: 600;
            color: #2271b1;
            margin-bottom: 5px;
        }
        .ml-practitioners-stat-label {
            font-size: 13px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ml-practitioners-info-box {
            background: #f0f6fc;
            border: 1px solid #c3e6ff;
            padding: 15px;
            border-radius: 3px;
            margin: 20px 0;
        }
        .ml-practitioners-info-box h3 {
            margin-top: 0;
            color: #135e96;
        }
        .ml-practitioners-info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .ml-practitioners-info-box li {
            margin: 5px 0;
        }
        ";
    }
    
    /**
     * Get admin page scripts
     */
    private function get_admin_scripts() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('ml_practitioners_nonce');
        
        return "
        jQuery(document).ready(function($) {
            
            // Status message helper
            function showStatus(message, type = 'info') {
                const status = $('.ml-practitioners-status');
                status.removeClass('success error warning').addClass(type);
                status.html('<strong>' + message + '</strong>').fadeIn();
                
                if (type === 'success') {
                    setTimeout(() => status.fadeOut(), 5000);
                }
            }
            
            // Save settings
            $('#ml-practitioners-save-settings').on('click', function() {
                const button = $(this);
                const token = $('#ml-practitioners-github-token').val();
                
                button.prop('disabled', true).text('Saving...');
                
                $.post('{$ajax_url}', {
                    action: 'ml_practitioners_save_settings',
                    nonce: '{$nonce}',
                    github_token: token
                }, function(response) {
                    if (response.success) {
                        showStatus('Settings saved successfully!', 'success');
                    } else {
                        showStatus('Error: ' + response.data.message, 'error');
                    }
                    button.prop('disabled', false).text('Save Settings');
                });
            });
            
            // Test connection
            $('#ml-practitioners-test-connection').on('click', function() {
                const button = $(this);
                const token = $('#ml-practitioners-github-token').val();
                
                button.prop('disabled', true).text('Testing...');
                showStatus('Testing GitHub connection...', 'info');
                
                $.post('{$ajax_url}', {
                    action: 'ml_practitioners_test_connection',
                    nonce: '{$nonce}',
                    github_token: token
                }, function(response) {
                    if (response.success) {
                        showStatus('✓ Connection successful! Found ' + response.data.products + ' products.', 'success');
                    } else {
                        showStatus('✗ Connection failed: ' + response.data.message, 'error');
                    }
                    button.prop('disabled', false).text('Test Connection');
                });
            });
            
            // Import catalog with progress updates
            $('#ml-practitioners-import-catalog').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Importing...');
                
                $('.ml-practitioners-progress').show();
                $('.ml-practitioners-progress-fill').css('width', '0%').text('0%');
                showStatus('Fetching catalog from GitHub...', 'info');
                
                let totalProducts = 0;
                let importedCount = 0;
                let imagesCount = 0;
                
                // Start import - this will process in batches
                function importBatch(offset) {
                    console.log('Starting batch import at offset:', offset);
                    
                    $.post('{$ajax_url}', {
                        action: 'ml_practitioners_import_batch',
                        nonce: '{$nonce}',
                        offset: offset,
                        batch_size: 20
                    })
                    .done(function(response) {
                        console.log('Batch response:', response);
                        
                        if (response.success) {
                            importedCount += response.data.imported;
                            imagesCount += response.data.images;
                            totalProducts = response.data.total;
                            
                            // Update progress
                            const percent = Math.round((importedCount / totalProducts) * 100);
                            $('.ml-practitioners-progress-fill').css('width', percent + '%').text(percent + '%');
                            showStatus('Importing... ' + importedCount + ' of ' + totalProducts + ' products', 'info');
                            
                            console.log('Progress:', importedCount, 'of', totalProducts, '(' + percent + '%)');
                            
                            // Continue if more to import
                            if (response.data.has_more) {
                                importBatch(offset + 20);
                            } else {
                                // Done!
                                $('.ml-practitioners-progress-fill').css('width', '100%').text('100%');
                                let message = '✓ Import complete! ' + importedCount + ' products imported, ' + imagesCount + ' images downloaded';
                                showStatus(message, 'success');
                                
                                // Update stats
                                $('#ml-practitioners-stat-products').text(importedCount);
                                $('#ml-practitioners-stat-images').text(imagesCount);
                                
                                setTimeout(() => {
                                    $('.ml-practitioners-progress').fadeOut();
                                }, 3000);
                                
                                button.prop('disabled', false).text('Import Catalog');
                            }
                        } else {
                            showStatus('✗ Import failed: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                            button.prop('disabled', false).text('Import Catalog');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('AJAX error:', status, error, xhr.responseText);
                        showStatus('✗ AJAX error: ' + error, 'error');
                        button.prop('disabled', false).text('Import Catalog');
                    });
                }
                
                // Start with first batch
                importBatch(0);
            });
            
            // Reset catalog
            $('#ml-practitioners-reset-catalog').on('click', function() {
                if (!confirm('WARNING: This will DELETE ALL imported products and their images. This cannot be undone. Continue?')) {
                    return;
                }
                
                const button = $(this);
                button.prop('disabled', true).text('Deleting...');
                showStatus('Deleting all products...', 'warning');
                
                $.post('{$ajax_url}', {
                    action: 'ml_practitioners_reset_catalog',
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        showStatus('✓ Reset complete! All products deleted. Ready for fresh import.', 'success');
                        $('#ml-practitioners-stat-products').text('0');
                        $('#ml-practitioners-stat-images').text('0');
                    } else {
                        showStatus('✗ Reset failed: ' + response.data.message, 'error');
                    }
                    button.prop('disabled', false).text('Reset & Delete All Products');
                });
            });
            
            // Make Store LIVE button
            $('#ml-practitioners-go-live').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Making Store LIVE...');
                showStatus('Disabling Coming Soon mode...', 'info');
                
                $.post('{$ajax_url}', {
                    action: 'ml_practitioners_go_live',
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        showStatus('✓ ' + response.data.message, 'success');
                        // Reload page to update status indicator
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showStatus('✗ Failed: ' + response.data.message, 'error');
                        button.prop('disabled', false).text('Make Store LIVE Now');
                    }
                });
            });
            
        });
        ";
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>You do not have sufficient permissions to access this page.</p></div></div>';
            return;
        }
        
        // Get current settings
        $github_token = $this->get_github_token();
        $import_stats = $this->get_import_stats();
        
        ?>
        <div class="wrap ml-practitioners-wrap">
            
            <!-- Header -->
            <div class="ml-practitioners-header">
                <h1>Professional Nutritionals Catalog Import</h1>
                <p>Import the complete PN product catalog from GitHub to your WooCommerce store. One-click setup with 196+ products, images, and descriptions.</p>
            </div>
            
            <!-- Status Messages -->
            <div class="ml-practitioners-status"></div>
            
            <!-- WooCommerce Store Status Card -->
            <div class="ml-practitioners-card">
                <h2>Store Status</h2>
                
                <?php 
                $coming_soon = get_option('woocommerce_coming_soon', 'yes');
                $is_live = ($coming_soon === 'no');
                ?>
                
                <div class="ml-practitioners-store-status">
                    <div class="ml-practitioners-status-indicator <?php echo $is_live ? 'live' : 'coming-soon'; ?>">
                        <span class="status-dot"></span>
                        <strong>Store Status:</strong> 
                        <?php echo $is_live ? 'LIVE' : 'Coming Soon Mode'; ?>
                    </div>
                    
                    <?php if (!$is_live): ?>
                        <p style="margin: 15px 0 0 0; color: #d63638;">
                            ⚠️ Your store is currently in "Coming Soon" mode. Customers cannot see your products.
                        </p>
                        <button type="button" id="ml-practitioners-go-live" class="ml-practitioners-button success" style="margin-top: 15px;">
                            Make Store LIVE Now
                        </button>
                    <?php else: ?>
                        <p style="margin: 15px 0 0 0; color: #00a32a;">
                            ✓ Your store is LIVE and visible to customers.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Import Card -->
            <div class="ml-practitioners-card">
                <h2>Import Catalog</h2>
                
                <div class="ml-practitioners-info-box">
                    <h3>What This Will Do:</h3>
                    <ul>
                        <li>✓ Fetch product catalog from GitHub repository</li>
                        <li>✓ Download 196 high-quality product images (500x500, matted & centered)</li>
                        <li>✓ Upload images to WordPress media library</li>
                        <li>✓ Create 196+ WooCommerce products with complete details</li>
                        <li>✓ Set pricing, descriptions, categories, and metadata</li>
                        <li>✓ Products become fully independent (no GitHub dependency)</li>
                    </ul>
                    <p><strong>Note:</strong> After import, you can customize any product or image. They will be stored locally in your media library.</p>
                </div>
                
                <!-- Progress Bar -->
                <div class="ml-practitioners-progress">
                    <div class="ml-practitioners-progress-bar">
                        <div class="ml-practitioners-progress-fill">0%</div>
                    </div>
                </div>
                
                <!-- Debug Log -->
                <div id="ml-practitioners-debug-log" style="display:none; margin: 20px 0; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 11px; line-height: 1.4;"></div>
                
                <!-- Import Buttons -->
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="button" id="ml-practitioners-import-catalog" class="ml-practitioners-button success">
                        Import Catalog Now
                    </button>
                    <button type="button" id="ml-practitioners-reset-catalog" class="ml-practitioners-button" style="background: #dc3545;">
                        Reset & Delete All Products
                    </button>
                </div>
            </div>
            
            <!-- Stats Card -->
            <div class="ml-practitioners-card">
                <h2>Import Statistics</h2>
                
                <div class="ml-practitioners-stats">
                    <div class="ml-practitioners-stat">
                        <div class="ml-practitioners-stat-value" id="ml-practitioners-stat-products">
                            <?php echo $import_stats['products']; ?>
                        </div>
                        <div class="ml-practitioners-stat-label">Products Imported</div>
                    </div>
                    
                    <div class="ml-practitioners-stat">
                        <div class="ml-practitioners-stat-value" id="ml-practitioners-stat-images">
                            <?php echo $import_stats['images']; ?>
                        </div>
                        <div class="ml-practitioners-stat-label">Images Downloaded</div>
                    </div>
                    
                    <div class="ml-practitioners-stat">
                        <div class="ml-practitioners-stat-value">
                            <?php echo $import_stats['last_import'] ? date('M j, Y', $import_stats['last_import']) : 'Never'; ?>
                        </div>
                        <div class="ml-practitioners-stat-label">Last Import</div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
    }

/**
 * ============================================================================
 * 3. SETTINGS MANAGEMENT
 * ============================================================================
 */

    /**
     * Get GitHub token (from settings or default)
     */
    private function get_github_token() {
        $token = get_option('ml_practitioners_github_token');
        return $token ? $token : $this->default_github_token;
    }
    
    /**
     * Save GitHub token
     */
    private function save_github_token($token) {
        return update_option('ml_practitioners_github_token', sanitize_text_field($token));
    }
    
    /**
     * Get import statistics
     */
    private function get_import_stats() {
        return [
            'products' => (int) get_option('ml_practitioners_products_imported', 0),
            'images' => (int) get_option('ml_practitioners_images_imported', 0),
            'last_import' => (int) get_option('ml_practitioners_last_import', 0)
        ];
    }
    
    /**
     * Update import statistics
     */
    private function update_import_stats($products, $images) {
        update_option('ml_practitioners_products_imported', $products);
        update_option('ml_practitioners_images_imported', $images);
        update_option('ml_practitioners_last_import', time());
    }

/**
 * ============================================================================
 * 4. GITHUB INTEGRATION
 * ============================================================================
 */

    /**
     * Fetch catalog JSON from GitHub
     */
    private function fetch_catalog_from_github() {
        $token = $this->get_github_token();
        $url = 'https://api.github.com/repos/' . ML_WC_PRACTITIONERS_GITHUB_REPO . '/contents/catalog/PRACTITIONER-CATALOG-FINAL.csv';
        
        // Build headers - only add Authorization if token exists
        $headers = [
            'Accept' => 'application/vnd.github.v3.raw'
        ];
        
        if (!empty($token)) {
            $headers['Authorization'] = 'token ' . $token;
        }
        
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Debug: Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return ['error' => 'GitHub API error: HTTP ' . $response_code . ' - ' . substr($body, 0, 200)];
        }
        
        // Parse CSV data
        $products = $this->parse_csv($body);
        
        if (empty($products)) {
            return ['error' => 'No products found in CSV file'];
        }
        
        return ['products' => $products];
    }
    
    /**
     * Parse CSV data into products array
     */
    private function parse_csv($csv_data) {
        $lines = explode("\n", $csv_data);
        $products = [];
        $headers = [];
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse CSV line (handle quoted fields with commas)
            $fields = str_getcsv($line);
            
            if ($index === 0) {
                // First line is headers
                $headers = $fields;
                continue;
            }
            
            // Map fields to product data
            if (count($fields) >= 16) {
                $products[] = [
                    'stock_code' => $fields[0],
                    'product_name' => $fields[1],
                    'wholesale_price' => $fields[2],
                    'retail_price' => $fields[3],
                    'profit' => $fields[4],
                    'image_url' => $fields[5],
                    'image_preview' => $fields[6],
                    'category' => $fields[7],
                    'form' => $fields[8],
                    'count' => $fields[9],
                    'description' => $fields[10],
                    'ingredients' => $fields[11],
                    'benefits' => $fields[12],
                    'directions' => $fields[13],
                    'warnings' => $fields[14],
                    'pn_sku' => $fields[15]
                ];
            }
        }
        
        return $products;
    }
    
    /**
     * Download image from GitHub with authentication
     */
    private function download_image_from_github($image_url) {
        $this->log('Downloading image from: ' . $image_url);
        
        // For public repositories, use raw.githubusercontent.com directly (no auth needed)
        $response = wp_remote_get($image_url, [
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->log('wp_remote_get WP_Error: ' . $response->get_error_message());
            return ['error' => $response->get_error_message()];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $this->log('Image download response code: ' . $response_code);
        
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log('Image download failed: HTTP ' . $response_code . ' - ' . substr($body, 0, 200));
            return ['error' => 'HTTP ' . $response_code];
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $this->log('Image downloaded successfully, content-type: ' . $content_type . ', size: ' . strlen($body) . ' bytes');
        
        return [
            'data' => $body,
            'type' => $content_type
        ];
    }

/**
 * ============================================================================
 * 5. CATALOG IMPORT
 * ============================================================================
 */

    /**
     * Import entire catalog
     */
    private function import_catalog() {
        // Fetch catalog data
        $catalog = $this->fetch_catalog_from_github();
        
        if (isset($catalog['error'])) {
            return ['error' => $catalog['error']];
        }
        
        if (!isset($catalog['products']) || !is_array($catalog['products'])) {
            // Debug: Log what we actually got
            $debug_info = 'Catalog keys: ' . implode(', ', array_keys($catalog));
            $debug_info .= ' | Type: ' . gettype($catalog);
            if (isset($catalog['catalog_info'])) {
                $debug_info .= ' | Has catalog_info';
            }
            return ['error' => 'Invalid catalog format. Debug: ' . $debug_info];
        }
        
        $products = $catalog['products'];
        $imported = 0;
        $images_downloaded = 0;
        $errors = [];
        
        // Import each product
        foreach ($products as $product_data) {
            $result = $this->import_single_product($product_data);
            
            if (isset($result['error'])) {
                $errors[] = $product_data['stock_code'] . ': ' . $result['error'];
            } else {
                $imported++;
                if ($result['image_downloaded']) {
                    $images_downloaded++;
                }
            }
        }
        
        // Update stats
        $this->update_import_stats($imported, $images_downloaded);
        
        // CRITICAL: Force WordPress to recognize all attachments in Media Library
        // This is needed because programmatic uploads don't auto-index like manual uploads
        global $wpdb;
        
        // Force regenerate attachment metadata for all imported images
        $attachment_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image%'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        $this->log('Found ' . count($attachment_ids) . ' recent attachments to reindex');
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        foreach ($attachment_ids as $att_id) {
            $file = get_attached_file($att_id);
            if ($file && file_exists($file)) {
                $metadata = wp_generate_attachment_metadata($att_id, $file);
                wp_update_attachment_metadata($att_id, $metadata);
            }
        }
        
        $this->log('Regenerated metadata for all recent attachments');
        
        // Flush rewrite rules and caches
        flush_rewrite_rules();
        wp_cache_flush();
        
        // Clear WooCommerce transients
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }
        
        $this->log('Flushed rewrite rules and cleared all caches');
        
        return [
            'imported' => $imported,
            'images' => $images_downloaded,
            'errors' => $errors
        ];
    }
    
    /**
     * Import a single product
     */
    private function import_single_product($data) {
        // Check if product already exists by SKU
        $existing_id = wc_get_product_id_by_sku($data['stock_code']);
        
        if ($existing_id) {
            // Update existing product
            $product = wc_get_product($existing_id);
        } else {
            // Create new product
            $product = new WC_Product_Simple();
        }
        
        // Set basic product data
        $product->set_name($data['product_name']);
        $product->set_sku($data['stock_code']);
        $product->set_regular_price($data['retail_price']);
        $product->set_description($data['description'] ?? '');
        $product->set_short_description($data['benefits'] ?? '');
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        
        // Set categories
        if (!empty($data['category'])) {
            $category_ids = $this->get_or_create_categories($data['category']);
            $product->set_category_ids($category_ids);
        }
        
        // Set custom meta fields
        $product->update_meta_data('_pn_stock_code', $data['stock_code']);
        $product->update_meta_data('_pn_wholesale_price', $data['wholesale_price']);
        $product->update_meta_data('_pn_profit', $data['profit']);
        $product->update_meta_data('_pn_sku', $data['pn_sku'] ?? '');
        $product->update_meta_data('_pn_form', $data['form'] ?? '');
        $product->update_meta_data('_pn_count', $data['count'] ?? '');
        $product->update_meta_data('_pn_ingredients', $data['ingredients'] ?? '');
        $product->update_meta_data('_pn_directions', $data['directions'] ?? '');
        $product->update_meta_data('_pn_warnings', $data['warnings'] ?? '');
        
        // Save product
        $product_id = $product->save();
        
        // Handle product image
        $image_downloaded = false;
        if (!empty($data['image_url'])) {
            try {
                $this->log('Attempting to download image for ' . $data['stock_code'] . ': ' . $data['image_url']);
                $image_result = $this->attach_product_image($product_id, $data['image_url'], $data['product_name']);
                if (isset($image_result['error'])) {
                    $this->log('Image ERROR for ' . $data['stock_code'] . ': ' . $image_result['error']);
                } else {
                    $this->log('Image SUCCESS for ' . $data['stock_code'] . ' - Attachment ID: ' . $image_result['attachment_id']);
                    
                    // ALSO set via WooCommerce product object
                    $product->set_image_id($image_result['attachment_id']);
                    $product->save();
                    $this->log('WooCommerce product->set_image_id() called and saved');
                    
                    $image_downloaded = true;
                }
            } catch (Exception $e) {
                // Log but don't fail - product is still created
                $this->log('Image EXCEPTION for ' . $data['stock_code'] . ': ' . $e->getMessage());
            }
        } else {
            $this->log('NO IMAGE URL for ' . $data['stock_code']);
        }
        
        return [
            'product_id' => $product_id,
            'image_downloaded' => $image_downloaded
        ];
    }
    
    /**
     * Get or create product categories
     */
    private function get_or_create_categories($category_string) {
        $categories = array_map('trim', explode(',', $category_string));
        $category_ids = [];
        
        foreach ($categories as $category_name) {
            if (empty($category_name)) {
                continue;
            }
            
            // Check if category exists
            $term = get_term_by('name', $category_name, 'product_cat');
            
            if ($term) {
                $category_ids[] = $term->term_id;
            } else {
                // Create new category
                $result = wp_insert_term($category_name, 'product_cat');
                if (!is_wp_error($result)) {
                    $category_ids[] = $result['term_id'];
                }
            }
        }
        
        return $category_ids;
    }

/**
 * ============================================================================
 * 6. IMAGE MANAGEMENT
 * ============================================================================
 */

    /**
     * Download image from GitHub and attach to product
     */
    private function attach_product_image($product_id, $image_url, $product_name) {
        // Download image from GitHub
        $this->log('download_image_from_github called for: ' . $image_url);
        $image_result = $this->download_image_from_github($image_url);
        
        if (isset($image_result['error'])) {
            $this->log('download_image_from_github returned error: ' . $image_result['error']);
            return ['error' => $image_result['error']];
        }
        
        $this->log('Image downloaded, size: ' . strlen($image_result['data']) . ' bytes');
        
        // Get filename from URL
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        $this->log('Filename: ' . $filename);
        
        // Upload to WordPress
        $upload = wp_upload_bits($filename, null, $image_result['data']);
        
        if ($upload['error']) {
            $this->log('wp_upload_bits error: ' . $upload['error']);
            return ['error' => $upload['error']];
        }
        
        $this->log('wp_upload_bits success: ' . $upload['file']);
        $this->log('File URL: ' . $upload['url']);
        
        // Determine the *actual* mime type from the saved file, not from GitHub's header
        $wp_filetype = wp_check_filetype_and_ext($upload['file'], $filename);
        $detected_mime = !empty($wp_filetype['type']) ? $wp_filetype['type'] : 'image/jpeg'; // sensible default
        $this->log('GitHub returned mime: ' . $image_result['type']);
        $this->log('Detected actual mime type: ' . $detected_mime);
        
        $file_title = sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME));
        
        // Build attachment array with CORRECT MIME TYPE
        $attachment = [
            'post_mime_type' => $detected_mime,  // USE DETECTED MIME, NOT GITHUB'S!
            'post_title'     => $file_title,
            'post_name'      => $file_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $product_id,
            'guid'           => $upload['url'],
        ];
        
        $this->log('Creating attachment with detected mime: ' . $detected_mime);
        
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
        
        if (is_wp_error($attachment_id)) {
            $this->log('wp_insert_attachment ERROR: ' . $attachment_id->get_error_message());
            return ['error' => $attachment_id->get_error_message()];
        }
        
        $this->log('wp_insert_attachment success: Attachment ID ' . $attachment_id);
        
        // FORCE the post_name/slug with wp_update_post (wp_insert_attachment ignores it!)
        wp_update_post([
            'ID' => $attachment_id,
            'post_name' => $file_title
        ]);
        $this->log('Forced post_name slug: ' . $file_title);
        
        // Link file path meta so WordPress "owns" the file
        $uploads = wp_get_upload_dir();
        $relative_path = ltrim(str_replace($uploads['basedir'], '', $upload['file']), '/');
        update_post_meta($attachment_id, '_wp_attached_file', $relative_path);
        $this->log('Set _wp_attached_file: ' . $relative_path);
        
        // Generate sizes/metadata (thumbnails, etc.)
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        
        if (empty($metadata) || !isset($metadata['width'])) {
            $this->log('WARNING: wp_generate_attachment_metadata failed or incomplete: ' . json_encode($metadata));
        } else {
            $this->log('Generated metadata with dimensions: ' . json_encode($metadata));
        }
        
        wp_update_attachment_metadata($attachment_id, $metadata);
        $this->log('Metadata saved to database');
        
        // Set as product featured image
        set_post_thumbnail($product_id, $attachment_id);
        update_post_meta($product_id, '_thumbnail_id', $attachment_id);
        
        $this->log('Image attached to product successfully');
        
        return ['attachment_id' => $attachment_id];
    }

/**
 * ============================================================================
 * 7. AJAX HANDLERS
 * ============================================================================
 */

    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $token = sanitize_text_field($_POST['github_token']);
        $this->save_github_token($token);
        
        wp_send_json_success(['message' => 'Settings saved']);
    }
    
    /**
     * AJAX: Test GitHub connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Override token if provided
        if (!empty($_POST['github_token'])) {
            $this->save_github_token(sanitize_text_field($_POST['github_token']));
        }
        
        // Try to fetch catalog
        $catalog = $this->fetch_catalog_from_github();
        
        if (isset($catalog['error'])) {
            wp_send_json_error(['message' => $catalog['error']]);
        }
        
        $product_count = isset($catalog['products']) ? count($catalog['products']) : 0;
        
        wp_send_json_success([
            'message' => 'Connection successful',
            'products' => $product_count
        ]);
    }
    
    /**
     * AJAX: Import catalog
     */
    public function ajax_import_catalog() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Increase timeout for large imports
        set_time_limit(300);
        
        $result = $this->import_catalog();
        
        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }
        
        wp_send_json_success([
            'imported' => $result['imported'],
            'images' => $result['images'],
            'errors' => $result['errors'],
            'log' => $this->log_entries
        ]);
    }
    
    /**
     * AJAX: Import catalog in batches
     */
    public function ajax_import_batch() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 20;
        
        // Fetch catalog
        $catalog = $this->fetch_catalog_from_github();
        
        if (isset($catalog['error'])) {
            wp_send_json_error(['message' => $catalog['error']]);
        }
        
        if (!isset($catalog['products']) || !is_array($catalog['products'])) {
            wp_send_json_error(['message' => 'Invalid catalog format']);
        }
        
        $total = count($catalog['products']);
        $batch = array_slice($catalog['products'], $offset, $batch_size);
        
        $imported = 0;
        $images = 0;
        
        foreach ($batch as $product_data) {
            $result = $this->import_single_product($product_data);
            if (!isset($result['error'])) {
                $imported++;
                if ($result['image_downloaded']) {
                    $images++;
                }
            }
        }
        
        wp_send_json_success([
            'imported' => $imported,
            'images' => $images,
            'total' => $total,
            'has_more' => ($offset + $batch_size) < $total
        ]);
    }
    
    /**
     * AJAX: Get import status
     */
    public function ajax_get_import_status() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $stats = $this->get_import_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Reset catalog - Delete all imported products
     */
    public function ajax_reset_catalog() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Get all products
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $product_ids = get_posts($args);
        $deleted_products = 0;
        $deleted_images = 0;
        
        foreach ($product_ids as $product_id) {
            // Get and delete product image attachment first
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id) {
                wp_delete_attachment($thumbnail_id, true);
                $deleted_images++;
            }
            
            // Delete product (force delete, bypass trash)
            wp_delete_post($product_id, true);
            $deleted_products++;
        }
        
        // Also delete any orphaned attachments from previous imports
        $orphan_args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_wp_attached_file',
                    'value' => 'composite_squared',
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        $orphan_ids = get_posts($orphan_args);
        foreach ($orphan_ids as $orphan_id) {
            wp_delete_attachment($orphan_id, true);
            $deleted_images++;
        }
        
        // Reset stats
        update_option('ml_practitioners_products_imported', 0);
        update_option('ml_practitioners_images_imported', 0);
        update_option('ml_practitioners_last_import', 0);
        
        wp_send_json_success([
            'message' => 'All products and images deleted successfully',
            'deleted_products' => $deleted_products,
            'deleted_images' => $deleted_images
        ]);
    }
    
    /**
     * AJAX: Make store LIVE (disable Coming Soon mode)
     */
    public function ajax_go_live() {
        check_ajax_referer('ml_practitioners_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        // Force store LIVE
        update_option('woocommerce_coming_soon', 'no');
        update_option('woocommerce_store_pages_only', 'no');
        update_option('woocommerce_private_link', '');
        
        wp_send_json_success([
            'message' => 'Store is now LIVE! Customers can see your products.'
        ]);
    }

/**
 * ============================================================================
 * 8. UTILITY FUNCTIONS
 * ============================================================================
 */

    /**
     * Log message to debug.log AND store for display
     */
    private function log($message) {
        // Store for display
        $this->log_entries[] = $message;
        
        // Also log to debug.log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[ML Practitioners Catalog] ' . $message);
        }
    }

/**
 * ============================================================================
 * 9. ACTIVATION/DEACTIVATION
 * ============================================================================
 */

    /**
     * Plugin activation - Auto-install WooCommerce silently
     */
    public static function activate() {
        // Auto-install WooCommerce if not present (silently, no modals)
        if (!class_exists('WooCommerce')) {
            self::auto_install_woocommerce();
        } else {
            // Kill the WooCommerce wizard if WC is already active
            self::kill_woocommerce_wizard();
        }
        
        // Set default options
        add_option('ml_practitioners_products_imported', 0);
        add_option('ml_practitioners_images_imported', 0);
        add_option('ml_practitioners_last_import', 0);
    }
    
    /**
     * Auto-install WooCommerce silently in background
     */
    private static function auto_install_woocommerce() {
        // Include required files
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        
        // Get WooCommerce plugin info
        $api = plugins_api('plugin_information', [
            'slug' => 'woocommerce',
            'fields' => ['sections' => false]
        ]);
        
        if (is_wp_error($api)) {
            return;
        }
        
        // Install WooCommerce silently
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($api->download_link);
        
        if (is_wp_error($result)) {
            return;
        }
        
        // Activate WooCommerce
        activate_plugin('woocommerce/woocommerce.php');
        
        // Kill wizard immediately
        self::kill_woocommerce_wizard();
    }
    
    /**
     * Mark WooCommerce setup wizard as complete (proper method)
     * 
     * IMPORTANT: This uses the CORRECT approach - marking wizard as "complete"
     * rather than trying to disable WooCommerce admin features.
     * 
     * DO NOT USE these filters (they break WC admin access):
     *   - woocommerce_admin_disabled
     *   - woocommerce_admin_features returning empty array
     * 
     * References:
     *   - https://stackoverflow.com/questions/62775999/how-to-disable-woocommerce-setup-wizard
     *   - https://randomadult.com/disable-woocommerce-setup-wizard/
     */
    private static function kill_woocommerce_wizard() {
        // =====================================================================
        // THE KEY SETTING: Mark onboarding profile as "skipped"
        // This is what WooCommerce checks to determine if wizard should show
        // =====================================================================
        update_option('woocommerce_onboarding_profile', ['skipped' => true]);
        
        // =====================================================================
        // Mark all task lists as complete and hidden
        // =====================================================================
        update_option('woocommerce_task_list_complete', 'yes');
        update_option('woocommerce_task_list_hidden', 'yes');
        update_option('woocommerce_extended_task_list_hidden', 'yes');
        update_option('woocommerce_task_list_welcome_modal_dismissed', 'yes');
        update_option('woocommerce_task_list_tracked_completed_tasks', []);
        
        // =====================================================================
        // Disable onboarding opt-in and wizard redirect
        // =====================================================================
        update_option('woocommerce_onboarding_opt_in', 'no');
        delete_transient('_wc_activation_redirect');
        delete_option('woocommerce_admin_install_timestamp');
        
        // =====================================================================
        // Disable marketplace suggestions and notifications
        // =====================================================================
        update_option('woocommerce_show_marketplace_suggestions', 'no');
        update_option('woocommerce_merchant_email_notifications', 'no');
        update_option('woocommerce_allow_marketplace_suggestions', 'no');
        
        // Mark as already set up (backdate install timestamp)
        update_option('woocommerce_admin_install_timestamp', time() - (30 * DAY_IN_SECONDS));
        
        // =====================================================================
        // Force store LIVE (disable "Coming Soon" mode)
        // =====================================================================
        update_option('woocommerce_coming_soon', 'no');
        update_option('woocommerce_store_pages_only', 'no');
        update_option('woocommerce_private_link', '');
        
        // =====================================================================
        // Set sensible store defaults
        // =====================================================================
        update_option('woocommerce_store_address', '');
        update_option('woocommerce_store_city', '');
        update_option('woocommerce_default_country', 'US');
        update_option('woocommerce_store_postcode', '');
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_product_type', 'both');
        update_option('woocommerce_sell_in_person', 'no');
        
        // Disable analytics (optional, reduces overhead)
        update_option('woocommerce_analytics_enabled', 'no');
        update_option('woocommerce_remote_variant_assignment', 'no');
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Cleanup if needed
    }
}

/**
 * ============================================================================
 * INITIALIZE PLUGIN
 * ============================================================================
 */

// Register activation/deactivation hooks
register_activation_hook(__FILE__, ['ML_WC_Practitioners_Catalog', 'activate']);
register_deactivation_hook(__FILE__, ['ML_WC_Practitioners_Catalog', 'deactivate']);

// Initialize plugin
function ml_wc_practitioners_init() {
    return ML_WC_Practitioners_Catalog::get_instance();
}
add_action('plugins_loaded', 'ml_wc_practitioners_init');


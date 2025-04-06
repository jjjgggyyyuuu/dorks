<?php
/**
 * Plugin Name: Domain Value Predictor
 * Description: An AI-powered tool to predict potentially profitable domain names for users with a subscription model.
 * Version: 1.0.0
 * Author: Your Name
 * License: MIT
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DVP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DVP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DVP_VERSION', '1.0.0');

// Check for required PHP extensions before plugin loads
function dvp_check_dependencies() {
    $required_extensions = array('mysqli', 'json', 'curl');
    $missing_extensions = array();
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (!empty($missing_extensions)) {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Format error message
        $message = '<div class="error"><p>';
        $message .= '<strong>Domain Value Predictor Error:</strong> ';
        $message .= 'Your PHP installation is missing required extension(s): <strong>' . implode(', ', $missing_extensions) . '</strong>. ';
        $message .= 'Please contact your hosting provider to enable these extensions.';
        $message .= '</p></div>';
        
        // Output error message using WordPress admin notices
        add_action('admin_notices', function() use ($message) {
            echo $message;
        });
        
        // Prevent plugin from loading further
        return false;
    }
    
    return true;
}

// Run dependency check early during plugins_loaded
add_action('plugins_loaded', 'dvp_check_dependencies', 0);

// ===== IMPORTANT: This function is critical for plugin operation =====
// Core function to load all plugin files WITHOUT initializing them
// This should run as early as possible in the WordPress lifecycle
function dvp_load_core_files() {
    // Check if dependencies are met before loading files
    if (!dvp_check_dependencies()) {
        return;
    }
    
    // Double-check ABSPATH is defined (WordPress is loaded)
    if (!defined('ABSPATH')) {
        return;
    }
    
    // Core plugin files are included here but NOT initialized
    require_once DVP_PLUGIN_DIR . 'includes/class-domain-predictor.php';
    require_once DVP_PLUGIN_DIR . 'includes/class-stripe-integration.php';
    require_once DVP_PLUGIN_DIR . 'includes/class-api-handler.php';
    require_once DVP_PLUGIN_DIR . 'includes/class-domain-checker.php';
    
    // Load admin files in admin context
    if (is_admin()) {
        require_once DVP_PLUGIN_DIR . 'admin/class-admin-settings.php';
    }
}

// Run this function on the plugins_loaded hook with a very early priority (1)
// This ensures that files are loaded early, but NOT initialized
add_action('plugins_loaded', 'dvp_load_core_files', 1);

// Initialize the admin class AFTER WordPress is fully loaded
function dvp_init_admin_class() {
    // Double-check WordPress is fully loaded with essential functions
    if (!function_exists('add_action') || !function_exists('is_admin') || !function_exists('class_exists')) {
        return;
    }
    
    if (is_admin() && class_exists('DVP_Admin_Settings')) {
        // Statically initialize the class after WordPress is fully loaded
        DVP_Admin_Settings::init();
    }
}
// Very late priority ensures WordPress is fully loaded
add_action('plugins_loaded', 'dvp_init_admin_class', 999);

// Initialize all plugin classes
function dvp_init_plugin_classes() {
    // Double-check WordPress is fully loaded with essential functions
    if (!function_exists('add_action') || !function_exists('class_exists')) {
        return;
    }
    
    if (class_exists('DVP_Domain_Predictor')) {
        DVP_Domain_Predictor::init();
    }
    
    if (class_exists('DVP_Stripe_Integration')) {
        DVP_Stripe_Integration::init();
    }
    
    if (class_exists('DVP_API_Handler')) {
        DVP_API_Handler::init();
    }
    
    if (class_exists('DVP_Domain_Checker')) {
        DVP_Domain_Checker::init();
    }
}
// Initialize plugin classes with an even later priority
add_action('plugins_loaded', 'dvp_init_plugin_classes', 1000);

// Create necessary directories
$vendor_dir = DVP_PLUGIN_DIR . 'vendor';
if (!file_exists($vendor_dir)) {
    if (!@mkdir($vendor_dir, 0755, true)) {
        // Log error if directory creation fails
        error_log('Domain Value Predictor: Failed to create vendor directory');
    } else {
        // Create index.php file in vendor directory for security
        $index_file = $vendor_dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        // Create .htaccess to prevent direct access
        $htaccess_file = $vendor_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            @file_put_contents($htaccess_file, 'Deny from all');
        }
    }
}

/**
 * Main Plugin Class
 */
class Domain_Value_Predictor {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings;

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
     * Constructor - no WordPress functions here except hooks
     */
    private function __construct() {
        // Initialize the plugin on the 'init' hook which runs AFTER plugins_loaded
        add_action('init', array($this, 'init'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load plugin text domain
        load_plugin_textdomain('domain-value-predictor', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Classes are initialized separately via the functions above
        // This prevents any WordPress function calls too early in the loading process
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add shortcodes
        add_shortcode('domain_predictor', array($this, 'domain_predictor_shortcode'));
        add_shortcode('subscription_form', array($this, 'subscription_form_shortcode'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Make sure jQuery is loaded
        wp_enqueue_script('jquery');
        
        // Enqueue frontend styles
        wp_enqueue_style('dvp-styles', DVP_PLUGIN_URL . 'assets/css/domain-predictor.css', array(), DVP_VERSION);
        
        // Enqueue frontend scripts
        wp_enqueue_script('dvp-main', DVP_PLUGIN_URL . 'assets/js/domain-predictor.js', array('jquery'), DVP_VERSION, true);
        
        // Localize script with necessary data
        wp_localize_script('dvp-main', 'dvp_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dvp-nonce'),
            'is_subscribed' => $this->is_user_subscribed(),
            'home_url' => home_url(),
            'plugin_url' => DVP_PLUGIN_URL,
        ));
        
        // Enqueue Stripe.js if needed
        if (is_page('subscription') || has_shortcode(get_the_content(), 'subscription_form')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array('jquery'), null, true);
        }
    }

    /**
     * Check if current user has an active subscription
     */
    private function is_user_subscribed() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $subscription_status = get_user_meta($user_id, 'dvp_subscription_status', true);
        
        return $subscription_status === 'active';
    }

    /**
     * Domain predictor shortcode
     */
    public function domain_predictor_shortcode($atts) {
        if (!$this->is_user_subscribed()) {
            // Get subscription page URL safely
            // Use WP_Query instead of deprecated get_page_by_path
            $subscription_url = '#';
            $query = new WP_Query(array(
                'post_type' => 'page',
                'name' => 'subscription',
                'posts_per_page' => 1
            ));
            if ($query->have_posts()) {
                $query->the_post();
                $subscription_url = get_permalink();
                wp_reset_postdata();
            }
            
            return '<div class="dvp-subscription-required">
                <h3>Subscription Required</h3>
                <p>You need an active subscription to use the Domain Value Predictor tool.</p>
                <a href="' . $subscription_url . '" class="dvp-button">Subscribe Now</a>
            </div>';
        }
        
        ob_start();
        $template_file = DVP_PLUGIN_DIR . 'templates/domain-predictor.php';
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="dvp-error">Template file not found. Please contact the administrator.</div>';
            error_log('Domain Value Predictor: Template file not found - ' . $template_file);
        }
        return ob_get_clean();
    }

    /**
     * Subscription form shortcode
     */
    public function subscription_form_shortcode($atts) {
        if ($this->is_user_subscribed()) {
            // Get domain predictor page URL safely
            // Use WP_Query instead of deprecated get_page_by_path
            $predictor_url = '#';
            $query = new WP_Query(array(
                'post_type' => 'page',
                'name' => 'domain-predictor',
                'posts_per_page' => 1
            ));
            if ($query->have_posts()) {
                $query->the_post();
                $predictor_url = get_permalink();
                wp_reset_postdata();
            }
            
            return '<div class="dvp-subscription-active">
                <h3>You\'re Subscribed!</h3>
                <p>You already have an active subscription. Visit the <a href="' . $predictor_url . '">Domain Predictor</a> to start finding valuable domains.</p>
            </div>';
        }
        
        ob_start();
        $template_file = DVP_PLUGIN_DIR . 'templates/subscription-form.php';
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<div class="dvp-error">Template file not found. Please contact the administrator.</div>';
            error_log('Domain Value Predictor: Template file not found - ' . $template_file);
        }
        return ob_get_clean();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check for required mysqli extension before proceeding
        if (!extension_loaded('mysqli')) {
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Throw an error that WordPress will catch and display
            wp_die(
                'Domain Value Predictor Error: Your PHP installation is missing the required mysqli extension. ' .
                'Please contact your hosting provider to enable this extension before activating the plugin.',
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
        
        // Create necessary database tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'dvp_predictions';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            search_params longtext NOT NULL,
            domains longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create subscription status user meta field
        add_user_meta(1, 'dvp_subscription_status', 'inactive', true);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
Domain_Value_Predictor::get_instance();

// Add a helper function to check database connection
function dvp_is_db_connected() {
    if (!function_exists('mysqli_connect')) {
        return false;
    }
    
    global $wpdb;
    
    // Try a simple query to check connection
    try {
        $test = $wpdb->get_var("SELECT 1");
        return $test === '1';
    } catch (Exception $e) {
        error_log('Domain Value Predictor - Database connection error: ' . $e->getMessage());
        return false;
    }
}

// Check this on a slightly higher priority than our main loading function
add_action('plugins_loaded', function() {
    if (!dvp_is_db_connected()) {
        // Show admin notice about database connection issues
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo '<strong>Domain Value Predictor Error:</strong> ';
            echo 'Unable to connect to the WordPress database. Please check your database connection settings.';
            echo '</p></div>';
        });
    }
}, 2); 
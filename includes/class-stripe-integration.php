<?php
/**
 * Stripe Integration Class
 * Handles Stripe API integration for subscription management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===== IMPORTANT: NEVER USE WORDPRESS FUNCTIONS BEFORE THIS CHECK =====
// Skip execution ENTIRELY if WordPress isn't fully loaded
if (!function_exists('add_action') || !function_exists('get_option') || !function_exists('wp_send_json_error')) {
    return;
}

class DVP_Stripe_Integration {

    /**
     * Stripe API keys
     */
    private $secret_key;
    private $publishable_key;
    
    /**
     * Static initializer to safely create the class instance
     */
    public static function init() {
        // Double-check WordPress functions are available
        if (!function_exists('get_option') || !function_exists('add_action')) {
            return false;
        }
        
        $instance = new self();
        $instance->setup_hooks();
        return $instance;
    }
    
    /**
     * Constructor - no WordPress functions here!
     */
    private function __construct() {
        // Intentionally empty - no WordPress functions allowed here
    }
    
    /**
     * Setup all hooks and initialize properties
     * ONLY called from init() after WordPress is fully loaded
     */
    public function setup_hooks() {
        // Now it's safe to use WordPress functions
        $this->secret_key = get_option('dvp_stripe_secret_key');
        $this->publishable_key = get_option('dvp_stripe_publishable_key');
        
        // Try to load the Stripe library early to catch any issues
        try {
            $this->include_stripe_php();
        } catch (Exception $e) {
            // Log the error but don't stop plugin initialization
            error_log('Domain Value Predictor - Stripe library error: ' . $e->getMessage());
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_dvp_create_subscription', array($this, 'create_subscription'));
        add_action('wp_ajax_nopriv_dvp_create_subscription', array($this, 'unauthorized_access'));
        
        // Register webhook handler
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Unauthorized access handler
     */
    public function unauthorized_access() {
        wp_send_json_error(array(
            'message' => 'You must be logged in to create a subscription.'
        ));
    }
    
    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('domain-value-predictor/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Create a new subscription
     */
    public function create_subscription() {
        // Verify nonce and permissions
        if (!$this->validate_subscription_request()) {
            return;
        }
        
        // Get and validate parameters
        $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';
        $price_id = isset($_POST['price_id']) ? sanitize_text_field($_POST['price_id']) : '';
        
        if (empty($payment_method_id) || empty($price_id)) {
            wp_send_json_error(array(
                'message' => 'Payment method and price are required.'
            ));
            return;
        }
        
        // Get user details
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_send_json_error(array(
                'message' => 'Invalid user account.'
            ));
            return;
        }
        
        try {
            // Get or create customer
            $stripe_customer_id = $this->get_or_create_customer($user, $payment_method_id);
            if (!$stripe_customer_id) {
                throw new Exception('Failed to create or retrieve Stripe customer');
            }
            
            // Create subscription
            $subscription = $this->create_stripe_subscription($stripe_customer_id, $price_id, $user_id);
            
            // Return the subscription and client secret for confirmation
            wp_send_json_success(array(
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret,
                'status' => $subscription->status
            ));
            
        } catch (\Exception $e) {
            // Log error and return error message
            error_log('Stripe Error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => 'Error creating subscription: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Validate the subscription request
     * 
     * @return bool True if validation passes, false otherwise
     */
    private function validate_subscription_request() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dvp-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            return false;
        }
        
        // Make sure Stripe PHP library is loaded BEFORE any operation
        try {
            if (!class_exists('\Stripe\Stripe')) {
                $this->include_stripe_php();
                
                if (!class_exists('\Stripe\Stripe') || !class_exists('\Stripe\Customer') || !class_exists('\Stripe\Subscription')) {
                    throw new Exception('Stripe PHP library could not be loaded properly.');
                }
            }
            
            // Set the API key after confirming library is loaded
            \Stripe\Stripe::setApiKey($this->secret_key);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Stripe configuration error: ' . $e->getMessage()));
            return false;
        }
        
        // Check if Stripe keys are set
        if (empty($this->secret_key) || empty($this->publishable_key)) {
            wp_send_json_error(array(
                'message' => 'Stripe API keys are not configured. Please contact the administrator.'
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Get or create a Stripe customer
     * 
     * @param WP_User $user WordPress user object
     * @param string $payment_method_id Stripe payment method ID
     * @return string|false Customer ID or false on failure
     */
    private function get_or_create_customer($user, $payment_method_id) {
        $user_id = $user->ID;
        $stripe_customer_id = get_user_meta($user_id, 'dvp_stripe_customer_id', true);
        
        if (empty($stripe_customer_id)) {
            // Create new customer
            $customer = \Stripe\Customer::create([
                'email' => $user->user_email,
                'name' => $user->display_name,
                'payment_method' => $payment_method_id,
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);
            
            $stripe_customer_id = $customer->id;
            update_user_meta($user_id, 'dvp_stripe_customer_id', $stripe_customer_id);
        } else {
            // Update payment method for existing customer
            \Stripe\PaymentMethod::attach([
                'customer' => $stripe_customer_id,
                'payment_method' => $payment_method_id,
            ]);
            
            \Stripe\Customer::update($stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method_id,
                ],
            ]);
        }
        
        return $stripe_customer_id;
    }
    
    /**
     * Create a Stripe subscription
     * 
     * @param string $stripe_customer_id Stripe customer ID
     * @param string $price_id Stripe price ID
     * @param int $user_id WordPress user ID
     * @return \Stripe\Subscription Subscription object
     */
    private function create_stripe_subscription($stripe_customer_id, $price_id, $user_id) {
        // Create subscription
        $subscription = \Stripe\Subscription::create([
            'customer' => $stripe_customer_id,
            'items' => [
                ['price' => $price_id],
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);
        
        // Update user metadata with subscription info
        update_user_meta($user_id, 'dvp_stripe_subscription_id', $subscription->id);
        update_user_meta($user_id, 'dvp_subscription_status', $subscription->status);
        update_user_meta($user_id, 'dvp_subscription_price_id', $price_id);
        update_user_meta($user_id, 'dvp_subscription_created', current_time('mysql'));
        
        return $subscription;
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook($request) {
        // Verify Stripe webhook signature
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhook_secret = get_option('dvp_stripe_webhook_secret');
        
        if (empty($webhook_secret)) {
            return new WP_Error('webhook_error', 'Webhook secret is not configured', array('status' => 400));
        }
        
        try {
            // Include Stripe PHP library if needed
            if (!class_exists('\Stripe\Stripe')) {
                $this->include_stripe_php();
            }
            
            // Set Stripe API key
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $webhook_secret
            );
            
            // Handle the event
            switch ($event->type) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $subscription = $event->data->object;
                    $this->update_subscription_status($subscription);
                    break;
                    
                case 'customer.subscription.deleted':
                    $subscription = $event->data->object;
                    $this->cancel_subscription($subscription);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $invoice = $event->data->object;
                    $this->handle_invoice_paid($invoice);
                    break;
                    
                case 'invoice.payment_failed':
                    $invoice = $event->data->object;
                    $this->handle_invoice_failed($invoice);
                    break;
            }
            
            return new WP_REST_Response(array('success' => true), 200);
            
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return new WP_Error('webhook_error', 'Invalid payload', array('status' => 400));
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return new WP_Error('webhook_error', 'Invalid signature', array('status' => 400));
        } catch (\Exception $e) {
            // Generic error
            return new WP_Error('webhook_error', $e->getMessage(), array('status' => 400));
        }
    }
    
    /**
     * Update subscription status
     */
    private function update_subscription_status($subscription) {
        global $wpdb;
        
        // Get user by Stripe customer ID
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'dvp_stripe_customer_id' AND meta_value = %s LIMIT 1",
            $subscription->customer
        ));
        
        if (!$user_id) {
            return;
        }
        
        // Update subscription status
        update_user_meta($user_id, 'dvp_subscription_status', $subscription->status);
        update_user_meta($user_id, 'dvp_subscription_current_period_end', date('Y-m-d H:i:s', $subscription->current_period_end));
        
        // If subscription is active, ensure user has the correct role
        if ($subscription->status === 'active') {
            $user = get_user_by('id', $user_id);
            if ($user && !in_array('subscriber', $user->roles)) {
                $user->add_role('subscriber');
            }
        }
    }
    
    /**
     * Cancel subscription
     */
    private function cancel_subscription($subscription) {
        global $wpdb;
        
        // Get user by Stripe customer ID
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'dvp_stripe_customer_id' AND meta_value = %s LIMIT 1",
            $subscription->customer
        ));
        
        if (!$user_id) {
            return;
        }
        
        // Update subscription status
        update_user_meta($user_id, 'dvp_subscription_status', 'cancelled');
    }
    
    /**
     * Handle successful invoice payment
     */
    private function handle_invoice_paid($invoice) {
        if (empty($invoice->subscription)) {
            return;
        }
        
        global $wpdb;
        
        // Get user by Stripe customer ID
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'dvp_stripe_customer_id' AND meta_value = %s LIMIT 1",
            $invoice->customer
        ));
        
        if (!$user_id) {
            return;
        }
        
        // Update payment info
        update_user_meta($user_id, 'dvp_last_payment_date', current_time('mysql'));
        update_user_meta($user_id, 'dvp_last_payment_amount', $invoice->amount_paid / 100); // Convert from cents
        
        // Update subscription status to active
        update_user_meta($user_id, 'dvp_subscription_status', 'active');
    }
    
    /**
     * Handle failed invoice payment
     */
    private function handle_invoice_failed($invoice) {
        if (empty($invoice->subscription)) {
            return;
        }
        
        global $wpdb;
        
        // Get user by Stripe customer ID
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'dvp_stripe_customer_id' AND meta_value = %s LIMIT 1",
            $invoice->customer
        ));
        
        if (!$user_id) {
            return;
        }
        
        // Update subscription status
        update_user_meta($user_id, 'dvp_subscription_status', 'past_due');
        
        // Optional: Send email notification to user about failed payment
        $user = get_user_by('id', $user_id);
        if ($user) {
            $subject = 'Payment Failed for Domain Value Predictor Subscription';
            $message = "Dear {$user->display_name},\n\n";
            $message .= "We were unable to process your payment for your Domain Value Predictor subscription. ";
            $message .= "Please update your payment information to continue using our service.\n\n";
            $message .= "Thank you,\n";
            $message .= get_bloginfo('name');
            
            // Check if wp_mail function exists before calling it
            if (function_exists('wp_mail')) {
                wp_mail($user->user_email, $subject, $message);
            } else {
                error_log('Domain Value Predictor: wp_mail function not available for sending payment failure notification');
            }
        }
    }
    
    /**
     * Include Stripe PHP library
     */
    private function include_stripe_php() {
        // Check if Stripe library is already included
        if (class_exists('\Stripe\Stripe')) {
            return true;
        }
        
        // First check: Try to use Composer autoloader if available (recommended method)
        if (file_exists(DVP_PLUGIN_DIR . 'vendor/autoload.php')) {
            require_once DVP_PLUGIN_DIR . 'vendor/autoload.php';
            
            if (class_exists('\Stripe\Stripe')) {
                return true;
            }
        }
        
        // Second check: Look for the library in common WordPress plugin directories
        $possible_paths = array(
            // Our plugin directory
            DVP_PLUGIN_DIR . 'vendor/stripe-php/init.php',
            DVP_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php',
            // WP content directory
            WP_CONTENT_DIR . '/vendor/stripe/stripe-php/init.php',
            // Other common plugin locations
            WP_PLUGIN_DIR . '/woocommerce-gateway-stripe/includes/stripe-php/init.php',
            WP_PLUGIN_DIR . '/stripe/includes/lib/stripe-php/init.php',
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                if (class_exists('\Stripe\Stripe')) {
                    return true;
                }
            }
        }
        
        // Final fallback: Try to download the library directly
        return $this->download_stripe_library();
    }
    
    /**
     * Download and install Stripe PHP library
     */
    private function download_stripe_library() {
        // As a fallback, download the library
        $stripe_zip_url = 'https://github.com/stripe/stripe-php/archive/v9.10.0.zip';
        $download_path = DVP_PLUGIN_DIR . 'vendor/stripe-php.zip';
        $extract_path = DVP_PLUGIN_DIR . 'vendor/';
        
        // Create vendor directory if it doesn't exist
        if (!file_exists($extract_path)) {
            if (!mkdir($extract_path, 0755, true)) {
                error_log('Error creating vendor directory for Stripe');
                throw new Exception('Unable to create directory for Stripe SDK.');
            }
        }
        
        // Check if PHP ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            error_log('ZipArchive class not available. Cannot install Stripe SDK automatically.');
            throw new Exception('Your server does not support ZipArchive. Please install Stripe SDK manually or contact your hosting provider.');
        }
        
        // Download the Stripe PHP library
        $response = wp_remote_get($stripe_zip_url, array(
            'timeout' => 60,
            'stream' => true,
            'filename' => $download_path
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Error downloading Stripe PHP library: ' . $error_message);
            throw new Exception('Unable to download Stripe PHP library: ' . $error_message);
        }
        
        // Make sure the file was downloaded correctly
        if (!file_exists($download_path) || filesize($download_path) < 1000) {
            error_log('Stripe library download appears to have failed');
            throw new Exception('Stripe library could not be downloaded properly.');
        }
        
        // Extract the library
        $zip = new ZipArchive;
        $zip_result = $zip->open($download_path);
        if ($zip_result === true) {
            $result = $zip->extractTo($extract_path);
            $zip->close();
            
            if (!$result) {
                error_log('Error extracting files from Stripe PHP library zip');
                throw new Exception('Failed to extract Stripe PHP files.');
            }
            
            // Rename the extracted directory
            if (file_exists($extract_path . 'stripe-php-9.10.0')) {
                if (file_exists($extract_path . 'stripe-php')) {
                    // Remove old directory if it exists
                    $this->recursive_rmdir($extract_path . 'stripe-php');
                }
                
                if (!rename(
                    $extract_path . 'stripe-php-9.10.0',
                    $extract_path . 'stripe-php'
                )) {
                    error_log('Error renaming Stripe PHP directory');
                    throw new Exception('Failed to set up Stripe PHP directory.');
                }
            } else {
                error_log('Extracted Stripe directory not found: ' . $extract_path . 'stripe-php-9.10.0');
                throw new Exception('Stripe library extraction failed.');
            }
            
            // Clean up the zip file
            @unlink($download_path);
            
            // Include the library
            if (file_exists($extract_path . 'stripe-php/init.php')) {
                require_once $extract_path . 'stripe-php/init.php';
            } else {
                error_log('Stripe init.php not found after extraction');
                throw new Exception('Stripe library files are incomplete.');
            }
        } else {
            error_log('Error opening Stripe PHP library zip: ' . $zip_result);
            throw new Exception('Unable to extract Stripe PHP library (Code: ' . $zip_result . ').');
        }
    }
    
    /**
     * Recursively remove a directory
     */
    private function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                $this->recursive_rmdir("$dir/$file");
            } else {
                @unlink("$dir/$file");
            }
        }
        
        return @rmdir($dir);
    }
    
    /**
     * Get available subscription plans
     */
    public function get_subscription_plans() {
        // In a real implementation, these would be fetched from Stripe
        // For now, we'll return hardcoded plans
        
        return array(
            array(
                'id' => 'price_monthly',
                'name' => 'Monthly Subscription',
                'description' => 'Access to Domain Value Predictor for one month',
                'price' => 9.99,
                'interval' => 'month',
                'features' => array(
                    'Unlimited domain searches',
                    'Value prediction analytics',
                    'Domain availability checking',
                    'Registration recommendations'
                )
            ),
            array(
                'id' => 'price_yearly',
                'name' => 'Yearly Subscription',
                'description' => 'Access to Domain Value Predictor for one year',
                'price' => 99.99,
                'interval' => 'year',
                'features' => array(
                    'Unlimited domain searches',
                    'Value prediction analytics',
                    'Domain availability checking',
                    'Registration recommendations',
                    'Priority support',
                    'Advanced market trend analysis'
                )
            )
        );
    }
} 
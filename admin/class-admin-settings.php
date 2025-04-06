<?php
/**
 * Admin Settings Class
 * Handles plugin settings and admin interface
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===== IMPORTANT: NEVER USE WORDPRESS FUNCTIONS BEFORE THIS CHECK =====
// Skip execution ENTIRELY if WordPress isn't fully loaded
if (!function_exists('add_action') || !function_exists('add_filter') || !function_exists('get_option')) {
    return;
}

// Only define the class - don't instantiate it yet!
// This prevents any WordPress functions from being called during include
class DVP_Admin_Settings {
    /**
     * Static method to initialize the class
     * Call this method from a hook that runs after WordPress is fully loaded
     */
    public static function init() {
        // Double-check WordPress functions are available
        if (!function_exists('add_action') || !function_exists('add_filter')) {
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
     * Setup all hooks - This runs after WordPress is fully loaded
     */
    public function setup_hooks() {
        // Now it's safe to use WordPress functions
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_domain-value-predictor/domain-value-predictor.php', array($this, 'add_settings_link'));
        
        // Hook form validation
        add_action('admin_init', array($this, 'process_settings_form'));
        
        // Register admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Domain Value Predictor',
            'Domain Predictor',
            'manage_options',
            'domain-value-predictor',
            array($this, 'display_settings_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'domain-value-predictor',
            'Settings',
            'Settings',
            'manage_options',
            'domain-value-predictor',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'domain-value-predictor',
            'Subscriptions',
            'Subscriptions',
            'manage_options',
            'dvp-subscriptions',
            array($this, 'display_subscriptions_page')
        );
        
        add_submenu_page(
            'domain-value-predictor',
            'Analytics',
            'Analytics',
            'manage_options',
            'dvp-analytics',
            array($this, 'display_analytics_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register validation callback
        add_filter('pre_update_option_dvp_openai_api_key', array($this, 'validate_api_keys'), 10, 2);
        add_filter('pre_update_option_dvp_stripe_secret_key', array($this, 'validate_api_keys'), 10, 2);
        add_filter('pre_update_option_dvp_stripe_webhook_secret', array($this, 'validate_api_keys'), 10, 2);
        add_filter('pre_update_option_dvp_domain_api_key', array($this, 'validate_api_keys'), 10, 2);
        
        // Register settings section
        add_settings_section(
            'dvp_api_settings',
            'API Settings',
            array($this, 'api_settings_section_callback'),
            'domain-value-predictor'
        );
        
        add_settings_section(
            'dvp_stripe_settings',
            'Stripe Settings',
            array($this, 'stripe_settings_section_callback'),
            'domain-value-predictor'
        );
        
        add_settings_section(
            'dvp_general_settings',
            'General Settings',
            array($this, 'general_settings_section_callback'),
            'domain-value-predictor'
        );
        
        // Register OpenAI API settings
        register_setting(
            'domain-value-predictor',
            'dvp_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key'),
                'default' => ''
            )
        );
        
        add_settings_field(
            'dvp_openai_api_key',
            'OpenAI API Key',
            array($this, 'openai_api_key_callback'),
            'domain-value-predictor',
            'dvp_api_settings'
        );
        
        // Register Domain API settings
        register_setting(
            'domain-value-predictor',
            'dvp_domain_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key'),
                'default' => ''
            )
        );
        
        add_settings_field(
            'dvp_domain_api_key',
            'Domain API Key',
            array($this, 'domain_api_key_callback'),
            'domain-value-predictor',
            'dvp_api_settings'
        );
        
        // Register Stripe API settings
        register_setting(
            'domain-value-predictor',
            'dvp_stripe_publishable_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        add_settings_field(
            'dvp_stripe_publishable_key',
            'Stripe Publishable Key',
            array($this, 'stripe_publishable_key_callback'),
            'domain-value-predictor',
            'dvp_stripe_settings'
        );
        
        register_setting(
            'domain-value-predictor',
            'dvp_stripe_secret_key',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key'),
                'default' => ''
            )
        );
        
        add_settings_field(
            'dvp_stripe_secret_key',
            'Stripe Secret Key',
            array($this, 'stripe_secret_key_callback'),
            'domain-value-predictor',
            'dvp_stripe_settings'
        );
        
        register_setting(
            'domain-value-predictor',
            'dvp_stripe_webhook_secret',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_api_key'),
                'default' => ''
            )
        );
        
        add_settings_field(
            'dvp_stripe_webhook_secret',
            'Stripe Webhook Secret',
            array($this, 'stripe_webhook_secret_callback'),
            'domain-value-predictor',
            'dvp_stripe_settings'
        );
        
        // Register general settings
        register_setting(
            'domain-value-predictor',
            'dvp_results_per_search',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 10
            )
        );
        
        add_settings_field(
            'dvp_results_per_search',
            'Results Per Search',
            array($this, 'results_per_search_callback'),
            'domain-value-predictor',
            'dvp_general_settings'
        );
        
        register_setting(
            'domain-value-predictor',
            'dvp_default_timeframe',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'intval',
                'default' => 3
            )
        );
        
        add_settings_field(
            'dvp_default_timeframe',
            'Default Timeframe (months)',
            array($this, 'default_timeframe_callback'),
            'domain-value-predictor',
            'dvp_general_settings'
        );
    }
    
    /**
     * API settings section callback
     */
    public function api_settings_section_callback() {
        echo '<p>Configure the API keys required for domain prediction and checking.</p>';
    }
    
    /**
     * Stripe settings section callback
     */
    public function stripe_settings_section_callback() {
        echo '<p>Configure Stripe payment integration for subscription management.</p>';
    }
    
    /**
     * General settings section callback
     */
    public function general_settings_section_callback() {
        echo '<p>Configure general plugin settings.</p>';
    }
    
    /**
     * OpenAI API key field callback
     */
    public function openai_api_key_callback() {
        $api_key = get_option('dvp_openai_api_key');
        
        echo '<input type="password" id="dvp_openai_api_key" name="dvp_openai_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your OpenAI API key for domain value prediction.</p>';
    }
    
    /**
     * Domain API key field callback
     */
    public function domain_api_key_callback() {
        $api_key = get_option('dvp_domain_api_key');
        
        echo '<input type="password" id="dvp_domain_api_key" name="dvp_domain_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your Domain API key for checking domain availability.</p>';
    }
    
    /**
     * Stripe publishable key field callback
     */
    public function stripe_publishable_key_callback() {
        $api_key = get_option('dvp_stripe_publishable_key');
        
        echo '<input type="text" id="dvp_stripe_publishable_key" name="dvp_stripe_publishable_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your Stripe publishable key.</p>';
    }
    
    /**
     * Stripe secret key field callback
     */
    public function stripe_secret_key_callback() {
        $api_key = get_option('dvp_stripe_secret_key');
        
        echo '<input type="password" id="dvp_stripe_secret_key" name="dvp_stripe_secret_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your Stripe secret key.</p>';
    }
    
    /**
     * Stripe webhook secret field callback
     */
    public function stripe_webhook_secret_callback() {
        $webhook_secret = get_option('dvp_stripe_webhook_secret');
        
        echo '<input type="password" id="dvp_stripe_webhook_secret" name="dvp_stripe_webhook_secret" value="' . esc_attr($webhook_secret) . '" class="regular-text" />';
        echo '<p class="description">Enter your Stripe webhook signing secret.</p>';
    }
    
    /**
     * Results per search field callback
     */
    public function results_per_search_callback() {
        $results_per_search = get_option('dvp_results_per_search', 10);
        
        echo '<input type="number" id="dvp_results_per_search" name="dvp_results_per_search" value="' . esc_attr($results_per_search) . '" class="small-text" min="1" max="20" />';
        echo '<p class="description">Number of domain suggestions to show per search.</p>';
    }
    
    /**
     * Default timeframe field callback
     */
    public function default_timeframe_callback() {
        $default_timeframe = get_option('dvp_default_timeframe', 3);
        
        echo '<input type="number" id="dvp_default_timeframe" name="dvp_default_timeframe" value="' . esc_attr($default_timeframe) . '" class="small-text" min="1" max="24" />';
        echo '<p class="description">Default timeframe in months for domain value prediction.</p>';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        // Ensure user has permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('domain-value-predictor');
                do_settings_sections('domain-value-predictor');
                wp_nonce_field('domain_value_predictor_settings', 'dvp_settings_nonce');
                submit_button('Save Settings');
                ?>
            </form>
            
            <div class="dvp-admin-info-box">
                <h2>Plugin Information</h2>
                <p>Domain Value Predictor uses AI to help your users discover potentially profitable domain names.</p>
                <p>To get started:</p>
                <ol>
                    <li>Enter your OpenAI API key above</li>
                    <li>Configure your Stripe API keys for subscription processing</li>
                    <li>Add the <code>[subscription_form]</code> shortcode to a page for users to subscribe</li>
                    <li>Add the <code>[domain_predictor]</code> shortcode to a page where subscribed users can search for domains</li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display subscriptions page
     */
    public function display_subscriptions_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get subscribers
        $subscribers = $this->get_subscribers();
        
        // Count active subscriptions safely
        $active_subscriptions = 0;
        foreach ($subscribers as $sub) {
            if (isset($sub['status']) && $sub['status'] === 'active') {
                $active_subscriptions++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="subscription-stats">
                <div class="stats-box">
                    <h3>Active Subscriptions</h3>
                    <p class="stat-number"><?php echo esc_html($active_subscriptions); ?></p>
                </div>
                <div class="stats-box">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">$<?php echo esc_html($this->calculate_total_revenue($subscribers)); ?></p>
                </div>
                <div class="stats-box">
                    <h3>Average Subscription Age</h3>
                    <p class="stat-number"><?php echo esc_html($this->calculate_avg_subscription_age($subscribers)); ?> days</p>
                </div>
            </div>
            
            <h2>Subscribers</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Subscription Plan</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Last Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscribers)): ?>
                        <tr>
                            <td colspan="7">No subscribers found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscribers as $subscriber): ?>
                            <tr>
                                <td><?php echo esc_html($subscriber['name']); ?></td>
                                <td><?php echo esc_html($subscriber['email']); ?></td>
                                <td><?php echo esc_html($subscriber['plan']); ?></td>
                                <td>
                                    <span class="subscription-status status-<?php echo esc_attr($subscriber['status']); ?>">
                                        <?php echo esc_html(ucfirst($subscriber['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($subscriber['start_date']); ?></td>
                                <td><?php echo esc_html($subscriber['last_payment']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $subscriber['user_id'])); ?>" class="button button-small">Edit User</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .subscription-stats {
                display: flex;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }
            
            .stats-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 15px;
                margin-right: 20px;
                margin-bottom: 20px;
                min-width: 200px;
                text-align: center;
            }
            
            .stats-box h3 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin: 0;
            }
            
            .subscription-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
            }
            
            .status-active {
                background-color: #dff0d8;
                color: #3c763d;
            }
            
            .status-cancelled {
                background-color: #f2dede;
                color: #a94442;
            }
            
            .status-past_due {
                background-color: #fcf8e3;
                color: #8a6d3b;
            }
        </style>
        <?php
    }
    
    /**
     * Display analytics page
     */
    public function display_analytics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get search statistics
        $search_stats = $this->get_search_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="analytics-stats">
                <div class="stats-box">
                    <h3>Total Searches</h3>
                    <p class="stat-number"><?php echo esc_html($search_stats['total_searches']); ?></p>
                </div>
                <div class="stats-box">
                    <h3>Unique Users</h3>
                    <p class="stat-number"><?php echo esc_html($search_stats['unique_users']); ?></p>
                </div>
                <div class="stats-box">
                    <h3>Average Searches Per User</h3>
                    <p class="stat-number"><?php echo esc_html($search_stats['avg_searches_per_user']); ?></p>
                </div>
            </div>
            
            <h2>Popular Niches</h2>
            <?php if (empty($search_stats['top_niches_labels'])): ?>
                <div class="dvp-notice dvp-info">
                    <p>No niche data available yet. It will appear here after users perform searches.</p>
                </div>
            <?php else: ?>
                <div class="analytics-chart">
                    <canvas id="niches-chart" width="800" height="400"></canvas>
                    <div class="dvp-chart-fallback" style="display:none;">
                        <h3>Top Niches:</h3>
                        <ul>
                            <?php foreach ($search_stats['top_niches_labels'] as $index => $niche): ?>
                                <li><?php echo esc_html($niche); ?>: <?php echo esc_html($search_stats['top_niches_data'][$index]); ?> searches</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <h2>Recent Searches</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Niche</th>
                        <th>Timeframe</th>
                        <th>Keywords</th>
                        <th>Date</th>
                        <th>Domains Found</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($search_stats['recent_searches'])): ?>
                        <tr>
                            <td colspan="6">No searches found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($search_stats['recent_searches'] as $search): ?>
                            <tr>
                                <td><?php echo esc_html($search['user']); ?></td>
                                <td><?php echo esc_html($search['niche']); ?></td>
                                <td><?php echo esc_html($search['timeframe']); ?> months</td>
                                <td><?php echo esc_html($search['keywords']); ?></td>
                                <td><?php echo esc_html($search['date']); ?></td>
                                <td><?php echo esc_html($search['domains_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .analytics-stats {
                display: flex;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }
            
            .stats-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 15px;
                margin-right: 20px;
                margin-bottom: 20px;
                min-width: 200px;
                text-align: center;
            }
            
            .stats-box h3 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin: 0;
            }
            
            .analytics-chart {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .dvp-chart-error {
                padding: 15px;
                color: #721c24;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                margin-top: 15px;
            }
            
            .dvp-notice {
                padding: 12px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .dvp-info {
                color: #004085;
                background-color: #cce5ff;
                border: 1px solid #b8daff;
            }
        </style>
        
        <script>
            jQuery(document).ready(function() {
                // Check if Chart.js is loaded
                if (typeof Chart !== 'undefined') {
                    var ctx = document.getElementById('niches-chart').getContext('2d');
                    var nichesChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($search_stats['top_niches_labels']); ?>,
                            datasets: [{
                                label: 'Number of Searches',
                                data: <?php echo json_encode($search_stats['top_niches_data']); ?>,
                                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Show fallback if Chart.js isn't available
                    jQuery('.dvp-chart-fallback').show();
                    jQuery('#niches-chart').hide();
                }
            });
        </script>
        <?php
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=domain-value-predictor">' . __('Settings', 'domain-value-predictor') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Get subscribers for admin display
     */
    private function get_subscribers() {
        global $wpdb;
        
        $subscribers = array();
        
        // Get users with subscription metadata
        $users_with_subs = $wpdb->get_results(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'dvp_subscription_status' LIMIT 100"
        );
        
        if (empty($users_with_subs)) {
            return $subscribers;
        }
        
        foreach ($users_with_subs as $user_row) {
            $user_id = $user_row->user_id;
            $user = get_userdata($user_id);
            
            if (!$user) {
                continue;
            }
            
            $status = get_user_meta($user_id, 'dvp_subscription_status', true);
            $price_id = get_user_meta($user_id, 'dvp_subscription_price_id', true);
            $created = get_user_meta($user_id, 'dvp_subscription_created', true);
            $last_payment_date = get_user_meta($user_id, 'dvp_last_payment_date', true);
            
            // Determine plan name
            $plan_name = 'Unknown';
            if ($price_id === 'price_monthly') {
                $plan_name = 'Monthly ($9.99)';
            } elseif ($price_id === 'price_yearly') {
                $plan_name = 'Yearly ($99.99)';
            }
            
            $subscribers[] = array(
                'user_id' => $user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'plan' => $plan_name,
                'status' => $status,
                'start_date' => !empty($created) ? $created : 'N/A',
                'last_payment' => !empty($last_payment_date) ? $last_payment_date : 'N/A'
            );
        }
        
        return $subscribers;
    }
    
    /**
     * Calculate total revenue
     */
    private function calculate_total_revenue($subscribers) {
        $total = 0;
        
        if (!is_array($subscribers)) {
            error_log('Domain Value Predictor: Invalid subscribers data in revenue calculation');
            return number_format($total, 2);
        }
        
        foreach ($subscribers as $subscriber) {
            if (isset($subscriber['status']) && $subscriber['status'] === 'active' && isset($subscriber['plan'])) {
                if (strpos($subscriber['plan'], 'Monthly') !== false) {
                    $total += 9.99;
                } elseif (strpos($subscriber['plan'], 'Yearly') !== false) {
                    $total += 99.99;
                }
            }
        }
        
        return number_format($total, 2);
    }
    
    /**
     * Calculate average subscription age
     */
    private function calculate_avg_subscription_age($subscribers) {
        $total_days = 0;
        $count = 0;
        
        if (!is_array($subscribers)) {
            error_log('Domain Value Predictor: Invalid subscribers data in age calculation');
            return 0;
        }
        
        try {
            foreach ($subscribers as $subscriber) {
                if (isset($subscriber['status']) && $subscriber['status'] === 'active' && 
                    isset($subscriber['start_date']) && $subscriber['start_date'] !== 'N/A') {
                    try {
                        $start_date = new DateTime($subscriber['start_date']);
                        $now = new DateTime();
                        $interval = $now->diff($start_date);
                        $total_days += $interval->days;
                        $count++;
                    } catch (Exception $e) {
                        error_log('Domain Value Predictor: Error calculating subscription age: ' . $e->getMessage());
                        // Continue with next subscriber if there's an error with this one
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Domain Value Predictor: Error in calculate_avg_subscription_age: ' . $e->getMessage());
        }
        
        return $count > 0 ? round($total_days / $count) : 0;
    }
    
    /**
     * Get search statistics
     */
    private function get_search_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dvp_predictions';
        
        // Default stats
        $stats = array(
            'total_searches' => 0,
            'unique_users' => 0,
            'avg_searches_per_user' => 0,
            'top_niches_labels' => array(),
            'top_niches_data' => array(),
            'recent_searches' => array()
        );
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('Domain Value Predictor: Predictions table does not exist.');
            return $stats;
        }
        
        // Get total searches (use try/catch to handle DB errors)
        try {
            $stats['total_searches'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // Get unique users
            $stats['unique_users'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
            
            // Calculate average searches per user
            $stats['avg_searches_per_user'] = $stats['unique_users'] > 0 ? round($stats['total_searches'] / $stats['unique_users'], 1) : 0;
            
            // Get top niches
            $niches = array();
            $predictions = $wpdb->get_results("SELECT search_params FROM $table_name");
            
            if ($predictions && !empty($predictions)) {
                foreach ($predictions as $prediction) {
                    $search_params = json_decode($prediction->search_params, true);
                    if (isset($search_params['niche'])) {
                        $niche = strtolower($search_params['niche']);
                        if (!isset($niches[$niche])) {
                            $niches[$niche] = 0;
                        }
                        $niches[$niche]++;
                    }
                }
                
                // Sort niches by popularity
                arsort($niches);
                
                // Get top 10 niches
                $niches = array_slice($niches, 0, 10);
                
                $stats['top_niches_labels'] = array_keys($niches);
                $stats['top_niches_data'] = array_values($niches);
            }
            
            // Get recent searches
            $recent = $wpdb->get_results(
                "SELECT p.*, u.display_name, u.user_email 
                FROM $table_name p 
                LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
                ORDER BY p.created_at DESC 
                LIMIT 10"
            );
            
            if ($recent && !empty($recent)) {
                foreach ($recent as $search) {
                    $search_params = json_decode($search->search_params, true);
                    $domains = json_decode($search->domains, true);
                    
                    $stats['recent_searches'][] = array(
                        'user' => isset($search->display_name) ? $search->display_name : 'Unknown',
                        'niche' => isset($search_params['niche']) ? $search_params['niche'] : 'Unknown',
                        'timeframe' => isset($search_params['timeframe']) ? $search_params['timeframe'] : 'N/A',
                        'keywords' => isset($search_params['keywords']) ? $search_params['keywords'] : 'None',
                        'date' => $search->created_at,
                        'domains_count' => is_array($domains) ? count($domains) : 0
                    );
                }
            }
        } catch (Exception $e) {
            error_log('Domain Value Predictor: Error retrieving search statistics: ' . $e->getMessage());
        }
        
        return $stats;
    }

    /**
     * Process settings form submission
     */
    public function process_settings_form() {
        // Only process when our settings form is submitted
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'domain-value-predictor') {
            // Verify the nonce
            if (!isset($_POST['dvp_settings_nonce']) || !wp_verify_nonce($_POST['dvp_settings_nonce'], 'domain_value_predictor_settings')) {
                add_settings_error('domain-value-predictor', 'invalid-nonce', 'Security validation failed. Please try again.');
                return;
            }
            
            // Ensure user has permission
            if (!current_user_can('manage_options')) {
                add_settings_error('domain-value-predictor', 'insufficient-permissions', 'You do not have permission to modify these settings.');
                return;
            }
            
            // Additional validation can be added here
            
            // Only add the success message if no errors have been added
            $settings_errors = get_settings_errors('domain-value-predictor');
            if (empty($settings_errors)) {
                add_settings_error('domain-value-predictor', 'settings-updated', 'Settings saved.', 'updated');
            }
        }
    }
    
    /**
     * Sanitize API key
     * 
     * @param string $key The API key to sanitize
     * @return string Sanitized API key
     */
    public function sanitize_api_key($key) {
        // Basic sanitization
        $key = sanitize_text_field($key);
        return $key;
    }

    /**
     * Validate API keys
     * 
     * @param string $new_value The new value of the option
     * @param string $old_value The old value of the option
     * @return string Sanitized API key
     */
    public function validate_api_keys($new_value, $old_value) {
        // Ensure user has permission
        if (!current_user_can('manage_options')) {
            return $old_value;
        }
        
        // Sanitize the new value
        $sanitized_value = $this->sanitize_api_key($new_value);
        
        // Log sanitization without exposing the key (safely handle short keys)
        if ($sanitized_value !== $old_value && !empty($sanitized_value)) {
            $key_length = strlen($sanitized_value);
            
            // Make sure we don't get an index error with short keys
            if ($key_length > 8) {
                $masked_value = substr($sanitized_value, 0, 4) . str_repeat('*', $key_length - 8) . substr($sanitized_value, -4);
            } else if ($key_length > 4) {
                $masked_value = substr($sanitized_value, 0, 2) . str_repeat('*', $key_length - 4) . substr($sanitized_value, -2);
            } else if ($key_length > 0) {
                $masked_value = substr($sanitized_value, 0, 1) . str_repeat('*', $key_length - 1);
            } else {
                $masked_value = '(empty)';
            }
            
            error_log('Domain Value Predictor: API key updated. Masked key: ' . $masked_value);
        }
        
        return $sanitized_value;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our plugin pages
        if (strpos($hook, 'dvp-analytics') !== false) {
            // Register and enqueue Chart.js
            wp_register_script('dvp-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array('jquery'), '3.9.1', true);
            wp_enqueue_script('dvp-chartjs');
            
            // Add inline script to handle Chart.js loading errors
            wp_add_inline_script('dvp-chartjs', '
                jQuery(document).ready(function($) {
                    if (typeof Chart === "undefined") {
                        console.error("Chart.js failed to load. Falling back to text display.");
                        $("#niches-chart").replaceWith("<div class=\"dvp-chart-error\"><p>Chart display unavailable. Please check your internet connection or contact the administrator.</p></div>");
                    }
                });
            ');
        }
    }
} 
<?php
/**
 * API Handler Class
 * Manages external API requests and caching
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===== IMPORTANT: NEVER USE WORDPRESS FUNCTIONS BEFORE THIS CHECK =====
// Skip execution ENTIRELY if WordPress isn't fully loaded
if (!function_exists('add_action') || !function_exists('wp_remote_get') || !function_exists('set_transient')) {
    return;
}

class DVP_API_Handler {

    /**
     * API request cache expiration time in seconds
     */
    private $cache_expiration = 3600; // 1 hour
    
    /**
     * Static initializer to safely create the class instance
     */
    public static function init() {
        // Double-check WordPress functions are available
        if (!function_exists('set_transient') || !function_exists('wp_remote_get')) {
            return false;
        }
        
        $instance = new self();
        return $instance;
    }
    
    /**
     * Constructor - no WordPress functions here!
     */
    private function __construct() {
        // Intentionally empty - no WordPress functions allowed here
    }
    
    /**
     * Make a GET request to an external API with caching
     */
    public function get($url, $args = array(), $cache_key = '') {
        // Generate cache key if not provided
        if (empty($cache_key)) {
            $cache_key = 'dvp_api_' . md5($url . serialize($args));
        }
        
        // Try to get cached response
        $cached_response = get_transient($cache_key);
        
        if ($cached_response !== false) {
            return $cached_response;
        }
        
        // Make the request
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            error_log('API GET Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            error_log('API GET Error: Unexpected response code ' . $response_code);
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Cache the response
        set_transient($cache_key, $response_data, $this->cache_expiration);
        
        return $response_data;
    }
    
    /**
     * Make a POST request to an external API
     */
    public function post($url, $args = array()) {
        // Make the request
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('API POST Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code < 200 || $response_code >= 300) {
            error_log('API POST Error: Unexpected response code ' . $response_code);
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        return $response_data;
    }
    
    /**
     * Clear all API cache
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_dvp_api_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_timeout_dvp_api_%'
            )
        );
    }
    
    /**
     * Clear specific API cache
     */
    public function clear_specific_cache($cache_key) {
        delete_transient($cache_key);
    }
    
    /**
     * Get trending keywords for domain suggestions
     */
    public function get_trending_keywords() {
        // In a real implementation, this would fetch from an actual API
        // For now, we'll return a hardcoded list
        
        $cache_key = 'dvp_trending_keywords';
        $cached_keywords = get_transient($cache_key);
        
        if ($cached_keywords !== false) {
            return $cached_keywords;
        }
        
        // Simulate API request delay
        usleep(500000); // 0.5 seconds
        
        $trending_keywords = array(
            'crypto',
            'ai',
            'nft',
            'metaverse',
            'defi',
            'blockchain',
            'saas',
            'fintech',
            'ecommerce',
            'healthtech',
            'edtech',
            'sustainability',
            'remote',
            'virtual',
            'digital',
            'cloud',
            'security',
            'analytics',
            'automation',
            'streaming'
        );
        
        // Cache the keywords
        set_transient($cache_key, $trending_keywords, 86400); // 24 hours
        
        return $trending_keywords;
    }
    
    /**
     * Get domain market trends
     */
    public function get_domain_market_trends() {
        // In a real implementation, this would fetch from an actual API
        // For now, we'll return a hardcoded set of trends
        
        $cache_key = 'dvp_domain_market_trends';
        $cached_trends = get_transient($cache_key);
        
        if ($cached_trends !== false) {
            return $cached_trends;
        }
        
        // Simulate API request delay
        usleep(800000); // 0.8 seconds
        
        $market_trends = array(
            array(
                'category' => 'Technology',
                'growth_rate' => 15.2,
                'popularity' => 'High',
                'trending_tlds' => array('.ai', '.tech', '.io'),
                'trending_keywords' => array('ai', 'tech', 'data', 'cloud', 'cyber'),
                'avg_sale_price' => 4250.00
            ),
            array(
                'category' => 'Finance',
                'growth_rate' => 12.8,
                'popularity' => 'High',
                'trending_tlds' => array('.finance', '.bank', '.money'),
                'trending_keywords' => array('crypto', 'defi', 'fintech', 'pay', 'wallet'),
                'avg_sale_price' => 5680.00
            ),
            array(
                'category' => 'Health',
                'growth_rate' => 10.5,
                'popularity' => 'Medium',
                'trending_tlds' => array('.health', '.care', '.med'),
                'trending_keywords' => array('health', 'wellness', 'medical', 'care', 'bio'),
                'avg_sale_price' => 3870.00
            ),
            array(
                'category' => 'E-commerce',
                'growth_rate' => 14.3,
                'popularity' => 'High',
                'trending_tlds' => array('.shop', '.store', '.market'),
                'trending_keywords' => array('shop', 'buy', 'store', 'cart', 'market'),
                'avg_sale_price' => 4120.00
            ),
            array(
                'category' => 'Entertainment',
                'growth_rate' => 9.7,
                'popularity' => 'Medium',
                'trending_tlds' => array('.media', '.tv', '.stream'),
                'trending_keywords' => array('stream', 'play', 'watch', 'game', 'entertainment'),
                'avg_sale_price' => 3540.00
            )
        );
        
        // Cache the trends
        set_transient($cache_key, $market_trends, 86400); // 24 hours
        
        return $market_trends;
    }
    
    /**
     * Get TLD performance data
     */
    public function get_tld_performance() {
        // In a real implementation, this would fetch from an actual API
        // For now, we'll return a hardcoded set of TLD performance data
        
        $cache_key = 'dvp_tld_performance';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Simulate API request delay
        usleep(600000); // 0.6 seconds
        
        $tld_performance = array(
            array(
                'tld' => '.com',
                'market_share' => 37.6,
                'avg_price' => 12.99,
                'growth_rate' => 5.2,
                'value_rating' => 4.8
            ),
            array(
                'tld' => '.net',
                'market_share' => 8.3,
                'avg_price' => 12.99,
                'growth_rate' => 3.1,
                'value_rating' => 4.2
            ),
            array(
                'tld' => '.org',
                'market_share' => 7.4,
                'avg_price' => 12.99,
                'growth_rate' => 2.9,
                'value_rating' => 4.0
            ),
            array(
                'tld' => '.io',
                'market_share' => 3.2,
                'avg_price' => 39.99,
                'growth_rate' => 12.5,
                'value_rating' => 4.7
            ),
            array(
                'tld' => '.ai',
                'market_share' => 2.1,
                'avg_price' => 59.99,
                'growth_rate' => 24.7,
                'value_rating' => 4.9
            ),
            array(
                'tld' => '.co',
                'market_share' => 4.5,
                'avg_price' => 29.99,
                'growth_rate' => 8.3,
                'value_rating' => 4.4
            ),
            array(
                'tld' => '.me',
                'market_share' => 2.7,
                'avg_price' => 19.99,
                'growth_rate' => 6.8,
                'value_rating' => 4.1
            ),
            array(
                'tld' => '.tech',
                'market_share' => 1.9,
                'avg_price' => 49.99,
                'growth_rate' => 15.2,
                'value_rating' => 4.5
            ),
            array(
                'tld' => '.app',
                'market_share' => 2.4,
                'avg_price' => 14.99,
                'growth_rate' => 16.7,
                'value_rating' => 4.6
            ),
            array(
                'tld' => '.dev',
                'market_share' => 1.8,
                'avg_price' => 14.99,
                'growth_rate' => 17.3,
                'value_rating' => 4.6
            )
        );
        
        // Cache the data
        set_transient($cache_key, $tld_performance, 86400); // 24 hours
        
        return $tld_performance;
    }
} 
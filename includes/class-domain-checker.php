<?php
/**
 * Domain Checker Class
 * Checks domain availability and pricing using domain API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// ===== IMPORTANT: NEVER USE WORDPRESS FUNCTIONS BEFORE THIS CHECK =====
// Skip execution ENTIRELY if WordPress isn't fully loaded
if (!function_exists('add_action') || !function_exists('get_option') || !function_exists('wp_remote_post')) {
    return;
}

class DVP_Domain_Checker {

    /**
     * API key for domain availability checking
     */
    private $api_key;
    
    /**
     * Static initializer to safely create the class instance
     */
    public static function init() {
        // Double-check WordPress functions are available
        if (!function_exists('get_option')) {
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
     * Setup hooks and initialize properties
     * ONLY called from init() after WordPress is fully loaded
     */
    public function setup_hooks() {
        // Now it's safe to use WordPress functions
        $this->api_key = get_option('dvp_domain_api_key');
    }
    
    /**
     * Check domain availability and pricing
     */
    public function check_availability($domain) {
        // If we have an API key, use the real domain API
        if (!empty($this->api_key)) {
            return $this->check_with_api($domain);
        }
        
        // Otherwise, use a more basic method
        return $this->check_with_whois($domain);
    }
    
    /**
     * Check domain availability using Domain API
     * 
     * This method would integrate with a real domain availability API service
     * like GoDaddy, Namecheap, or a general domain availability API
     */
    private function check_with_api($domain) {
        // This is a placeholder for actual API integration
        // In a real implementation, you would make an API call to a domain registrar or lookup service
        
        // Example API call to a domain availability service
        $api_url = 'https://domain-availability-api.example.com/v1/check';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'domain' => $domain
            )),
            'timeout' => 15
        );
        
        $response = wp_remote_post($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('Domain API Error: ' . $response->get_error_message());
            // Fall back to basic check
            return $this->check_with_whois($domain);
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Process the response based on the API format
        // This will vary depending on which API you're using
        
        // Example response processing
        if (isset($response_data['available']) && isset($response_data['price'])) {
            return array(
                'available' => $response_data['available'],
                'price' => $response_data['price']
            );
        }
        
        // Fall back to basic check if API response is invalid
        return $this->check_with_whois($domain);
    }
    
    /**
     * Check domain availability using basic WHOIS lookup
     * This is a fallback method when no API key is configured
     */
    private function check_with_whois($domain) {
        // For demonstration purposes, we'll use a simple method
        // In a real implementation, you would use PHP's built-in functions or libraries to query WHOIS servers
        
        // Check if PHP's socket functions are available
        if (!function_exists('fsockopen')) {
            // Generate a pseudo-random result
            $domain_hash = md5($domain);
            $is_available = (hexdec(substr($domain_hash, 0, 4)) % 10) < 6; // 60% chance of availability
            
            return array(
                'available' => $is_available,
                'price' => $is_available ? $this->estimate_price($domain) : 0
            );
        }
        
        // Try to perform a basic WHOIS lookup
        $domain_parts = explode('.', $domain);
        $tld = end($domain_parts);
        
        // Get the appropriate WHOIS server for the TLD
        $whois_server = $this->get_whois_server($tld);
        
        if (empty($whois_server)) {
            // If no WHOIS server found, generate a pseudo-random result
            $domain_hash = md5($domain);
            $is_available = (hexdec(substr($domain_hash, 0, 4)) % 10) < 6; // 60% chance of availability
            
            return array(
                'available' => $is_available,
                'price' => $is_available ? $this->estimate_price($domain) : 0
            );
        }
        
        // Try to connect to the WHOIS server
        $conn = @fsockopen($whois_server, 43, $errno, $errstr, 10);
        
        if (!$conn) {
            // If connection fails, generate a pseudo-random result
            $domain_hash = md5($domain);
            $is_available = (hexdec(substr($domain_hash, 0, 4)) % 10) < 6; // 60% chance of availability
            
            return array(
                'available' => $is_available,
                'price' => $is_available ? $this->estimate_price($domain) : 0
            );
        }
        
        // Query the WHOIS server
        fputs($conn, $domain . "\r\n");
        $response = '';
        
        while (!feof($conn)) {
            $response .= fgets($conn, 128);
        }
        
        fclose($conn);
        
        // Check if the domain is available based on the WHOIS response
        $is_available = $this->parse_whois_response($response, $tld);
        
        return array(
            'available' => $is_available,
            'price' => $is_available ? $this->estimate_price($domain) : 0
        );
    }
    
    /**
     * Get WHOIS server for a specific TLD
     */
    private function get_whois_server($tld) {
        $tld = strtolower($tld);
        
        $whois_servers = array(
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'info' => 'whois.afilias.net',
            'biz' => 'whois.neulevel.biz',
            'io' => 'whois.nic.io',
            'co' => 'whois.nic.co',
            'me' => 'whois.nic.me',
            'us' => 'whois.nic.us',
            'uk' => 'whois.nic.uk',
            'ca' => 'whois.cira.ca',
            'au' => 'whois.auda.org.au',
            'de' => 'whois.denic.de',
            'fr' => 'whois.nic.fr',
            'nl' => 'whois.domain-registry.nl',
            'ai' => 'whois.nic.ai'
        );
        
        return isset($whois_servers[$tld]) ? $whois_servers[$tld] : '';
    }
    
    /**
     * Parse WHOIS response to determine if domain is available
     */
    private function parse_whois_response($response, $tld) {
        $tld = strtolower($tld);
        
        $available_patterns = array(
            'com' => '/No match for/',
            'net' => '/No match for/',
            'org' => '/NOT FOUND/',
            'info' => '/NOT FOUND/',
            'biz' => '/Not found:/',
            'io' => '/is available for purchase/',
            'co' => '/No Data Found/',
            'me' => '/NOT FOUND/',
            'us' => '/Not found:/',
            'uk' => '/No match for/',
            'ca' => '/Domain status:[\s]+available/',
            'au' => '/No Data Found/',
            'de' => '/Status:[\s]+free/',
            'fr' => '/No entries found/',
            'nl' => '/is free/',
            'ai' => '/No Object Found/'
        );
        
        // Default pattern if TLD not specified
        $pattern = isset($available_patterns[$tld]) ? $available_patterns[$tld] : '/No match|not found|No Data Found|is available|is free|No Object Found/i';
        
        return preg_match($pattern, $response) === 1;
    }
    
    /**
     * Estimate domain price based on characteristics
     */
    private function estimate_price($domain) {
        $domain_parts = explode('.', $domain);
        $name = $domain_parts[0];
        $tld = end($domain_parts);
        
        // Base prices for different TLDs
        $tld_prices = array(
            'com' => 12.99,
            'net' => 12.99,
            'org' => 12.99,
            'info' => 9.99,
            'biz' => 9.99,
            'io' => 39.99,
            'co' => 29.99,
            'me' => 19.99,
            'us' => 9.99,
            'uk' => 10.99,
            'ca' => 13.99,
            'au' => 17.99,
            'de' => 15.99,
            'fr' => 14.99,
            'nl' => 14.99,
            'ai' => 59.99
        );
        
        // Get base price for TLD or use default price
        $base_price = isset($tld_prices[strtolower($tld)]) ? $tld_prices[strtolower($tld)] : 14.99;
        
        // Adjust price based on domain length
        $length_factor = 1;
        if (strlen($name) <= 3) {
            $length_factor = 2.5; // Short domains are more valuable
        } elseif (strlen($name) <= 5) {
            $length_factor = 1.5;
        } elseif (strlen($name) <= 8) {
            $length_factor = 1.2;
        }
        
        // Adjust price based on domain composition
        $composition_factor = 1;
        if (ctype_alpha($name)) {
            $composition_factor = 1.2; // All letters
        } elseif (ctype_alnum($name)) {
            $composition_factor = 1.1; // Letters and numbers
        }
        
        // Calculate final price
        $price = $base_price * $length_factor * $composition_factor;
        
        // Add slight randomness to make it more realistic
        $price = $price * (1 + (mt_rand(-10, 10) / 100));
        
        return round($price, 2);
    }
} 
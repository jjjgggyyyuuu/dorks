<?php
/**
 * Domain Predictor Class
 * Handles domain value prediction logic using OpenAI API
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

class DVP_Domain_Predictor {

    /**
     * OpenAI API key
     */
    private $api_key;
    
    /**
     * Domain API handler
     */
    private $domain_checker;
    
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
        $this->api_key = get_option('dvp_openai_api_key');
        
        // Get domain checker class - only if it exists
        if (class_exists('DVP_Domain_Checker')) {
            $this->domain_checker = DVP_Domain_Checker::init();
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_dvp_predict_domains', array($this, 'predict_domains'));
        add_action('wp_ajax_nopriv_dvp_predict_domains', array($this, 'unauthorized_access'));
    }
    
    /**
     * Unauthorized access handler
     */
    public function unauthorized_access() {
        wp_send_json_error(array(
            'message' => 'You must be logged in and have an active subscription to use this feature.'
        ));
    }
    
    /**
     * Predict valuable domains based on user parameters
     */
    public function predict_domains() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dvp-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        // Check if user has an active subscription
        if (!$this->user_has_subscription()) {
            wp_send_json_error(array(
                'message' => 'You need an active subscription to use this feature.'
            ));
        }
        
        // Get and validate parameters
        $niche = isset($_POST['niche']) ? sanitize_text_field($_POST['niche']) : '';
        $timeframe = isset($_POST['timeframe']) ? intval($_POST['timeframe']) : 3;
        $budget = isset($_POST['budget']) ? floatval($_POST['budget']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        
        if (empty($niche)) {
            wp_send_json_error(array('message' => 'Please specify a niche.'));
        }
        
        // Prepare data for AI analysis
        $prediction_data = array(
            'niche' => $niche,
            'timeframe' => $timeframe,
            'budget' => $budget,
            'keywords' => $keywords,
        );
        
        try {
            // Generate domain suggestions using OpenAI
            $domains = $this->generate_domain_suggestions($prediction_data);
            
            if (empty($domains)) {
                wp_send_json_error(array(
                    'message' => 'No domain suggestions could be generated. Please try different criteria.'
                ));
                return;
            }
            
            // Check domain availability and pricing
            $domain_results = $this->check_domains_availability($domains);
            
            // Store the prediction results
            $this->store_prediction_results($prediction_data, $domain_results);
            
            wp_send_json_success(array(
                'domains' => $domain_results,
                'message' => 'Successfully generated domain suggestions.'
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Check if user has an active subscription
     */
    private function user_has_subscription() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $subscription_status = get_user_meta($user_id, 'dvp_subscription_status', true);
        
        return $subscription_status === 'active';
    }
    
    /**
     * Generate domain suggestions using OpenAI
     */
    private function generate_domain_suggestions($data) {
        if (empty($this->api_key)) {
            error_log('OpenAI API key is missing');
            throw new Exception('OpenAI API key is missing. Please add it in the plugin settings.');
        }
        
        $openai_url = 'https://api.openai.com/v1/chat/completions';
        
        // Prepare the prompt
        $system_message = "You are an expert domain investor and market analyst. Your task is to suggest potentially valuable domain names based on the following criteria.";
        
        $user_message = "I'm looking for domain name suggestions in the {$data['niche']} niche that could increase in value within {$data['timeframe']} months.";
        
        if (!empty($data['budget'])) {
            $user_message .= " My budget is \${$data['budget']}.";
        }
        
        if (!empty($data['keywords'])) {
            $user_message .= " Keywords to consider: {$data['keywords']}.";
        }
        
        $user_message .= " Please suggest 10 domain names that are likely available and have good investment potential. For each domain, provide a brief explanation of why it might gain value.";
        
        // Prepare the request body
        $request_body = json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => $system_message),
                array('role' => 'user', 'content' => $user_message)
            ),
            'temperature' => 0.7,
            'max_tokens' => 1000
        ));
        
        // Prepare the request args
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => $request_body,
            'timeout' => 30,
            'httpversion' => '1.1',
            'sslverify' => true
        );
        
        // Make the API request
        $response = wp_remote_post($openai_url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('OpenAI API Error: ' . $error_message);
            throw new Exception('Error connecting to AI service: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (empty($response_body)) {
            error_log('OpenAI API Error: Empty response body');
            throw new Exception('AI service returned an empty response');
        }
        
        $response_data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('OpenAI API Error: Invalid JSON response - ' . json_last_error_msg());
            throw new Exception('AI service returned an invalid response format');
        }
        
        if ($response_code !== 200) {
            $error_message = isset($response_data['error']['message']) ? $response_data['error']['message'] : 'Unknown error';
            error_log('OpenAI API Error: Response code ' . $response_code . ' - ' . $error_message);
            throw new Exception('AI service returned an error: ' . $error_message);
        }
        
        if (!isset($response_data['choices'][0]['message']['content'])) {
            error_log('OpenAI API Error: Unexpected response format - ' . wp_json_encode($response_data));
            throw new Exception('AI service returned an unexpected response format');
        }
        
        // Extract domain suggestions from the response
        $ai_suggestions = $response_data['choices'][0]['message']['content'];
        
        // Process the AI response to extract domain names
        $domains = $this->extract_domains_from_ai_response($ai_suggestions);
        
        if (empty($domains)) {
            $modified_suggestions = $this->fallback_domain_extraction($ai_suggestions);
            if (!empty($modified_suggestions)) {
                return $modified_suggestions;
            }
            
            error_log('OpenAI API Error: No domains extracted from response: ' . substr($ai_suggestions, 0, 500));
            throw new Exception('AI service did not return any valid domain suggestions');
        }
        
        return $domains;
    }
    
    /**
     * Extract domain names from AI response
     */
    private function extract_domains_from_ai_response($ai_response) {
        $domains = array();
        
        // Look for domain patterns in the response
        preg_match_all('/(?:^|\s)([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z]{2,})+)\b/m', $ai_response, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $domain = strtolower(trim($match));
                if (!in_array($domain, $domains)) {
                    $domains[] = $domain;
                }
            }
        }
        
        // If no domains were found, try to extract words that look like domains
        if (empty($domains)) {
            preg_match_all('/(?:^|\s|\")([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z]{2,})?)\b/m', $ai_response, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    $domain = strtolower(trim($match));
                    // Add common TLDs if missing
                    if (strpos($domain, '.') === false) {
                        $domains[] = $domain . '.com';
                        $domains[] = $domain . '.net';
                        $domains[] = $domain . '.org';
                    } else {
                        $domains[] = $domain;
                    }
                }
            }
        }
        
        // Limit to 10 unique domains
        $domains = array_unique($domains);
        $domains = array_slice($domains, 0, 10);
        
        return $domains;
    }
    
    /**
     * Check domain availability and pricing
     */
    private function check_domains_availability($domains) {
        $results = array();
        
        foreach ($domains as $domain) {
            $availability = $this->domain_checker->check_availability($domain);
            
            $results[] = array(
                'domain' => $domain,
                'available' => $availability['available'],
                'price' => $availability['price'],
                'potential_value' => $this->estimate_potential_value($domain),
                'registrar_link' => $this->get_registrar_link($domain)
            );
        }
        
        return $results;
    }
    
    /**
     * Estimate potential value of a domain
     */
    private function estimate_potential_value($domain) {
        // In a real implementation, this would use more sophisticated valuation methods
        // For now, we're using a simple algorithm based on domain length and keywords
        
        $domain_parts = explode('.', $domain);
        $name = $domain_parts[0];
        
        $length_factor = 1;
        if (strlen($name) <= 5) {
            $length_factor = 2.5;
        } elseif (strlen($name) <= 8) {
            $length_factor = 1.8;
        } elseif (strlen($name) <= 12) {
            $length_factor = 1.2;
        }
        
        // Check for premium keywords
        $premium_keywords = array(
            'crypto', 'nft', 'bitcoin', 'ai', 'data', 'tech', 'cloud', 'finance',
            'invest', 'health', 'medical', 'travel', 'luxury', 'premium', 'cyber'
        );
        
        $keyword_factor = 1;
        foreach ($premium_keywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                $keyword_factor = 1.5;
                break;
            }
        }
        
        // Base value estimation
        $base_value = rand(20, 200);
        $estimated_value = $base_value * $length_factor * $keyword_factor;
        
        return round($estimated_value, 2);
    }
    
    /**
     * Get registrar link for domain registration
     */
    private function get_registrar_link($domain) {
        // You can integrate with specific registrars via their APIs
        // For now, we'll just return a generic link to a popular registrar
        return 'https://www.namecheap.com/domains/registration/results/?domain=' . urlencode($domain);
    }
    
    /**
     * Store prediction results in the database
     */
    private function store_prediction_results($prediction_data, $domain_results) {
        if (!is_user_logged_in()) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dvp_predictions';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'search_params' => json_encode($prediction_data),
                'domains' => json_encode($domain_results),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Fallback domain extraction when regular extraction fails
     */
    private function fallback_domain_extraction($ai_response) {
        // Try to extract potential domain name words
        preg_match_all('/\b([a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9])\b/i', $ai_response, $matches);
        
        if (empty($matches[1])) {
            return array();
        }
        
        $domains = array();
        $tlds = array('.com', '.net', '.org', '.io', '.co');
        
        foreach ($matches[1] as $match) {
            if (strlen($match) < 3 || strlen($match) > 20 || is_numeric($match)) {
                continue; // Skip very short/long words and numbers
            }
            
            $word = strtolower(trim($match));
            
            // Add the word with different TLDs
            $domains[] = $word . $tlds[0]; // Always add .com
            
            // Randomly add some other TLDs to diversify
            if (count($domains) < 8 && rand(0, 2) === 0) {
                $domains[] = $word . $tlds[array_rand($tlds, 1)];
            }
            
            if (count($domains) >= 10) {
                break;
            }
        }
        
        return $domains;
    }
} 
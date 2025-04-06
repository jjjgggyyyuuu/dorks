<?php
/**
 * Subscription Form Template
 * Frontend interface for the subscription signup
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get Stripe integration class to get subscription plans
$stripe = new DVP_Stripe_Integration();
$plans = $stripe->get_subscription_plans();

// Get Stripe publishable key
$publishable_key = get_option('dvp_stripe_publishable_key');
$has_stripe_keys = !empty($publishable_key) && !empty(get_option('dvp_stripe_secret_key'));
?>

<div class="dvp-subscription-wrapper">
    <div class="dvp-subscription-header">
        <h2>Subscribe to Domain Value Predictor</h2>
        <p>Get access to our AI-powered domain value prediction tool and discover potentially profitable domain names.</p>
    </div>
    
    <?php if (!$has_stripe_keys): ?>
        <div class="dvp-notice dvp-error">
            <p>Stripe payment configuration is incomplete. Please contact the administrator.</p>
        </div>
    <?php elseif (!is_ssl()): ?>
        <div class="dvp-notice dvp-error">
            <p>Secure connection (HTTPS) is required for payment processing. Please contact the administrator.</p>
        </div>
    <?php else: ?>
    
    <div class="dvp-pricing-plans">
        <?php foreach ($plans as $plan): ?>
            <div class="dvp-pricing-plan" data-plan-id="<?php echo esc_attr($plan['id']); ?>">
                <div class="dvp-plan-header">
                    <h3><?php echo esc_html($plan['name']); ?></h3>
                    <div class="dvp-plan-price">
                        <span class="dvp-price-amount">$<?php echo esc_html($plan['price']); ?></span>
                        <span class="dvp-price-period">/<?php echo esc_html($plan['interval']); ?></span>
                    </div>
                </div>
                
                <div class="dvp-plan-description">
                    <p><?php echo esc_html($plan['description']); ?></p>
                </div>
                
                <div class="dvp-plan-features">
                    <ul>
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="dvp-plan-action">
                    <button class="dvp-button dvp-select-plan-button" data-price-id="<?php echo esc_attr($plan['id']); ?>">
                        Select Plan
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div id="dvp-subscription-form-container" class="dvp-subscription-form-container" style="display: none;">
        <h3>Complete Your Subscription</h3>
        
        <div id="dvp-payment-form" class="dvp-payment-form">
            <div class="dvp-form-row">
                <div class="dvp-form-field">
                    <label for="dvp-card-holder-name">Cardholder Name</label>
                    <input id="dvp-card-holder-name" type="text" placeholder="Name on card" required>
                </div>
            </div>
            
            <div class="dvp-form-row">
                <div class="dvp-form-field">
                    <label for="dvp-card-element">Credit or Debit Card</label>
                    <div id="dvp-card-element">
                        <!-- Stripe Card Element will be inserted here -->
                    </div>
                    <div id="dvp-card-errors" class="dvp-card-errors" role="alert"></div>
                </div>
            </div>
            
            <div class="dvp-form-row dvp-payment-summary">
                <div class="dvp-selected-plan">
                    <h4>Selected Plan: <span id="dvp-selected-plan-name"></span></h4>
                    <p>Amount: <span id="dvp-selected-plan-price"></span></p>
                </div>
            </div>
            
            <div class="dvp-form-actions">
                <button id="dvp-submit-payment" type="submit" class="dvp-button dvp-submit-button">
                    <span class="dvp-button-text">Subscribe Now</span>
                    <span class="dvp-spinner"></span>
                </button>
                <button id="dvp-cancel-payment" type="button" class="dvp-button dvp-cancel-button">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    
    <div id="dvp-subscription-success" class="dvp-subscription-success" style="display: none;">
        <div class="dvp-success-icon">✓</div>
        <h3>Subscription Successful!</h3>
        <p>Thank you for subscribing to Domain Value Predictor.</p>
        <p>You now have access to our AI-powered domain value prediction tool.</p>
        <div class="dvp-success-actions">
            <?php
            // Get domain predictor page URL safely
            $predictor_page = get_page_by_path('domain-predictor');
            $predictor_url = $predictor_page ? get_permalink($predictor_page) : home_url();
            ?>
            <a href="<?php echo esc_url($predictor_url); ?>" class="dvp-button">
                Start Finding Valuable Domains
            </a>
        </div>
    </div>
    
    <div class="dvp-subscription-faq">
        <h3>Frequently Asked Questions</h3>
        
        <div class="dvp-faq-item">
            <h4>What is included in the subscription?</h4>
            <div class="dvp-faq-content">
                <p>Your subscription gives you access to our AI-powered domain value prediction tool, which helps you discover potentially profitable domain names. You can search based on niche, keywords, and timeframe to find domains that are likely to increase in value.</p>
            </div>
        </div>
        
        <div class="dvp-faq-item">
            <h4>How does the domain value prediction work?</h4>
            <div class="dvp-faq-content">
                <p>Our tool uses advanced AI algorithms to analyze market trends, search volume data, and domain characteristics to predict which domain names are likely to gain value over time. The AI considers factors like keyword popularity, niche growth, and domain length to make its predictions.</p>
            </div>
        </div>
        
        <div class="dvp-faq-item">
            <h4>Can I cancel my subscription?</h4>
            <div class="dvp-faq-content">
                <p>Yes, you can cancel your subscription at any time from your account settings. Your subscription will remain active until the end of the current billing period.</p>
            </div>
        </div>
        
        <div class="dvp-faq-item">
            <h4>Is there a guarantee that domains will increase in value?</h4>
            <div class="dvp-faq-content">
                <p>While our AI uses sophisticated algorithms to identify domains with high potential, we cannot guarantee that every domain will increase in value. Domain investing involves risk, and market conditions can change. Our tool provides predictions based on current data and trends.</p>
            </div>
        </div>
    </div>
    
    <script>
        // Stripe configuration will be handled by domain-predictor.js
        var stripePublishableKey = '<?php echo esc_js($publishable_key); ?>';
        var dvpPlans = <?php echo json_encode($plans); ?>;
    </script>
    <?php endif; ?>
</div> 
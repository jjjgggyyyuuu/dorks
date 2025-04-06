/**
 * Domain Value Predictor JavaScript
 * Handles frontend interactions and AJAX requests
 */

(function($) {
    'use strict';
    
    // Make sure jQuery is available
    if (typeof $ === 'undefined') {
        console.error('Domain Value Predictor: jQuery is required but not available.');
        return;
    }
    
    // DOM elements - initialize with defaults in case elements don't exist
    const $document = $(document);
    let $searchForm = $('#dvp-domain-search-form');
    let $searchResults = $('#dvp-search-results');
    let $resultsContainer = $('.dvp-results-container');
    let $loadingMessage = $('.dvp-loading-message');
    let $noResults = $('.dvp-no-results');
    let $resultCount = $('#dvp-count');
    let $trendingTags = $('.dvp-tag');
    let $keywordsInput = $('#dvp-keywords');
    
    // Subscription elements
    let $pricingPlans = $('.dvp-pricing-plans');
    let $selectPlanButtons = $('.dvp-select-plan-button');
    let $subscriptionFormContainer = $('#dvp-subscription-form-container');
    let $selectedPlanName = $('#dvp-selected-plan-name');
    let $selectedPlanPrice = $('#dvp-selected-plan-price');
    let $cardHolderName = $('#dvp-card-holder-name');
    let $submitPaymentButton = $('#dvp-submit-payment');
    let $cancelPaymentButton = $('#dvp-cancel-payment');
    let $subscriptionSuccess = $('#dvp-subscription-success');
    let $faqItems = $('.dvp-faq-item h4');
    
    // Stripe elements
    let stripe = null;
    let elements = null;
    let cardElement = null;
    let selectedPriceId = null;
    
    /**
     * Initialize the app
     */
    function init() {
        // Set up event listeners (only if elements exist)
        if ($searchForm.length) {
            $searchForm.on('submit', handleSearchSubmit);
        }
        
        if ($trendingTags.length) {
            $trendingTags.on('click', handleTagClick);
        }
        
        if ($selectPlanButtons.length) {
            $selectPlanButtons.on('click', handlePlanSelection);
        }
        
        if ($cancelPaymentButton.length) {
            $cancelPaymentButton.on('click', handleCancelPayment);
        }
        
        if ($faqItems.length) {
            $faqItems.on('click', toggleFaqItem);
        }
        
        // Initialize Stripe if available and needed
        if (typeof Stripe !== 'undefined' && typeof stripePublishableKey !== 'undefined' && $('#dvp-card-element').length) {
            initializeStripe();
        }
        
        // Initialize UI state
        if ($searchResults.length) {
            $searchResults.hide();
        }
        
        if ($loadingMessage.length) {
            $loadingMessage.hide();
        }
        
        if ($noResults.length) {
            $noResults.hide();
        }
    }
    
    /**
     * Initialize Stripe elements
     */
    function initializeStripe() {
        stripe = Stripe(stripePublishableKey);
        elements = stripe.elements();
        
        // Create card element
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });
        
        // Mount the card element
        cardElement.mount('#dvp-card-element');
        
        // Handle card errors
        cardElement.on('change', function(event) {
            const $cardErrors = $('#dvp-card-errors');
            
            if (event.error) {
                $cardErrors.text(event.error.message);
            } else {
                $cardErrors.text('');
            }
        });
        
        // Set up payment submission
        $submitPaymentButton.on('click', handlePaymentSubmission);
    }
    
    /**
     * Handle domain search form submission
     */
    function handleSearchSubmit(e) {
        e.preventDefault();
        
        // Basic input validation
        const niche = $('#dvp-niche').val().trim();
        
        if (!niche) {
            showError('Please enter a niche.');
            return;
        }
        
        // Show loading state
        $searchResults.show();
        $loadingMessage.show();
        $resultsContainer.hide();
        $noResults.hide();
        $('.dvp-search-button .dvp-button-text').text('Searching...');
        $('.dvp-search-button .dvp-spinner').show();
        
        // Get form data
        const formData = new FormData($searchForm[0]);
        formData.append('action', 'dvp_predict_domains');
        formData.append('nonce', dvp_data.nonce);
        
        // Make AJAX request
        $.ajax({
            url: dvp_data.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: handleSearchSuccess,
            error: handleSearchError,
            complete: function() {
                // Reset button state
                $('.dvp-search-button .dvp-button-text').text('Find Valuable Domains');
                $('.dvp-search-button .dvp-spinner').hide();
            },
            timeout: 60000 // 60 second timeout for API requests
        });
    }
    
    /**
     * Handle successful search response
     */
    function handleSearchSuccess(response) {
        $loadingMessage.hide();
        
        if (response.success && response.data.domains && response.data.domains.length > 0) {
            renderDomainResults(response.data.domains);
            $resultCount.text(response.data.domains.length);
            $resultsContainer.show();
        } else {
            $noResults.show();
            $resultCount.text(0);
        }
    }
    
    /**
     * Handle search error
     */
    function handleSearchError(jqXHR, textStatus, errorThrown) {
        $loadingMessage.hide();
        
        let errorMessage = 'An error occurred while searching for domains.';
        
        if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
            errorMessage = jqXHR.responseJSON.data.message;
        }
        
        console.error('Domain search error:', textStatus, errorThrown, jqXHR.responseText);
        
        $noResults.html(`<p>${errorMessage}</p>`);
        $noResults.show();
        $resultCount.text(0);
    }
    
    /**
     * Render domain results
     */
    function renderDomainResults(domains) {
        $resultsContainer.empty();
        
        domains.forEach(function(domain) {
            // Get the result template
            let template = $('#dvp-domain-result-template').html();
            
            // Calculate value increase percentage
            const registrationPrice = parseFloat(domain.price);
            const potentialValue = parseFloat(domain.potential_value);
            const valueIncrease = registrationPrice > 0 ? ((potentialValue - registrationPrice) / registrationPrice * 100).toFixed(1) : 0;
            
            // Replace placeholders with actual data
            template = template.replace('{domain}', domain.domain);
            template = template.replace('{available}', domain.available ? 'Available' : 'Unavailable');
            template = template.replace('{price}', domain.price);
            template = template.replace('{potential_value}', domain.potential_value);
            template = template.replace('{value_increase}', valueIncrease);
            template = template.replace('{registrar_link}', domain.registrar_link);
            
            // Add CSS classes based on availability
            const $result = $(template);
            if (!domain.available) {
                $result.addClass('dvp-domain-unavailable');
                $result.find('.dvp-domain-available').addClass('dvp-unavailable');
                $result.find('.dvp-registrar-link').text('Check Similar Domains').attr('href', 'https://www.namecheap.com/domains/registration/results/?domain=' + encodeURIComponent(domain.domain.split('.')[0]));
            } else {
                $result.find('.dvp-domain-available').addClass('dvp-available');
            }
            
            // Add CSS classes based on value increase
            const $valueIncrease = $result.find('.dvp-value-increase');
            if (valueIncrease >= 50) {
                $valueIncrease.addClass('dvp-high-value');
            } else if (valueIncrease >= 20) {
                $valueIncrease.addClass('dvp-medium-value');
            } else {
                $valueIncrease.addClass('dvp-low-value');
            }
            
            // Append the result to the container
            $resultsContainer.append($result);
        });
    }
    
    /**
     * Handle clicking on a trending keyword tag
     */
    function handleTagClick() {
        const keyword = $(this).data('keyword');
        const currentKeywords = $keywordsInput.val();
        
        if (currentKeywords) {
            // Add the new keyword to the existing ones
            const keywordsArray = currentKeywords.split(',').map(k => k.trim());
            
            if (!keywordsArray.includes(keyword)) {
                keywordsArray.push(keyword);
                $keywordsInput.val(keywordsArray.join(', '));
            }
        } else {
            // No existing keywords, just set this one
            $keywordsInput.val(keyword);
        }
        
        // Visual feedback
        $(this).addClass('dvp-tag-selected');
        setTimeout(() => {
            $(this).removeClass('dvp-tag-selected');
        }, 500);
    }
    
    /**
     * Handle subscription plan selection
     */
    function handlePlanSelection() {
        // Get the selected plan data
        selectedPriceId = $(this).data('price-id');
        const planId = $(this).closest('.dvp-pricing-plan').data('plan-id');
        
        // Find the selected plan in the plans array
        const selectedPlan = dvpPlans.find(plan => plan.id === planId);
        
        if (selectedPlan) {
            // Update plan display
            $selectedPlanName.text(selectedPlan.name);
            $selectedPlanPrice.text(`$${selectedPlan.price}/${selectedPlan.interval}`);
            
            // Show the subscription form
            $pricingPlans.hide();
            $subscriptionFormContainer.show();
        }
    }
    
    /**
     * Handle cancellation of payment
     */
    function handleCancelPayment() {
        // Reset and hide the subscription form
        $subscriptionFormContainer.hide();
        $pricingPlans.show();
        cardElement.clear();
        $cardHolderName.val('');
        $('#dvp-card-errors').text('');
    }
    
    /**
     * Handle payment form submission
     */
    function handlePaymentSubmission(e) {
        e.preventDefault();
        
        if (!selectedPriceId) {
            return;
        }
        
        // Disable the submit button to prevent multiple clicks
        $submitPaymentButton.prop('disabled', true);
        $submitPaymentButton.find('.dvp-button-text').text('Processing...');
        $submitPaymentButton.find('.dvp-spinner').show();
        
        // Create a payment method and confirm payment
        stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
                name: $cardHolderName.val()
            }
        }).then(function(result) {
            if (result.error) {
                // Show error
                $('#dvp-card-errors').text(result.error.message);
                
                // Reset button state
                $submitPaymentButton.prop('disabled', false);
                $submitPaymentButton.find('.dvp-button-text').text('Subscribe Now');
                $submitPaymentButton.find('.dvp-spinner').hide();
            } else {
                // Create subscription
                createSubscription(result.paymentMethod.id);
            }
        });
    }
    
    /**
     * Create subscription via AJAX
     */
    function createSubscription(paymentMethodId) {
        $.ajax({
            url: dvp_data.ajax_url,
            type: 'POST',
            data: {
                action: 'dvp_create_subscription',
                nonce: dvp_data.nonce,
                payment_method_id: paymentMethodId,
                price_id: selectedPriceId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Handle subscription created
                    handleSubscriptionCreated(response.data);
                } else {
                    // Handle error
                    $('#dvp-card-errors').text(response.data.message || 'An error occurred.');
                    
                    // Reset button state
                    $submitPaymentButton.prop('disabled', false);
                    $submitPaymentButton.find('.dvp-button-text').text('Subscribe Now');
                    $submitPaymentButton.find('.dvp-spinner').hide();
                }
            },
            error: function() {
                $('#dvp-card-errors').text('A server error occurred. Please try again.');
                
                // Reset button state
                $submitPaymentButton.prop('disabled', false);
                $submitPaymentButton.find('.dvp-button-text').text('Subscribe Now');
                $submitPaymentButton.find('.dvp-spinner').hide();
            }
        });
    }
    
    /**
     * Handle successful subscription creation
     */
    function handleSubscriptionCreated(data) {
        const { subscription_id, client_secret, status } = data;
        
        if (status === 'active') {
            // Subscription is already active, show success
            showSubscriptionSuccess();
        } else {
            // Subscription requires additional authentication
            stripe.confirmCardPayment(client_secret).then(function(result) {
                if (result.error) {
                    // Show error
                    $('#dvp-card-errors').text(result.error.message);
                    
                    // Reset button state
                    $submitPaymentButton.prop('disabled', false);
                    $submitPaymentButton.find('.dvp-button-text').text('Subscribe Now');
                    $submitPaymentButton.find('.dvp-spinner').hide();
                } else {
                    // Show success
                    showSubscriptionSuccess();
                }
            });
        }
    }
    
    /**
     * Show subscription success message
     */
    function showSubscriptionSuccess() {
        $subscriptionFormContainer.hide();
        $subscriptionSuccess.show();
        
        // Refresh the page after a delay to update subscription status
        setTimeout(function() {
            window.location.reload();
        }, 3000);
    }
    
    /**
     * Toggle FAQ item visibility
     */
    function toggleFaqItem() {
        const $content = $(this).next('.dvp-faq-content');
        $content.slideToggle(200);
        $(this).toggleClass('dvp-faq-active');
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        $noResults.html(`<p>${message}</p>`);
        $noResults.show();
        $resultCount.text(0);
        $loadingMessage.hide();
        
        // Log for debugging
        console.error('Domain Predictor Error:', message);
    }
    
    // Initialize when the document is ready
    $document.ready(init);
    
})(jQuery); 
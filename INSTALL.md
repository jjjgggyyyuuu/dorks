# Domain Value Predictor - Installation & Configuration Guide

This guide will help you install and configure the Domain Value Predictor WordPress plugin on your Hostinger WordPress site.

## Prerequisites

- WordPress site hosted on Hostinger
- Admin access to your WordPress dashboard
- Stripe account for payment processing
- OpenAI API key for AI domain prediction
- Domain availability API key (optional)

## Installation

### Option 1: Direct Upload to WordPress

1. Download the `domain-value-predictor.zip` file from this repository
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins > Add New**
4. Click the **Upload Plugin** button at the top
5. Choose the downloaded ZIP file and click **Install Now**
6. After installation, click **Activate Plugin**

### Option 2: Using Git with Hostinger

1. Log in to your Hostinger account and access SSH for your hosting (if available)
2. Navigate to your WordPress plugins directory:
   ```
   cd /path/to/wordpress/wp-content/plugins
   ```
3. Clone the repository:
   ```
   git clone https://github.com/yourusername/domain-value-predictor.git
   ```
4. Log in to your WordPress admin and activate the plugin

## Configuration

### 1. API Keys Setup

1. Navigate to **Domain Predictor > Settings** in your WordPress admin
2. Enter your API keys in the appropriate fields:
   - OpenAI API Key: Required for AI domain value prediction
   - Stripe Publishable Key: Required for payment processing
   - Stripe Secret Key: Required for payment processing
   - Stripe Webhook Secret: Required for subscription management
   - Domain API Key: Optional for more accurate domain availability checking

### 2. Stripe Configuration

1. Log in to your Stripe Dashboard (https://dashboard.stripe.com/)
2. Create two subscription products with the following IDs:
   - `price_monthly` - Monthly subscription at $9.99/month
   - `price_yearly` - Yearly subscription at $99.99/year
3. Set up a webhook in Stripe:
   - Go to **Developers > Webhooks** in your Stripe dashboard
   - Add an endpoint with URL: `https://yourdomain.com/wp-json/domain-value-predictor/v1/webhook`
   - Select events to listen for:
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`
   - Copy the webhook signing secret and paste it in the plugin settings

### 3. Create WordPress Pages

1. Create a page for the domain predictor tool:
   - Title: "Domain Value Predictor"
   - Content: `[domain_predictor]`
   - URL: `/domain-predictor/`

2. Create a page for the subscription signup:
   - Title: "Subscribe"
   - Content: `[subscription_form]`
   - URL: `/subscription/`

### 4. Configure Hostinger with Git Deployment

1. Log in to your Hostinger control panel
2. Navigate to **Advanced > Git**
3. Set up a Git repository and connect it to your domain-value-predictor repository
4. Configure auto-deployment when you push to the repository

## Usage

1. Visit your subscription page (e.g., `https://yourdomain.com/subscription/`) to sign up
2. Choose a plan and complete payment
3. After subscribing, access the domain predictor tool at `https://yourdomain.com/domain-predictor/`
4. Enter search criteria and find valuable domains

## Troubleshooting

### Subscription Issues

- Verify Stripe API keys are correct
- Check Stripe webhook is properly configured
- Ensure webhook secret matches in both Stripe and plugin settings

### Domain Prediction Issues

- Verify OpenAI API key is correct and has sufficient credits
- Check server logs for errors in the AI prediction process

### Domain Availability Issues

- If not using a domain API, results rely on WHOIS lookups which may be rate-limited
- Consider adding a domain availability API for more accurate results

## Support

For support, please contact support@yourdomain.com or create an issue in the GitHub repository. 
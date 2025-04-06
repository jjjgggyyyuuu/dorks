# Domain Value Predictor

![Domain Value Predictor](assets/img/plugin-logo.png)

A WordPress plugin that helps users discover potentially profitable domain names using AI-powered prediction.

## Features

- AI-powered domain value prediction using OpenAI
- Domain availability checking
- Subscription-based access with Stripe integration
- Admin dashboard for monitoring usage and statistics
- User-friendly frontend interface

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- PHP Extensions: mysqli, json, curl

## Installation

1. Upload the `domain-value-predictor` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Domain Predictor > Settings to configure your API keys
4. Add the shortcodes to your pages

## Configuration

The plugin requires the following API keys to function properly:

- **OpenAI API Key**: For AI-powered domain value prediction
- **Domain API Key**: For checking domain availability (optional)
- **Stripe API Keys**: For handling subscription payments

## Shortcodes

The plugin provides two main shortcodes:

- `[domain_predictor]`: Displays the domain search interface for subscribers
- `[subscription_form]`: Displays the subscription signup form for non-subscribers

## License

This plugin is licensed under the MIT License - see the LICENSE file for details. 
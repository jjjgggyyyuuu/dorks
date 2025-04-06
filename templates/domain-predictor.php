<?php
/**
 * Domain Predictor Template
 * Frontend interface for the domain value prediction tool
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get default timeframe
$default_timeframe = get_option('dvp_default_timeframe', 3);

// Get API handler to fetch trending keywords
$api_handler = new DVP_API_Handler();
$trending_keywords = $api_handler->get_trending_keywords();
$market_trends = $api_handler->get_domain_market_trends();
$tld_performance = $api_handler->get_tld_performance();
?>

<div class="dvp-domain-predictor-wrapper">
    <div class="dvp-market-trends">
        <h2>Domain Market Trends</h2>
        <div class="dvp-trends-grid">
            <?php foreach ($market_trends as $trend): ?>
                <div class="dvp-trend-card">
                    <h3><?php echo esc_html($trend['category']); ?></h3>
                    <p><strong>Growth Rate:</strong> <?php echo esc_html($trend['growth_rate']); ?>%</p>
                    <p><strong>Popularity:</strong> <?php echo esc_html($trend['popularity']); ?></p>
                    <p><strong>Trending TLDs:</strong> <?php echo esc_html(implode(', ', $trend['trending_tlds'])); ?></p>
                    <p><strong>Trending Keywords:</strong> <?php echo esc_html(implode(', ', $trend['trending_keywords'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dvp-search-form-container">
        <h2>Find Valuable Domains</h2>
        <p>Enter your criteria below to discover domain names with future potential value.</p>
        
        <form id="dvp-domain-search-form" class="dvp-search-form">
            <div class="dvp-form-row">
                <div class="dvp-form-field">
                    <label for="dvp-niche">Niche or Industry:</label>
                    <input type="text" id="dvp-niche" name="niche" placeholder="e.g. Technology, Finance, Health" required>
                </div>
                
                <div class="dvp-form-field">
                    <label for="dvp-timeframe">Value Prediction Timeframe:</label>
                    <select id="dvp-timeframe" name="timeframe">
                        <option value="3" <?php selected($default_timeframe, 3); ?>>3 months</option>
                        <option value="6" <?php selected($default_timeframe, 6); ?>>6 months</option>
                        <option value="12" <?php selected($default_timeframe, 12); ?>>12 months</option>
                        <option value="24" <?php selected($default_timeframe, 24); ?>>24 months</option>
                    </select>
                </div>
            </div>
            
            <div class="dvp-form-row">
                <div class="dvp-form-field">
                    <label for="dvp-budget">Max Budget for Domain (USD):</label>
                    <input type="number" id="dvp-budget" name="budget" placeholder="Optional" min="0" step="0.01">
                </div>
                
                <div class="dvp-form-field">
                    <label for="dvp-keywords">Specific Keywords (comma separated):</label>
                    <input type="text" id="dvp-keywords" name="keywords" placeholder="Optional">
                </div>
            </div>
            
            <div class="dvp-form-row dvp-trending-keywords">
                <p><strong>Trending Keywords:</strong> Click to add to search</p>
                <div class="dvp-tags">
                    <?php foreach ($trending_keywords as $keyword): ?>
                        <span class="dvp-tag" data-keyword="<?php echo esc_attr($keyword); ?>"><?php echo esc_html($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="dvp-form-actions">
                <button type="submit" class="dvp-button dvp-search-button">
                    <span class="dvp-button-text">Find Valuable Domains</span>
                    <span class="dvp-spinner"></span>
                </button>
            </div>
        </form>
    </div>
    
    <div id="dvp-search-results" class="dvp-search-results">
        <div class="dvp-results-header">
            <h2>Domain Suggestions</h2>
            <p class="dvp-results-count">Showing <span id="dvp-count">0</span> results</p>
        </div>
        
        <div class="dvp-loading-message">
            <p>Analyzing domain market trends and generating predictions...</p>
            <div class="dvp-loading-spinner"></div>
        </div>
        
        <div class="dvp-results-container"></div>
        
        <div class="dvp-no-results">
            <p>No domain suggestions found. Please try different search criteria.</p>
        </div>
    </div>
    
    <div class="dvp-tld-performance">
        <h2>TLD Performance</h2>
        <table class="dvp-tld-table">
            <thead>
                <tr>
                    <th>TLD</th>
                    <th>Market Share</th>
                    <th>Avg. Price</th>
                    <th>Growth Rate</th>
                    <th>Value Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tld_performance as $tld): ?>
                    <tr>
                        <td><?php echo esc_html($tld['tld']); ?></td>
                        <td><?php echo esc_html($tld['market_share']); ?>%</td>
                        <td>$<?php echo esc_html($tld['avg_price']); ?></td>
                        <td><?php echo esc_html($tld['growth_rate']); ?>%</td>
                        <td>
                            <div class="dvp-rating">
                                <?php 
                                $rating = round($tld['value_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<span class="dvp-star filled"></span>';
                                    } else {
                                        echo '<span class="dvp-star"></span>';
                                    }
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Results template -->
<script type="text/template" id="dvp-domain-result-template">
    <div class="dvp-domain-result" data-domain="{domain}">
        <div class="dvp-domain-header">
            <h3 class="dvp-domain-name">{domain}</h3>
            <span class="dvp-domain-available">{available}</span>
        </div>
        <div class="dvp-domain-details">
            <div class="dvp-domain-info">
                <p><strong>Registration Price:</strong> ${price}</p>
                <p><strong>Potential Value:</strong> ${potential_value}</p>
                <p><strong>Value Increase:</strong> <span class="dvp-value-increase">{value_increase}%</span></p>
            </div>
            <div class="dvp-domain-actions">
                <a href="{registrar_link}" class="dvp-button dvp-registrar-link" target="_blank">Register Domain</a>
            </div>
        </div>
    </div>
</script> 
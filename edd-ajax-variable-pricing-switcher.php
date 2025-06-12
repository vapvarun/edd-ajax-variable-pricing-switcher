<?php
/*
Plugin Name: EDD AJAX Variable Pricing Switcher
Description: Enhanced EDD Variable Pricing Switcher with AJAX updates and Annual/Lifetime grouping - enabled for all products by default
Version: 2.0.0
Author: Wbcom Designs
Text Domain: edd-ajax-vps
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EDD_AJAX_Variable_Pricing_Switcher {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_edd_switch_price', array($this, 'ajax_switch_price'));
        add_action('wp_ajax_nopriv_edd_switch_price', array($this, 'ajax_switch_price'));
        add_action('wp_ajax_edd_update_cart_item_price', array($this, 'ajax_update_cart_item_price'));
        add_action('wp_ajax_nopriv_edd_update_cart_item_price', array($this, 'ajax_update_cart_item_price'));
        
        // Filter the price table output for product pages
        add_filter('edd_download_price_table', array($this, 'custom_price_table'), 100, 2);
        
        // Add checkout page cart item pricing
        add_action('edd_checkout_cart_item_title_after', array($this, 'add_checkout_pricing_switcher'), 10, 2);
        
        // Add custom styles
        add_action('wp_head', array($this, 'add_custom_styles'));
        
        // Add default selection script
        add_action('wp_footer', array($this, 'add_default_selection_script'));
    }
    
    /**
     * Get variable prices with proper is_lifetime detection
     */
    private function get_variable_prices_with_lifetime($download_id) {
        // Get the serialized variable prices meta
        $variable_prices_meta = get_post_meta($download_id, 'edd_variable_prices', true);
        
        if (empty($variable_prices_meta) || !is_array($variable_prices_meta)) {
            return array();
        }
        
        $processed_prices = array();
        
        foreach ($variable_prices_meta as $price_id => $price_data) {
            // Add the is_lifetime flag if it exists in the serialized data
            $processed_prices[$price_id] = $price_data;
            
            // Check if is_lifetime exists in the price data
            if (isset($price_data['is_lifetime']) && $price_data['is_lifetime'] === '1') {
                $processed_prices[$price_id]['is_lifetime'] = true;
            } else {
                $processed_prices[$price_id]['is_lifetime'] = false;
            }
        }
        
        return $processed_prices;
    }
    
    /**
     * Enqueue JavaScript and localize data
     */
    public function enqueue_scripts() {
        if (!edd_is_checkout() && !is_singular('download')) {
            return;
        }
        
        wp_enqueue_script(
            'edd-ajax-pricing-switcher', 
            plugin_dir_url(__FILE__) . 'edd-ajax-pricing-switcher.js', 
            array('jquery'), 
            '2.0.0', 
            true
        );
        
        wp_localize_script('edd-ajax-pricing-switcher', 'edd_ajax_switcher', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('edd_ajax_pricing_nonce'),
            'loading_text' => __('Loading...', 'edd-ajax-vps'),
            'error_text' => __('Error updating price. Please try again.', 'edd-ajax-vps'),
            'updating_cart_text' => __('Updating cart...', 'edd-ajax-vps'),
            'is_checkout' => edd_is_checkout() ? '1' : '0'
        ));
    }
    
    /**
     * Custom price table with Annual/Lifetime grouping and AJAX - enabled for all products
     */
    public function custom_price_table($price_table, $download_id) {
        if (!edd_has_variable_prices($download_id)) {
            return $price_table;
        }
        
        $prices = $this->get_variable_prices_with_lifetime($download_id);
        if (empty($prices)) {
            return $price_table;
        }
        
        // Group prices by Annual/Lifetime
        $annual_prices = array();
        $lifetime_prices = array();
        $default_price_id = null;
        $first_price_id = key($prices);
        
        foreach ($prices as $key => $price) {
            if ($price['is_lifetime']) {
                $lifetime_prices[$key] = $price;
            } else {
                $annual_prices[$key] = $price;
                // Set first annual as default
                if ($default_price_id === null) {
                    $default_price_id = $key;
                }
            }
        }
        
        // If no annual prices, use first lifetime as default
        if ($default_price_id === null && !empty($lifetime_prices)) {
            $default_price_id = key($lifetime_prices);
        }
        
        // Final fallback
        if ($default_price_id === null) {
            $default_price_id = $first_price_id;
        }
        
        ob_start();
        ?>
        <div class="edd-ajax-pricing-wrapper" data-download-id="<?php echo esc_attr($download_id); ?>" data-default-price="<?php echo esc_attr($default_price_id); ?>">
            
            <?php if (!empty($annual_prices)): ?>
                <div class="edd-pricing-group annual-group">
                    <h4 class="pricing-group-title"><?php _e('Annual Licenses', 'edd-ajax-vps'); ?></h4>
                    <div class="pricing-options">
                        <?php foreach ($annual_prices as $key => $price): ?>
                            <label class="pricing-option">
                                <input type="radio" 
                                       name="edd_price_option[<?php echo $download_id; ?>]" 
                                       class="edd-ajax-pricing-switcher" 
                                       data-download-id="<?php echo $download_id; ?>" 
                                       value="<?php echo $key; ?>"
                                       <?php checked($key, $default_price_id); ?>>
                                <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($lifetime_prices)): ?>
                <div class="edd-pricing-group lifetime-group">
                    <h4 class="pricing-group-title"><?php _e('Lifetime Licenses', 'edd-ajax-vps'); ?></h4>
                    <div class="pricing-options">
                        <?php foreach ($lifetime_prices as $key => $price): ?>
                            <label class="pricing-option">
                                <input type="radio" 
                                       name="edd_price_option[<?php echo $download_id; ?>]" 
                                       class="edd-ajax-pricing-switcher" 
                                       data-download-id="<?php echo $download_id; ?>" 
                                       value="<?php echo $key; ?>"
                                       <?php checked($key, $default_price_id); ?>>
                                <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Price Display -->
            <div class="edd-ajax-price-display">
                <div id="edd_price_<?php echo esc_attr($download_id); ?>" class="edd-price-wrap">
                    <?php echo edd_currency_filter(edd_format_amount($prices[$default_price_id]['amount'])); ?>
                </div>
                <div class="edd-loading-indicator" style="display: none;">
                    <?php _e('Updating price...', 'edd-ajax-vps'); ?>
                </div>
            </div>
            
            <!-- Hidden input for form submission -->
            <input type="hidden" name="edd_options[<?php echo $download_id; ?>]" value="<?php echo esc_attr($default_price_id); ?>" class="edd-selected-price-input">
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX price switching
     */
    public function ajax_switch_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'edd_ajax_pricing_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $download_id = absint($_POST['download_id']);
        $price_id = absint($_POST['price_id']);
        
        if (!$download_id || !isset($_POST['price_id'])) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }
        
        $prices = $this->get_variable_prices_with_lifetime($download_id);
        
        if (!isset($prices[$price_id])) {
            wp_send_json_error(array('message' => 'Invalid price option'));
        }
        
        $selected_price = $prices[$price_id];
        $amount = $selected_price['amount'];
        $formatted_price = edd_currency_filter(edd_format_amount($amount));
        
        // Check for active discounts
        $discounted_amount = $amount;
        $discount_html = '';
        
        if (function_exists('edd_get_cart_discounts')) {
            $discounts = edd_get_cart_discounts();
            if (!empty($discounts)) {
                foreach ($discounts as $discount) {
                    if (function_exists('edd_get_discount_amount')) {
                        $discount_amount = edd_get_discount_amount($discount, $amount);
                        $discounted_amount = $amount - $discount_amount;
                    }
                }
                
                if ($discounted_amount < $amount) {
                    $discount_html = sprintf(
                        '<span class="original-price">%s</span> <span class="discounted-price">%s</span>',
                        $formatted_price,
                        edd_currency_filter(edd_format_amount($discounted_amount))
                    );
                    $formatted_price = $discount_html;
                }
            }
        }
        
        $response_data = array(
            'price_html' => $formatted_price,
            'raw_amount' => $amount,
            'discounted_amount' => $discounted_amount,
            'price_id' => $price_id,
            'price_name' => $selected_price['name']
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Add pricing switcher to checkout page cart items
     */
    public function add_checkout_pricing_switcher($item, $cart_key) {
        if (!edd_is_checkout()) {
            return;
        }
        
        $download_id = $item['id'];
        
        if (!edd_has_variable_prices($download_id)) {
            return;
        }
        
        $prices = $this->get_variable_prices_with_lifetime($download_id);
        if (empty($prices) || count($prices) < 2) {
            return;
        }
        
        $current_price_id = isset($item['options']['price_id']) ? $item['options']['price_id'] : 0;
        
        // Group prices by Annual/Lifetime
        $annual_prices = array();
        $lifetime_prices = array();
        
        foreach ($prices as $key => $price) {
            if ($price['is_lifetime']) {
                $lifetime_prices[$key] = $price;
            } else {
                $annual_prices[$key] = $price;
            }
        }
        
        ?>
        <div class="edd-checkout-pricing-switcher" data-cart-key="<?php echo esc_attr($cart_key); ?>" data-download-id="<?php echo esc_attr($download_id); ?>">
            <div class="pricing-switcher-toggle">
                <button type="button" class="toggle-pricing-options">
                    <?php _e('Change License Type', 'edd-ajax-vps'); ?>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
            </div>
            
            <div class="pricing-options-panel" style="display: none;">
                
                <?php if (!empty($annual_prices)): ?>
                    <div class="checkout-pricing-group annual-group">
                        <h5 class="checkout-group-title"><?php _e('Annual Licenses', 'edd-ajax-vps'); ?></h5>
                        <?php foreach ($annual_prices as $key => $price): ?>
                            <label class="checkout-pricing-option">
                                <input type="radio" 
                                       name="checkout_price_option_<?php echo $cart_key; ?>" 
                                       class="edd-checkout-pricing-switcher" 
                                       data-cart-key="<?php echo $cart_key; ?>"
                                       data-download-id="<?php echo $download_id; ?>" 
                                       value="<?php echo $key; ?>"
                                       <?php checked($key, $current_price_id); ?>>
                                <span class="option-details">
                                    <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                    <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($lifetime_prices)): ?>
                    <div class="checkout-pricing-group lifetime-group">
                        <h5 class="checkout-group-title"><?php _e('Lifetime Licenses', 'edd-ajax-vps'); ?></h5>
                        <?php foreach ($lifetime_prices as $key => $price): ?>
                            <label class="checkout-pricing-option">
                                <input type="radio" 
                                       name="checkout_price_option_<?php echo $cart_key; ?>" 
                                       class="edd-checkout-pricing-switcher" 
                                       data-cart-key="<?php echo $cart_key; ?>"
                                       data-download-id="<?php echo $download_id; ?>" 
                                       value="<?php echo $key; ?>"
                                       <?php checked($key, $current_price_id); ?>>
                                <span class="option-details">
                                    <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                    <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="pricing-update-status" style="display: none;">
                    <span class="updating-message"><?php _e('Updating cart...', 'edd-ajax-vps'); ?></span>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX cart item price update
     */
    public function ajax_update_cart_item_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'edd_ajax_pricing_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $cart_key = sanitize_text_field($_POST['cart_key']);
        $download_id = absint($_POST['download_id']);
        $new_price_id = absint($_POST['price_id']);
        
        if (!$cart_key || !$download_id || !isset($_POST['price_id'])) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }
        
        // Get current cart
        $cart_contents = edd_get_cart_contents();
        
        if (!isset($cart_contents[$cart_key])) {
            wp_send_json_error(array('message' => 'Cart item not found'));
        }
        
        $prices = $this->get_variable_prices_with_lifetime($download_id);
        if (!isset($prices[$new_price_id])) {
            wp_send_json_error(array('message' => 'Invalid price option'));
        }
        
        // Update cart item with new price option
        $cart_item = $cart_contents[$cart_key];
        $cart_item['options']['price_id'] = $new_price_id;
        
        // Remove old item and add new one
        edd_remove_from_cart($cart_key);
        $new_cart_key = edd_add_to_cart($download_id, $cart_item['options']);
        
        if ($new_cart_key !== false) {
            $selected_price = $prices[$new_price_id];
            $new_total = edd_get_cart_total();
            $new_subtotal = edd_get_cart_subtotal();
            
            $response_data = array(
                'success' => true,
                'new_cart_key' => $new_cart_key,
                'old_cart_key' => $cart_key,
                'price_name' => $selected_price['name'],
                'price_amount' => edd_currency_filter(edd_format_amount($selected_price['amount'])),
                'cart_total' => edd_currency_filter($new_total),
                'cart_subtotal' => edd_currency_filter($new_subtotal)
            );
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array('message' => 'Failed to update cart item'));
        }
    }
    
    /**
     * Add custom CSS styles
     */
    public function add_custom_styles() {
        if (!edd_is_checkout() && !is_singular('download')) {
            return;
        }
        ?>
        <style type="text/css">
        /* Product Page Styles */
        .edd-ajax-pricing-wrapper {
            margin: 20px 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .edd-pricing-group {
            border-bottom: 1px solid #e0e0e0;
            background: #fafafa;
        }
        
        .edd-pricing-group:last-child {
            border-bottom: none;
        }
        
        .pricing-group-title {
            margin: 0;
            padding: 15px 20px;
            background: #f0f0f0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .annual-group .pricing-group-title {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .lifetime-group .pricing-group-title {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .pricing-options {
            padding: 15px 20px;
        }
        
        .pricing-option {
            display: flex;
            align-items: center;
            padding: 10px 0;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .pricing-option:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .pricing-option input[type="radio"] {
            margin: 0 12px 0 0;
        }
        
        .option-name {
            flex: 1;
            font-weight: 500;
        }
        
        .option-price {
            font-weight: 600;
            color: #2c5282;
        }
        
        .edd-ajax-price-display {
            padding: 20px;
            background: #fff;
            text-align: center;
            border-top: 2px solid #f0f0f0;
        }
        
        .edd-price-wrap {
            font-size: 24px;
            font-weight: 700;
            color: #2c5282;
        }
        
        .edd-loading-indicator {
            color: #666;
            font-style: italic;
        }
        
        .original-price {
            text-decoration: line-through;
            color: #999;
        }
        
        .discounted-price {
            color: #27ae60;
            font-weight: bold;
        }
        
        .edd-ajax-pricing-wrapper.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Checkout Page Styles */
        .edd-checkout-pricing-switcher {
            margin: 10px 0;
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        
        .pricing-switcher-toggle {
            background: #fff;
        }
        
        .toggle-pricing-options {
            width: 100%;
            padding: 12px 15px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
            color: #0073aa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s ease;
            position: relative;
            z-index: 10;
        }
        
        .toggle-pricing-options:hover {
            background-color: #f8f9fa;
        }
        
        .toggle-pricing-options:focus {
            outline: 2px solid #0073aa;
            outline-offset: -2px;
        }
        
        .toggle-pricing-options .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }
        
        .pricing-options-panel {
            border-top: 1px solid #e0e0e0;
            background: #fafafa;
            display: none;
            position: relative;
            z-index: 5;
        }
        
        .checkout-pricing-group {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .checkout-pricing-group:last-child {
            border-bottom: none;
        }
        
        .checkout-group-title {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .annual-group .checkout-group-title {
            color: #1976d2;
        }
        
        .lifetime-group .checkout-group-title {
            color: #388e3c;
        }
        
        .checkout-pricing-option {
            display: flex;
            align-items: center;
            padding: 8px 0;
            cursor: pointer;
            font-size: 14px;
        }
        
        .checkout-pricing-option input[type="radio"] {
            margin: 0 10px 0 0;
        }
        
        .checkout-pricing-option .option-details {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .checkout-pricing-option .option-name {
            font-weight: 500;
        }
        
        .checkout-pricing-option .option-price {
            font-weight: 600;
            color: #2c5282;
        }
        
        .pricing-update-status {
            padding: 15px;
            text-align: center;
            background: #fff3cd;
            border-top: 1px solid #ffeaa7;
            color: #856404;
            font-style: italic;
        }
        
        .edd-checkout-pricing-switcher.updating {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .edd-checkout-pricing-switcher.updating .pricing-options-panel {
            pointer-events: none;
        }
        
        .checkout-pricing-option:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .checkout-pricing-option input[type="radio"]:focus {
            outline: 2px solid #0073aa;
            outline-offset: 1px;
        }
        
        @media (max-width: 768px) {
            .pricing-group-title,
            .pricing-options {
                padding: 12px 15px;
            }
            
            .edd-ajax-price-display {
                padding: 15px;
            }
            
            .edd-price-wrap {
                font-size: 20px;
            }
            
            .checkout-pricing-option .option-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .toggle-pricing-options {
                font-size: 13px;
                padding: 10px 12px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Add default selection script to footer
     */
    public function add_default_selection_script() {
        if (!edd_is_checkout() && !is_singular('download')) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Ensure default options are selected on page load
            $('.edd-ajax-pricing-wrapper').each(function() {
                var $wrapper = $(this);
                var defaultPrice = $wrapper.data('default-price');
                
                // Check the default option if none is selected
                var $checkedInput = $wrapper.find('input[type="radio"]:checked');
                if ($checkedInput.length === 0 && defaultPrice) {
                    $wrapper.find('input[value="' + defaultPrice + '"]').prop('checked', true);
                }
                
                // Update hidden input
                var selectedValue = $wrapper.find('input[type="radio"]:checked').val();
                if (selectedValue) {
                    $wrapper.find('.edd-selected-price-input').val(selectedValue);
                }
            });
            
            // Handle checkout pricing toggle
            $(document).on('click', '.toggle-pricing-options', function(e) {
                e.preventDefault();
                var $button = $(this);
                var $panel = $button.closest('.edd-checkout-pricing-switcher').find('.pricing-options-panel');
                var $icon = $button.find('.dashicons');
                
                $panel.slideToggle(300, function() {
                    $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize the plugin
new EDD_AJAX_Variable_Pricing_Switcher();

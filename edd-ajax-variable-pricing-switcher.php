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
     * Enqueue JavaScript and CSS
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
        
        wp_enqueue_style(
            'edd-ajax-pricing-switcher-css',
            plugin_dir_url(__FILE__) . 'edd-ajax-pricing-switcher.css',
            array(),
            '2.0.0'
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
     * Handle AJAX price switching for product pages
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
        
        // Get current price ID - handle both formats
        $current_price_id = 0;
        if (isset($item['options']['price_id'])) {
            $current_price_id = $item['options']['price_id'];
        } elseif (isset($item['options'][0])) {
            $current_price_id = $item['options'][0];
        }
        
        $current_price_name = isset($prices[$current_price_id]) ? $prices[$current_price_id]['name'] : 'Unknown';
        
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
        
        // Generate unique ID for this switcher
        $switcher_id = 'edd_price_switcher_' . $download_id . '_' . $cart_key;
        
        ?>
        <div class="edd-simple-pricing-switcher" data-cart-key="<?php echo esc_attr($cart_key); ?>" data-download-id="<?php echo esc_attr($download_id); ?>">
            
            <!-- Current Selection Display -->
            <div class="current-selection">
                <strong><?php _e('Current License:', 'edd-ajax-vps'); ?></strong> 
                <span class="current-license-name"><?php echo esc_html($current_price_name); ?></span>
                
                <button type="button" 
                        onclick="togglePricingOptions('<?php echo esc_js($switcher_id); ?>')" 
                        class="change-license-btn">
                    <?php _e('Change License', 'edd-ajax-vps'); ?>
                </button>
            </div>
            
            <!-- Pricing Options (Initially Hidden) -->
            <div id="<?php echo esc_attr($switcher_id); ?>" class="pricing-options-container" style="display: none;">
                
                <div class="pricing-options-header">
                    <h4><?php _e('Select License Type:', 'edd-ajax-vps'); ?></h4>
                    <button type="button" onclick="document.getElementById('<?php echo esc_js($switcher_id); ?>').style.display = 'none'" class="close-options-btn">×</button>
                </div>
                
                <form class="pricing-options-form">
                    
                    <?php if (!empty($annual_prices)): ?>
                        <div class="price-group annual-group">
                            <h5 class="group-title"><?php _e('Annual Licenses', 'edd-ajax-vps'); ?></h5>
                            <?php foreach ($annual_prices as $key => $price): ?>
                                <label class="price-option">
                                    <input type="radio" 
                                           name="selected_price_<?php echo esc_attr($download_id); ?>_<?php echo esc_attr($cart_key); ?>" 
                                           value="<?php echo esc_attr($key); ?>"
                                           data-cart-key="<?php echo esc_attr($cart_key); ?>"
                                           data-download-id="<?php echo esc_attr($download_id); ?>"
                                           data-price-name="<?php echo esc_attr($price['name']); ?>"
                                           class="edd-simple-price-radio"
                                           <?php checked($key, $current_price_id); ?>>
                                    <span class="option-info">
                                        <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                        <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($lifetime_prices)): ?>
                        <div class="price-group lifetime-group">
                            <h5 class="group-title"><?php _e('Lifetime Licenses', 'edd-ajax-vps'); ?></h5>
                            <?php foreach ($lifetime_prices as $key => $price): ?>
                                <label class="price-option">
                                    <input type="radio" 
                                           name="selected_price_<?php echo esc_attr($download_id); ?>_<?php echo esc_attr($cart_key); ?>" 
                                           value="<?php echo esc_attr($key); ?>"
                                           data-cart-key="<?php echo esc_attr($cart_key); ?>"
                                           data-download-id="<?php echo esc_attr($download_id); ?>"
                                           data-price-name="<?php echo esc_attr($price['name']); ?>"
                                           class="edd-simple-price-radio"
                                           <?php checked($key, $current_price_id); ?>>
                                    <span class="option-info">
                                        <span class="option-name"><?php echo esc_html($price['name']); ?></span>
                                        <span class="option-price"><?php echo edd_currency_filter(edd_format_amount($price['amount'])); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="pricing-actions">
                        <button type="button" 
                                onclick="updatePricingInline('<?php echo esc_js($switcher_id); ?>', '<?php echo esc_js($cart_key); ?>', '<?php echo esc_js($download_id); ?>')"
                                class="update-price-btn">
                            <?php _e('Update License', 'edd-ajax-vps'); ?>
                        </button>
                        
                        <button type="button" 
                                onclick="document.getElementById('<?php echo esc_js($switcher_id); ?>').style.display = 'none'"
                                class="cancel-price-btn">
                            <?php _e('Cancel', 'edd-ajax-vps'); ?>
                        </button>
                    </div>
                    
                </form>
                
                <div class="pricing-status" style="display: none;"></div>
                
            </div>
            
        </div>
        
        <script>
        // Inline JavaScript functions for this specific switcher
        if (typeof window.eddPricingSwitcherInitialized === 'undefined') {
            window.eddPricingSwitcherInitialized = true;
            
            // Global toggle function
            window.togglePricingOptions = function(targetId) {
                // Hide all other pricing containers
                var allContainers = document.querySelectorAll('.pricing-options-container');
                allContainers.forEach(function(container) {
                    if (container.id !== targetId) {
                        container.style.display = 'none';
                    }
                });
                
                // Toggle the target container
                var targetContainer = document.getElementById(targetId);
                if (targetContainer) {
                    if (targetContainer.style.display === 'none' || !targetContainer.style.display) {
                        targetContainer.style.display = 'block';
                    } else {
                        targetContainer.style.display = 'none';
                    }
                }
            };
            
            // Global update function
            window.updatePricingInline = function(targetId, cartKey, downloadId) {
                var container = document.getElementById(targetId);
                if (!container) {
                    alert('Container not found');
                    return;
                }
                
                var selectedRadio = container.querySelector('.edd-simple-price-radio:checked');
                if (!selectedRadio) {
                    alert('Please select a license option.');
                    return;
                }
                
                var priceId = selectedRadio.value;
                var priceName = selectedRadio.getAttribute('data-price-name');
                
                if (!cartKey || !downloadId || !priceId) {
                    alert('Missing required data');
                    return;
                }
                
                // Show loading
                var statusDiv = container.querySelector('.pricing-status');
                if (statusDiv) {
                    statusDiv.className = 'pricing-status loading';
                    statusDiv.innerHTML = 'Updating your cart...';
                    statusDiv.style.display = 'block';
                }
                
                // Disable button
                var button = container.querySelector('.update-price-btn');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = 'Updating...';
                }
                
                // Make AJAX request
                if (typeof jQuery !== 'undefined' && typeof edd_ajax_switcher !== 'undefined') {
                    var ajaxData = {
                        action: 'edd_update_cart_item_price',
                        cart_key: cartKey,
                        download_id: downloadId,
                        price_id: priceId,
                        nonce: edd_ajax_switcher.nonce
                    };
                    
                    jQuery.ajax({
                        type: 'POST',
                        url: edd_ajax_switcher.ajaxurl,
                        data: ajaxData,
                        dataType: 'json',
                        timeout: 15000,
                        success: function(response) {
                            if (response.success && response.data) {
                                // Update current license display
                                var switcher = container.closest('.edd-simple-pricing-switcher');
                                if (switcher) {
                                    var currentName = switcher.querySelector('.current-license-name');
                                    if (currentName) {
                                        currentName.textContent = priceName;
                                    }
                                }
                                
                                // Show success
                                if (statusDiv) {
                                    statusDiv.className = 'pricing-status success';
                                    statusDiv.innerHTML = '✓ License updated successfully! Refreshing...';
                                }
                                
                                // Update current license display
                                var switcher = container.closest('.edd-simple-pricing-switcher');
                                if (switcher) {
                                    var currentName = switcher.querySelector('.current-license-name');
                                    if (currentName) {
                                        currentName.textContent = priceName;
                                    }
                                }
                                
                                // Update cart key for future requests
                                if (switcher) {
                                    switcher.setAttribute('data-cart-key', response.data.new_cart_key);
                                    var radios = switcher.querySelectorAll('.edd-simple-price-radio');
                                    radios.forEach(function(radio) {
                                        radio.setAttribute('data-cart-key', response.data.new_cart_key);
                                    });
                                }
                                
                                // Close panel
                                setTimeout(function() {
                                    container.style.display = 'none';
                                }, 1000);
                                
                                // Simple approach: Just reload the page after a short delay
                                // This ensures all cart calculations are 100% accurate
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                                
                            } else {
                                var errorMsg = response.data && response.data.message ? 
                                    response.data.message : 'Failed to update license.';
                                
                                if (statusDiv) {
                                    statusDiv.className = 'pricing-status error';
                                    statusDiv.innerHTML = errorMsg;
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = status === 'timeout' ? 
                                'Update timed out. Please try again.' : 
                                'Network error. Please try again.';
                            
                            if (statusDiv) {
                                statusDiv.className = 'pricing-status error';
                                statusDiv.innerHTML = errorMsg;
                            }
                        },
                        complete: function() {
                            if (button) {
                                button.disabled = false;
                                button.innerHTML = 'Update License';
                            }
                        }
                    });
                } else {
                    alert('AJAX not available');
                }
            };
        }
        </script>
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
        
        // Fix: Don't use empty() for cart_key since "0" is a valid cart key
        if (!isset($_POST['cart_key']) || $download_id === 0 || !isset($_POST['price_id'])) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }
        
        // Get current cart
        $cart_contents = edd_get_cart_contents();
        
        // Check if cart key exists (handle both string and numeric keys)
        $cart_key_exists = false;
        $actual_cart_item = null;
        
        // Try exact match first
        if (isset($cart_contents[$cart_key])) {
            $cart_key_exists = true;
            $actual_cart_item = $cart_contents[$cart_key];
        } 
        // Try numeric conversion
        elseif (is_numeric($cart_key) && isset($cart_contents[(int)$cart_key])) {
            $cart_key = (int)$cart_key;
            $cart_key_exists = true;
            $actual_cart_item = $cart_contents[$cart_key];
        }
        // Try string conversion  
        elseif (isset($cart_contents[(string)$cart_key])) {
            $cart_key = (string)$cart_key;
            $cart_key_exists = true;
            $actual_cart_item = $cart_contents[$cart_key];
        }
        
        if (!$cart_key_exists) {
            wp_send_json_error(array('message' => 'Cart item not found'));
        }
        
        $prices = $this->get_variable_prices_with_lifetime($download_id);
        if (!isset($prices[$new_price_id])) {
            wp_send_json_error(array('message' => 'Invalid price option'));
        }
        
        // Update cart item with new price option
        $cart_item = $actual_cart_item;
        $cart_item['options']['price_id'] = $new_price_id;
        
        // Remove old item and add new one
        edd_remove_from_cart($cart_key);
        $new_cart_key = edd_add_to_cart($download_id, $cart_item['options']);
        
        if ($new_cart_key !== false) {
            $selected_price = $prices[$new_price_id];
            $new_total = edd_get_cart_total();
            $new_subtotal = edd_get_cart_subtotal();
            $new_tax = edd_use_taxes() ? edd_get_cart_tax() : 0;
            $new_discount = edd_get_cart_discounted_amount();
            
            $response_data = array(
                'success' => true,
                'new_cart_key' => $new_cart_key,
                'old_cart_key' => $cart_key,
                'download_id' => $download_id,
                'price_name' => $selected_price['name'],
                'price_amount' => edd_currency_filter(edd_format_amount($selected_price['amount'])),
                'cart_total' => edd_currency_filter($new_total),
                'cart_subtotal' => edd_currency_filter($new_subtotal),
                'cart_tax' => edd_currency_filter(edd_format_amount($new_tax)),
                'cart_discount' => edd_currency_filter(edd_format_amount($new_discount)),
                'cart_total_raw' => $new_total,
                'cart_subtotal_raw' => $new_subtotal,
                'cart_tax_raw' => $new_tax,
                'cart_discount_raw' => $new_discount,
                'cart_html' => $this->get_updated_cart_html()
            );
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array('message' => 'Failed to update cart item'));
        }
    }
    
    /**
     * Get updated cart HTML for AJAX response
     */
    private function get_updated_cart_html() {
        if (!edd_is_checkout()) {
            return '';
        }
        
        ob_start();
        
        // Get the updated cart contents
        $cart_items = edd_get_cart_contents();
        if (empty($cart_items)) {
            return '';
        }
        
        // Generate the cart rows HTML
        foreach ($cart_items as $key => $item) {
            ?>
            <tr class="edd_cart_item" id="edd_cart_item_<?php echo esc_attr($key) . '_' . esc_attr($item['id']); ?>" data-download-id="<?php echo esc_attr($item['id']); ?>">
                <?php do_action('edd_checkout_table_body_first', $item); ?>
                <td class="edd_cart_item_name">
                    <?php
                    if (current_theme_supports('post-thumbnails') && has_post_thumbnail($item['id'])) {
                        echo '<div class="edd_cart_item_image">';
                        echo get_the_post_thumbnail($item['id'], apply_filters('edd_checkout_image_size', array(25, 25)));
                        echo '</div>';
                    }
                    $item_title = edd_get_cart_item_name($item);
                    echo '<span class="edd_checkout_cart_item_title">' . esc_html($item_title) . '</span>';
                    
                    // Include our pricing switcher
                    do_action('edd_checkout_cart_item_title_after', $item, $key);
                    ?>
                </td>
                <td class="edd_cart_item_price">
                    <?php
                    echo edd_cart_item_price($item['id'], $item['options']);
                    do_action('edd_checkout_cart_item_price_after', $item);
                    ?>
                </td>
                <td class="edd_cart_actions">
                    <?php if (edd_item_quantities_enabled() && !edd_download_quantities_disabled($item['id'])): ?>
                        <input type="number" min="1" step="1" name="edd-cart-download-<?php echo esc_attr($key); ?>-quantity" data-key="<?php echo esc_attr($key); ?>" class="edd-input edd-item-quantity" value="<?php echo esc_attr(edd_get_cart_item_quantity($item['id'], $item['options'])); ?>"/>
                        <input type="hidden" name="edd-cart-downloads[]" value="<?php echo esc_attr($item['id']); ?>"/>
                        <input type="hidden" name="edd-cart-download-<?php echo esc_attr($key); ?>-options" value="<?php echo esc_attr(json_encode($item['options'])); ?>"/>
                    <?php endif; ?>
                    <?php do_action('edd_cart_actions', $item, $key); ?>
                    <a class="edd_cart_remove_item_btn" href="<?php echo esc_url(wp_nonce_url(edd_remove_item_url($key), 'edd-remove-from-cart-' . sanitize_key($key), 'edd_remove_from_cart_nonce')); ?>"><?php esc_html_e('Remove', 'easy-digital-downloads'); ?></a>
                </td>
                <?php do_action('edd_checkout_table_body_last', $item); ?>
            </tr>
            <?php
        }
        
        return ob_get_clean();
    }
}

// Initialize the plugin
new EDD_AJAX_Variable_Pricing_Switcher();
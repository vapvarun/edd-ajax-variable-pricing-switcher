jQuery(document).ready(function($) {
    
    // Check if the required variables are available
    if (typeof edd_ajax_switcher === 'undefined') {
        console.warn('EDD AJAX Switcher: Required variables not found');
        return;
    }
    
    // Handle pricing option changes on product pages
    $(document).on('change', '.edd-ajax-pricing-switcher', function() {
        var $this = $(this);
        var $wrapper = $this.closest('.edd-ajax-pricing-wrapper');
        var $priceDisplay = $wrapper.find('.edd-price-wrap');
        var $loadingIndicator = $wrapper.find('.edd-loading-indicator');
        var $hiddenInput = $wrapper.find('.edd-selected-price-input');
        
        var downloadId = $this.data('download-id');
        var priceId = $this.val();
        
        if (!downloadId || !priceId) {
            console.error('Missing download ID or price ID');
            return;
        }
        
        // Show loading state
        $wrapper.addClass('loading');
        $priceDisplay.hide();
        $loadingIndicator.show();
        
        // Make AJAX request
        $.ajax({
            type: 'POST',
            url: edd_ajax_switcher.ajaxurl,
            data: {
                action: 'edd_switch_price',
                download_id: downloadId,
                price_id: priceId,
                nonce: edd_ajax_switcher.nonce
            },
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            success: function(response) {
                if (response.success && response.data) {
                    // Update price display
                    $priceDisplay.html(response.data.price_html);
                    
                    // Update hidden input for form submission
                    if ($hiddenInput.length) {
                        $hiddenInput.val(priceId);
                    }
                    
                    // Trigger custom event for other scripts/plugins
                    $wrapper.trigger('edd_price_updated', [response.data, priceId]);
                    
                    // Update any EDD purchase forms on the page
                    updatePurchaseForm(downloadId, priceId, response.data);
                    
                } else {
                    var errorMessage = response.data && response.data.message ? 
                        response.data.message : edd_ajax_switcher.error_text;
                    console.error('EDD Price Update Error:', errorMessage);
                    showError($wrapper, errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                var errorMessage = status === 'timeout' ? 
                    'Request timed out. Please try again.' : 
                    edd_ajax_switcher.error_text;
                showError($wrapper, errorMessage);
            },
            complete: function() {
                // Hide loading state
                $wrapper.removeClass('loading');
                $priceDisplay.show();
                $loadingIndicator.hide();
            }
        });
    });
    
    // Handle checkout page pricing changes
    $(document).on('change', '.edd-checkout-pricing-switcher', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        
        var $this = $(this);
        var $switcherWrapper = $this.closest('.edd-checkout-pricing-switcher');
        var $statusDiv = $switcherWrapper.find('.pricing-update-status');
        
        var cartKey = $this.data('cart-key');
        var downloadId = $this.data('download-id');
        var priceId = $this.val();
        
        if (!cartKey || !downloadId || !priceId) {
            console.error('Missing required cart data');
            return;
        }
        
        // Show updating status
        $switcherWrapper.addClass('updating');
        $statusDiv.html('<span class="updating-message">' + edd_ajax_switcher.updating_cart_text + '</span>').show();
        
        // Make AJAX request to update cart item
        $.ajax({
            type: 'POST',
            url: edd_ajax_switcher.ajaxurl,
            data: {
                action: 'edd_update_cart_item_price',
                cart_key: cartKey,
                download_id: downloadId,
                price_id: priceId,
                nonce: edd_ajax_switcher.nonce
            },
            dataType: 'json',
            timeout: 15000, // 15 second timeout for cart updates
            success: function(response) {
                if (response.success && response.data) {
                    // Update the cart key data attribute
                    $switcherWrapper.attr('data-cart-key', response.data.new_cart_key);
                    $switcherWrapper.find('.edd-checkout-pricing-switcher').attr('data-cart-key', response.data.new_cart_key);
                    
                    // Update radio button names to prevent conflicts
                    $switcherWrapper.find('input[type="radio"]').attr('name', 'checkout_price_option_' + response.data.new_cart_key);
                    
                    // Show success message briefly
                    $statusDiv.html('<span class="success-message" style="color: #27ae60;">✓ Updated to ' + response.data.price_name + '</span>');
                    
                    // Auto-close panel after successful update
                    setTimeout(function() {
                        $statusDiv.fadeOut();
                        var $panel = $switcherWrapper.find('.pricing-options-panel');
                        var $icon = $switcherWrapper.find('.toggle-pricing-options .dashicons');
                        $panel.slideUp(300, function() {
                            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                        });
                    }, 2000);
                    
                    // Update cart totals display
                    updateCartTotals(response.data);
                    
                    // Trigger custom event
                    $('body').trigger('edd_cart_item_price_updated', [response.data]);
                    
                } else {
                    var errorMessage = response.data && response.data.message ? 
                        response.data.message : edd_ajax_switcher.error_text;
                    console.error('Cart Update Error:', errorMessage);
                    showCheckoutError($switcherWrapper, errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.error('Cart Update AJAX Error:', status, error);
                var errorMessage = status === 'timeout' ? 
                    'Cart update timed out. Please refresh the page.' : 
                    edd_ajax_switcher.error_text;
                showCheckoutError($switcherWrapper, errorMessage);
            },
            complete: function() {
                // Hide updating state
                $switcherWrapper.removeClass('updating');
            }
        });
    });
    
    // Update cart totals after price change
    function updateCartTotals(responseData) {
        if (!responseData) return;
        
        // Update cart total with multiple selectors
        var totalSelectors = [
            '.edd_cart_total',
            '.edd-cart-meta .cart-total',
            '#edd_checkout_cart_total',
            '.edd-cart-total-amount',
            '[data-edd-cart-total]'
        ];
        
        totalSelectors.forEach(function(selector) {
            var $elements = $(selector);
            if ($elements.length && responseData.cart_total) {
                $elements.html(responseData.cart_total);
            }
        });
        
        // Update subtotal with multiple selectors
        var subtotalSelectors = [
            '.edd_cart_subtotal',
            '.edd-cart-meta .cart-subtotal',
            '#edd_checkout_cart_subtotal',
            '.edd-cart-subtotal-amount',
            '[data-edd-cart-subtotal]'
        ];
        
        subtotalSelectors.forEach(function(selector) {
            var $elements = $(selector);
            if ($elements.length && responseData.cart_subtotal) {
                $elements.html(responseData.cart_subtotal);
            }
        });
        
        // Update any price displays in the cart
        $('.edd-cart-item-price').each(function() {
            var $this = $(this);
            var itemCartKey = $this.data('cart-key');
            if (itemCartKey === responseData.old_cart_key) {
                $this.html(responseData.price_amount);
                $this.attr('data-cart-key', responseData.new_cart_key);
            }
        });
        
        // Trigger EDD's cart update events
        $('body').trigger('edd_cart_updated');
        $('body').trigger('edd_quantity_updated');
    }
    
    // Update EDD purchase forms with new price selection
    function updatePurchaseForm(downloadId, priceId, priceData) {
        if (!downloadId || !priceId || !priceData) return;
        
        // Update any existing EDD purchase forms
        $('form.edd_download_purchase_form').each(function() {
            var $form = $(this);
            var formDownloadId = $form.find('input[name="download_id"]').val();
            
            if (formDownloadId == downloadId) {
                // Update the price option selection in various input formats
                var priceInputSelectors = [
                    'input[name="edd_options[' + downloadId + ']"]',
                    'input[name="edd_options[' + downloadId + '][price_id]"]',
                    'select[name="edd_options[' + downloadId + '][price_id]"]'
                ];
                
                priceInputSelectors.forEach(function(selector) {
                    var $input = $form.find(selector);
                    if ($input.length) {
                        $input.val(priceId);
                    }
                });
                
                // Update any price displays in the form
                $form.find('.edd_price').html(priceData.price_html);
                
                // Update purchase button if needed
                var $button = $form.find('.edd-add-to-cart');
                if ($button.length && priceData.price_name) {
                    var originalText = $button.data('original-text') || $button.text();
                    if (!$button.data('original-text')) {
                        $button.data('original-text', originalText);
                    }
                    
                    // Optionally update button text with selected option
                    // $button.text('Add ' + priceData.price_name + ' to Cart');
                }
            }
        });
    }
    
    // Show error message for product pages
    function showError($wrapper, message) {
        if (!$wrapper.length || !message) return;
        
        var $errorDiv = $wrapper.find('.edd-pricing-error');
        if ($errorDiv.length === 0) {
            $errorDiv = $('<div class="edd-pricing-error" style="color: #d32f2f; padding: 10px; text-align: center; background: #ffebee; border-radius: 4px; margin: 10px 0; border: 1px solid #ffcdd2;"></div>');
            $wrapper.find('.edd-ajax-price-display').append($errorDiv);
        }
        
        $errorDiv.html('⚠ ' + message).show();
        
        // Hide error after 5 seconds
        setTimeout(function() {
            $errorDiv.fadeOut();
        }, 5000);
    }
    
    // Show error message for checkout page
    function showCheckoutError($wrapper, message) {
        if (!$wrapper.length || !message) return;
        
        var $statusDiv = $wrapper.find('.pricing-update-status');
        $statusDiv.html('<span class="error-message" style="color: #d32f2f; font-weight: bold;">✗ ' + message + '</span>').show();
        
        setTimeout(function() {
            $statusDiv.fadeOut();
        }, 5000);
    }
    
    // Handle checkout pricing toggle
    $(document).on('click', '.toggle-pricing-options', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var $switcher = $button.closest('.edd-checkout-pricing-switcher');
        var $panel = $switcher.find('.pricing-options-panel');
        var $icon = $button.find('.dashicons');
        
        // Close other open panels first
        $('.edd-checkout-pricing-switcher').not($switcher).each(function() {
            var $otherPanel = $(this).find('.pricing-options-panel');
            var $otherIcon = $(this).find('.dashicons');
            if ($otherPanel.is(':visible')) {
                $otherPanel.slideUp(200);
                $otherIcon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });
        
        // Toggle current panel
        if ($panel.length) {
            if ($panel.is(':visible')) {
                $panel.slideUp(300, function() {
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                });
            } else {
                $panel.slideDown(300, function() {
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                });
            }
        }
    });
    
    // Prevent panel from closing when clicking inside it
    $(document).on('click', '.pricing-options-panel', function(e) {
        e.stopPropagation();
    });
    
    // Close panels when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.edd-checkout-pricing-switcher').length) {
            $('.pricing-options-panel:visible').slideUp(200, function() {
                $('.toggle-pricing-options .dashicons')
                    .removeClass('dashicons-arrow-up-alt2')
                    .addClass('dashicons-arrow-down-alt2');
            });
        }
    });
    
    // Handle discount code application/removal
    $('body').on('edd_discount_applied edd_discount_removed', function(event, data) {
        // Small delay to ensure discount is processed
        setTimeout(function() {
            // Refresh all price displays when discounts change
            $('.edd-ajax-pricing-switcher:checked').each(function() {
                $(this).trigger('change');
            });
        }, 100);
        
        // Refresh checkout page if applicable
        if (edd_ajax_switcher.is_checkout === '1') {
            setTimeout(function() {
                // Refresh cart totals by triggering a small cart update
                $('.edd-checkout-pricing-switcher:checked').first().trigger('change');
            }, 500);
        }
    });
    
    // Initialize default selections on page load
    function initializeDefaultSelections() {
        $('.edd-ajax-pricing-wrapper').each(function() {
            var $wrapper = $(this);
            var defaultPrice = $wrapper.data('default-price');
            
            if (!defaultPrice) return;
            
            // Check if any option is already selected
            var $checkedInput = $wrapper.find('input[type="radio"]:checked');
            if ($checkedInput.length === 0) {
                // Select the default price option
                var $defaultInput = $wrapper.find('input[value="' + defaultPrice + '"]');
                if ($defaultInput.length) {
                    $defaultInput.prop('checked', true);
                }
            }
            
            // Update hidden input with current selection
            var selectedValue = $wrapper.find('input[type="radio"]:checked').val();
            if (selectedValue) {
                var $hiddenInput = $wrapper.find('.edd-selected-price-input');
                if ($hiddenInput.length) {
                    $hiddenInput.val(selectedValue);
                }
            }
        });
    }
    
    // Initialize on page load
    initializeDefaultSelections();
    
    // Handle page visibility changes (when user returns to tab)
    if (typeof document.hidden !== "undefined") {
        document.addEventListener("visibilitychange", function() {
            if (!document.hidden) {
                // Refresh prices when user returns to ensure they're current
                setTimeout(function() {
                    $('.edd-ajax-pricing-switcher:checked').each(function() {
                        $(this).trigger('change');
                    });
                }, 1000);
            }
        });
    }
    
    // Prevent form submission while pricing is updating
    $(document).on('submit', 'form.edd_download_purchase_form', function(e) {
        var $form = $(this);
        var $loadingWrappers = $form.find('.edd-ajax-pricing-wrapper.loading');
        
        if ($loadingWrappers.length > 0) {
            e.preventDefault();
            alert(edd_ajax_switcher.loading_text + ' Please wait...');
            return false;
        }
    });
    
    // Handle EDD's existing events for integration
    $('body').on('edd_cart_item_added', function(event, response) {
        // Reinitialize pricing switchers for newly added items
        setTimeout(function() {
            initializeDefaultSelections();
        }, 100);
    });
    
    // Support for EDD's checkout page updates
    $('body').on('edd_checkout_page_updated', function() {
        // Reinitialize any pricing switchers that may have been reloaded
        setTimeout(function() {
            initializeDefaultSelections();
            
            // Ensure toggle functionality is working for new elements
            $('.edd-checkout-pricing-switcher').each(function() {
                var $wrapper = $(this);
                var $toggle = $wrapper.find('.toggle-pricing-options');
                
                // Check if toggle needs reinitialization
                if ($toggle.length && !$toggle.data('events-bound')) {
                    $toggle.data('events-bound', true);
                }
            });
        }, 200);
    });
    
    // Handle window resize for responsive adjustments
    $(window).on('resize', debounce(function() {
        // Adjust any pricing panels that might be open
        $('.pricing-options-panel:visible').each(function() {
            var $panel = $(this);
            // Force recalculation of panel height
            $panel.css('height', 'auto');
        });
    }, 250));
    
    // Utility function for debouncing
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Debug function (can be removed in production)
    function debugLog(message, data) {
        if (typeof console !== 'undefined' && console.log) {
            console.log('EDD AJAX Pricing:', message, data || '');
        }
    }
    
    // Expose some functions globally for debugging/integration
    window.eddAjaxPricing = {
        updateCartTotals: updateCartTotals,
        initializeDefaults: initializeDefaultSelections,
        version: '2.0.0'
    };
    
    // Final initialization check
    setTimeout(function() {
        if ($('.edd-ajax-pricing-wrapper').length > 0) {
            debugLog('Initialized ' + $('.edd-ajax-pricing-wrapper').length + ' pricing wrappers');
        }
        if ($('.edd-checkout-pricing-switcher').length > 0) {
            debugLog('Initialized ' + $('.edd-checkout-pricing-switcher').length + ' checkout switchers');
        }
    }, 500);
});

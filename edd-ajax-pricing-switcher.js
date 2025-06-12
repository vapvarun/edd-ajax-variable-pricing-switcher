jQuery(document).ready(function($) {
    
    // Check if required variables are available
    if (typeof edd_ajax_switcher === 'undefined') {
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
            timeout: 10000,
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
                    showError($wrapper, errorMessage);
                }
            },
            error: function(xhr, status, error) {
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
    
    // Handle discount code application/removal
    $('body').on('edd_discount_applied edd_discount_removed', function(event, data) {
        // Small delay to ensure discount is processed
        setTimeout(function() {
            // Refresh all price displays when discounts change
            $('.edd-ajax-pricing-switcher:checked').each(function() {
                $(this).trigger('change');
            });
        }, 100);
    });
    
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
});

// Global functions for inline onclick handlers
function togglePricingOptions(targetId) {
    // Prevent multiple rapid clicks
    var targetContainer = document.getElementById(targetId);
    if (!targetContainer) {
        return;
    }
    
    if (targetContainer.getAttribute('data-toggling') === 'true') {
        return; // Already toggling
    }
    
    targetContainer.setAttribute('data-toggling', 'true');
    
    // Hide all other pricing containers
    var allContainers = document.querySelectorAll('.pricing-options-container');
    allContainers.forEach(function(container) {
        if (container.id !== targetId) {
            container.style.display = 'none';
        }
    });
    
    // Toggle the target container
    if (targetContainer.style.display === 'none' || !targetContainer.style.display) {
        targetContainer.style.display = 'block';
    } else {
        targetContainer.style.display = 'none';
    }
    
    // Remove toggling flag after short delay
    setTimeout(function() {
        targetContainer.removeAttribute('data-toggling');
    }, 300);
}

function updatePricingInline(targetId, cartKey, downloadId) {
    var container = document.getElementById(targetId);
    if (!container) {
        alert('Container not found');
        return;
    }
    
    // Check if already updating
    if (container.getAttribute('data-updating') === 'true') {
        return; // Prevent multiple simultaneous updates
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
    
    // Mark as updating to prevent double clicks
    container.setAttribute('data-updating', 'true');
    
    // Disable all Change License buttons temporarily
    var allChangeButtons = document.querySelectorAll('.change-license-btn');
    allChangeButtons.forEach(function(btn) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.pointerEvents = 'none';
    });
    
    // Show loading
    var statusDiv = container.querySelector('.pricing-status');
    if (statusDiv) {
        statusDiv.className = 'pricing-status loading';
        statusDiv.innerHTML = 'Updating your cart...';
        statusDiv.style.display = 'block';
    }
    
    // Disable update button
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
                        
                        // Update this specific switcher's cart key for future updates
                        switcher.setAttribute('data-cart-key', response.data.new_cart_key);
                        var radios = switcher.querySelectorAll('.edd-simple-price-radio');
                        radios.forEach(function(radio) {
                            radio.setAttribute('data-cart-key', response.data.new_cart_key);
                        });
                    }
                    
                    // Show success
                    if (statusDiv) {
                        statusDiv.className = 'pricing-status success';
                        statusDiv.innerHTML = '✓ License updated successfully! Refreshing...';
                    }
                    
                    // Close panel
                    setTimeout(function() {
                        container.style.display = 'none';
                    }, 1000);
                    
                    // Reload page to ensure all cart values are correct
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                    
                } else {
                    var errorMsg = response.data && response.data.message ? 
                        response.data.message : 'Failed to update license.';
                    
                    if (statusDiv) {
                        statusDiv.className = 'pricing-status error';
                        statusDiv.innerHTML = '✗ ' + errorMsg;
                    }
                    
                    // Re-enable buttons on error
                    enableAllButtons();
                    container.removeAttribute('data-updating');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = status === 'timeout' ? 
                    'Update timed out. Please try again.' : 
                    'Network error. Please try again.';
                
                if (statusDiv) {
                    statusDiv.className = 'pricing-status error';
                    statusDiv.innerHTML = '✗ ' + errorMsg;
                }
                
                // Re-enable buttons on error
                enableAllButtons();
                container.removeAttribute('data-updating');
            },
            complete: function() {
                // Re-enable update button
                if (button) {
                    button.disabled = false;
                    button.innerHTML = 'Update License';
                }
            }
        });
    } else {
        alert('AJAX not available');
        enableAllButtons();
        container.removeAttribute('data-updating');
    }
}

// Helper function to re-enable all change license buttons
function enableAllButtons() {
    var allChangeButtons = document.querySelectorAll('.change-license-btn');
    allChangeButtons.forEach(function(btn) {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    });
}

// Advanced cart totals update function (kept for potential future use)
function updateCartTotalsAdvanced(responseData) {
    // Update all possible cart total elements
    var totalSelectors = [
        '.edd_cart_total',
        '.edd-cart-total',
        '.edd-cart-total-amount',
        '#edd_checkout_cart_total',
        '.edd_checkout_cart_total',
        '[data-cart-total]',
        '.cart-total',
        '.edd_cart_amount'
    ];
    
    totalSelectors.forEach(function(selector) {
        var elements = document.querySelectorAll(selector);
        elements.forEach(function(element) {
            if (responseData.cart_total) {
                element.innerHTML = responseData.cart_total;
            }
        });
    });
    
    // Update subtotal elements
    var subtotalSelectors = [
        '.edd_cart_subtotal',
        '.edd-cart-subtotal',
        '.edd-cart-subtotal-amount',
        '#edd_checkout_cart_subtotal',
        '[data-cart-subtotal]',
        '.cart-subtotal',
        '.edd_cart_subtotal_amount'
    ];
    
    subtotalSelectors.forEach(function(selector) {
        var elements = document.querySelectorAll(selector);
        elements.forEach(function(element) {
            if (responseData.cart_subtotal) {
                element.innerHTML = responseData.cart_subtotal;
            }
        });
    });
    
    // Update tax elements
    if (responseData.cart_tax) {
        var taxSelectors = [
            '.edd_cart_tax',
            '.edd_cart_tax_amount',
            '.edd-cart-tax',
            '[data-tax]'
        ];
        
        taxSelectors.forEach(function(selector) {
            var elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
                element.innerHTML = responseData.cart_tax;
                if (element.hasAttribute('data-tax') && responseData.cart_tax_raw) {
                    element.setAttribute('data-tax', responseData.cart_tax_raw);
                }
            });
        });
    }
    
    // Update discount elements
    if (responseData.cart_discount) {
        var discountSelectors = [
            '.edd_cart_discount',
            '.edd-cart-discount',
            '.edd_discount_amount'
        ];
        
        discountSelectors.forEach(function(selector) {
            var elements = document.querySelectorAll(selector);
            elements.forEach(function(element) {
                element.innerHTML = responseData.cart_discount;
            });
        });
    }
    
    // Update the specific cart item price in the checkout table
    if (responseData.old_cart_key && responseData.new_cart_key && responseData.price_amount) {
        // Find the old cart item row and update it
        var oldCartRow = document.querySelector('#edd_cart_item_' + responseData.old_cart_key + '_' + responseData.download_id);
        if (oldCartRow) {
            oldCartRow.id = 'edd_cart_item_' + responseData.new_cart_key + '_' + responseData.download_id;
            
            // Update the item price in the specific row
            var priceCell = oldCartRow.querySelector('.edd_cart_item_price');
            if (priceCell) {
                priceCell.innerHTML = responseData.price_amount;
            }
        }
        
        // Fallback: Update any cart item price that matches the download ID
        var allCartItems = document.querySelectorAll('[data-download-id="' + responseData.download_id + '"] .edd_cart_item_price');
        allCartItems.forEach(function(priceElement) {
            priceElement.innerHTML = responseData.price_amount;
        });
    }
    
    // Update data attributes for cart totals
    var cartAmountElements = document.querySelectorAll('.edd_cart_amount[data-total]');
    cartAmountElements.forEach(function(element) {
        if (responseData.cart_total_raw) {
            element.setAttribute('data-total', responseData.cart_total_raw);
        }
        if (responseData.cart_subtotal_raw) {
            element.setAttribute('data-subtotal', responseData.cart_subtotal_raw);
        }
    });
    
    // Trigger EDD events if jQuery is available
    if (typeof jQuery !== 'undefined') {
        jQuery('body').trigger('edd_cart_updated');
        jQuery('body').trigger('edd_quantity_updated');
        jQuery('body').trigger('edd_taxes_updated');
        jQuery('body').trigger('edd_discount_updated');
        jQuery('body').trigger('edd_checkout_cart_item_updated');
        jQuery('body').trigger('edd_checkout_updated');
    }
    
    // Show visual feedback that elements were updated
    var totalElements = document.querySelectorAll('.edd_cart_total, .edd-cart-total, .edd_cart_item_price, .edd_cart_tax_amount, .edd_cart_discount');
    totalElements.forEach(function(element) {
        element.style.backgroundColor = '#d4edda';
        element.style.transition = 'background-color 0.3s';
        setTimeout(function() {
            element.style.backgroundColor = '';
        }, 1000);
    });
}
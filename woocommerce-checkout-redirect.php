<?php
/**
 * WooCommerce Checkout Redirect Fix
 * Forces redirect to checkout instead of cart when adding products via URL
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce_Checkout_Redirect {
    
    public function __construct() {
        // Force redirect to checkout when adding to cart via URL
        add_filter('woocommerce_add_to_cart_redirect', array($this, 'force_checkout_redirect'), 99, 2);
        
        // Disable cart redirect setting for quiz flows
        add_filter('option_woocommerce_cart_redirect_after_add', array($this, 'disable_cart_redirect_for_quiz'), 10, 1);
    }
    
    /**
     * Force redirect to checkout instead of cart for quiz flows
     */
    public function force_checkout_redirect($url, $adding_to_cart = null) {
        // Check if this is a quiz-related add to cart (has quiz_passed parameter)
        if (isset($_GET['quiz_passed']) || isset($_REQUEST['quiz_passed'])) {
            error_log('Quiz checkout redirect triggered - forcing checkout instead of cart');
            return wc_get_checkout_url();
        }
        
        // Check if URL contains add-to-cart parameter (direct URL access)
        if (isset($_GET['add-to-cart']) && !empty($_GET['add-to-cart'])) {
            error_log('Direct add-to-cart URL detected - redirecting to checkout');
            return wc_get_checkout_url();
        }
        
        return $url;
    }
    
    /**
     * Disable cart redirect setting for quiz flows
     */
    public function disable_cart_redirect_for_quiz($value) {
        if (isset($_GET['quiz_passed']) || isset($_REQUEST['quiz_passed']) || isset($_GET['add-to-cart'])) {
            return 'no'; // Disable cart redirect
        }
        
        return $value;
    }
}

// Initialize the class
new WooCommerce_Checkout_Redirect();

// Additional hook to handle AJAX add to cart redirects
add_action('wp_loaded', function() {
    if (isset($_GET['add-to-cart']) && !empty($_GET['add-to-cart'])) {
        // Remove default WooCommerce redirect behavior for direct add-to-cart URLs
        remove_action('wp_loaded', array('WC_Form_Handler', 'add_to_cart_action'), 20);
        
        // Add our custom handler
        add_action('wp_loaded', 'custom_add_to_cart_redirect_handler', 20);
    }
});

function custom_add_to_cart_redirect_handler() {
    if (!isset($_GET['add-to-cart']) || empty($_GET['add-to-cart'])) {
        return;
    }
    
    $product_id = absint($_GET['add-to-cart']);
    $was_added_to_cart = false;
    
    if ($product_id > 0) {
        // Add product to cart
        $quantity = isset($_GET['quantity']) ? absint($_GET['quantity']) : 1;
        $was_added_to_cart = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($was_added_to_cart) {
            error_log('Product added to cart via URL - redirecting to checkout');
            
            // Build checkout URL with parameters
            $checkout_url = wc_get_checkout_url();
            $params = array();
            
            // Preserve important parameters
            if (isset($_GET['monthly'])) $params['monthly'] = '1';
            if (isset($_GET['yearly'])) $params['yearly'] = '1';
            if (isset($_GET['trial'])) $params['trial'] = '1';
            if (isset($_GET['quiz_passed'])) $params['quiz_passed'] = '1';
            if (isset($_GET['score'])) $params['score'] = sanitize_text_field($_GET['score']);
            
            if (!empty($params)) {
                $checkout_url = add_query_arg($params, $checkout_url);
            }
            
            wp_safe_redirect($checkout_url);
            exit;
        }
    }
}

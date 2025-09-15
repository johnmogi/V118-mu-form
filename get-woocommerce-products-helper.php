<?php
/**
 * Helper method to get WooCommerce products for ACF select field
 */

// Add this method to the ACF_Quiz_Calculator class
if (!function_exists('get_woocommerce_products_for_acf')) {
    function get_woocommerce_products_for_acf() {
        $products = array();
        
        if (!class_exists('WooCommerce')) {
            return array('' => 'WooCommerce not active');
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $product_posts = get_posts($args);
        
        if (empty($product_posts)) {
            return array('' => 'No products found');
        }
        
        foreach ($product_posts as $product_post) {
            $product = wc_get_product($product_post->ID);
            if ($product) {
                $price = $product->get_price();
                $currency = get_woocommerce_currency_symbol();
                $price_display = $price ? " ({$currency}{$price})" : '';
                $products[$product_post->ID] = $product_post->post_title . $price_display;
            }
        }
        
        return $products;
    }
}

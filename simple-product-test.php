<?php
/**
 * Simple Product Test - Hook into WordPress admin
 */

// Hook into WordPress admin to test products
add_action('wp_ajax_test_wc_products', 'test_wc_products_ajax');
add_action('wp_ajax_nopriv_test_wc_products', 'test_wc_products_ajax');

function test_wc_products_ajax() {
    header('Content-Type: application/json');
    
    $result = array();
    
    // Test WooCommerce availability
    $result['woocommerce_active'] = class_exists('WooCommerce');
    $result['wc_get_products_exists'] = function_exists('wc_get_products');
    
    // Test database query
    global $wpdb;
    $product_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product'");
    $result['database_product_count'] = $product_count;
    
    // Test direct products query
    $products_query = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_status, pm.meta_value as price 
        FROM {$wpdb->posts} p 
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
        WHERE p.post_type = 'product' 
        ORDER BY p.post_title
    ");
    $result['direct_query_products'] = $products_query;
    
    // Test WooCommerce function
    if (function_exists('wc_get_products')) {
        try {
            $wc_products = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $products_array = array();
            foreach ($wc_products as $product) {
                $products_array[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'status' => $product->get_status()
                );
            }
            $result['wc_get_products_result'] = $products_array;
        } catch (Exception $e) {
            $result['wc_get_products_error'] = $e->getMessage();
        }
    }
    
    // Test ACF Quiz Calculator method
    if (class_exists('ACF_Quiz_Calculator')) {
        $quiz_instance = ACF_Quiz_Calculator::get_instance();
        if (method_exists($quiz_instance, 'get_woocommerce_products')) {
            $acf_products = $quiz_instance->get_woocommerce_products();
            $result['acf_method_result'] = $acf_products;
        } else {
            $result['acf_method_error'] = 'Method not found';
        }
    } else {
        $result['acf_class_error'] = 'Class not found';
    }
    
    wp_die(json_encode($result, JSON_PRETTY_PRINT));
}

// Add admin menu for easy access
add_action('admin_menu', function() {
    add_submenu_page(
        'quiz-settings',
        'Product Test',
        'Product Test',
        'manage_options',
        'product-test',
        'render_product_test_page'
    );
});

function render_product_test_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce Product Test</h1>
        <button id="run-test" class="button button-primary">Run Product Test</button>
        <div id="test-results" style="margin-top: 20px;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-test').click(function() {
                $('#test-results').html('Running test...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_wc_products'
                    },
                    success: function(response) {
                        $('#test-results').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                    },
                    error: function(xhr, status, error) {
                        $('#test-results').html('<p style="color: red;">Error: ' + error + '</p>');
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}
?>

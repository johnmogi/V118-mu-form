<?php
/**
 * WooCommerce Product Connectivity Test Script
 * 
 * This script tests WooCommerce product connectivity and data retrieval
 * Access via: https://v118.local/wp-content/mu-plugins/wc-product-test.php
 */

// Load WordPress - try multiple paths
$wp_load_paths = [
    '../../wp-load.php',
    '../../../wp-load.php', 
    '../../../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

// Set content type
header('Content-Type: text/html; charset=utf-8');

echo '<h1>WooCommerce Product Connectivity Test</h1>';
echo '<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>';

// Test 1: WordPress loaded
if (!$wp_loaded) {
    echo '<p class="error">✗ Could not find wp-load.php. Tried paths:</p>';
    echo '<ul>';
    foreach ($wp_load_paths as $path) {
        echo '<li>' . $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND') . '</li>';
    }
    echo '</ul>';
    exit;
}

echo '<h2>1. WordPress Status</h2>';
if (defined('ABSPATH')) {
    echo '<p class="success">✓ WordPress loaded successfully</p>';
    echo '<p class="info">WordPress version: ' . get_bloginfo('version') . '</p>';
} else {
    echo '<p class="error">✗ WordPress not loaded</p>';
    exit;
}

// Test 2: WooCommerce availability
echo '<h2>2. WooCommerce Status</h2>';
if (class_exists('WooCommerce')) {
    echo '<p class="success">✓ WooCommerce class available</p>';
    if (function_exists('wc_get_products')) {
        echo '<p class="success">✓ wc_get_products() function available</p>';
    } else {
        echo '<p class="error">✗ wc_get_products() function not available</p>';
    }
} else {
    echo '<p class="error">✗ WooCommerce class not available</p>';
}

// Test 3: Database connection
echo '<h2>3. Database Connection</h2>';
global $wpdb;
$result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product'");
echo '<p class="info">Products in database: ' . $result . '</p>';

// Test 4: Direct product query
echo '<h2>4. Direct Product Query</h2>';
$products_query = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_status, pm.meta_value as price 
    FROM {$wpdb->posts} p 
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
    WHERE p.post_type = 'product' 
    ORDER BY p.post_title
");

if ($products_query) {
    echo '<p class="success">✓ Found ' . count($products_query) . ' products via direct query</p>';
    echo '<pre>';
    foreach ($products_query as $product) {
        echo "ID: {$product->ID} | Title: {$product->post_title} | Status: {$product->post_status} | Price: {$product->price}\n";
    }
    echo '</pre>';
} else {
    echo '<p class="error">✗ No products found via direct query</p>';
}

// Test 5: WooCommerce wc_get_products()
echo '<h2>5. WooCommerce wc_get_products() Test</h2>';
if (function_exists('wc_get_products')) {
    try {
        $wc_products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'return' => 'objects'
        ));
        
        echo '<p class="success">✓ wc_get_products() executed successfully</p>';
        echo '<p class="info">Found ' . count($wc_products) . ' published products</p>';
        
        if (!empty($wc_products)) {
            echo '<pre>';
            foreach ($wc_products as $product) {
                echo "ID: {$product->get_id()} | Name: {$product->get_name()} | Price: {$product->get_price()} | Status: {$product->get_status()}\n";
            }
            echo '</pre>';
        }
        
        // Test with all statuses
        $all_products = wc_get_products(array(
            'status' => array('publish', 'draft', 'private'),
            'limit' => -1,
            'return' => 'objects'
        ));
        
        echo '<p class="info">Found ' . count($all_products) . ' products (all statuses)</p>';
        
    } catch (Exception $e) {
        echo '<p class="error">✗ Error with wc_get_products(): ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">✗ wc_get_products() function not available</p>';
}

// Test 6: Currency symbol
echo '<h2>6. Currency Settings</h2>';
if (function_exists('get_woocommerce_currency_symbol')) {
    $currency = get_woocommerce_currency_symbol();
    echo '<p class="info">Currency symbol: ' . $currency . '</p>';
} else {
    echo '<p class="error">✗ get_woocommerce_currency_symbol() not available</p>';
}

// Test 7: ACF function test
echo '<h2>7. ACF Product Function Test</h2>';
if (class_exists('ACF_Quiz_Calculator')) {
    $quiz_instance = ACF_Quiz_Calculator::get_instance();
    if (method_exists($quiz_instance, 'get_woocommerce_products')) {
        $acf_products = $quiz_instance->get_woocommerce_products();
        echo '<p class="success">✓ ACF get_woocommerce_products() method available</p>';
        echo '<p class="info">ACF method returned ' . count($acf_products) . ' products</p>';
        echo '<pre>';
        print_r($acf_products);
        echo '</pre>';
    } else {
        echo '<p class="error">✗ get_woocommerce_products() method not found in ACF_Quiz_Calculator</p>';
    }
} else {
    echo '<p class="error">✗ ACF_Quiz_Calculator class not found</p>';
}

echo '<h2>Test Complete</h2>';
echo '<p><a href="' . admin_url('admin.php?page=quiz-settings') . '">Go to Quiz Settings</a></p>';
?>

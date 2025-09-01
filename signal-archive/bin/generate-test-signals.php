<?php
/**
 * Generate test signal posts with ACF fields
 */

// Only run from command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Load WordPress
require_once dirname(__DIR__, 6) . '/wp-load.php';

// Sample data
$signals = [
    [
        'title' => 'AAPL - Buy',
        'security_number' => 'AAPL',
        'security_name' => 'Apple Inc.',
        'buy_price' => 170.50,
        'target_price' => 185.00,
        'stop_loss' => 165.00,
        'status' => 'open',
    ],
    [
        'title' => 'MSFT - Buy',
        'security_number' => 'MSFT',
        'security_name' => 'Microsoft Corporation',
        'buy_price' => 300.25,
        'target_price' => 330.00,
        'stop_loss' => 290.00,
        'status' => 'closed',
    ],
    [
        'title' => 'GOOGL - Sell',
        'security_number' => 'GOOGL',
        'security_name' => 'Alphabet Inc.',
        'buy_price' => 140.75,
        'target_price' => 130.00,
        'stop_loss' => 150.00,
        'status' => 'open',
    ],
    [
        'title' => 'AMZN - Buy',
        'security_number' => 'AMZN',
        'security_name' => 'Amazon.com Inc.',
        'buy_price' => 175.30,
        'target_price' => 195.00,
        'stop_loss' => 165.00,
        'status' => 'open',
    ],
    [
        'title' => 'TSLA - Sell',
        'security_number' => 'TSLA',
        'security_name' => 'Tesla Inc.',
        'buy_price' => 250.00,
        'target_price' => 230.00,
        'stop_loss' => 270.00,
        'status' => 'closed',
    ],
];

// Generate posts for the last 7 days
$today = new DateTime();
$today->setTime(0, 0, 0);

foreach ($signals as $index => $signal_data) {
    // Create post data
    $post_data = [
        'post_title'    => $signal_data['title'],
        'post_status'   => 'publish',
        'post_type'     => 'signal',
        'post_date'     => $today->format('Y-m-d H:i:s'),
    ];
    
    // Insert the post
    $post_id = wp_insert_post($post_data);
    
    if ($post_id && !is_wp_error($post_id)) {
        // Set ACF fields
        update_field('security_number', $signal_data['security_number'], $post_id);
        update_field('security_name', $signal_data['security_name'], $post_id);
        update_field('signal_date', $today->format('Y-m-d H:i:s'), $post_id);
        update_field('buy_price', $signal_data['buy_price'], $post_id);
        update_field('target_price', $signal_data['target_price'], $post_id);
        update_field('stop_loss', $signal_data['stop_loss'], $post_id);
        update_field('status', $signal_data['status'], $post_id);
        
        // Set a random current price between buy_price and target_price for open positions
        if ($signal_data['status'] === 'open') {
            $current_price = mt_rand(
                $signal_data['buy_price'] * 100 * 0.95, 
                $signal_data['buy_price'] * 100 * 1.10
            ) / 100;
            update_field('current_price', $current_price, $post_id);
        } else {
            // For closed positions, set current price to target price
            update_field('current_price', $signal_data['target_price'], $post_id);
            update_field('close_date', $today->format('Y-m-d H:i:s'), $post_id);
        }
        
        echo "Created signal: {$signal_data['title']} (ID: $post_id)\n";
    } else {
        echo "Failed to create signal: {$signal_data['title']}\n";
        if (is_wp_error($post_id)) {
            echo "Error: " . $post_id->get_error_message() . "\n";
        }
    }
    
    // Move to previous day for next signal
    $today->modify('-1 day');
}

echo "\nTest signals generation complete!\n";

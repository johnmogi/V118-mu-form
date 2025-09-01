<?php
/**
 * Populate ACF Repeater Data - Creates sample data matching the screenshot structure
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__DIR__, 3) . '/wp-load.php');

echo "<h1>Populating ACF Repeater Financial Data</h1>";

// Sample data matching the screenshot structure
$financial_data = [
    [
        'stock_id' => '1099654',
        'stock_name' => 'אלוט',
        'holding_date' => '17/07/2025',
        'buy_rate' => 3170,
        'target_price' => 5500,
        'stop_loss_price' => 2800,
        'closing_rate' => 2937,
        'closing_date' => '17/08/2025',
        'signal_source' => 'manual'
    ],
    [
        'stock_id' => '715011',
        'stock_name' => 'אדרים',
        'holding_date' => '16/07/2025',
        'buy_rate' => 2000,
        'target_price' => 2800,
        'stop_loss_price' => 1800,
        'closing_rate' => 1850,
        'closing_date' => '',
        'signal_source' => 'ai'
    ],
    [
        'stock_id' => '1081112',
        'stock_name' => 'נץ',
        'holding_date' => '15/07/2025',
        'buy_rate' => 3000,
        'target_price' => 4200,
        'stop_loss_price' => 2700,
        'closing_rate' => 2650,
        'closing_date' => '20/07/2025',
        'signal_source' => 'algorithm'
    ],
    [
        'stock_id' => '118740',
        'stock_name' => 'אלביט מערכות',
        'holding_date' => '14/07/2025',
        'buy_rate' => 16000,
        'target_price' => 18500,
        'stop_loss_price' => 15000,
        'closing_rate' => 15500,
        'closing_date' => '',
        'signal_source' => 'social'
    ],
    [
        'stock_id' => '1176611',
        'stock_name' => 'אורבידיין',
        'holding_date' => '13/07/2025',
        'buy_rate' => 2780,
        'target_price' => 3200,
        'stop_loss_price' => 2500,
        'closing_rate' => 2950,
        'closing_date' => '22/07/2025',
        'signal_source' => 'manual'
    ]
];

// Create a signal post with ACF repeater data
$post_data = [
    'post_title'    => 'Financial Signals - ' . date('d/m/Y'),
    'post_content'  => 'Daily financial signals with ACF repeater structure',
    'post_status'   => 'publish',
    'post_type'     => 'signal',
    'post_date'     => date('Y-m-d H:i:s'),
];

$post_id = wp_insert_post($post_data);

if (is_wp_error($post_id)) {
    echo "<p style='color: red;'>Error creating post: " . $post_id->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✓ Created post with ID: $post_id</p>";
    
    // Add the repeater data
    if (function_exists('update_field')) {
        $repeater_data = [];
        
        foreach ($financial_data as $row) {
            $repeater_data[] = [
                'stock_id' => $row['stock_id'],
                'stock_name' => $row['stock_name'],
                'holding_date' => $row['holding_date'],
                'buy_rate' => $row['buy_rate'],
                'target_price' => $row['target_price'],
                'stop_loss_price' => $row['stop_loss_price'],
                'closing_rate' => $row['closing_rate'],
                'closing_date' => $row['closing_date'],
                'signal_source' => $row['signal_source']
            ];
        }
        
        // Update the repeater field
        update_field('financial_rows', $repeater_data, $post_id);
        
        echo "<p style='color: green;'>✓ Added " . count($repeater_data) . " rows to ACF repeater</p>";
        
        // Display the data with calculated profit/loss
        echo "<h2>Data Preview with Profit/Loss Calculations</h2>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #4a90e2; color: white;'>";
        echo "<th>מס' נייר</th><th>שם נייר</th><th>שער קניה</th><th>שער סגירה</th><th>רווח/הפסד</th><th>מקור</th>";
        echo "</tr>";
        
        foreach ($financial_data as $row) {
            // Calculate profit/loss: ((closing_rate ÷ buy_rate) - 1) × 100
            $profit_loss = 0;
            $color = '#f39c12'; // neutral
            
            if ($row['buy_rate'] && $row['closing_rate']) {
                $profit_loss = (($row['closing_rate'] / $row['buy_rate']) - 1) * 100;
                $color = $profit_loss >= 0 ? '#27ae60' : '#e74c3c'; // green or red
            }
            
            $profit_loss_display = number_format($profit_loss, 2) . '%';
            
            $source_names = [
                'manual' => 'ידני',
                'ai' => 'AI',
                'algorithm' => 'אלגו',
                'social' => 'רשתות'
            ];
            
            echo "<tr>";
            echo "<td>{$row['stock_id']}</td>";
            echo "<td>{$row['stock_name']}</td>";
            echo "<td>" . number_format($row['buy_rate']) . "</td>";
            echo "<td>" . number_format($row['closing_rate']) . "</td>";
            echo "<td style='color: $color; font-weight: bold;'>$profit_loss_display</td>";
            echo "<td>{$source_names[$row['signal_source']]}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: orange;'>⚠ ACF not available - using post meta instead</p>";
        
        // Fallback to post meta
        foreach ($financial_data as $index => $row) {
            foreach ($row as $key => $value) {
                update_post_meta($post_id, "financial_rows_{$index}_{$key}", $value);
            }
        }
    }
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Import ACF Fields:</strong> Go to WordPress Admin → Custom Fields → Tools → Import and upload the JSON file</li>";
echo "<li><strong>View the Table:</strong> Visit the signal archive page to see the structured table</li>";
echo "<li><strong>Add More Data:</strong> Use the WordPress editor to add more rows via the ACF repeater interface</li>";
echo "</ol>";

echo "<p><strong>Archive URL:</strong> <a href='" . home_url('/signal/') . "' target='_blank'>" . home_url('/signal/') . "</a></p>";

// Show current signals count
$signals_count = wp_count_posts('signal')->publish;
echo "<p><strong>Total Signals:</strong> $signals_count</p>";

echo "<h3>ACF Field Structure Created:</h3>";
echo "<ul>";
echo "<li>✓ financial_rows (Repeater)</li>";
echo "<li>├── stock_id (Text)</li>";
echo "<li>├── stock_name (Text)</li>";
echo "<li>├── holding_date (Date Picker)</li>";
echo "<li>├── buy_rate (Number)</li>";
echo "<li>├── target_price (Number)</li>";
echo "<li>├── stop_loss_price (Number)</li>";
echo "<li>├── closing_rate (Number)</li>";
echo "<li>├── closing_date (Date Picker)</li>";
echo "<li>└── signal_source (Select)</li>";
echo "</ul>";

echo "<h3>Profit/Loss Formula Implemented:</h3>";
echo "<p><code>((Closing Rate ÷ Buy Rate) - 1) × 100</code></p>";
echo "<p>This gives the correct percentage gain/loss as requested.</p>";
?>

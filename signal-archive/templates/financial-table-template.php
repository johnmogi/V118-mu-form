<?php
/**
 * Financial Table Template - Recreates the exact table structure from screenshot
 * Uses ACF Repeater fields to display structured financial data
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<style>
.financial-table-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.financial-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.financial-table th {
    background: linear-gradient(135deg, #4a90e2, #357abd);
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    border: 1px solid #357abd;
}

.financial-table td {
    padding: 10px 8px;
    text-align: center;
    border: 1px solid #ddd;
    font-size: 13px;
    vertical-align: middle;
}

.financial-table tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

.financial-table tbody tr:hover {
    background-color: #e3f2fd;
}

.stock-info {
    text-align: right !important;
    padding-right: 12px !important;
}

.stock-id {
    font-weight: bold;
    color: #2c3e50;
    display: block;
    margin-bottom: 2px;
}

.stock-name {
    color: #7f8c8d;
    font-size: 11px;
}

.profit {
    color: #27ae60;
    font-weight: bold;
}

.loss {
    color: #e74c3c;
    font-weight: bold;
}

.neutral {
    color: #f39c12;
    font-weight: bold;
}

.price-field {
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

.source-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-left: 5px;
}

.source-manual { background: #3498db; }
.source-ai { background: #9b59b6; }
.source-algorithm { background: #e74c3c; }
.source-social { background: #f39c12; }

@media (max-width: 768px) {
    .financial-table {
        font-size: 11px;
    }
    
    .financial-table th,
    .financial-table td {
        padding: 6px 4px;
    }
}
</style>

<main id="content" class="site-main">
    <div class="financial-table-container">
        <h1 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">אירכון התרועות למניים</h1>
        
        <?php
        // Get current date from URL parameter or default to today
        $current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $date_obj = DateTime::createFromFormat('Y-m-d', $current_date);
        if (!$date_obj) {
            $date_obj = new DateTime();
            $current_date = $date_obj->format('Y-m-d');
        }
        
        // Query for signals on the selected date
        $signals_query = new WP_Query(array(
            'post_type' => 'signal',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'year'  => $date_obj->format('Y'),
                    'month' => $date_obj->format('m'),
                    'day'   => $date_obj->format('d'),
                ),
            ),
        ));

        if ($signals_query->have_posts()) : ?>
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>מקור</th>
                        <th>מס' נייר</th>
                        <th>שם נייר</th>
                        <th>תאריך האחזקה</th>
                        <th>שער קניה</th>
                        <th>מחיר יעד</th>
                        <th>מחיר סטופ לוס</th>
                        <th>שער סגירה</th>
                        <th>תאריך סגירה</th>
                        <th>אחוז רווח או הפסד</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($signals_query->have_posts()) : $signals_query->the_post(); 
                        $post_id = get_the_ID();
                        
                        // Check if we have ACF repeater data
                        if (have_rows('financial_rows', $post_id)) :
                            while (have_rows('financial_rows', $post_id)) : the_row();
                                
                                // Get all subfield values
                                $stock_id = get_sub_field('stock_id');
                                $stock_name = get_sub_field('stock_name');
                                $holding_date = get_sub_field('holding_date');
                                $buy_rate = get_sub_field('buy_rate');
                                $target_price = get_sub_field('target_price');
                                $stop_loss_price = get_sub_field('stop_loss_price');
                                $closing_rate = get_sub_field('closing_rate');
                                $closing_date = get_sub_field('closing_date');
                                $signal_source = get_sub_field('signal_source');
                                
                                // Calculate profit/loss: ((closing_rate ÷ buy_rate) - 1) × 100
                                $profit_loss_percentage = 0;
                                $profit_loss_class = 'neutral';
                                
                                if ($buy_rate && $closing_rate) {
                                    $profit_loss_percentage = (($closing_rate / $buy_rate) - 1) * 100;
                                    
                                    if ($profit_loss_percentage > 0) {
                                        $profit_loss_class = 'profit';
                                    } elseif ($profit_loss_percentage < 0) {
                                        $profit_loss_class = 'loss';
                                    }
                                }
                                
                                // Format the percentage
                                $profit_loss_display = number_format($profit_loss_percentage, 2) . '%';
                                
                                // Get source display name and indicator class
                                $source_names = [
                                    'manual' => 'ידני',
                                    'ai' => 'AI',
                                    'algorithm' => 'אלגו',
                                    'social' => 'רשתות'
                                ];
                                $source_display = $source_names[$signal_source] ?? 'לא ידוע';
                                $source_class = 'source-' . $signal_source;
                                ?>
                                <tr>
                                    <td>
                                        <span class="source-indicator <?php echo esc_attr($source_class); ?>"></span>
                                        <?php echo esc_html($source_display); ?>
                                    </td>
                                    <td class="stock-info">
                                        <span class="stock-id"><?php echo esc_html($stock_id); ?></span>
                                        <span class="stock-name"><?php echo esc_html($stock_name); ?></span>
                                    </td>
                                    <td><?php echo esc_html($stock_name); ?></td>
                                    <td><?php echo esc_html($holding_date); ?></td>
                                    <td class="price-field"><?php echo esc_html(number_format($buy_rate, 0)); ?></td>
                                    <td class="price-field"><?php echo esc_html(number_format($target_price, 0)); ?></td>
                                    <td class="price-field"><?php echo esc_html(number_format($stop_loss_price, 0)); ?></td>
                                    <td class="price-field"><?php echo esc_html(number_format($closing_rate, 0)); ?></td>
                                    <td><?php echo esc_html($closing_date ?: '-'); ?></td>
                                    <td class="<?php echo esc_attr($profit_loss_class); ?>">
                                        <?php echo esc_html($profit_loss_display); ?>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else :
                            // Fallback to old field structure if no repeater data
                            $security_number = get_field('security_number', $post_id);
                            $security_name = get_field('security_name', $post_id);
                            $buy_price = get_field('buy_price', $post_id);
                            $current_price = get_field('current_price', $post_id);
                            $target_price = get_field('target_price', $post_id);
                            $stop_loss = get_field('stop_loss', $post_id);
                            $signal_date = get_field('signal_date', $post_id);
                            $close_date = get_field('close_date', $post_id);
                            
                            // Calculate profit/loss for fallback data
                            $profit_loss_percentage = 0;
                            $profit_loss_class = 'neutral';
                            
                            if ($buy_price && $current_price) {
                                $profit_loss_percentage = (($current_price / $buy_price) - 1) * 100;
                                
                                if ($profit_loss_percentage > 0) {
                                    $profit_loss_class = 'profit';
                                } elseif ($profit_loss_percentage < 0) {
                                    $profit_loss_class = 'loss';
                                }
                            }
                            
                            $profit_loss_display = number_format($profit_loss_percentage, 2) . '%';
                            ?>
                            <tr>
                                <td>
                                    <span class="source-indicator source-manual"></span>
                                    ידני
                                </td>
                                <td class="stock-info">
                                    <span class="stock-id"><?php echo esc_html($security_number); ?></span>
                                    <span class="stock-name"><?php echo esc_html($security_name); ?></span>
                                </td>
                                <td><?php echo esc_html($security_name); ?></td>
                                <td><?php echo esc_html($signal_date); ?></td>
                                <td class="price-field"><?php echo esc_html(number_format($buy_price, 0)); ?></td>
                                <td class="price-field"><?php echo esc_html(number_format($target_price, 0)); ?></td>
                                <td class="price-field"><?php echo esc_html(number_format($stop_loss, 0)); ?></td>
                                <td class="price-field"><?php echo esc_html(number_format($current_price, 0)); ?></td>
                                <td><?php echo esc_html($close_date ?: '-'); ?></td>
                                <td class="<?php echo esc_attr($profit_loss_class); ?>">
                                    <?php echo esc_html($profit_loss_display); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <h2>לא נמצאו אותות לתאריך זה</h2>
                <p>אין כרגע נתונים זמינים לתאריך <?php echo esc_html($date_obj->format('d/m/Y')); ?></p>
            </div>
        <?php endif;
        
        wp_reset_postdata();
        ?>
        
        <!-- Navigation -->
        <div style="text-align: center; margin-top: 30px;">
            <?php
            $prev_date = clone $date_obj;
            $prev_date->modify('-1 day');
            $next_date = clone $date_obj;
            $next_date->modify('+1 day');
            ?>
            <a href="?date=<?php echo $prev_date->format('Y-m-d'); ?>" style="margin: 0 10px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">◀ יום קודם</a>
            <span style="margin: 0 20px; font-weight: bold;"><?php echo $date_obj->format('d/m/Y'); ?></span>
            <a href="?date=<?php echo $next_date->format('Y-m-d'); ?>" style="margin: 0 10px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">יום הבא ▶</a>
        </div>
    </div>
</main>

<?php get_footer(); ?>

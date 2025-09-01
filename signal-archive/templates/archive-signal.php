<?php
/**
 * Archive template for Signal custom post type
 * 
 * @package Vider\SignalArchive
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Removed SignalArchiveManager dependency

get_header();
?>

<main id="content" class="site-main">
    <div class="page-header">
        <div class="container">
            <h1 class="page-title">אירכון התרועות למניים</h1>
            <p class="archive-description">צפה בכל האותות המסחריים והביצועים שלהם</p>
        </div>
    </div>

    <div class="container">
        <div class="signals-archive">
            <?php
            // Get current date from URL parameter or default to today
            $current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
            $date_obj = DateTime::createFromFormat('Y-m-d', $current_date);
            if (!$date_obj) {
                $date_obj = new DateTime();
                $current_date = $date_obj->format('Y-m-d');
            }
            
            // Calculate previous and next dates
            $prev_date = clone $date_obj;
            $prev_date->modify('-1 day');
            $next_date = clone $date_obj;
            $next_date->modify('+1 day');
            
            // Get current month and year for the dropdown
            $current_month = $date_obj->format('m');
            $current_year = $date_obj->format('Y');
            
            // Function to get the most recent signal
            function get_most_recent_signal() {
                $args = array(
                    'post_type' => 'signal',
                    'posts_per_page' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_query' => array(
                        array(
                            'key' => 'signal_date',
                            'compare' => 'EXISTS',
                        ),
                    ),
                );
                
                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    return $query->posts[0];
                }
                return false;
            }
            ?>
            
            <!-- Calendar Navigation -->
            <div class="calendar-navigation">
                <div class="nav-container">
                    <a href="?date=<?php echo $prev_date->format('Y-m-d'); ?>" class="nav-arrow prev-arrow">
                        <span class="arrow">◀</span>
                    </a>
                    
                    <div class="current-date">
                        <h2><?php echo $date_obj->format('d/m/Y'); ?></h2>
                        <span class="day-name"><?php echo $date_obj->format('l'); ?></span>
                    </div>
                    
                    <a href="?date=<?php echo $next_date->format('Y-m-d'); ?>" class="nav-arrow next-arrow">
                        <span class="arrow">▶</span>
                    </a>
                </div>
                
                <!-- Month/Year Dropdown -->
                <div class="month-year-selector">
                    <form method="get" action="" class="month-year-form">
                        <input type="hidden" name="date" value="<?php echo $current_date; ?>">
                        <select name="month" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                $selected = ($m == $current_month) ? 'selected' : '';
                                echo "<option value='$m' $selected>$month_name</option>";
                            endfor; ?>
                        </select>
                        <select name="year" onchange="this.form.submit()">
                            <?php 
                            $current_year = (int)date('Y');
                            for ($y = $current_year - 1; $y <= $current_year + 1; $y++): 
                                $selected = ($y == $date_obj->format('Y')) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            endfor; 
                            ?>
                        </select>
                    </form>
                </div>
            </div>
            
            <?php
            // Query for signals on the selected date
            $signals_query = new WP_Query(array(
                'post_type' => 'signal',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => 'signal_date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'signal_date',
                        'value' => $date_obj->format('d/m/Y'),
                        'compare' => '='
                    )
                )
            ));

            if ($signals_query->have_posts()) : ?>
                <div class="signals-table-wrapper">
                    <table class="signals-table">
                        <thead>
                            <tr>
                                <th>מני נייר</th>
                                <th>תאריך האיתות</th>
                                <th>מחיר קנייה</th>
                                <th>מחיר יעד</th>
                                <th>סטופ לוס</th>
                                <th>מחיר נוכחי</th>
                                <th>סטטוס</th>
                                <th>% רווח או הפסד</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($signals_query->have_posts()) : $signals_query->the_post(); 
                                // Get ACF fields
                                $security_number = get_field('security_number');
                                $security_name = get_field('security_name');
                                $signal_date = get_field('signal_date');
                                $buy_price = get_field('buy_price');
                                $target_price = get_field('target_price');
                                $stop_loss = get_field('stop-loss_price');
                                $current_price = get_field('current_price');
                                $close_date = get_field('close_date');
                                $profit_loss = get_field('%_profitloss');
                                
                                // Determine status in Hebrew
                                $status = 'פעיל';
                                $status_class = 'status-active';
                                if ($close_date) {
                                    $status = 'סגור';
                                    $status_class = 'status-closed';
                                }
                                
                                // Calculate proper profit/loss: (current_price - buy_price) / buy_price * 100
                                $pl_class = '';
                                $calculated_pl = '';
                                if ($buy_price && $current_price) {
                                    $pl_percentage = (($current_price - $buy_price) / $buy_price) * 100;
                                    $calculated_pl = number_format($pl_percentage, 2) . '%';
                                    $pl_class = $pl_percentage >= 0 ? 'profit' : 'loss';
                                } elseif ($profit_loss) {
                                    $pl_value = floatval(str_replace('%', '', $profit_loss));
                                    $calculated_pl = $profit_loss;
                                    $pl_class = $pl_value >= 0 ? 'profit' : 'loss';
                                }
                            ?>
                                <tr class="signal-row">
                                    <td class="security-info">
                                        <strong><?php echo esc_html($security_number); ?></strong>
                                        <br>
                                        <span class="security-name"><?php echo esc_html($security_name); ?></span>
                                    </td>
                                    <td class="signal-date"><?php echo esc_html($signal_date); ?></td>
                                    <td class="buy-price"><?php echo esc_html($buy_price); ?></td>
                                    <td class="target-price"><?php echo esc_html($target_price); ?></td>
                                    <td class="stop-loss"><?php echo esc_html($stop_loss); ?></td>
                                    <td class="current-price"><?php echo esc_html($current_price); ?></td>
                                    <td class="status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status); ?></td>
                                    <td class="profit-loss <?php echo esc_attr($pl_class); ?>"><?php echo esc_html($calculated_pl); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : 
                // If no signals found for the selected date, try to get the most recent signal
                $recent_signal = get_most_recent_signal();
                
                if ($recent_signal) : 
                    // Get ACF fields for the most recent signal
                    $security_number = get_field('security_number', $recent_signal->ID);
                    $security_name = get_field('security_name', $recent_signal->ID);
                    $signal_date = get_field('signal_date', $recent_signal->ID);
                    $buy_price = get_field('buy_price', $recent_signal->ID);
                    $target_price = get_field('target_price', $recent_signal->ID);
                    $stop_loss = get_field('stop-loss_price', $recent_signal->ID);
                    $current_price = get_field('current_price', $recent_signal->ID);
                    $close_date = get_field('close_date', $recent_signal->ID);
                    
                    // Determine status in Hebrew
                    $status = $close_date ? 'סגור' : 'פעיל';
                    $status_class = $close_date ? 'status-closed' : 'status-active';
                    
                    // Calculate profit/loss
                    $pl_class = '';
                    $calculated_pl = '';
                    if ($buy_price && $current_price) {
                        $pl_percentage = (($current_price - $buy_price) / $buy_price) * 100;
                        $calculated_pl = number_format($pl_percentage, 2) . '%';
                        $pl_class = $pl_percentage >= 0 ? 'profit' : 'loss';
                    }
                    
                    // Handle different date formats safely
                    $formatted_date = $signal_date;
                    if ($signal_date) {
                        // Try to parse the date in different formats
                        $signal_date_obj = DateTime::createFromFormat('d/m/Y', $signal_date);
                        if (!$signal_date_obj) {
                            $signal_date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $signal_date);
                        }
                        if (!$signal_date_obj) {
                            $signal_date_obj = DateTime::createFromFormat('Y-m-d', $signal_date);
                        }
                        if ($signal_date_obj) {
                            $formatted_date = $signal_date_obj->format('d/m/Y');
                        }
                    }
            ?>
                <div class="no-signals">
                    <h2>לא נמצאו אותות לתאריך זה</h2>
                    <p>להלן האות העדכני ביותר:</p>
                    
                    <div class="most-recent-signal">
                        <div class="signals-table-wrapper">
                            <table class="signals-table">
                                <thead>
                                    <tr>
                                        <th>מני נייר</th>
                                        <th>תאריך האיתות</th>
                                        <th>מחיר קנייה</th>
                                        <th>מחיר יעד</th>
                                        <th>סטופ לוס</th>
                                        <th>מחיר נוכחי</th>
                                        <th>סטטוס</th>
                                        <th>% רווח או הפסד</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="signal-row">
                                        <td class="security-info">
                                            <strong><?php echo esc_html($security_number); ?></strong>
                                            <br>
                                            <span class="security-name"><?php echo esc_html($security_name); ?></span>
                                        </td>
                                        <td class="signal-date"><?php echo esc_html($formatted_date); ?></td>
                                        <td class="buy-price"><?php echo esc_html($buy_price); ?></td>
                                        <td class="target-price"><?php echo esc_html($target_price); ?></td>
                                        <td class="stop-loss"><?php echo esc_html($stop_loss); ?></td>
                                        <td class="current-price"><?php echo esc_html($current_price); ?></td>
                                        <td class="status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status); ?></td>
                                        <td class="profit-loss <?php echo esc_attr($pl_class); ?>"><?php echo esc_html($calculated_pl); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="no-signals">
                    <h2>לא נמצאו אותות</h2>
                    <p>אין כרגע אותות מסחר זמינים במערכת.</p>
                </div>
            <?php endif; ?>
            <?php endif;
            
            wp_reset_postdata();
            ?>
        </div>
    </div>
</main>

<?php 
// Add some CSS for the month/year selector
?>
<style>
    .month-year-selector {
        margin: 20px 0;
        text-align: center;
    }
    
    .month-year-form {
        display: inline-block;
    }
    
    .month-year-form select {
        padding: 8px 12px;
        margin: 0 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #fff;
        font-size: 14px;
    }
    
    .no-signals .most-recent-signal {
        margin-top: 20px;
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #eee;
    }
    
    .no-signals h2 {
        color: #d32f2f;
    }
    
    .no-signals p {
        margin-bottom: 20px;
        font-size: 16px;
    }
</style>

<script>
// Handle month/year form submission
jQuery(document).ready(function($) {
    // Update the date when month or year changes
    $('.month-year-form select').on('change', function() {
        var month = $('select[name="month"]').val();
        var year = $('select[name="year"]').val();
        
        // Redirect to the first day of the selected month/year
        window.location.href = '?date=' + year + '-' + month + '-01';
    });
    
    // Set the selected month and year in the dropdowns
    var currentDate = new Date('<?php echo $current_date; ?>');
    $('select[name="month"]').val(currentDate.getMonth() + 1);
    $('select[name="year"]').val(currentDate.getFullYear());
});
</script>

<?php get_footer(); ?>

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
            <h1 class="page-title">Trading Signals</h1>
            <p class="archive-description">View all trading signals and their performance</p>
        </div>
    </div>

    <div class="container">
        <div class="signals-archive">
            <?php
            // Query for signals directly
            $signals_query = new WP_Query(array(
                'post_type' => 'signal',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => 'signal_date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'signal_date',
                        'compare' => 'EXISTS'
                    )
                )
            ));

            if ($signals_query->have_posts()) : ?>
                <div class="signals-table-wrapper">
                    <table class="signals-table">
                        <thead>
                            <tr>
                                <th>Security</th>
                                <th>Signal Date</th>
                                <th>Buy Price</th>
                                <th>Target Price</th>
                                <th>Stop-Loss</th>
                                <th>Current Price</th>
                                <th>Status</th>
                                <th>% P/L</th>
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
                                
                                // Determine status
                                $status = 'Active';
                                $status_class = 'status-active';
                                if ($close_date) {
                                    $status = 'Closed';
                                    $status_class = 'status-closed';
                                }
                                
                                // Format profit/loss with color
                                $pl_class = '';
                                if ($profit_loss) {
                                    $pl_value = floatval(str_replace('%', '', $profit_loss));
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
                                    <td class="profit-loss <?php echo esc_attr($pl_class); ?>"><?php echo esc_html($profit_loss); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="no-signals">
                    <h2>No signals found</h2>
                    <p>There are currently no trading signals available.</p>
                </div>
            <?php endif;
            
            wp_reset_postdata();
            ?>
        </div>
    </div>
</main>

<?php get_footer(); ?>

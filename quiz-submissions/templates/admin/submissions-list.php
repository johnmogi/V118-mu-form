<?php
/**
 * Admin submissions list template
 *
 * @package Quiz_Submissions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get current URL for form action
$current_url = remove_query_arg(array('action', 'id', '_wpnonce', 'deleted'));
$deleted = isset($_GET['deleted']);

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Quiz Submissions', 'quiz-submissions'); ?></h1>
    
    <?php if ($deleted) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Submission deleted successfully.', 'quiz-submissions'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="get" action="">
        <input type="hidden" name="page" value="quiz-submissions" />
        
        <p class="search-box">
            <label class="screen-reader-text" for="search-submissions"><?php esc_html_e('Search Submissions', 'quiz-submissions'); ?>:</label>
            <input type="search" id="search-submissions" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" />
            <input type="submit" class="button" value="<?php esc_attr_e('Search', 'quiz-submissions'); ?>" />
        </p>
    </form>
    
    <div class="tablenav top">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php 
                // @todo: Add pagination count
                printf(
                    _n('%s submission', '%s submissions', count($submissions), 'quiz-submissions'),
                    number_format_i18n(count($submissions))
                );
                ?>
            </span>
        </div>
        <br class="clear">
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-primary">
                    <?php esc_html_e('Name', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Email', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Phone', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Submitted', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Actions', 'quiz-submissions'); ?>
                </th>
            </tr>
        </thead>
        
        <tbody id="the-list">
            <?php if (!empty($submissions)) : ?>
                <?php foreach ($submissions as $submission) : 
                    $view_url = add_query_arg(
                        array(
                            'page' => 'quiz-submissions',
                            'action' => 'view',
                            'id' => $submission->id,
                            '_wpnonce' => wp_create_nonce('quiz_submission_action')
                        ),
                        admin_url('admin.php')
                    );
                    
                    $delete_url = add_query_arg(
                        array(
                            'page' => 'quiz-submissions',
                            'action' => 'delete',
                            'id' => $submission->id,
                            '_wpnonce' => wp_create_nonce('quiz_submission_action')
                        ),
                        admin_url('admin.php')
                    );
                    
                    $name = esc_html(sprintf(
                        '%s %s',
                        $submission->first_name,
                        $submission->last_name
                    ));
                    
                    $submitted = sprintf(
                        /* translators: %s: Human-readable time difference */
                        esc_html__('%s ago', 'quiz-submissions'),
                        human_time_diff(
                            strtotime($submission->created_at),
                            current_time('timestamp')
                        )
                    );
                    ?>
                    <tr>
                        <td class="column-primary" data-colname="<?php esc_attr_e('Name', 'quiz-submissions'); ?>">
                            <strong>
                                <a href="<?php echo esc_url($view_url); ?>">
                                    <?php echo $name; ?>
                                </a>
                            </strong>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text"><?php esc_html_e('Show more details', 'quiz-submissions'); ?></span>
                            </button>
                        </td>
                        <td data-colname="<?php esc_attr_e('Email', 'quiz-submissions'); ?>">
                            <a href="mailto:<?php echo esc_attr($submission->email); ?>">
                                <?php echo esc_html($submission->email); ?>
                            </a>
                        </td>
                        <td data-colname="<?php esc_attr_e('Phone', 'quiz-submissions'); ?>">
                            <?php echo esc_html($submission->phone); ?>
                        </td>
                        <td data-colname="<?php esc_attr_e('Submitted', 'quiz-submissions'); ?>">
                            <?php echo $submitted; ?>
                        </td>
                        <td class="actions" data-colname="<?php esc_attr_e('Actions', 'quiz-submissions'); ?>">
                            <a href="<?php echo esc_url($view_url); ?>" class="button button-small">
                                <?php esc_html_e('View', 'quiz-submissions'); ?>
                            </a>
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-small delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this submission?', 'quiz-submissions')); ?>')">
                                <?php esc_html_e('Delete', 'quiz-submissions'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5">
                        <?php esc_html_e('No submissions found.', 'quiz-submissions'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-primary">
                    <?php esc_html_e('Name', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Email', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Phone', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Submitted', 'quiz-submissions'); ?>
                </th>
                <th scope="col" class="manage-column">
                    <?php esc_html_e('Actions', 'quiz-submissions'); ?>
                </th>
            </tr>
        </tfoot>
    </table>
    
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php 
                printf(
                    _n('%s submission', '%s submissions', count($submissions), 'quiz-submissions'),
                    number_format_i18n(count($submissions))
                );
                ?>
            </span>
        </div>
        <br class="clear">
    </div>
</div>

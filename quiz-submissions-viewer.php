<?php
/**
 * Plugin Name: Quiz Submissions Viewer
 * Description: Professional submissions viewer with proper permissions and database mapping
 * Version: 2.0.0
 * Author: Vider Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Quiz Submissions Viewer Class
 */
class Quiz_Submissions_Viewer {
    private static $instance = null;
    private $table_name;
    private $capability = 'manage_options';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_delete_quiz_submission', array($this, 'handle_delete_submission'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add AJAX handlers for real-time actions
        add_action('wp_ajax_get_submission_details', array($this, 'ajax_get_submission_details'));
        add_action('wp_ajax_delete_submission_ajax', array($this, 'ajax_delete_submission'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Check if user has required capability
        if (!current_user_can($this->capability)) {
            return;
        }

        $hook = add_menu_page(
            __('Quiz Submissions', 'quiz-submissions-viewer'),
            __('Quiz Submissions', 'quiz-submissions-viewer'),
            $this->capability,
            'quiz-submissions-viewer',
            array($this, 'render_admin_page'),
            'dashicons-clipboard',
            26
        );

        // Add page load hook
        add_action("load-{$hook}", array($this, 'admin_page_load'));
    }

    /**
     * Admin page load - handle actions and enqueue scripts
     */
    public function admin_page_load() {
        // Verify user capability again
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submissions
        $this->handle_admin_actions();
    }

    /**
     * Handle admin actions
     */
    private function handle_admin_actions() {
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_submission') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'delete_submission_' . $_POST['submission_id'])) {
                wp_die(__('Security check failed.'));
            }

            $submission_id = intval($_POST['submission_id']);
            if ($this->delete_submission($submission_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Submission deleted successfully.</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Failed to delete submission.</p></div>';
                });
            }
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_quiz-submissions-viewer') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_inline_javascript());
        wp_add_inline_style('wp-admin', $this->get_inline_css());
    }

    /**
     * Get inline JavaScript
     */
    private function get_inline_javascript() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('quiz_submissions_ajax');
        
        return "
        jQuery(document).ready(function($) {
            // View submission details
            window.viewSubmissionDetails = function(id) {
                $.post('{$ajax_url}', {
                    action: 'get_submission_details',
                    submission_id: id,
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#submission-details-content').html(response.data.html);
                        $('#submission-details-modal').show();
                    } else {
                        alert('Failed to load submission details: ' + response.data.message);
                    }
                });
            };

            // Close modal
            window.closeSubmissionModal = function() {
                $('#submission-details-modal').hide();
            };

            // Delete submission with AJAX
            window.deleteSubmissionAjax = function(id) {
                if (!confirm('Are you sure you want to delete this submission?')) {
                    return;
                }

                $.post('{$ajax_url}', {
                    action: 'delete_submission_ajax',
                    submission_id: id,
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete submission: ' + response.data.message);
                    }
                });
            };

            // Close modal when clicking outside
            $('#submission-details-modal').click(function(e) {
                if (e.target === this) {
                    closeSubmissionModal();
                }
            });
        });
        ";
    }

    /**
     * Get inline CSS
     */
    private function get_inline_css() {
        return "
        .quiz-submissions-table .status-complete {
            color: #00a32a;
            font-weight: bold;
        }
        .quiz-submissions-table .status-incomplete {
            color: #dba617;
            font-weight: bold;
        }
        .quiz-submissions-table .status-failed {
            color: #d63638;
            font-weight: bold;
        }
        #submission-details-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        #submission-details-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        .submission-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .submission-detail-section h4 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
        }
        ";
    }

    /**
     * AJAX: Get submission details
     */
    public function ajax_get_submission_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_submissions_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can($this->capability)) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $submission_id = intval($_POST['submission_id']);
        $submission = $this->get_submission($submission_id);

        if (!$submission) {
            wp_send_json_error(array('message' => 'Submission not found'));
        }

        $html = $this->render_submission_details($submission);
        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Delete submission
     */
    public function ajax_delete_submission() {
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_submissions_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can($this->capability)) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $submission_id = intval($_POST['submission_id']);
        if ($this->delete_submission($submission_id)) {
            wp_send_json_success(array('message' => 'Submission deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete submission'));
        }
    }

    /**
     * Get single submission
     */
    private function get_submission($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }

    /**
     * Get submissions with search and pagination
     */
    private function get_submissions($search = '', $limit = 50, $offset = 0) {
        global $wpdb;
        
        $where_clause = '';
        if (!empty($search)) {
            $where_clause = $wpdb->prepare(
                " WHERE first_name LIKE %s OR last_name LIKE %s OR user_name LIKE %s OR user_email LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY submission_time DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Get total submissions count
     */
    private function get_submissions_count($search = '') {
        global $wpdb;
        
        $where_clause = '';
        if (!empty($search)) {
            $where_clause = $wpdb->prepare(
                " WHERE first_name LIKE %s OR last_name LIKE %s OR user_name LIKE %s OR user_email LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$where_clause}");
    }

    /**
     * Delete submission
     */
    private function delete_submission($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
    }

    /**
     * Render submission details
     */
    private function render_submission_details($submission) {
        ob_start();
        ?>
        <span class="close-modal" onclick="closeSubmissionModal()">&times;</span>
        <h2>Submission Details - ID: <?php echo esc_html($submission->id); ?></h2>
        
        <div class="submission-detail-grid">
            <div class="submission-detail-section">
                <h4>Personal Information</h4>
                <p><strong>Name:</strong> <?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></p>
                <p><strong>Username:</strong> <?php echo esc_html($submission->user_name); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($submission->user_email); ?></p>
                <p><strong>Phone:</strong> <?php echo esc_html($submission->user_phone); ?></p>
                <?php if ($submission->id_number): ?>
                <p><strong>ID Number:</strong> <?php echo esc_html($submission->id_number); ?></p>
                <?php endif; ?>
                <?php if ($submission->gender): ?>
                <p><strong>Gender:</strong> <?php echo esc_html($submission->gender); ?></p>
                <?php endif; ?>
                <?php if ($submission->birth_date): ?>
                <p><strong>Birth Date:</strong> <?php echo esc_html($submission->birth_date); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="submission-detail-section">
                <h4>Additional Information</h4>
                <p><strong>Citizenship:</strong> <?php echo esc_html($submission->citizenship); ?></p>
                <?php if ($submission->address): ?>
                <p><strong>Address:</strong> <?php echo esc_html($submission->address); ?></p>
                <?php endif; ?>
                <?php if ($submission->marital_status): ?>
                <p><strong>Marital Status:</strong> <?php echo esc_html($submission->marital_status); ?></p>
                <?php endif; ?>
                <?php if ($submission->employment_status): ?>
                <p><strong>Employment:</strong> <?php echo esc_html($submission->employment_status); ?></p>
                <?php endif; ?>
                <?php if ($submission->education): ?>
                <p><strong>Education:</strong> <?php echo esc_html($submission->education); ?></p>
                <?php endif; ?>
                <?php if ($submission->profession): ?>
                <p><strong>Profession:</strong> <?php echo esc_html($submission->profession); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="submission-detail-section">
            <h4>Quiz Information</h4>
            <p><strong>Package Selected:</strong> <?php echo esc_html($submission->package_selected); ?></p>
            <p><strong>Package Price:</strong> $<?php echo esc_html($submission->package_price); ?></p>
            <p><strong>Score:</strong> <?php echo esc_html($submission->score); ?>/<?php echo esc_html($submission->max_score); ?> (<?php echo esc_html($submission->score_percentage); ?>%)</p>
            <p><strong>Passed:</strong> <?php echo $submission->passed ? 'Yes' : 'No'; ?></p>
            <p><strong>Current Step:</strong> <?php echo esc_html($submission->current_step); ?></p>
            <p><strong>Completed:</strong> <?php echo $submission->completed ? 'Yes' : 'No'; ?></p>
        </div>
        
        <div class="submission-detail-section">
            <h4>Submission Metadata</h4>
            <p><strong>Submission Time:</strong> <?php echo esc_html($submission->submission_time); ?></p>
            <p><strong>IP Address:</strong> <?php echo esc_html($submission->ip_address); ?></p>
            <p><strong>User Agent:</strong> <?php echo esc_html(substr($submission->user_agent, 0, 100)); ?>...</p>
            <p><strong>Contact Consent:</strong> <?php echo $submission->contact_consent ? 'Yes' : 'No'; ?></p>
            <p><strong>Declaration Accepted:</strong> <?php echo $submission->declaration_accepted ? 'Yes' : 'No'; ?></p>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <button type="button" class="button button-primary" onclick="closeSubmissionModal()">Close</button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Double-check permissions
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $submissions = $this->get_submissions($search, $per_page, $offset);
        $total_submissions = $this->get_submissions_count($search);
        $total_pages = ceil($total_submissions / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Quiz Submissions', 'quiz-submissions-viewer'); ?></h1>
            
            <form method="get" class="search-form" style="float: right;">
                <input type="hidden" name="page" value="quiz-submissions-viewer">
                <p class="search-box">
                    <label class="screen-reader-text" for="submission-search-input">Search Submissions:</label>
                    <input type="search" id="submission-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search submissions...">
                    <input type="submit" id="search-submit" class="button" value="Search Submissions">
                </p>
            </form>
            
            <div class="clear"></div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_submissions), number_format_i18n($total_submissions)); ?></span>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
                <?php endif; ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped quiz-submissions-table">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-id">ID</th>
                        <th scope="col" class="manage-column column-name">Name</th>
                        <th scope="col" class="manage-column column-email">Email</th>
                        <th scope="col" class="manage-column column-phone">Phone</th>
                        <th scope="col" class="manage-column column-package">Package</th>
                        <th scope="col" class="manage-column column-score">Score</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-date">Date</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="9">No submissions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td class="column-id"><?php echo esc_html($submission->id); ?></td>
                                <td class="column-name">
                                    <strong><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></strong>
                                    <?php if ($submission->user_name): ?>
                                        <br><small><?php echo esc_html($submission->user_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-email"><?php echo esc_html($submission->user_email); ?></td>
                                <td class="column-phone"><?php echo esc_html($submission->user_phone); ?></td>
                                <td class="column-package">
                                    <?php echo esc_html($submission->package_selected); ?>
                                    <?php if ($submission->package_price > 0): ?>
                                        <br><small>$<?php echo esc_html($submission->package_price); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-score">
                                    <?php echo esc_html($submission->score); ?>/<?php echo esc_html($submission->max_score); ?>
                                    <?php if ($submission->score_percentage): ?>
                                        <br><small>(<?php echo esc_html($submission->score_percentage); ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php if ($submission->completed): ?>
                                        <span class="status-complete">✓ Complete</span>
                                    <?php elseif ($submission->passed): ?>
                                        <span class="status-complete">✓ Passed</span>
                                    <?php elseif ($submission->current_step > 1): ?>
                                        <span class="status-incomplete">⚠ In Progress</span>
                                    <?php else: ?>
                                        <span class="status-incomplete">⚠ Started</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-date"><?php echo esc_html(date('M j, Y g:i A', strtotime($submission->submission_time))); ?></td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small" onclick="viewSubmissionDetails(<?php echo $submission->id; ?>)">View</button>
                                    <button type="button" class="button button-small button-link-delete" onclick="deleteSubmissionAjax(<?php echo $submission->id; ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal for submission details -->
        <div id="submission-details-modal">
            <div id="submission-details-content"></div>
        </div>
        
        <?php
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    Quiz_Submissions_Viewer::get_instance();
});

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
        add_action('wp_ajax_bulk_delete_submissions', array($this, 'ajax_bulk_delete_submissions'));
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
            'צפייה בהגשות טפסים', // Hebrew: View Form Submissions
            'הגשות טפסים', // Hebrew: Form Submissions
            $this->capability,
            'quiz-submissions-viewer',
            array($this, 'render_admin_page'),
            'dashicons-clipboard',
            26
        );
        
        // Add cleanup submenu
        add_submenu_page(
            'quiz-submissions-viewer',
            'ניקוי כפילויות', // Hebrew: Clean Duplicates
            'ניקוי כפילויות',
            $this->capability,
            'quiz-submissions-cleanup',
            array($this, 'render_cleanup_page')
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
        // Handle bulk delete action
        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_submissions')) {
                wp_die(__('Security check failed.'));
            }

            $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : array();
            if (!empty($submission_ids)) {
                $deleted_count = $this->bulk_delete_submissions($submission_ids);
                add_action('admin_notices', function() use ($deleted_count) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf('נמחקו %d הגשות בהצלחה.', $deleted_count) . '</p></div>';
                });
            }
        }

        // Handle single delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_submission') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'delete_submission_' . $_POST['submission_id'])) {
                wp_die(__('Security check failed.'));
            }

            $submission_id = intval($_POST['submission_id']);
            if ($this->delete_submission($submission_id)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>ההגשה נמחקה בהצלחה.</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>שגיאה במחיקת ההגשה.</p></div>';
                });
            }
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'quiz-submissions-viewer') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        
        // Add inline JavaScript directly to the page
        add_action('admin_footer', function() {
            echo '<script type="text/javascript">';
            echo $this->get_inline_javascript();
            echo '</script>';
        });
        
        // Add inline CSS
        add_action('admin_head', function() {
            echo '<style type="text/css">';
            echo $this->get_inline_css();
            echo '</style>';
        });
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
                        alert('שגיאה בטעינת פרטי ההגשה: ' + response.data.message);
                    }
                });
            };

            // Close modal
            window.closeSubmissionModal = function() {
                $('#submission-details-modal').hide();
            };

            // Delete submission with AJAX
            window.deleteSubmissionAjax = function(id) {
                if (!confirm('האם אתה בטוח שברצונך למחוק הגשה זו?')) {
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
                        alert('שגיאה במחיקת ההגשה: ' + response.data.message);
                    }
                });
            };

            // Bulk delete functionality
            window.bulkDeleteSubmissions = function() {
                var checkedBoxes = $('.submission-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('אנא בחר לפחות הגשה אחת למחיקה.');
                    return;
                }

                if (!confirm('האם אתה בטוח שברצונך למחוק ' + checkedBoxes.length + ' הגשות?')) {
                    return;
                }

                var submissionIds = [];
                checkedBoxes.each(function() {
                    submissionIds.push($(this).val());
                });

                $.post('{$ajax_url}', {
                    action: 'bulk_delete_submissions',
                    submission_ids: submissionIds,
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('שגיאה במחיקה המרובה: ' + response.data.message);
                    }
                });
            };

            // Select all checkboxes
            $('#select-all-submissions').change(function() {
                $('.submission-checkbox').prop('checked', $(this).is(':checked'));
                updateBulkActions();
            });

            // Update bulk actions visibility
            $('.submission-checkbox').change(function() {
                updateBulkActions();
            });

            function updateBulkActions() {
                var checkedCount = $('.submission-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('#bulk-actions-container').show();
                    $('#selected-count').text(checkedCount);
                } else {
                    $('#bulk-actions-container').hide();
                }
            }

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
        #bulk-actions-container {
            display: none;
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 10px;
            margin: 10px 0;
        }
        #bulk-actions-container.show {
            display: block;
        }
        .bulk-action-button {
            background: #dc3232;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 10px;
        }
        .bulk-action-button:hover {
            background: #a00;
        }
        .column-cb {
            width: 2.2em;
        }
        .check-column {
            text-align: center;
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
     * Bulk delete submissions
     */
    private function bulk_delete_submissions($ids) {
        global $wpdb;
        
        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
            ...$ids
        ));

        return $deleted;
    }

    /**
     * AJAX: Bulk delete submissions
     */
    public function ajax_bulk_delete_submissions() {
        if (!wp_verify_nonce($_POST['nonce'], 'quiz_submissions_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can($this->capability)) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : array();
        if (empty($submission_ids)) {
            wp_send_json_error(array('message' => 'No submissions selected'));
        }

        $deleted_count = $this->bulk_delete_submissions($submission_ids);
        if ($deleted_count > 0) {
            wp_send_json_success(array('message' => sprintf('נמחקו %d הגשות בהצלחה', $deleted_count)));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete submissions'));
        }
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
            <h1 class="wp-heading-inline">הגשות טפסים</h1>
            
            <form method="get" class="search-form" style="float: right;">
                <input type="hidden" name="page" value="quiz-submissions-viewer">
                <p class="search-box">
                    <label class="screen-reader-text" for="submission-search-input">חיפוש הגשות:</label>
                    <input type="search" id="submission-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="חיפוש הגשות...">
                    <input type="submit" id="search-submit" class="button" value="חיפוש">
                </p>
            </form>
            
            <div class="clear"></div>
            
            <!-- Bulk Actions Container -->
            <div id="bulk-actions-container">
                <span>נבחרו <span id="selected-count">0</span> הגשות</span>
                <button type="button" class="bulk-action-button" onclick="bulkDeleteSubmissions()">מחק נבחרות</button>
            </div>
            
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
                        <th scope="col" class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-submissions">
                        </th>
                        <th scope="col" class="manage-column column-id">מזהה</th>
                        <th scope="col" class="manage-column column-name">שם</th>
                        <th scope="col" class="manage-column column-email">אימייל</th>
                        <th scope="col" class="manage-column column-phone">טלפון</th>
                        <th scope="col" class="manage-column column-package">חבילה</th>
                        <th scope="col" class="manage-column column-score">ציון</th>
                        <th scope="col" class="manage-column column-status">סטטוס</th>
                        <th scope="col" class="manage-column column-date">תאריך</th>
                        <th scope="col" class="manage-column column-actions">פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="10">לא נמצאו הגשות.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="submission-checkbox" value="<?php echo $submission->id; ?>">
                                </th>
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
                                        <br><small>₪<?php echo esc_html($submission->package_price); ?></small>
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
                                        <span class="status-complete">✓ הושלם</span>
                                    <?php elseif ($submission->passed): ?>
                                        <span class="status-complete">✓ עבר</span>
                                    <?php elseif ($submission->current_step > 1): ?>
                                        <span class="status-incomplete">⚠ בתהליך</span>
                                    <?php else: ?>
                                        <span class="status-incomplete">⚠ התחיל</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-date"><?php echo esc_html(date('j/m/Y H:i', strtotime($submission->submission_time))); ?></td>
                                <td class="column-actions">
                                    <button type="button" class="button button-small" onclick="viewSubmissionDetails(<?php echo $submission->id; ?>)">צפה</button>
                                    <button type="button" class="button button-small button-link-delete" onclick="deleteSubmissionAjax(<?php echo $submission->id; ?>)">מחק</button>
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

    /**
     * Render cleanup page for duplicate submissions
     */
    public function render_cleanup_page() {
        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle cleanup action
        if (isset($_POST['action']) && $_POST['action'] === 'cleanup_duplicates') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'cleanup_duplicates')) {
                wp_die(__('Security check failed.'));
            }

            $cleaned = $this->cleanup_duplicate_submissions();
            echo '<div class="notice notice-success is-dismissible"><p>נוקו ' . $cleaned . ' הגשות כפולות.</p></div>';
        }

        // Find potential duplicates
        $duplicates = $this->find_duplicate_submissions();

        ?>
        <div class="wrap">
            <h1>ניקוי הגשות כפולות</h1>
            
            <div class="notice notice-info">
                <p><strong>הסבר:</strong> המערכת יוצרת לפעמים הגשות כפולות כאשר משתמש ממלא שלב 1 ואז שלב 2. 
                כלי זה ימזג הגשות כפולות לרשומה אחת.</p>
            </div>

            <?php if (empty($duplicates)): ?>
                <div class="notice notice-success">
                    <p>לא נמצאו הגשות כפולות! 🎉</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p>נמצאו <?php echo count($duplicates); ?> קבוצות של הגשות כפולות.</p>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="cleanup_duplicates">
                    <?php wp_nonce_field('cleanup_duplicates'); ?>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>אימייל</th>
                                <th>מספר הגשות</th>
                                <th>מזהים</th>
                                <th>פרטים</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duplicates as $email => $submissions): ?>
                                <tr>
                                    <td><?php echo esc_html($email); ?></td>
                                    <td><?php echo count($submissions); ?></td>
                                    <td><?php echo implode(', ', array_column($submissions, 'id')); ?></td>
                                    <td>
                                        <?php foreach ($submissions as $sub): ?>
                                            <small>ID <?php echo $sub->id; ?>: שלב <?php echo $sub->current_step; ?> 
                                            (<?php echo $sub->submission_time; ?>)</small><br>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="נקה הגשות כפולות" 
                               onclick="return confirm('האם אתה בטוח? פעולה זו תמזג הגשות כפולות ותמחק את הכפילויות.')">
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Find duplicate submissions by email
     */
    private function find_duplicate_submissions() {
        global $wpdb;
        
        $duplicates = array();
        
        // Find emails with multiple submissions
        $results = $wpdb->get_results("
            SELECT user_email, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE user_email IS NOT NULL AND user_email != '' 
            GROUP BY user_email 
            HAVING count > 1
            ORDER BY count DESC
        ");

        foreach ($results as $result) {
            $submissions = $wpdb->get_results($wpdb->prepare("
                SELECT id, current_step, submission_time, first_name, last_name 
                FROM {$this->table_name} 
                WHERE user_email = %s 
                ORDER BY submission_time ASC
            ", $result->user_email));
            
            $duplicates[$result->user_email] = $submissions;
        }

        return $duplicates;
    }

    /**
     * Cleanup duplicate submissions
     */
    private function cleanup_duplicate_submissions() {
        global $wpdb;
        
        $duplicates = $this->find_duplicate_submissions();
        $cleaned_count = 0;

        foreach ($duplicates as $email => $submissions) {
            if (count($submissions) < 2) continue;

            // Sort by submission time
            usort($submissions, function($a, $b) {
                return strtotime($a->submission_time) - strtotime($b->submission_time);
            });

            // Keep the latest submission, merge data from others
            $keep_submission = end($submissions);
            $merge_submissions = array_slice($submissions, 0, -1);

            // Get full data for all submissions
            $keep_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $keep_submission->id));
            
            foreach ($merge_submissions as $merge_sub) {
                $merge_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $merge_sub->id));
                
                // Merge non-empty fields from older submission to newer one
                $update_data = array();
                
                if (empty($keep_data->first_name) && !empty($merge_data->first_name)) {
                    $update_data['first_name'] = $merge_data->first_name;
                }
                if (empty($keep_data->last_name) && !empty($merge_data->last_name)) {
                    $update_data['last_name'] = $merge_data->last_name;
                }
                if (empty($keep_data->package_selected) && !empty($merge_data->package_selected)) {
                    $update_data['package_selected'] = $merge_data->package_selected;
                    $update_data['package_price'] = $merge_data->package_price;
                }

                // Update the keep submission with merged data
                if (!empty($update_data)) {
                    $wpdb->update($this->table_name, $update_data, array('id' => $keep_submission->id));
                }

                // Delete the duplicate
                $wpdb->delete($this->table_name, array('id' => $merge_sub->id), array('%d'));
                $cleaned_count++;
            }
        }

        return $cleaned_count;
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    Quiz_Submissions_Viewer::get_instance();
});

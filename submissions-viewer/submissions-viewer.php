<?php
/**
 * Plugin Name: Submissions Viewer
 * Description: Simple viewer for quiz submissions data - for testing and verification
 * Version: 1.0.0
 * Author: Vider Team
 * Text Domain: submissions-viewer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Submissions Viewer Class
 */
class Simple_Submissions_Viewer {
    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'quiz_submissions';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_delete_submission', array($this, 'handle_delete_submission'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Submissions Viewer',
            'Submissions Viewer', 
            'read',
            'submissions-viewer',
            array($this, 'render_page'),
            'dashicons-list-view',
            25
        );
    }

    public function handle_delete_submission() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'delete_submission')) {
            wp_die('Invalid nonce');
        }
        
        global $wpdb;
        $id = intval($_POST['submission_id']);
        $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
        
        wp_redirect(admin_url('admin.php?page=submissions-viewer&deleted=1'));
        exit;
    }

    public function render_page() {
        global $wpdb;
        
        // Handle search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_clause = '';
        if ($search) {
            $where_clause = $wpdb->prepare(" WHERE name LIKE %s OR email LIKE %s", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Get submissions
        $submissions = $wpdb->get_results("SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Submissions Viewer</h1>
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Submission deleted successfully.</p>
                </div>
            <?php endif; ?>
            
            <form method="get">
                <input type="hidden" name="page" value="submissions-viewer">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search submissions...">
                    <input type="submit" class="button" value="Search">
                </p>
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="7">No submissions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html($submission->name); ?></td>
                                <td><?php echo esc_html($submission->email); ?></td>
                                <td><?php echo esc_html($submission->phone); ?></td>
                                <td>
                                    <?php if ($submission->is_complete): ?>
                                        <span style="color: green;">Complete</span>
                                    <?php else: ?>
                                        <span style="color: orange;">Step 1 Only</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($submission->created_at); ?></td>
                                <td>
                                    <a href="#" onclick="viewSubmission(<?php echo $submission->id; ?>)" class="button button-small">View</a>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_submission">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission->id; ?>">
                                            <?php wp_nonce_field('delete_submission'); ?>
                                            <input type="submit" class="button button-small" value="Delete" onclick="return confirm('Are you sure?')">
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div id="submission-details" style="display: none; margin-top: 20px; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;">
                <h3>Submission Details</h3>
                <div id="submission-content"></div>
                <button type="button" onclick="document.getElementById('submission-details').style.display='none'">Close</button>
            </div>
        </div>
        
        <script>
        function viewSubmission(id) {
            // Find the submission data
            <?php foreach ($submissions as $submission): ?>
                if (id === <?php echo $submission->id; ?>) {
                    var content = '<strong>ID:</strong> <?php echo esc_js($submission->id); ?><br>';
                    content += '<strong>Name:</strong> <?php echo esc_js($submission->name); ?><br>';
                    content += '<strong>Email:</strong> <?php echo esc_js($submission->email); ?><br>';
                    content += '<strong>Phone:</strong> <?php echo esc_js($submission->phone); ?><br>';
                    <?php if ($submission->id_number): ?>
                    content += '<strong>ID Number:</strong> <?php echo esc_js($submission->id_number); ?><br>';
                    <?php endif; ?>
                    <?php if ($submission->birth_date): ?>
                    content += '<strong>Birth Date:</strong> <?php echo esc_js($submission->birth_date); ?><br>';
                    <?php endif; ?>
                    content += '<strong>Status:</strong> <?php echo $submission->is_complete ? 'Complete' : 'Step 1 Only'; ?><br>';
                    content += '<strong>Created:</strong> <?php echo esc_js($submission->created_at); ?><br>';
                    content += '<strong>IP Address:</strong> <?php echo esc_js($submission->ip_address); ?><br>';
                    <?php if (!empty($submission->signature_data)): ?>
                    content += '<div class="signature-display-section">';
                    content += '<strong>Digital Signature:</strong><br>';
                    content += '<div class="signature-image-container">';
                    content += '<img src="<?php echo esc_js($submission->signature_data); ?>" class="signature-image" alt="Digital Signature">';
                    content += '<div class="signature-actions">';
                    content += '<button onclick="downloadSignature(<?php echo $submission->id; ?>)" class="button button-small">Download</button>';
                    content += '<button onclick="viewSignatureFullscreen(<?php echo $submission->id; ?>)" class="button button-small">View Full Size</button>';
                    content += '</div>';
                    content += '</div>';
                    content += '</div>';
                    <?php else: ?>
                    content += '<div class="signature-display-section no-signature">';
                    content += '<strong>Digital Signature:</strong><br>';
                    content += '<span class="no-signature-text">❌ No signature provided</span>';
                    content += '</div>';
                    <?php endif; ?>
                    
                    document.getElementById('submission-content').innerHTML = content;
                    document.getElementById('submission-details').style.display = 'block';
                }
            <?php endforeach; ?>
        }
        
        // Enhanced signature viewing functions
        function downloadSignature(submissionId) {
            <?php foreach ($submissions as $submission): ?>
                if (submissionId === <?php echo $submission->id; ?>) {
                    <?php if (!empty($submission->signature_data)): ?>
                        const link = document.createElement('a');
                        link.href = '<?php echo esc_js($submission->signature_data); ?>';
                        link.download = 'signature_<?php echo $submission->id; ?>_<?php echo date('Y-m-d'); ?>.png';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    <?php endif; ?>
                }
            <?php endforeach; ?>
        }
        
        function viewSignatureFullscreen(submissionId) {
            <?php foreach ($submissions as $submission): ?>
                if (submissionId === <?php echo $submission->id; ?>) {
                    <?php if (!empty($submission->signature_data)): ?>
                        openSignatureModal('<?php echo esc_js($submission->signature_data); ?>');
                    <?php endif; ?>
                }
            <?php endforeach; ?>
        }
        
        function openSignatureModal(imageSrc) {
            // Create modal overlay
            const modal = document.createElement('div');
            modal.className = 'signature-modal-overlay';
            modal.innerHTML = `
                <div class="signature-modal-content">
                    <div class="signature-modal-header">
                        <h3>Digital Signature - Full Size</h3>
                        <button class="signature-modal-close" onclick="closeSignatureModal()">&times;</button>
                    </div>
                    <div class="signature-modal-body">
                        <img src="${imageSrc}" class="signature-modal-image" alt="Digital Signature">
                    </div>
                    <div class="signature-modal-footer">
                        <button onclick="downloadSignatureFromModal('${imageSrc}')" class="button button-primary">Download PNG</button>
                        <button onclick="closeSignatureModal()" class="button">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'flex';
            
            // Close on background click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeSignatureModal();
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSignatureModal();
                }
            });
        }
        
        function closeSignatureModal() {
            const modal = document.querySelector('.signature-modal-overlay');
            if (modal) {
                document.body.removeChild(modal);
            }
        }
        
        function downloadSignatureFromModal(imageSrc) {
            const link = document.createElement('a');
            link.href = imageSrc;
            link.download = 'signature_' + new Date().toISOString().split('T')[0] + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        </script>
        
        <style>
        /* Enhanced Signature Display Styles */
        .signature-display-section {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .signature-image-container {
            margin-top: 10px;
        }
        
        .signature-image {
            max-width: 100%;
            height: auto;
            border: 2px solid #007cba;
            border-radius: 6px;
            background: white;
            padding: 5px;
        }
        
        .signature-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        .no-signature {
            background: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .no-signature-text {
            color: #856404;
            font-weight: 500;
        }
        
        /* Detailed view styles */
        .signature-display-wrapper {
            max-width: 500px;
        }
        
        .signature-preview {
            margin-bottom: 10px;
        }
        
        .signature-image-detail {
            max-width: 100%;
            height: auto;
            border: 2px solid #007cba;
            border-radius: 6px;
            background: white;
            padding: 8px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .signature-image-detail:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.3);
        }
        
        .signature-metadata {
            color: #666;
            font-size: 12px;
        }
        
        .signature-metadata a {
            color: #007cba;
            text-decoration: none;
        }
        
        .signature-metadata a:hover {
            text-decoration: underline;
        }
        
        .no-signature-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        
        /* Modal styles */
        .signature-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .signature-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .signature-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .signature-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .signature-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .signature-modal-close:hover {
            color: #000;
        }
        
        .signature-modal-body {
            padding: 20px;
            text-align: center;
        }
        
        .signature-modal-image {
            max-width: 100%;
            height: auto;
            border: 2px solid #007cba;
            border-radius: 6px;
            background: white;
            padding: 10px;
        }
        
        .signature-modal-footer {
            padding: 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .signature-modal-content {
                max-width: 95vw;
                max-height: 95vh;
            }
            
            .signature-modal-header,
            .signature-modal-body,
            .signature-modal-footer {
                padding: 15px;
            }
            
            .signature-actions {
                flex-direction: column;
            }
        }
        </style>
        
        <?php
    }
}

/**
 * Initialize the submissions viewer
 */
function submissions_viewer_init() {
    error_log('Submissions Viewer: Plugin initializing...');
    return Simple_Submissions_Viewer::get_instance();
}

// Initialize immediately instead of waiting for plugins_loaded
add_action('init', 'submissions_viewer_init', 1);

// Duplicate menu removed - using main submissions viewer instead

// Removed duplicate direct page function - using main viewer

/**
 * Fallback class if Composer autoloader fails
 */
class Submissions_Viewer_Fallback {
    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'quiz_submissions';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Submissions Viewer', 'submissions-viewer'),
            __('Submissions Viewer', 'submissions-viewer'),
            'manage_options',
            'submissions-viewer',
            array($this, 'render_page'),
            'dashicons-list-view',
            25
        );
    }

    public function render_page() {
        global $wpdb;
        
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
            echo '<div class="notice notice-success"><p>Submission deleted successfully.</p></div>';
        }
        
        // Get all submissions
        $submissions = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Quiz Submissions Viewer</h1>
            <p>This is a simple viewer to test and verify the quiz submissions data.</p>
            
            <?php if (empty($submissions)): ?>
                <div class="notice notice-info">
                    <p>No submissions found. Try filling out the quiz form to test the data saving.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Step</th>
                            <th>Complete</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></td>
                                <td><?php echo esc_html($submission->email); ?></td>
                                <td><?php echo esc_html($submission->phone); ?></td>
                                <td><?php echo esc_html($submission->current_step); ?></td>
                                <td><?php echo $submission->is_complete ? '✅' : '⏳'; ?></td>
                                <td><?php echo esc_html($submission->created_at); ?></td>
                                <td>
                                    <a href="?page=submissions-viewer&action=view&id=<?php echo $submission->id; ?>" class="button button-small">View</a>
                                    <a href="?page=submissions-viewer&action=delete&id=<?php echo $submission->id; ?>" class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
                <?php
                $id = intval($_GET['id']);
                $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
                ?>
                
                <?php if ($submission): ?>
                    <div style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Submission Details - ID: <?php echo esc_html($submission->id); ?></h2>
                        
                        <h3>Step 1 Data (Basic Info)</h3>
                        <table class="form-table">
                            <tr><th>First Name:</th><td><?php echo esc_html($submission->first_name); ?></td></tr>
                            <tr><th>Last Name:</th><td><?php echo esc_html($submission->last_name); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo esc_html($submission->email); ?></td></tr>
                            <tr><th>Phone:</th><td><?php echo esc_html($submission->phone); ?></td></tr>
                        </table>
                        
                        <h3>Step 2 Data (Extended Info)</h3>
                        <table class="form-table">
                            <tr><th>ID Number:</th><td><?php echo esc_html($submission->id_number ?: 'Not provided'); ?></td></tr>
                            <tr><th>Gender:</th><td><?php echo esc_html($submission->gender ?: 'Not provided'); ?></td></tr>
                            <tr><th>Birth Date:</th><td><?php echo esc_html($submission->birth_date ?: 'Not provided'); ?></td></tr>
                            <tr><th>Citizenship:</th><td><?php echo esc_html($submission->citizenship ?: 'Not provided'); ?></td></tr>
                            <tr><th>Address:</th><td><?php echo esc_html($submission->address ?: 'Not provided'); ?></td></tr>
                            <tr><th>Marital Status:</th><td><?php echo esc_html($submission->marital_status ?: 'Not provided'); ?></td></tr>
                            <tr><th>Employment:</th><td><?php echo esc_html($submission->employment_status ?: 'Not provided'); ?></td></tr>
                            <tr><th>Education:</th><td><?php echo esc_html($submission->education ?: 'Not provided'); ?></td></tr>
                            <tr><th>Profession:</th><td><?php echo esc_html($submission->profession ?: 'Not provided'); ?></td></tr>
                        </table>
                        
                        <h3>Metadata</h3>
                        <table class="form-table">
                            <tr><th>Current Step:</th><td><?php echo esc_html($submission->current_step); ?></td></tr>
                            <tr><th>Is Complete:</th><td><?php echo $submission->is_complete ? 'Yes' : 'No'; ?></td></tr>
                            <tr><th>Created:</th><td><?php echo esc_html($submission->created_at); ?></td></tr>
                            <tr><th>Updated:</th><td><?php echo esc_html($submission->updated_at); ?></td></tr>
                            <tr><th>IP Address:</th><td><?php echo esc_html($submission->ip_address ?: 'Not recorded'); ?></td></tr>
                            <tr><th>Digital Signature:</th><td>
                                <?php if (!empty($submission->signature_data)): ?>
                                    <div class="signature-display-wrapper">
                                        <div class="signature-preview">
                                            <img src="<?php echo esc_attr($submission->signature_data); ?>" 
                                                 class="signature-image-detail" 
                                                 alt="Digital Signature"
                                                 onclick="openSignatureModal(this.src)">
                                        </div>
                                        <div class="signature-metadata">
                                            <small>Click to view full size | 
                                            <a href="<?php echo esc_attr($submission->signature_data); ?>" 
                                               download="signature_<?php echo $submission->id; ?>.png">Download PNG</a></small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="no-signature-indicator">
                                        <span class="dashicons dashicons-dismiss" style="color: #dc3545;"></span>
                                        <span>No signature provided</span>
                                    </div>
                                <?php endif; ?>
                            </td></tr>
                        </table>
                        
                        <p><a href="?page=submissions-viewer" class="button">← Back to List</a></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'submissions_viewer_init');

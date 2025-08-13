<?php

namespace Vider\SubmissionsViewer;

/**
 * Submissions Viewer
 * 
 * Simple viewer for quiz submissions data - for testing and verification
 */
class SubmissionsViewer {
    private static $instance = null;
    private $table_name;

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'quiz_submissions';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add admin menu
     */
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

    /**
     * Render the admin page
     */
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
                        </table>
                        
                        <p><a href="?page=submissions-viewer" class="button">← Back to List</a></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

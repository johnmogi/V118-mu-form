<?php

namespace Vider\QuizSubmissions;

/**
 * Quiz Submissions Manager
 * 
 * Handles storage and management of quiz form submissions
 */
class QuizSubmissionsManager {
    private static $instance = null;
    private $table_name;
    private $db_version = '1.0.0';

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
        
        // Activation/Deactivation hooks
        register_activation_hook(QUIZ_SUBMISSIONS_PLUGIN_DIR . 'quiz-submissions.php', array($this, 'activate'));
        register_deactivation_hook(QUIZ_SUBMISSIONS_PLUGIN_DIR . 'quiz-submissions.php', array($this, 'deactivate'));
        
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_tables();
        update_option('quiz_submissions_db_version', $this->db_version);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('quiz-submissions', false, dirname(plugin_basename(QUIZ_SUBMISSIONS_PLUGIN_DIR . 'quiz-submissions.php')) . '/languages');
        
        // Initialize admin
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Initialize AJAX handlers
        $this->init_ajax();
    }

    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize AJAX handlers
     */
    private function init_ajax() {
        // Hook into existing AJAX handlers from the main quiz plugin
        add_action('wp_ajax_save_step_data', array($this, 'intercept_step_data'), 5);
        add_action('wp_ajax_nopriv_save_step_data', array($this, 'intercept_step_data'), 5);
        
        // Our own AJAX handlers
        add_action('wp_ajax_save_quiz_step', array($this, 'save_quiz_step'));
        add_action('wp_ajax_nopriv_save_quiz_step', array($this, 'save_quiz_step'));
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            
            -- Step 1: Initial Info
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            
            -- Step 2: Extended Info
            id_number varchar(20) DEFAULT NULL,
            gender varchar(10) DEFAULT NULL,
            birth_date date DEFAULT NULL,
            citizenship varchar(50) DEFAULT 'ישראלית',
            address text DEFAULT NULL,
            marital_status varchar(20) DEFAULT NULL,
            employment_status varchar(50) DEFAULT NULL,
            education varchar(50) DEFAULT NULL,
            profession varchar(100) DEFAULT NULL,
            
            -- Submission Metadata
            current_step tinyint(1) DEFAULT 1,
            is_complete tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY email (email),
            KEY is_complete (is_complete)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'quiz-settings',
            __('Quiz Submissions', 'quiz-submissions'),
            __('Submissions', 'quiz-submissions'),
            'manage_options',
            'quiz-submissions',
            array($this, 'render_admin_page')
        );
        
        // Add a simple viewer submenu
        add_submenu_page(
            'quiz-settings',
            __('View Submissions', 'quiz-submissions'),
            __('View Submissions', 'quiz-submissions'),
            'read',
            'quiz-submissions-viewer',
            array($this, 'render_viewer_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'quiz-submissions') === false) {
            return;
        }

        wp_enqueue_style(
            'quiz-submissions-admin',
            QUIZ_SUBMISSIONS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            QUIZ_SUBMISSIONS_VERSION
        );

        wp_enqueue_script(
            'quiz-submissions-admin',
            QUIZ_SUBMISSIONS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            QUIZ_SUBMISSIONS_VERSION,
            true
        );

        wp_localize_script('quiz-submissions-admin', 'quizSubmissions', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_submissions_nonce'),
            'are_you_sure' => __('Are you sure you want to delete this submission?', 'quiz-submissions'),
        ));
    }

    /**
     * Intercept existing form data from the main quiz plugin
     */
    public function intercept_step_data() {
        // Get the step from POST data
        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        
        // Only handle steps 1 and 2 (personal info)
        if (!in_array($step, array(1, 2))) {
            return; // Let the original handler process other steps
        }
        
        // Extract and sanitize the data
        $data = $_POST;
        unset($data['action'], $data['step']); // Remove action and step from data
        
        // Sanitize data based on step
        $clean_data = $this->sanitize_submission_data($data, $step);
        
        // Save to our database
        $result = $this->save_submission($clean_data, $step);
        
        // Continue with the original handler (don't interrupt the flow)
        return;
    }

    /**
     * Save quiz step data
     */
    public function save_quiz_step() {
        check_ajax_referer('quiz_submissions_nonce', 'nonce');
        
        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        
        if (empty($data) || !in_array($step, array(1, 2))) {
            wp_send_json_error(__('Invalid data or step', 'quiz-submissions'));
        }

        // Sanitize data
        $clean_data = $this->sanitize_submission_data($data, $step);
        
        // Save to database
        $result = $this->save_submission($clean_data, $step);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Data saved successfully', 'quiz-submissions'),
            'submission_id' => $result
        ));
    }
    
    /**
     * Sanitize submission data
     */
    private function sanitize_submission_data($data, $step) {
        $clean = array();
        
        // Common fields
        $clean['ip_address'] = $this->get_client_ip();
        $clean['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Step 1 fields (basic personal info)
        if ($step === 1) {
            $clean['first_name'] = isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
            $clean['last_name'] = isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
            $clean['email'] = isset($data['email']) ? sanitize_email($data['email']) : '';
            $clean['phone'] = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
            $clean['current_step'] = 1;
        }
        
        // Step 2 fields (extended personal info)
        if ($step === 2) {
            // Include email for matching existing record
            $clean['email'] = isset($data['email']) ? sanitize_email($data['email']) : '';
            
            $clean['id_number'] = isset($data['id_number']) ? sanitize_text_field($data['id_number']) : '';
            $clean['gender'] = isset($data['gender']) ? sanitize_text_field($data['gender']) : '';
            $clean['birth_date'] = isset($data['birth_date']) && !empty($data['birth_date']) ? sanitize_text_field($data['birth_date']) : null;
            $clean['citizenship'] = isset($data['citizenship']) ? sanitize_text_field($data['citizenship']) : 'ישראלית';
            $clean['address'] = isset($data['address']) ? sanitize_textarea_field($data['address']) : '';
            $clean['marital_status'] = isset($data['marital_status']) ? sanitize_text_field($data['marital_status']) : '';
            $clean['employment_status'] = isset($data['employment_status']) ? sanitize_text_field($data['employment_status']) : '';
            $clean['education'] = isset($data['education']) ? sanitize_text_field($data['education']) : '';
            $clean['profession'] = isset($data['profession']) ? sanitize_text_field($data['profession']) : '';
            $clean['current_step'] = 2;
            $clean['is_complete'] = 1; // Mark as complete after step 2
        }
        
        return $clean;
    }
    
    /**
     * Save submission to database
     */
    private function save_submission($data, $step) {
        global $wpdb;
        
        // For step 1, we create a new submission
        if ($step === 1) {
            // Check if email already exists (prevent duplicates)
            if (!empty($data['email'])) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table_name} WHERE email = %s AND current_step = 1",
                    $data['email']
                ));
                
                if ($existing) {
                    // Update existing step 1 record instead of creating duplicate
                    $result = $wpdb->update(
                        $this->table_name,
                        $data,
                        array('id' => $existing),
                        array_fill(0, count($data), '%s'),
                        array('%d')
                    );
                    
                    return $existing;
                }
            }
            
            // Create new submission
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                array_fill(0, count($data), '%s')
            );
            
            if (false === $result) {
                return new \WP_Error('db_error', __('Failed to save submission', 'quiz-submissions'));
            }
            
            return $wpdb->insert_id;
        }
        
        // For step 2, we update the existing submission
        if ($step === 2 && !empty($data['email'])) {
            // Find the most recent incomplete submission for this email
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE email = %s AND is_complete = 0 ORDER BY id DESC LIMIT 1",
                $data['email']
            ));
            
            if ($existing_id) {
                // Update existing record
                $result = $wpdb->update(
                    $this->table_name,
                    $data,
                    array('id' => $existing_id),
                    array_fill(0, count($data), '%s'),
                    array('%d')
                );
                
                if (false === $result) {
                    return new \WP_Error('db_error', __('Failed to update submission', 'quiz-submissions'));
                }
                
                return $existing_id;
            } else {
                // No existing record found, create new one with step 2 data
                $result = $wpdb->insert(
                    $this->table_name,
                    $data,
                    array_fill(0, count($data), '%s')
                );
                
                if (false === $result) {
                    return new \WP_Error('db_error', __('Failed to create submission', 'quiz-submissions'));
                }
                
                return $wpdb->insert_id;
            }
        }
        
        return new \WP_Error('invalid_data', __('Invalid submission data', 'quiz-submissions'));
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'quiz-submissions'));
        }
        
        // Handle actions (delete, etc.)
        $this->handle_actions();
        
        // Get submissions
        $submissions = $this->get_submissions();
        
        // Include the template
        include QUIZ_SUBMISSIONS_PLUGIN_DIR . 'templates/admin/submissions-list.php';
    }
    
    /**
     * Handle admin actions (delete, etc.)
     */
    private function handle_actions() {
        if (!isset($_GET['action']) || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        if (!wp_verify_nonce($nonce, 'quiz_submission_action')) {
            wp_die(__('Security check failed', 'quiz-submissions'));
        }
        
        if ('delete' === $action && !empty($_GET['id'])) {
            $this->delete_submission(intval($_GET['id']));
            
            // Redirect to prevent resubmission
            wp_redirect(add_query_arg('deleted', 1, admin_url('admin.php?page=quiz-submissions')));
            exit;
        }
    }
    
    /**
     * Get submissions
     */
    private function get_submissions($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'paged' => 1,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $params = array();
        
        // Search
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= " AND (
                first_name LIKE %s OR 
                last_name LIKE %s OR 
                email LIKE %s OR 
                phone LIKE %s
            )";
            $params = array_merge($params, array_fill(0, 4, $search));
        }
        
        // Order
        $orderby = in_array($args['orderby'], array('first_name', 'last_name', 'email', 'created_at')) 
            ? $args['orderby'] 
            : 'created_at';
            
        $order = 'DESC' === strtoupper($args['order']) ? 'DESC' : 'ASC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Pagination
        if ($args['per_page'] > 0) {
            $offset = ($args['paged'] - 1) * $args['per_page'];
            $query .= " LIMIT %d, %d";
            $params[] = $offset;
            $params[] = $args['per_page'];
        }
        
        // Prepare and execute the query
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Delete a submission
     */
    private function delete_submission($id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Render submissions viewer page
     */
    public function render_viewer_page() {
        global $wpdb;
        
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['submission_id'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'delete_submission_' . $_POST['submission_id'])) {
                $this->delete_submission(intval($_POST['submission_id']));
                echo '<div class="notice notice-success is-dismissible"><p>Submission deleted successfully.</p></div>';
            }
        }
        
        // Handle search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_clause = '';
        if ($search) {
            $where_clause = $wpdb->prepare(" WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Get submissions
        $submissions = $wpdb->get_results("SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quiz Submissions Viewer', 'quiz-submissions'); ?></h1>
            
            <form method="get">
                <input type="hidden" name="page" value="quiz-submissions-viewer">
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
                                <td><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></td>
                                <td><?php echo esc_html($submission->email); ?></td>
                                <td><?php echo esc_html($submission->phone); ?></td>
                                <td>
                                    <?php if ($submission->is_complete): ?>
                                        <span style="color: green; font-weight: bold;">✓ Complete</span>
                                    <?php else: ?>
                                        <span style="color: orange; font-weight: bold;">⚠ Step 1 Only</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($submission->created_at); ?></td>
                                <td>
                                    <button type="button" onclick="viewSubmission(<?php echo $submission->id; ?>)" class="button button-small">View Details</button>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <form method="post" style="display: inline; margin-left: 5px;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="submission_id" value="<?php echo $submission->id; ?>">
                                            <?php wp_nonce_field('delete_submission_' . $submission->id); ?>
                                            <input type="submit" class="button button-small button-link-delete" value="Delete" onclick="return confirm('Are you sure you want to delete this submission?')">
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div id="submission-details" style="display: none; margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px;">
                <h3>Submission Details</h3>
                <div id="submission-content"></div>
                <button type="button" class="button" onclick="document.getElementById('submission-details').style.display='none'">Close</button>
            </div>
        </div>
        
        <script>
        function viewSubmission(id) {
            var submissions = <?php echo json_encode($submissions); ?>;
            var submission = submissions.find(s => s.id == id);
            
            if (submission) {
                var content = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                
                // Step 1 Info
                content += '<div><h4>Step 1: Basic Information</h4>';
                content += '<p><strong>ID:</strong> ' + submission.id + '</p>';
                content += '<p><strong>Name:</strong> ' + submission.first_name + ' ' + submission.last_name + '</p>';
                content += '<p><strong>Email:</strong> ' + submission.email + '</p>';
                content += '<p><strong>Phone:</strong> ' + submission.phone + '</p>';
                content += '</div>';
                
                // Step 2 Info
                content += '<div><h4>Step 2: Extended Information</h4>';
                if (submission.id_number) content += '<p><strong>ID Number:</strong> ' + submission.id_number + '</p>';
                if (submission.gender) content += '<p><strong>Gender:</strong> ' + submission.gender + '</p>';
                if (submission.birth_date) content += '<p><strong>Birth Date:</strong> ' + submission.birth_date + '</p>';
                if (submission.citizenship) content += '<p><strong>Citizenship:</strong> ' + submission.citizenship + '</p>';
                if (submission.address) content += '<p><strong>Address:</strong> ' + submission.address + '</p>';
                if (submission.marital_status) content += '<p><strong>Marital Status:</strong> ' + submission.marital_status + '</p>';
                if (submission.employment_status) content += '<p><strong>Employment:</strong> ' + submission.employment_status + '</p>';
                if (submission.education) content += '<p><strong>Education:</strong> ' + submission.education + '</p>';
                if (submission.profession) content += '<p><strong>Profession:</strong> ' + submission.profession + '</p>';
                content += '</div>';
                
                content += '</div>';
                
                // Metadata
                content += '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
                content += '<h4>Submission Metadata</h4>';
                content += '<p><strong>Status:</strong> ' + (submission.is_complete == '1' ? 'Complete (Both Steps)' : 'Incomplete (Step 1 Only)') + '</p>';
                content += '<p><strong>Current Step:</strong> ' + submission.current_step + '</p>';
                content += '<p><strong>Created:</strong> ' + submission.created_at + '</p>';
                content += '<p><strong>Updated:</strong> ' + submission.updated_at + '</p>';
                content += '<p><strong>IP Address:</strong> ' + submission.ip_address + '</p>';
                content += '<p><strong>User Agent:</strong> ' + submission.user_agent + '</p>';
                content += '</div>';
                
                document.getElementById('submission-content').innerHTML = content;
                document.getElementById('submission-details').style.display = 'block';
                
                // Scroll to details
                document.getElementById('submission-details').scrollIntoView({behavior: 'smooth'});
            }
        }
        </script>
        
        <style>
        .button-link-delete {
            color: #a00 !important;
        }
        .button-link-delete:hover {
            color: #dc3232 !important;
        }
        #submission-details {
            box-shadow: 0 1px 3px rgba(0,0,0,0.13);
        }
        </style>
        
        <?php
    }
}

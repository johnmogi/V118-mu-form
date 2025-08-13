<?php
/**
 * Plugin Name: Quiz Submissions Manager
 * Description: Handles storage and management of quiz form submissions
 * Version: 1.0.0
 * Author: Vider Team
 * Text Domain: quiz-submissions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Define plugin constants
define('QUIZ_SUBMISSIONS_VERSION', '1.0.0');
define('QUIZ_SUBMISSIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUIZ_SUBMISSIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

use Vider\QuizSubmissions\QuizSubmissionsManager;

/**
 * Initialize the plugin
 */
function quiz_submissions_init() {
    if (class_exists('Vider\QuizSubmissions\QuizSubmissionsManager')) {
        return QuizSubmissionsManager::get_instance();
    }
    
    // Fallback if autoloader fails
    return Quiz_Submissions_Manager::get_instance();
}

/**
 * Main Quiz Submissions Class (Fallback)
 */
class Quiz_Submissions_Manager {
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
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
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
        load_plugin_textdomain('quiz-submissions', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
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
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_quiz-submissions' !== $hook) {
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
                return new WP_Error('db_error', __('Failed to save submission', 'quiz-submissions'));
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
                    return new WP_Error('db_error', __('Failed to update submission', 'quiz-submissions'));
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
                    return new WP_Error('db_error', __('Failed to create submission', 'quiz-submissions'));
                }
                
                return $wpdb->insert_id;
            }
        }
        
        return new WP_Error('invalid_data', __('Invalid submission data', 'quiz-submissions'));
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
}

// Initialize the plugin
function quiz_submissions_manager() {
    return Quiz_Submissions_Manager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'quiz_submissions_manager');

// Create the admin menu
add_action('admin_menu', function() {
    // This is a fallback in case the main plugin class fails to load
    if (class_exists('Quiz_Submissions_Manager')) {
        quiz_submissions_manager();
    }
}, 20);

// Handle activation hook
register_activation_hook(__FILE__, array('Quiz_Submissions_Manager', 'activate'));

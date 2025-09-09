<?php
/**
 * Plugin Name: Database Diagnostic Tool
 * Description: Diagnose database connectivity and table structure differences between dev/prod
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DB_Diagnostic_Tool {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        // Integrated into main quiz system - standalone menu removed
        add_submenu_page(
            'quiz-submissions',
            'Database Diagnostic',
            'DB Diagnostic',
            'manage_options',
            'db-diagnostic',
            array($this, 'diagnostic_page')
        );
    }

    public function render_diagnostic_page() {
        global $wpdb;
        
        ?>
        <div class="wrap">
            <h1>Database Diagnostic Tool</h1>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This tool helps diagnose database connectivity and table structure differences between development and production environments.</p>
            </div>

            <!-- Environment Info -->
            <div class="card">
                <h2>Environment Information</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <td><strong>WordPress DB Host:</strong></td>
                        <td><?php echo esc_html(DB_HOST); ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress DB Name:</strong></td>
                        <td><?php echo esc_html(DB_NAME); ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress DB User:</strong></td>
                        <td><?php echo esc_html(DB_USER); ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress Table Prefix:</strong></td>
                        <td><?php echo esc_html($wpdb->prefix); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Current Environment:</strong></td>
                        <td><?php echo $this->detect_environment(); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Database Connection Test -->
            <div class="card">
                <h2>Database Connection Test</h2>
                <?php $this->test_db_connection(); ?>
            </div>

            <!-- Quiz Submissions Table Analysis -->
            <div class="card">
                <h2>Quiz Submissions Table Analysis</h2>
                <?php $this->analyze_quiz_table(); ?>
            </div>

            <!-- All Tables List -->
            <div class="card">
                <h2>All Database Tables</h2>
                <?php $this->list_all_tables(); ?>
            </div>

            <!-- Sample Data Check -->
            <div class="card">
                <h2>Sample Data Check</h2>
                <?php $this->check_sample_data(); ?>
            </div>
        </div>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin: 20px 0;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
        }
        .status-ok {
            color: #00a32a;
            font-weight: bold;
        }
        .status-error {
            color: #d63638;
            font-weight: bold;
        }
        .status-warning {
            color: #dba617;
            font-weight: bold;
        }
        </style>
        <?php
    }

    private function detect_environment() {
        $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
        if (strpos($host, 'local') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'localhost') !== false) {
            return '<span class="status-ok">Development (Local)</span>';
        } else {
            return '<span class="status-warning">Production</span>';
        }
    }

    private function test_db_connection() {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT 1");
            if ($result == 1) {
                echo '<p class="status-ok">✅ Database connection successful!</p>';
                
                // Test charset
                $charset = $wpdb->get_var("SELECT @@character_set_database");
                echo '<p><strong>Database Charset:</strong> ' . esc_html($charset) . '</p>';
                
                // Test version
                $version = $wpdb->get_var("SELECT VERSION()");
                echo '<p><strong>MySQL Version:</strong> ' . esc_html($version) . '</p>';
                
            } else {
                echo '<p class="status-error">❌ Database connection failed!</p>';
            }
        } catch (Exception $e) {
            echo '<p class="status-error">❌ Database connection error: ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    private function analyze_quiz_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            echo '<p class="status-error">❌ Table <code>' . esc_html($table_name) . '</code> does not exist!</p>';
            
            // Check for alternative table names
            $similar_tables = $wpdb->get_results("SHOW TABLES LIKE '%quiz%'");
            if ($similar_tables) {
                echo '<p class="status-warning">⚠️ Found similar tables:</p>';
                echo '<ul>';
                foreach ($similar_tables as $table) {
                    $table_name_val = array_values((array)$table)[0];
                    echo '<li><code>' . esc_html($table_name_val) . '</code></li>';
                }
                echo '</ul>';
            }
            
            return;
        }

        echo '<p class="status-ok">✅ Table <code>' . esc_html($table_name) . '</code> exists!</p>';

        // Get table structure
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        
        echo '<h3>Table Structure:</h3>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>';
        echo '<tbody>';
        foreach ($columns as $column) {
            echo '<tr>';
            echo '<td><code>' . esc_html($column->Field) . '</code></td>';
            echo '<td>' . esc_html($column->Type) . '</td>';
            echo '<td>' . esc_html($column->Null) . '</td>';
            echo '<td>' . esc_html($column->Key) . '</td>';
            echo '<td>' . esc_html($column->Default) . '</td>';
            echo '<td>' . esc_html($column->Extra) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Get row count
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo '<p><strong>Total Records:</strong> ' . number_format($count) . '</p>';

        // Check for recent submissions
        $recent = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE submission_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        echo '<p><strong>Recent Submissions (Last 7 days):</strong> ' . number_format($recent) . '</p>';
    }

    private function list_all_tables() {
        global $wpdb;
        
        $tables = $wpdb->get_results("SHOW TABLES");
        
        echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
        echo '<ul style="columns: 3; column-gap: 20px;">';
        
        foreach ($tables as $table) {
            $table_name = array_values((array)$table)[0];
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
            
            $highlight = '';
            if (strpos($table_name, 'quiz') !== false || strpos($table_name, 'submission') !== false) {
                $highlight = 'style="background-color: #fff3cd; font-weight: bold;"';
            }
            
            echo '<li ' . $highlight . '><code>' . esc_html($table_name) . '</code> (' . number_format($row_count) . ' rows)</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    private function check_sample_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            echo '<p class="status-error">❌ Cannot check sample data - table does not exist!</p>';
            return;
        }

        // Get latest 5 submissions
        $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submission_time DESC LIMIT 5");
        
        if (empty($submissions)) {
            echo '<p class="status-warning">⚠️ No submissions found in the table!</p>';
            
            // Check if there's any data at all
            $any_data = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            if ($any_data == 0) {
                echo '<p>The table exists but is completely empty.</p>';
            }
            
            return;
        }

        echo '<p class="status-ok">✅ Found ' . count($submissions) . ' recent submissions:</p>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Email</th><th>Name</th><th>Step</th><th>Completed</th><th>Submission Time</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($submissions as $submission) {
            echo '<tr>';
            echo '<td>' . esc_html($submission->id) . '</td>';
            echo '<td>' . esc_html($submission->user_email) . '</td>';
            echo '<td>' . esc_html($submission->first_name . ' ' . $submission->last_name) . '</td>';
            echo '<td>' . esc_html($submission->current_step) . '</td>';
            echo '<td>' . ($submission->completed ? '✅' : '❌') . '</td>';
            echo '<td>' . esc_html($submission->submission_time) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    public function render_table_creation_page() {
        global $wpdb;
        
        // Handle table creation
        if (isset($_POST['action']) && $_POST['action'] === 'create_quiz_table') {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'create_quiz_table')) {
                wp_die(__('Security check failed.'));
            }

            $result = $this->create_quiz_submissions_table();
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Success!</strong> Quiz submissions table created successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error!</strong> Failed to create quiz submissions table. Check error logs.</p></div>';
            }
        }

        $table_name = $wpdb->prefix . 'quiz_submissions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        ?>
        <div class="wrap">
            <h1>Create Quiz Submissions Table</h1>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This tool creates the missing quiz_submissions table on your production server.</p>
            </div>

            <?php if ($table_exists): ?>
                <div class="notice notice-success">
                    <p><strong>✅ Table Already Exists!</strong> The table <code><?php echo esc_html($table_name); ?></code> already exists in your database.</p>
                </div>
                
                <?php
                // Show table info
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo '<p><strong>Current Records:</strong> ' . number_format($count) . '</p>';
                ?>
                
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ Table Missing!</strong> The table <code><?php echo esc_html($table_name); ?></code> does not exist and needs to be created.</p>
                </div>

                <div class="card">
                    <h2>Table Structure to be Created</h2>
                    <p>The following table structure will be created to match your local development environment:</p>
                    
                    <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px; overflow-x: auto;">
CREATE TABLE <?php echo esc_html($table_name); ?> (
  id mediumint NOT NULL AUTO_INCREMENT,
  first_name varchar(100) DEFAULT NULL,
  last_name varchar(100) DEFAULT NULL,
  user_name varchar(100) DEFAULT NULL,
  user_phone varchar(20) DEFAULT NULL,
  user_email varchar(100) DEFAULT NULL,
  package_selected varchar(50) DEFAULT NULL,
  package_price decimal(10,2) DEFAULT 0.00,
  score int DEFAULT 0,
  max_score int DEFAULT 40,
  passed tinyint(1) DEFAULT 0,
  answers longtext DEFAULT NULL,
  current_step int DEFAULT 1,
  completed tinyint(1) DEFAULT 0,
  submission_time datetime DEFAULT CURRENT_TIMESTAMP,
  ip_address varchar(45) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  id_number varchar(20) DEFAULT NULL,
  gender varchar(10) DEFAULT NULL,
  birth_date date DEFAULT NULL,
  citizenship varchar(50) DEFAULT 'ישראלית',
  address text DEFAULT NULL,
  marital_status varchar(20) DEFAULT NULL,
  employment_status varchar(50) DEFAULT NULL,
  education varchar(50) DEFAULT NULL,
  profession varchar(100) DEFAULT NULL,
  contact_consent tinyint(1) DEFAULT 0,
  package_name varchar(100) DEFAULT NULL,
  package_source varchar(100) DEFAULT NULL,
  score_percentage decimal(5,2) DEFAULT NULL,
  declaration_accepted tinyint(1) DEFAULT 0,
  PRIMARY KEY (id),
  KEY user_email (user_email),
  KEY passed (passed),
  KEY completed (completed),
  KEY submission_time (submission_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    </pre>

                    <form method="post" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="create_quiz_table">
                        <?php wp_nonce_field('create_quiz_table'); ?>
                        
                        <p class="submit">
                            <input type="submit" class="button button-primary button-large" 
                                   value="🚀 Create Quiz Submissions Table" 
                                   onclick="return confirm('Are you sure you want to create the quiz_submissions table? This action cannot be undone.')">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>After Table Creation</h2>
                <p>Once the table is created, you should:</p>
                <ol>
                    <li><strong>Test the Submissions Viewer:</strong> Go to "הגשות טפסים" to verify it works</li>
                    <li><strong>Test Form Submissions:</strong> Submit a test form to ensure data is being saved</li>
                    <li><strong>Check Data Integration:</strong> Verify that your existing quiz plugin integrates properly</li>
                </ol>
            </div>
        </div>
        <?php
    }

    private function create_quiz_submissions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quiz_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint NOT NULL AUTO_INCREMENT,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            user_name varchar(100) DEFAULT NULL,
            user_phone varchar(20) DEFAULT NULL,
            user_email varchar(100) DEFAULT NULL,
            package_selected varchar(50) DEFAULT NULL,
            package_price decimal(10,2) DEFAULT 0.00,
            score int DEFAULT 0,
            max_score int DEFAULT 40,
            passed tinyint(1) DEFAULT 0,
            answers longtext DEFAULT NULL,
            current_step int DEFAULT 1,
            completed tinyint(1) DEFAULT 0,
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            id_number varchar(20) DEFAULT NULL,
            gender varchar(10) DEFAULT NULL,
            birth_date date DEFAULT NULL,
            citizenship varchar(50) DEFAULT 'ישראלית',
            address text DEFAULT NULL,
            marital_status varchar(20) DEFAULT NULL,
            employment_status varchar(50) DEFAULT NULL,
            education varchar(50) DEFAULT NULL,
            profession varchar(100) DEFAULT NULL,
            contact_consent tinyint(1) DEFAULT 0,
            package_name varchar(100) DEFAULT NULL,
            package_source varchar(100) DEFAULT NULL,
            score_percentage decimal(5,2) DEFAULT NULL,
            declaration_accepted tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY user_email (user_email),
            KEY passed (passed),
            KEY completed (completed),
            KEY submission_time (submission_time)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            dbDelta($sql);
            
            // Verify table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            return $table_exists;
            
        } catch (Exception $e) {
            error_log('Quiz Table Creation Error: ' . $e->getMessage());
            return false;
        }
    }
}

// Initialize the diagnostic tool
add_action('plugins_loaded', function() {
    DB_Diagnostic_Tool::get_instance();
});

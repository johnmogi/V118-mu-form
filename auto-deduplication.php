<?php
/**
 * Plugin Name: Auto Deduplication for Quiz Submissions
 * Description: Automatically prevents duplicate submissions and merges data in the background
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Auto_Deduplication_Manager {
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
        
        // Hook into database operations to prevent duplicates
        add_action('wp_insert_post_data', array($this, 'prevent_duplicate_submissions'), 10, 2);
        
        // Hook into any quiz submission saves
        add_action('init', array($this, 'setup_submission_hooks'));
        
        // Run automatic cleanup every hour
        add_action('wp', array($this, 'schedule_cleanup'));
        add_action('auto_dedup_cleanup', array($this, 'run_automatic_cleanup'));
    }

    /**
     * Setup hooks to intercept quiz submissions
     */
    public function setup_submission_hooks() {
        // Hook into WordPress database insertions
        add_filter('query', array($this, 'intercept_quiz_insertions'));
    }

    /**
     * Intercept INSERT queries to the quiz_submissions table
     */
    public function intercept_quiz_insertions($query) {
        global $wpdb;
        
        // Only process INSERT queries to our quiz table
        if (strpos($query, 'INSERT INTO') !== false && strpos($query, $this->table_name) !== false) {
            
            // Extract email from the query to check for duplicates
            if (preg_match("/user_email['\"]?\s*[=,]\s*['\"]([^'\"]+)['\"]/i", $query, $matches)) {
                $email = $matches[1];
                
                // Check if this email already has a recent submission (within last 10 minutes)
                $existing = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$this->table_name} 
                    WHERE user_email = %s 
                    AND submission_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                    ORDER BY submission_time DESC 
                    LIMIT 1
                ", $email));
                
                if ($existing) {
                    // Instead of inserting, update the existing record
                    $this->merge_submission_data($existing, $query);
                    
                    // Return empty query to prevent the original insert
                    return '';
                }
            }
        }
        
        return $query;
    }

    /**
     * Merge new submission data into existing record
     */
    private function merge_submission_data($existing_record, $insert_query) {
        global $wpdb;
        
        // Parse the INSERT query to extract field values
        $new_data = $this->parse_insert_query($insert_query);
        
        if (empty($new_data)) {
            return false;
        }

        // Merge logic: keep existing non-empty values, add new non-empty values
        $update_data = array();
        
        foreach ($new_data as $field => $value) {
            if (!empty($value) && (empty($existing_record->$field) || $existing_record->$field === null)) {
                $update_data[$field] = $value;
            }
        }

        // Special handling for step progression
        if (isset($new_data['current_step']) && $new_data['current_step'] > $existing_record->current_step) {
            $update_data['current_step'] = $new_data['current_step'];
        }

        // Update completion status if step 2 or higher
        if (isset($new_data['current_step']) && $new_data['current_step'] >= 2) {
            $update_data['completed'] = 1;
        }

        // Update the existing record if we have data to merge
        if (!empty($update_data)) {
            $update_data['submission_time'] = current_time('mysql'); // Update timestamp
            
            $result = $wpdb->update(
                $this->table_name,
                $update_data,
                array('id' => $existing_record->id),
                null,
                array('%d')
            );

            // Log the merge for debugging
            error_log("Auto-Dedup: Merged submission for {$existing_record->user_email}, updated " . count($update_data) . " fields");
            
            return $result;
        }

        return false;
    }

    /**
     * Parse INSERT query to extract field-value pairs
     */
    private function parse_insert_query($query) {
        $data = array();
        
        // Extract the VALUES part of the INSERT query
        if (preg_match('/INSERT INTO.*?\((.*?)\)\s*VALUES\s*\((.*?)\)/i', $query, $matches)) {
            $fields = array_map('trim', explode(',', $matches[1]));
            $values = array_map('trim', explode(',', $matches[2]));
            
            // Clean field names (remove backticks and quotes)
            $fields = array_map(function($field) {
                return trim($field, '`"\' ');
            }, $fields);
            
            // Clean values (remove quotes)
            $values = array_map(function($value) {
                return trim($value, '"\' ');
            }, $values);
            
            // Combine fields and values
            if (count($fields) === count($values)) {
                $data = array_combine($fields, $values);
            }
        }
        
        return $data;
    }

    /**
     * Schedule automatic cleanup
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('auto_dedup_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'auto_dedup_cleanup');
        }
    }

    /**
     * Run automatic cleanup of duplicates
     */
    public function run_automatic_cleanup() {
        global $wpdb;
        
        $cleaned_count = 0;
        
        // Find emails with multiple submissions
        $duplicates = $wpdb->get_results("
            SELECT user_email, COUNT(*) as count 
            FROM {$this->table_name} 
            WHERE user_email IS NOT NULL AND user_email != '' 
            GROUP BY user_email 
            HAVING count > 1
        ");

        foreach ($duplicates as $duplicate) {
            $submissions = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$this->table_name} 
                WHERE user_email = %s 
                ORDER BY submission_time ASC
            ", $duplicate->user_email));
            
            if (count($submissions) < 2) continue;

            // Keep the most complete submission (highest current_step)
            $keep_submission = null;
            $max_step = 0;
            
            foreach ($submissions as $submission) {
                if ($submission->current_step > $max_step) {
                    $max_step = $submission->current_step;
                    $keep_submission = $submission;
                }
            }

            if (!$keep_submission) continue;

            // Merge data from other submissions into the keeper
            $merge_data = array();
            
            foreach ($submissions as $submission) {
                if ($submission->id === $keep_submission->id) continue;
                
                // Merge non-empty fields
                foreach (get_object_vars($submission) as $field => $value) {
                    if ($field === 'id' || $field === 'submission_time') continue;
                    
                    if (!empty($value) && (empty($keep_submission->$field) || $keep_submission->$field === null)) {
                        $merge_data[$field] = $value;
                    }
                }
                
                // Delete the duplicate
                $wpdb->delete($this->table_name, array('id' => $submission->id), array('%d'));
                $cleaned_count++;
            }

            // Update the keeper with merged data
            if (!empty($merge_data)) {
                $wpdb->update(
                    $this->table_name,
                    $merge_data,
                    array('id' => $keep_submission->id),
                    null,
                    array('%d')
                );
            }
        }

        if ($cleaned_count > 0) {
            error_log("Auto-Dedup: Cleaned {$cleaned_count} duplicate submissions automatically");
        }

        return $cleaned_count;
    }

    /**
     * Prevent duplicate submissions (WordPress hook)
     */
    public function prevent_duplicate_submissions($data, $postarr) {
        // This hook is for WordPress posts, but we can use it as a general prevention mechanism
        return $data;
    }

    /**
     * Manual trigger for immediate cleanup (for testing)
     */
    public function trigger_immediate_cleanup() {
        return $this->run_automatic_cleanup();
    }
}

// Initialize the auto-deduplication manager
add_action('plugins_loaded', function() {
    Auto_Deduplication_Manager::get_instance();
});

// Add admin notice about auto-deduplication
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'quiz-submissions') !== false) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>🤖 Auto-Deduplication Active:</strong> Duplicate submissions are automatically prevented and cleaned up in the background.</p>';
            echo '</div>';
        }
    }
});
